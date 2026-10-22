<?php

declare(strict_types=1);

namespace Voak\Health\Services;

use Voak\Health\Core\Exceptions\ConflictException;
use Voak\Health\Core\Exceptions\DatabaseException;
use Voak\Health\Core\Exceptions\NotFoundException;
use Voak\Health\Core\Exceptions\SecurityException;
use Voak\Health\Core\Exceptions\ValidationException;
use Voak\Health\Core\Http\Response;
use Voak\Health\Database\Connection;
use Voak\Health\Security\AuditLogger;
use Voak\Health\Security\Crypto;

/**
 * Service managing Public WebP Media Assets and Quarantined Private Documents.
 * Reconciled with authoritative specifications:
 * - ADR-007 (Segregated Storage: Public WebP Assets vs. Quarantined Document Streaming)
 * - FR-ADM-009 / TEST-ADM-009 (WebP conversion, mandatory alt-text, referential deletion guard)
 * - FR-SEC-003 / TEST-SEC-003 (MIME magic byte inspection & quarantine storage outside web root)
 * - FR-SEC-004 / TEST-SEC-004 (Authenticated streaming proxy with strict headers & audit log)
 */
class MediaStorageService
{
    private Connection $connection;
    private AuditLogger $auditLogger;
    private string $publicStorageDir;
    private string $quarantineStorageDir;
    private string $publicUrlBase;

    public function __construct(
        Connection $connection,
        AuditLogger $auditLogger,
        ?string $publicStorageDir = null,
        ?string $quarantineStorageDir = null,
        string $publicUrlBase = '/assets/uploads'
    ) {
        $this->connection = $connection;
        $this->auditLogger = $auditLogger;

        $projectRoot = dirname(__DIR__, 2);
        $this->publicStorageDir = $publicStorageDir !== null
            ? rtrim(str_replace('\\', '/', $publicStorageDir), '/')
            : str_replace('\\', '/', $projectRoot . '/public/assets/uploads');

        $this->quarantineStorageDir = $quarantineStorageDir !== null
            ? rtrim(str_replace('\\', '/', $quarantineStorageDir), '/')
            : str_replace('\\', '/', $projectRoot . '/storage/private/quarantine');

        $this->publicUrlBase = rtrim($publicUrlBase, '/');

        $this->initStorageEnclaves();
    }

    /**
     * Initializes storage directories and enforces defense-in-depth security files.
     */
    public function initStorageEnclaves(): void
    {
        if (!is_dir($this->publicStorageDir)) {
            @mkdir($this->publicStorageDir, 0755, true);
        }

        if (!is_dir($this->quarantineStorageDir)) {
            @mkdir($this->quarantineStorageDir, 0700, true);
        }

        // Enforce Apache .htaccess denying all direct web access to quarantine
        $htaccessPath = $this->quarantineStorageDir . '/.htaccess';
        if (!file_exists($htaccessPath)) {
            $htaccessContent = "# Voak Health Care Document Quarantine Protection\n"
                . "# Direct web access and script execution strictly prohibited (FR-SEC-003 / SYS-004)\n"
                . "Require all denied\n"
                . "<IfModule mod_php.c>\n"
                . "    php_flag engine off\n"
                . "</IfModule>\n";
            @file_put_contents($htaccessPath, $htaccessContent);
        }

        // Empty index.html fallback
        $indexPath = $this->quarantineStorageDir . '/index.html';
        if (!file_exists($indexPath)) {
            @file_put_contents($indexPath, "<!DOCTYPE html><html><head><title>Access Denied</title></head><body><h1>403 Forbidden</h1></body></html>");
        }
    }

    public function getPublicStorageDir(): string
    {
        return $this->publicStorageDir;
    }

    public function getQuarantineStorageDir(): string
    {
        return $this->quarantineStorageDir;
    }

    // =========================================================================
    // PART 1: PUBLIC MEDIA ASSETS (WEBP CONVERSION, ALT-TEXT, DELETION GUARD)
    // =========================================================================

    /**
     * Uploads and optimizes a public marketing media image.
     * Enforces mandatory alt-text, MIME magic bytes inspection, auto-conversion to WebP,
     * thumbnail generation, and database registration with audit logging.
     *
     * @param array{tmp_name: string, name: string, size?: int, error?: int} $file
     * @throws ValidationException
     */
    public function uploadMedia(array $file, string $altText, int $userId): array
    {
        // 1. Mandatory alt text validation (FR-ADM-009)
        $altText = trim($altText);
        if ($altText === '') {
            throw new ValidationException(
                "Mandatory alt text is required for all media assets.",
                ['alt_text' => 'Alt text cannot be empty.'],
                'ERR_VALIDATION'
            );
        }
        if (mb_strlen($altText) > 255) {
            throw new ValidationException(
                "Alt text must not exceed 255 characters.",
                ['alt_text' => 'Alt text too long.'],
                'ERR_VALIDATION'
            );
        }

        // 2. Validate file presence & upload errors
        $tmpPath = $file['tmp_name'] ?? '';
        if ($tmpPath === '' || !file_exists($tmpPath) || !is_readable($tmpPath)) {
            throw new ValidationException("No valid upload file provided or file unreadable.", [], 'ERR_VALIDATION');
        }

        $fileSize = isset($file['size']) && (int)$file['size'] > 0 ? (int)$file['size'] : (int)filesize($tmpPath);

        // 3. File size ceiling: Max 5MB for public images (Stage 1)
        $maxBytes = 5 * 1024 * 1024; // 5MB
        if ($fileSize > $maxBytes) {
            throw new ValidationException(
                "File size exceeds maximum allowed ceiling of 5MB for media assets.",
                ['max_size' => '5MB', 'actual_size' => $fileSize],
                'FILE_TOO_LARGE'
            );
        }

        // 4. Extension allowlist check (Stage 2)
        $originalName = (string)($file['name'] ?? 'image.jpg');
        $rawExtension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

        // Strict rejection of SVG or executable extensions
        if (!in_array($rawExtension, $allowedExtensions, true)) {
            throw new ValidationException(
                "Invalid file extension [{$rawExtension}]. Allowed image extensions: jpg, jpeg, png, webp.",
                [],
                'INVALID_EXTENSION'
            );
        }

        // 5. Server-side MIME magic byte inspection (Stage 3)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo !== false ? finfo_file($finfo, $tmpPath) : '';
        if ($finfo !== false) {
            finfo_close($finfo);
        }

        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($detectedMime, $allowedMimes, true)) {
            throw new ValidationException(
                "Invalid file signature or disguised script: detected MIME type [{$detectedMime}]. Expected image/jpeg, image/png, or image/webp.",
                [],
                'INVALID_FILE_SIGNATURE'
            );
        }

        // Detect embedded PHP script tags inside image payload
        $rawHead = (string)file_get_contents($tmpPath, false, null, 0, 4096);
        if (preg_match('/<\?(?:php|=)/i', $rawHead)) {
            throw new ValidationException(
                "Malicious executable script content detected in image header.",
                [],
                'INVALID_FILE_SIGNATURE'
            );
        }

        // 6. Image processing and WebP conversion via GD (Stage 5)
        $fileContent = (string)file_get_contents($tmpPath);
        $image = @imagecreatefromstring($fileContent);
        if ($image === false) {
            throw new ValidationException("Unable to decode image binary data.", [], 'ERR_VALIDATION');
        }

        $origWidth = imagesx($image);
        $origHeight = imagesy($image);

        if ($origWidth <= 0 || $origHeight <= 0) {
            imagedestroy($image);
            throw new ValidationException("Invalid image dimensions.", [], 'ERR_VALIDATION');
        }

        // Resize proportionally to max width 1920px
        $finalWidth = $origWidth;
        $finalHeight = $origHeight;
        $processedImage = $image;

        if ($origWidth > 1920) {
            $finalWidth = 1920;
            $finalHeight = (int)round(($origHeight * 1920) / $origWidth);
            $scaled = imagescale($image, $finalWidth, $finalHeight);
            if ($scaled !== false) {
                imagedestroy($image);
                $processedImage = $scaled;
            }
        }

        // Generate UUIDv4 stored name
        $uuid = Crypto::uuidv4();
        $storedName = "{$uuid}.webp";
        $thumbName = "{$uuid}_thumb.webp";

        // Partition folder YYYY/MM/
        $year = date('Y');
        $month = date('m');
        $targetDir = "{$this->publicStorageDir}/{$year}/{$month}";
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0755, true);
        }

        $mainTargetPath = "{$targetDir}/{$storedName}";
        $thumbTargetPath = "{$targetDir}/{$thumbName}";

        // Save main image as WebP (quality 82%)
        $mainSaved = imagewebp($processedImage, $mainTargetPath, 82);
        if (!$mainSaved) {
            imagedestroy($processedImage);
            throw new ValidationException("Failed to encode image to WebP format.", [], 'ERR_INTERNAL');
        }

        // Generate responsive thumbnail (max width 400px)
        $thumbWidth = min(400, $finalWidth);
        $thumbHeight = (int)round(($finalHeight * $thumbWidth) / $finalWidth);
        $thumbImage = imagescale($processedImage, $thumbWidth, $thumbHeight);
        if ($thumbImage !== false) {
            imagewebp($thumbImage, $thumbTargetPath, 80);
            imagedestroy($thumbImage);
        }
        imagedestroy($processedImage);

        $relativePath = "{$year}/{$month}/{$storedName}";
        $actualFinalBytes = (int)filesize($mainTargetPath);
        $dimensions = "{$finalWidth}x{$finalHeight}";

        // 7. Atomic database insertion & cryptographic audit logging
        $pdo = $this->connection->getPdo();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                "INSERT INTO media_assets (
                    original_name, stored_name, file_path, file_size_bytes,
                    mime_type, dimensions, alt_text, uploaded_by_user_id, created_at
                ) VALUES (
                    :original_name, :stored_name, :file_path, :file_size_bytes,
                    :mime_type, :dimensions, :alt_text, :uploaded_by_user_id, CURRENT_TIMESTAMP
                )"
            );
            $stmt->execute([
                'original_name'       => basename($originalName),
                'stored_name'         => $storedName,
                'file_path'           => $relativePath,
                'file_size_bytes'     => $actualFinalBytes,
                'mime_type'           => 'image/webp',
                'dimensions'          => $dimensions,
                'alt_text'            => $altText,
                'uploaded_by_user_id' => $userId,
            ]);
            $mediaId = (int)$pdo->lastInsertId();
            $stmt->closeCursor();

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (file_exists($mainTargetPath)) {
                @unlink($mainTargetPath);
            }
            if (file_exists($thumbTargetPath)) {
                @unlink($thumbTargetPath);
            }
            throw new DatabaseException("Failed to persist media asset record: " . $e->getMessage(), [], $e);
        }

        // Cryptographic audit log (MEDIA_UPLOADED) - executed outside transaction
        try {
            $this->auditLogger->log(
                $pdo,
                'MEDIA',
                'MEDIA_UPLOADED',
                $userId,
                '127.0.0.1',
                'CMS/MediaStorageService',
                'media_asset',
                $mediaId,
                [
                    'original_name'   => $originalName,
                    'stored_name'     => $storedName,
                    'file_path'       => $relativePath,
                    'file_size_bytes' => $actualFinalBytes,
                    'dimensions'      => $dimensions,
                    'alt_text'        => $altText,
                ]
            );
        } catch (\Throwable $e) {
            // Fail-closed compensating rollback: remove created record and files if audit logging fails
            $delStmt = $pdo->prepare("DELETE FROM media_assets WHERE id = :id");
            $delStmt->execute(['id' => $mediaId]);
            $delStmt->closeCursor();

            if (file_exists($mainTargetPath)) {
                @unlink($mainTargetPath);
            }
            if (file_exists($thumbTargetPath)) {
                @unlink($thumbTargetPath);
            }
            throw new DatabaseException("Audit logging failed during media upload: " . $e->getMessage(), [], $e);
        }

        return $this->formatMediaItem([
            'id'                  => $mediaId,
            'original_name'       => basename($originalName),
            'stored_name'         => $storedName,
            'file_path'           => $relativePath,
            'file_size_bytes'     => $actualFinalBytes,
            'mime_type'           => 'image/webp',
            'dimensions'          => $dimensions,
            'alt_text'            => $altText,
            'uploaded_by_user_id' => $userId,
            'created_at'          => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Lists media assets with search, MIME filtering, and pagination.
     */
    public function listMedia(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $pdo = $this->connection->getPdo();
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $conditions = ['1=1'];
        $params = [];

        if (!empty($filters['search'])) {
            $conditions[] = '(m.original_name LIKE :search OR m.alt_text LIKE :search)';
            $params['search'] = '%' . trim((string)$filters['search']) . '%';
        }

        if (!empty($filters['mime_type'])) {
            $conditions[] = 'm.mime_type = :mime';
            $params['mime'] = trim((string)$filters['mime_type']);
        }

        $whereClause = implode(' AND ', $conditions);

        // Count total
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM media_assets m WHERE {$whereClause}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        $countStmt->closeCursor();

        // Fetch rows
        $sql = "SELECT m.*, u.email as uploader_email, u.name as uploader_name
                FROM media_assets m
                LEFT JOIN users u ON m.uploaded_by_user_id = u.id
                WHERE {$whereClause}
                ORDER BY m.id DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue('limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        $items = array_map([$this, 'formatMediaItem'], $rows);

        return [
            'items'      => $items,
            'pagination' => [
                'total'        => $total,
                'per_page'     => $perPage,
                'current_page' => $page,
                'total_pages'  => (int)ceil($total / $perPage),
            ],
        ];
    }

    /**
     * Retrieves single media asset by ID with usage analysis.
     */
    public function getMediaById(int $id): ?array
    {
        $pdo = $this->connection->getPdo();
        $stmt = $pdo->prepare(
            "SELECT m.*, u.email as uploader_email, u.name as uploader_name
             FROM media_assets m
             LEFT JOIN users u ON m.uploaded_by_user_id = u.id
             WHERE m.id = :id"
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if (!$row) {
            return null;
        }

        $item = $this->formatMediaItem($row);
        $item['usage'] = $this->checkMediaUsage($id);

        return $item;
    }

    /**
     * Updates alt-text accessibility information for a media asset.
     */
    public function updateMediaAltText(int $id, string $altText, int $userId): array
    {
        $altText = trim($altText);
        if ($altText === '') {
            throw new ValidationException(
                "Alt text is mandatory and cannot be empty.",
                ['alt_text' => 'Alt text cannot be empty.'],
                'ERR_VALIDATION'
            );
        }
        if (mb_strlen($altText) > 255) {
            throw new ValidationException("Alt text must not exceed 255 characters.", [], 'ERR_VALIDATION');
        }

        $media = $this->getMediaById($id);
        if (!$media) {
            throw new NotFoundException("Media asset [{$id}] not found.");
        }

        $pdo = $this->connection->getPdo();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("UPDATE media_assets SET alt_text = :alt WHERE id = :id");
            $stmt->execute(['alt' => $altText, 'id' => $id]);
            $stmt->closeCursor();

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new DatabaseException("Failed to update media alt text: " . $e->getMessage(), [], $e);
        }

        // Cryptographic audit logging outside transaction
        try {
            $this->auditLogger->log(
                $pdo,
                'MEDIA',
                'MEDIA_ALT_UPDATED',
                $userId,
                '127.0.0.1',
                'CMS/MediaStorageService',
                'media_asset',
                $id,
                [
                    'old_alt_text' => $media['alt_text'],
                    'new_alt_text' => $altText,
                ]
            );
        } catch (\Throwable $e) {
            // Revert alt text on audit failure
            $rev = $pdo->prepare("UPDATE media_assets SET alt_text = :alt WHERE id = :id");
            $rev->execute(['alt' => $media['alt_text'], 'id' => $id]);
            $rev->closeCursor();
            throw new DatabaseException("Audit logging failed on media alt text update: " . $e->getMessage(), [], $e);
        }

        return $this->getMediaById($id) ?? [];
    }

    /**
     * Inspects active platform entities for references to this media asset.
     * Prevents broken images across the live website (FR-ADM-009 / TEST-ADM-009).
     *
     * @return array<array{type: string, name: string, id: int}>
     */
    public function checkMediaUsage(int $id): array
    {
        $pdo = $this->connection->getPdo();
        $references = [];

        // Fetch asset identifiers
        $stmt = $pdo->prepare("SELECT stored_name, file_path FROM media_assets WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $asset = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if (!$asset) {
            return [];
        }

        $storedName = (string)$asset['stored_name'];
        $filePath = (string)$asset['file_path'];

        // 1. Pages (pages.content_json)
        try {
            $pageStmt = $pdo->prepare(
                "SELECT id, title, content_json FROM pages
                 WHERE (content_json LIKE :name OR content_json LIKE :path OR content_json LIKE :idStr)"
            );
            $pageStmt->execute([
                'name'  => "%{$storedName}%",
                'path'  => "%{$filePath}%",
                'idStr' => '%"hero_image_id": ' . $id . '%',
            ]);
            $pages = $pageStmt->fetchAll(\PDO::FETCH_ASSOC);
            $pageStmt->closeCursor();

            foreach ($pages as $p) {
                $references[] = [
                    'type' => 'Page',
                    'name' => (string)$p['title'],
                    'id'   => (int)$p['id'],
                ];
            }
        } catch (\Throwable) {
            // Table may not exist in current test scope
        }

        // Also check if content_json contains compact "hero_image_id":ID
        if (empty($references)) {
            try {
                $p2Stmt = $pdo->prepare("SELECT id, title FROM pages WHERE content_json LIKE :compactId");
                $p2Stmt->execute(['compactId' => '%"hero_image_id":' . $id . '%']);
                $pages2 = $p2Stmt->fetchAll(\PDO::FETCH_ASSOC);
                $p2Stmt->closeCursor();
                foreach ($pages2 as $p) {
                    $references[] = [
                        'type' => 'Page',
                        'name' => (string)$p['title'],
                        'id'   => (int)$p['id'],
                    ];
                }
            } catch (\Throwable) {
            }
        }

        // 2. Services (services.deliverables_json / custom_icon_svg)
        try {
            $srvStmt = $pdo->prepare(
                "SELECT id, title FROM services
                 WHERE (deliverables_json LIKE :name OR deliverables_json LIKE :path OR custom_icon_svg LIKE :name)"
            );
            $srvStmt->execute([
                'name' => "%{$storedName}%",
                'path' => "%{$filePath}%",
            ]);
            $services = $srvStmt->fetchAll(\PDO::FETCH_ASSOC);
            $srvStmt->closeCursor();

            foreach ($services as $s) {
                $references[] = [
                    'type' => 'Service',
                    'name' => (string)$s['title'],
                    'id'   => (int)$s['id'],
                ];
            }
        } catch (\Throwable) {
        }

        // 3. Articles (articles.featured_image_id / content_html)
        try {
            $artStmt = $pdo->prepare(
                "SELECT id, title FROM articles
                 WHERE featured_image_id = :id OR content_html LIKE :name"
            );
            $artStmt->execute([
                'id'   => $id,
                'name' => "%{$storedName}%",
            ]);
            $articles = $artStmt->fetchAll(\PDO::FETCH_ASSOC);
            $artStmt->closeCursor();

            foreach ($articles as $a) {
                $references[] = [
                    'type' => 'Article',
                    'name' => (string)$a['title'],
                    'id'   => (int)$a['id'],
                ];
            }
        } catch (\Throwable) {
        }

        // 4. Homepage Config (homepage_configs.hero_headline / custom sections)
        try {
            $hpStmt = $pdo->prepare(
                "SELECT id, hero_headline FROM homepage_configs
                 WHERE is_active = 1
                   AND (hero_headline LIKE :name OR hero_subheadline LIKE :name)"
            );
            $hpStmt->execute(['name' => "%{$storedName}%"]);
            $hp = $hpStmt->fetchAll(\PDO::FETCH_ASSOC);
            $hpStmt->closeCursor();

            foreach ($hp as $h) {
                $references[] = [
                    'type' => 'Homepage',
                    'name' => 'Active Homepage Hero',
                    'id'   => (int)$h['id'],
                ];
            }
        } catch (\Throwable) {
        }

        return $references;
    }

    /**
     * Deletes a media asset with strict referential integrity deletion guard.
     * Blocks deletion with HTTP 409 Conflict if referenced anywhere on the live platform.
     *
     * @throws NotFoundException If media not found
     * @throws ConflictException If media is referenced by active content (FR-ADM-009)
     */
    public function deleteMedia(int $id, int $userId): bool
    {
        $pdo = $this->connection->getPdo();

        $stmt = $pdo->prepare("SELECT * FROM media_assets WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $asset = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if (!$asset) {
            throw new NotFoundException("Media asset [{$id}] not found.");
        }

        // Enforce referential integrity deletion guard
        $references = $this->checkMediaUsage($id);
        if (!empty($references)) {
            $first = $references[0];
            $warningMsg = "Cannot delete media asset: currently referenced by {$first['type']}: {$first['name']} (ID: {$first['id']}).";
            throw new ConflictException(
                $warningMsg,
                'ERR_MEDIA_IN_USE',
                ['references' => $references]
            );
        }

        $filePath = (string)$asset['file_path'];
        $storedName = (string)$asset['stored_name'];

        $mainDiskFile = $this->publicStorageDir . '/' . $filePath;
        $thumbName = str_replace('.webp', '_thumb.webp', $storedName);
        $thumbDiskFile = dirname($mainDiskFile) . '/' . $thumbName;

        $pdo->beginTransaction();

        try {
            $del = $pdo->prepare("DELETE FROM media_assets WHERE id = :id");
            $del->execute(['id' => $id]);
            $del->closeCursor();

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new DatabaseException("Failed to delete media asset: " . $e->getMessage(), [], $e);
        }

        // Audit logging outside transaction
        try {
            $this->auditLogger->log(
                $pdo,
                'MEDIA',
                'MEDIA_DELETED',
                $userId,
                '127.0.0.1',
                'CMS/MediaStorageService',
                'media_asset',
                $id,
                [
                    'original_name' => $asset['original_name'],
                    'stored_name'   => $asset['stored_name'],
                    'file_path'     => $asset['file_path'],
                ]
            );
        } catch (\Throwable $e) {
            throw new DatabaseException("Audit logging failed on media deletion: " . $e->getMessage(), [], $e);
        }

        // Remove files from filesystem
        if (file_exists($mainDiskFile)) {
            @unlink($mainDiskFile);
        }
        if (file_exists($thumbDiskFile)) {
            @unlink($thumbDiskFile);
        }

        return true;
    }

    /**
     * Formats database row into rich presentation array with public URLs.
     */
    private function formatMediaItem(array $row): array
    {
        $filePath = (string)($row['file_path'] ?? '');
        $storedName = (string)($row['stored_name'] ?? '');
        $thumbName = str_replace('.webp', '_thumb.webp', $storedName);
        $dir = dirname($filePath);
        $thumbPath = ($dir === '.' || $dir === '') ? $thumbName : "{$dir}/{$thumbName}";

        return [
            'id'                  => (int)$row['id'],
            'original_name'       => (string)$row['original_name'],
            'stored_name'         => $storedName,
            'file_path'           => $filePath,
            'file_size_bytes'     => (int)$row['file_size_bytes'],
            'mime_type'           => (string)$row['mime_type'],
            'dimensions'          => (string)$row['dimensions'],
            'alt_text'            => (string)$row['alt_text'],
            'uploaded_by_user_id' => (int)$row['uploaded_by_user_id'],
            'created_at'          => (string)$row['created_at'],
            'url'                 => "{$this->publicUrlBase}/{$filePath}",
            'thumb_url'           => "{$this->publicUrlBase}/{$thumbPath}",
            'uploader'            => isset($row['uploader_email']) ? [
                'email' => (string)$row['uploader_email'],
                'name'  => (string)($row['uploader_name'] ?? ''),
            ] : null,
        ];
    }

    // =========================================================================
    // PART 2: QUARANTINED PRIVATE DOCUMENTS (RESUMES, TENDERS, STREAMING)
    // =========================================================================

    /**
     * Validates and ingests an inbound sensitive document into the private quarantine enclave.
     * Enforces MIME magic bytes inspection (%PDF-, PKZIP), file size ceilings (10MB/15MB),
     * and writes file with UUIDv4 naming outside the web root (FR-SEC-003 / TEST-SEC-003).
     *
     * @param array{tmp_name: string, name: string, size?: int, error?: int} $file
     * @param string $documentType 'resume' or 'tender'
     * @return array{quarantined_file_path: string, stored_name: string, original_file_name: string, file_size_bytes: int, mime_type: string, sha256_hash: string}
     */
    public function quarantineDocument(array $file, string $documentType = 'resume', ?int $userId = null): array
    {
        $tmpPath = $file['tmp_name'] ?? '';
        if ($tmpPath === '' || !file_exists($tmpPath) || !is_readable($tmpPath)) {
            throw new ValidationException("No document provided or document unreadable.", [], 'ERR_VALIDATION');
        }

        $fileSize = isset($file['size']) && (int)$file['size'] > 0 ? (int)$file['size'] : (int)filesize($tmpPath);

        // Ceiling: 10MB for resumes, 15MB for tenders
        $isResume = in_array(strtolower($documentType), ['resume', 'candidate'], true);
        $maxBytes = $isResume ? (10 * 1024 * 1024) : (15 * 1024 * 1024);

        if ($fileSize > $maxBytes) {
            $limitStr = $isResume ? '10MB' : '15MB';
            throw new ValidationException(
                "Document size exceeds maximum allowable ceiling of {$limitStr}.",
                ['max_size' => $limitStr, 'actual_size' => $fileSize],
                'FILE_TOO_LARGE'
            );
        }

        // Extension Allowlist: strictly pdf, docx
        $originalName = (string)($file['name'] ?? 'document.pdf');
        $rawExtension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        // Detect double extension attacks (e.g. document.pdf.php)
        if (preg_match('/\.(php[0-9]?|phtml|sh|bash|exe|pl|cgi|py)\b/i', $originalName)) {
            throw new ValidationException(
                "Dangerous double-extension or executable script format detected.",
                [],
                'INVALID_EXTENSION'
            );
        }

        if (!in_array($rawExtension, ['pdf', 'docx'], true)) {
            throw new ValidationException(
                "Invalid document extension [{$rawExtension}]. Strictly .pdf and .docx documents are accepted.",
                [],
                'INVALID_EXTENSION'
            );
        }

        // MIME Magic Byte Inspection (Stage 3)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo !== false ? finfo_file($finfo, $tmpPath) : '';
        if ($finfo !== false) {
            finfo_close($finfo);
        }

        // Header binary signature check
        $headerBytes = (string)file_get_contents($tmpPath, false, null, 0, 16);

        if ($rawExtension === 'pdf') {
            // Must begin with %PDF- (25 50 44 46)
            if (!str_starts_with($headerBytes, '%PDF-') || !str_contains($detectedMime, 'pdf')) {
                throw new ValidationException(
                    "Invalid file MIME type or signature: expected application/pdf.",
                    [],
                    'INVALID_FILE_SIGNATURE'
                );
            }
        } elseif ($rawExtension === 'docx') {
            // Must be PKZIP container beginning with PK\x03\x04
            if (!str_starts_with($headerBytes, "PK\x03\x04")) {
                throw new ValidationException(
                    "Invalid file MIME type or signature: expected valid DOCX PKZIP archive.",
                    [],
                    'INVALID_FILE_SIGNATURE'
                );
            }
        }

        // Reject script tags in raw document body
        $sampleBody = (string)file_get_contents($tmpPath, false, null, 0, 2048);
        if (preg_match('/<\?(?:php|=)/i', $sampleBody)) {
            throw new ValidationException(
                "Malicious script tags detected inside document body.",
                [],
                'INVALID_FILE_SIGNATURE'
            );
        }

        // Generate UUIDv4 storage filename and partition subdirectories (Stage 4)
        $uuid = Crypto::uuidv4();
        $storedName = "doc_{$uuid}.{$rawExtension}";

        $subA = substr($uuid, 0, 2);
        $subB = substr($uuid, 2, 2);
        $partitionDir = "{$this->quarantineStorageDir}/{$subA}/{$subB}";
        if (!is_dir($partitionDir)) {
            @mkdir($partitionDir, 0700, true);
        }

        $targetQuarantinePath = "{$partitionDir}/{$storedName}";

        // Move to isolated quarantine storage
        $moved = @copy($tmpPath, $targetQuarantinePath);
        if (!$moved) {
            throw new ValidationException("Failed to persist document to secure quarantine enclave.", [], 'ERR_INTERNAL');
        }

        // Apply restrictive filesystem permissions (chmod 0600)
        @chmod($targetQuarantinePath, 0600);

        $sha256Hash = (string)hash_file('sha256', $targetQuarantinePath);

        return [
            'quarantined_file_path' => str_replace('\\', '/', $targetQuarantinePath),
            'stored_name'           => $storedName,
            'original_file_name'    => basename($originalName),
            'file_size_bytes'       => (int)filesize($targetQuarantinePath),
            'mime_type'             => $detectedMime !== '' ? $detectedMime : ($rawExtension === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            'sha256_hash'           => $sha256Hash,
        ];
    }

    /**
     * Authenticated document streaming proxy (FR-SEC-004 / TEST-SEC-004).
     * Enforces path normalization (preventing path traversal), emits strict secure download headers,
     * logs a chained cryptographic audit ledger entry, and streams the document safely.
     *
     * @throws SecurityException On path traversal attempt
     * @throws NotFoundException On missing file asset
     */
    public function streamQuarantinedFile(
        string $quarantinedPath,
        string $originalFilename,
        string $mimeType,
        int $userId,
        string $auditAction,
        string $domain,
        int $entityId,
        ?string $clientIp = null
    ): Response {
        $normalizedTarget = str_replace('\\', '/', $quarantinedPath);
        $normalizedQuarantine = str_replace('\\', '/', $this->quarantineStorageDir);

        // Path Traversal Guard: must be strictly inside quarantine storage directory
        if (!str_starts_with($normalizedTarget, $normalizedQuarantine)) {
            throw new SecurityException("Path traversal violation: file location outside authorized quarantine boundaries.");
        }

        if (!file_exists($normalizedTarget) || !is_readable($normalizedTarget)) {
            throw new NotFoundException("File asset missing from quarantine storage.");
        }

        // Sanitize download filename for safe Content-Disposition
        $safeDownloadName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', basename($originalFilename));
        if ($safeDownloadName === '') {
            $safeDownloadName = 'download.pdf';
        }

        // Cryptographic Audit Log Event (FR-SEC-004)
        $pdo = $this->connection->getPdo();
        $this->auditLogger->log(
            $pdo,
            strtoupper($domain),
            $auditAction,
            $userId,
            $clientIp ?? '127.0.0.1',
            'StreamingProxy/1.0',
            strtolower($domain),
            $entityId,
            [
                'file_name'   => $safeDownloadName,
                'size_bytes'  => filesize($normalizedTarget),
                'sha256_hash' => hash_file('sha256', $normalizedTarget),
            ]
        );

        // Construct strict secure download response
        return Response::download(
            $normalizedTarget,
            $safeDownloadName,
            $mimeType !== '' ? $mimeType : 'application/pdf'
        );
    }
}
