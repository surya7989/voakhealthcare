<?php

declare(strict_types=1);

namespace Voak\Health\Services;

use Voak\Health\Core\Exceptions\BadRequestException;
use Voak\Health\Core\Exceptions\NotFoundException;
use Voak\Health\Core\Exceptions\SecurityException;
use Voak\Health\Core\Exceptions\ValidationException;
use Voak\Health\Database\Connection;
use Voak\Health\Security\AuditLogger;
use Voak\Health\Security\HtmlSanitizer;

/**
 * Enterprise Page Management and Content Revision Service.
 * Manages static corporate pages, publishing state machine, and immutable revision history.
 */
class PageService
{
    private const SLUG_REGEX = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';
    private const VALID_STATUSES = ['draft', 'published', 'archived'];

    private Connection $connection;
    private AuditLogger $auditLogger;
    private HtmlSanitizer $sanitizer;

    public function __construct(
        Connection $connection,
        AuditLogger $auditLogger,
        HtmlSanitizer $sanitizer
    ) {
        $this->connection = $connection;
        $this->auditLogger = $auditLogger;
        $this->sanitizer = $sanitizer;
    }

    /**
     * Validates and normalizes slug input.
     *
     * @throws BadRequestException If slug is invalid, empty, or contains traversal
     */
    public function validateSlug(string $slug): string
    {
        $trimmed = trim($slug);

        if ($trimmed === '') {
            throw new BadRequestException("Slug cannot be empty.");
        }

        // Strict rejection of path traversal, slashes, backslashes, query params
        if (str_contains($trimmed, '/') || str_contains($trimmed, '\\') || str_contains($trimmed, '..') || str_contains($trimmed, '?') || str_contains($trimmed, '#')) {
            throw new BadRequestException("Slug contains invalid or dangerous characters.");
        }

        // Canonical contract: lowercase only
        if ($trimmed !== strtolower($trimmed)) {
            throw new BadRequestException("Slug must be lowercase alphanumeric with hyphens.");
        }

        if (strlen($trimmed) > 100) {
            throw new BadRequestException("Slug cannot exceed 100 characters.");
        }

        if (!preg_match(self::SLUG_REGEX, $trimmed)) {
            throw new BadRequestException("Invalid slug format. Use lowercase alphanumeric with single hyphens (e.g., 'about-us').");
        }

        return $trimmed;
    }

    /**
     * Lists pages with pagination, search, and status filtering.
     *
     * @param array<string, mixed> $filters
     * @return array{pages: list<array<string, mixed>>, pagination: array<string, int>}
     */
    public function listPages(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $pdo = $this->connection->getPdo();

        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $where = ["1=1"];
        $params = [];

        // Status filter
        if (!empty($filters['status'])) {
            $status = strtolower((string)$filters['status']);
            if (in_array($status, self::VALID_STATUSES, true)) {
                if ($status === 'published') {
                    $where[] = "p.`is_published` = 1";
                } elseif ($status === 'draft') {
                    $where[] = "p.`is_published` = 0 AND (p.`content_json` NOT LIKE '%\"status\":\"archived\"%' AND p.`content_json` NOT LIKE '%\"deleted_at\":\"2%')";
                } elseif ($status === 'archived') {
                    $where[] = "p.`content_json` LIKE '%\"status\":\"archived\"%'";
                }
            }
        }

        // Search filter
        if (!empty($filters['search'])) {
            $search = '%' . trim((string)$filters['search']) . '%';
            $where[] = "(p.`title` LIKE :search OR p.`slug` LIKE :search)";
            $params['search'] = $search;
        }

        // Exclude soft-deleted by default
        $includeDeleted = !empty($filters['include_deleted']);
        if (!$includeDeleted) {
            $where[] = "(p.`content_json` NOT LIKE '%\"deleted_at\":\"2%')";
        }

        $whereClause = implode(' AND ', $where);

        // Count total
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM `pages` p WHERE {$whereClause}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        $countStmt->closeCursor();

        // Fetch records
        $sql = "SELECT p.* FROM `pages` p WHERE {$whereClause} ORDER BY p.`id` DESC LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        $pages = [];
        foreach ($rows as $row) {
            $pages[] = $this->formatPageRecord($row);
        }

        return [
            'pages' => $pages,
            'pagination' => [
                'total'        => $total,
                'page'         => $page,
                'per_page'     => $perPage,
                'total_pages'  => (int)ceil($total / $perPage),
            ],
        ];
    }

    /**
     * Retrieves a single page by its primary ID.
     */
    public function getPageById(int $id, bool $includeDeleted = false): ?array
    {
        $pdo = $this->connection->getPdo();
        $stmt = $pdo->prepare("SELECT * FROM `pages` WHERE `id` = :id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if (!$row) {
            return null;
        }

        $page = $this->formatPageRecord($row);

        if (!$includeDeleted && !empty($page['deleted_at'])) {
            return null;
        }

        return $page;
    }

    /**
     * Retrieves a published page by slug for public presentation.
     */
    public function getPublishedPageBySlug(string $slug): ?array
    {
        $cleanSlug = $this->validateSlug($slug);
        $pdo = $this->connection->getPdo();

        $stmt = $pdo->prepare(
            "SELECT * FROM `pages` WHERE `slug` = :slug AND `is_published` = 1"
        );
        $stmt->execute(['slug' => $cleanSlug]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if (!$row) {
            return null;
        }

        $page = $this->formatPageRecord($row);

        // Verify page is not soft-deleted and status is published
        if (!empty($page['deleted_at']) || $page['status'] !== 'published') {
            return null;
        }

        return $page;
    }

    /**
     * Creates a new page and its initial immutable content revision.
     */
    public function createPage(array $data, int $userId): array
    {
        $pdo = $this->connection->getPdo();

        // 1. Validate fields
        $slug = $this->validateSlug((string)($data['slug'] ?? ''));
        $title = trim((string)($data['title'] ?? ''));
        if ($title === '' || strlen($title) > 150) {
            throw new ValidationException("Title is required and must not exceed 150 characters.");
        }

        $metaTitle = trim((string)($data['meta_title'] ?? $title));
        if (strlen($metaTitle) > 150) {
            $metaTitle = substr($metaTitle, 0, 150);
        }

        $metaDescription = trim((string)($data['meta_description'] ?? ''));
        if (strlen($metaDescription) > 255) {
            $metaDescription = substr($metaDescription, 0, 255);
        }

        // Slug uniqueness check
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `pages` WHERE `slug` = :slug");
        $stmt->execute(['slug' => $slug]);
        if ((int)$stmt->fetchColumn() > 0) {
            $stmt->closeCursor();
            throw new BadRequestException("Page with slug '{$slug}' already exists.");
        }
        $stmt->closeCursor();

        $status = strtolower((string)($data['status'] ?? 'draft'));
        if (!in_array($status, self::VALID_STATUSES, true)) {
            $status = 'draft';
        }

        // 2. Sanitize rich HTML content
        $contentRaw = $data['content'] ?? $data['content_json'] ?? [];
        $contentSanitized = $this->sanitizer->sanitizePayload($contentRaw);

        $isPublished = ($status === 'published') ? 1 : 0;
        $publishedAt = ($status === 'published') ? gmdate('Y-m-d H:i:s') : null;

        $contentStructure = [
            'body'       => is_string($contentSanitized) ? $contentSanitized : ($contentSanitized['body'] ?? ''),
            'sections'   => is_array($contentSanitized) && isset($contentSanitized['sections']) ? $contentSanitized['sections'] : [],
            'status'     => $status,
            'created_by' => $userId,
            'updated_by' => $userId,
            'deleted_at' => null,
        ];

        $contentJson = json_encode($contentStructure, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // 3. Atomic database transaction
        $pdo->beginTransaction();
        try {
            $insertStmt = $pdo->prepare(
                "INSERT INTO `pages` 
                 (`slug`, `title`, `meta_title`, `meta_description`, `content_json`, `is_published`, `published_at`, `created_at`, `updated_at`) 
                 VALUES 
                 (:slug, :title, :meta_title, :meta_desc, :content, :is_pub, :pub_at, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
            );
            $insertStmt->execute([
                'slug'       => $slug,
                'title'      => $title,
                'meta_title' => $metaTitle,
                'meta_desc'  => $metaDescription,
                'content'    => $contentJson,
                'is_pub'     => $isPublished,
                'pub_at'     => $publishedAt,
            ]);
            $insertStmt->closeCursor();

            $pageId = (int)$pdo->lastInsertId();

            // Create initial content revision (revision 1)
            $this->insertRevisionInternal($pdo, $pageId, 1, $title, $metaTitle, $metaDescription, $contentStructure, "Initial page creation", $userId);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        // 4. Cryptographic audit log outside transaction with compensating rollback defense
        try {
            $this->auditLogger->log(
                $pdo,
                'cms',
                'page_created',
                $userId,
                '127.0.0.1',
                'CMS/PageService',
                'pages',
                $pageId,
                ['slug' => $slug, 'title' => $title, 'status' => $status]
            );
        } catch (\Throwable $auditEx) {
            // Compensating rollback: purge un-audited created page and initial revision
            try {
                $pdo->beginTransaction();
                $delRev = $pdo->prepare("DELETE FROM `content_revisions` WHERE `entity_type` = 'pages' AND `entity_id` = :id");
                $delRev->execute(['id' => $pageId]);
                $delRev->closeCursor();

                $delPage = $pdo->prepare("DELETE FROM `pages` WHERE `id` = :id");
                $delPage->execute(['id' => $pageId]);
                $delPage->closeCursor();
                $pdo->commit();
            } catch (\Throwable) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            }
            throw new SecurityException("Failed to append immutable audit ledger event: " . $auditEx->getMessage(), 'ERR_AUDIT_LOG_FAILED');
        }

        return $this->getPageById($pageId) ?? [];
    }

    /**
     * Updates an existing page and creates a new immutable content revision.
     */
    public function updatePage(int $id, array $data, int $userId): array
    {
        $pdo = $this->connection->getPdo();
        $existing = $this->getPageById($id);

        if (!$existing) {
            throw new NotFoundException("Page [{$id}] not found.");
        }

        // Validate slug if changed
        $slug = $existing['slug'];
        if (isset($data['slug']) && $data['slug'] !== '') {
            $newSlug = $this->validateSlug((string)$data['slug']);
            if ($newSlug !== $existing['slug']) {
                $check = $pdo->prepare("SELECT COUNT(*) FROM `pages` WHERE `slug` = :slug AND `id` != :id");
                $check->execute(['slug' => $newSlug, 'id' => $id]);
                if ((int)$check->fetchColumn() > 0) {
                    $check->closeCursor();
                    throw new BadRequestException("Slug '{$newSlug}' is already in use by another page.");
                }
                $check->closeCursor();
                $slug = $newSlug;
            }
        }

        $title = isset($data['title']) ? trim((string)$data['title']) : $existing['title'];
        if ($title === '' || strlen($title) > 150) {
            throw new ValidationException("Title must not be empty and cannot exceed 150 characters.");
        }

        $metaTitle = isset($data['meta_title']) ? trim((string)$data['meta_title']) : $existing['meta_title'];
        $metaDescription = isset($data['meta_description']) ? trim((string)$data['meta_description']) : $existing['meta_description'];

        // Content sanitization
        $existingContent = $existing['content_json'];
        if (isset($data['content']) || isset($data['content_json'])) {
            $rawContent = $data['content'] ?? $data['content_json'];
            $sanitized = $this->sanitizer->sanitizePayload($rawContent);
            $body = is_string($sanitized) ? $sanitized : ($sanitized['body'] ?? ($existingContent['body'] ?? ''));
            $sections = is_array($sanitized) && isset($sanitized['sections']) ? $sanitized['sections'] : ($existingContent['sections'] ?? []);
        } else {
            $body = $existingContent['body'] ?? '';
            $sections = $existingContent['sections'] ?? [];
        }

        $status = $existing['status'];
        if (isset($data['status'])) {
            $candidateStatus = strtolower((string)$data['status']);
            if (in_array($candidateStatus, self::VALID_STATUSES, true)) {
                $status = $candidateStatus;
            }
        }

        $isPublished = ($status === 'published') ? 1 : 0;
        $publishedAt = $existing['published_at'];
        if ($status === 'published' && empty($publishedAt)) {
            $publishedAt = gmdate('Y-m-d H:i:s');
        }

        $contentStructure = [
            'body'       => $body,
            'sections'   => $sections,
            'status'     => $status,
            'created_by' => $existingContent['created_by'] ?? $userId,
            'updated_by' => $userId,
            'deleted_at' => $existingContent['deleted_at'] ?? null,
        ];

        $contentJson = json_encode($contentStructure, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $changeSummary = isset($data['change_summary']) ? trim((string)$data['change_summary']) : 'Page content updated';

        $pdo->beginTransaction();
        try {
            // Row-level lock on the target page row to prevent concurrent race conditions
            $driver = (string)$pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
            $lockSql = "SELECT `id` FROM `pages` WHERE `id` = :id" . ($driver === 'mysql' ? " FOR UPDATE" : "");
            $lockStmt = $pdo->prepare($lockSql);
            $lockStmt->execute(['id' => $id]);
            $lockStmt->closeCursor();

            $updateStmt = $pdo->prepare(
                "UPDATE `pages` SET 
                 `slug` = :slug,
                 `title` = :title,
                 `meta_title` = :meta_title,
                 `meta_description` = :meta_desc,
                 `content_json` = :content,
                 `is_published` = :is_pub,
                 `published_at` = :pub_at,
                 `updated_at` = CURRENT_TIMESTAMP
                 WHERE `id` = :id"
            );
            $updateStmt->execute([
                'slug'       => $slug,
                'title'      => $title,
                'meta_title' => $metaTitle,
                'meta_desc'  => $metaDescription,
                'content'    => $contentJson,
                'is_pub'     => $isPublished,
                'pub_at'     => $publishedAt,
                'id'         => $id,
            ]);
            $updateStmt->closeCursor();

            // Calculate next monotonically increasing revision number
            $revCountStmt = $pdo->prepare(
                "SELECT COUNT(*) FROM `content_revisions` WHERE `entity_type` = 'pages' AND `entity_id` = :id"
            );
            $revCountStmt->execute(['id' => $id]);
            $nextRev = (int)$revCountStmt->fetchColumn() + 1;
            $revCountStmt->closeCursor();

            $insertedRevId = $this->insertRevisionInternal($pdo, $id, $nextRev, $title, $metaTitle, $metaDescription, $contentStructure, $changeSummary, $userId);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        // Post-commit audit logging with compensating rollback defense
        try {
            $this->auditLogger->log(
                $pdo,
                'cms',
                'page_updated',
                $userId,
                '127.0.0.1',
                'CMS/PageService',
                'pages',
                $id,
                ['slug' => $slug, 'title' => $title, 'revision' => $nextRev]
            );
        } catch (\Throwable $auditEx) {
            // Compensating rollback: restore previous page state and purge un-audited revision
            try {
                $pdo->beginTransaction();
                $revertStmt = $pdo->prepare(
                    "UPDATE `pages` SET 
                     `slug` = :slug,
                     `title` = :title,
                     `meta_title` = :meta_title,
                     `meta_description` = :meta_desc,
                     `content_json` = :content,
                     `is_published` = :is_pub,
                     `published_at` = :pub_at
                     WHERE `id` = :id"
                );
                $revertStmt->execute([
                    'slug'       => $existing['slug'],
                    'title'      => $existing['title'],
                    'meta_title' => $existing['meta_title'],
                    'meta_desc'  => $existing['meta_description'],
                    'content'    => is_array($existing['content_json']) ? json_encode($existing['content_json']) : $existing['content_json'],
                    'is_pub'     => $existing['is_published'] ? 1 : 0,
                    'pub_at'     => $existing['published_at'],
                    'id'         => $id,
                ]);
                $revertStmt->closeCursor();

                if ($insertedRevId > 0) {
                    $delRev = $pdo->prepare("DELETE FROM `content_revisions` WHERE `id` = :rev_id");
                    $delRev->execute(['rev_id' => $insertedRevId]);
                    $delRev->closeCursor();
                }
                $pdo->commit();
            } catch (\Throwable) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            }
            throw new SecurityException("Failed to append immutable audit ledger event: " . $auditEx->getMessage(), 'ERR_AUDIT_LOG_FAILED');
        }

        return $this->getPageById($id) ?? [];
    }

    /**
     * Transitions page publication status (draft, published, archived).
     */
    public function updatePageStatus(int $id, string $status, int $userId): array
    {
        $status = strtolower(trim($status));
        if (!in_array($status, self::VALID_STATUSES, true)) {
            throw new BadRequestException("Invalid status '{$status}'. Valid statuses: draft, published, archived.");
        }

        $existing = $this->getPageById($id);
        if (!$existing) {
            throw new NotFoundException("Page [{$id}] not found.");
        }

        return $this->updatePage($id, [
            'status'         => $status,
            'change_summary' => "Status transitioned to {$status}",
        ], $userId);
    }

    /**
     * Soft deletes a page.
     */
    public function softDeletePage(int $id, int $userId): void
    {
        $existing = $this->getPageById($id);
        if (!$existing) {
            throw new NotFoundException("Page [{$id}] not found.");
        }

        $pdo = $this->connection->getPdo();
        $now = gmdate('Y-m-d H:i:s');

        $contentStructure = $existing['content_json'];
        $contentStructure['deleted_at'] = $now;
        $contentStructure['status'] = 'archived';
        $contentJson = json_encode($contentStructure, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $pdo->beginTransaction();
        try {
            // Row-level lock on target page
            $driver = (string)$pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
            $lockSql = "SELECT `id` FROM `pages` WHERE `id` = :id" . ($driver === 'mysql' ? " FOR UPDATE" : "");
            $lockStmt = $pdo->prepare($lockSql);
            $lockStmt->execute(['id' => $id]);
            $lockStmt->closeCursor();

            $stmt = $pdo->prepare(
                "UPDATE `pages` SET `is_published` = 0, `content_json` = :content, `updated_at` = CURRENT_TIMESTAMP WHERE `id` = :id"
            );
            $stmt->execute(['content' => $contentJson, 'id' => $id]);
            $stmt->closeCursor();

            $revCountStmt = $pdo->prepare("SELECT COUNT(*) FROM `content_revisions` WHERE `entity_type` = 'pages' AND `entity_id` = :id");
            $revCountStmt->execute(['id' => $id]);
            $nextRev = (int)$revCountStmt->fetchColumn() + 1;
            $revCountStmt->closeCursor();

            $insertedRevId = $this->insertRevisionInternal($pdo, $id, $nextRev, $existing['title'], $existing['meta_title'], $existing['meta_description'], $contentStructure, "Page soft-deleted", $userId);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        // Post-commit audit logging with compensating rollback defense
        try {
            $this->auditLogger->log(
                $pdo,
                'cms',
                'page_deleted',
                $userId,
                '127.0.0.1',
                'CMS/PageService',
                'pages',
                $id,
                ['deleted_at' => $now]
            );
        } catch (\Throwable $auditEx) {
            // Compensating rollback: restore previous page state and purge un-audited revision
            try {
                $pdo->beginTransaction();
                $revertStmt = $pdo->prepare(
                    "UPDATE `pages` SET `is_published` = :is_pub, `content_json` = :content WHERE `id` = :id"
                );
                $revertStmt->execute([
                    'is_pub'  => $existing['is_published'] ? 1 : 0,
                    'content' => is_array($existing['content_json']) ? json_encode($existing['content_json']) : $existing['content_json'],
                    'id'      => $id,
                ]);
                $revertStmt->closeCursor();

                if ($insertedRevId > 0) {
                    $delRev = $pdo->prepare("DELETE FROM `content_revisions` WHERE `id` = :rev_id");
                    $delRev->execute(['rev_id' => $insertedRevId]);
                    $delRev->closeCursor();
                }
                $pdo->commit();
            } catch (\Throwable) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            }
            throw new SecurityException("Failed to append immutable audit ledger event: " . $auditEx->getMessage(), 'ERR_AUDIT_LOG_FAILED');
        }
    }

    /**
     * Lists immutable content revisions for a page.
     *
     * @return list<array<string, mixed>>
     */
    public function listRevisions(int $pageId): array
    {
        $pdo = $this->connection->getPdo();
        $stmt = $pdo->prepare(
            "SELECT r.`id`, r.`entity_id` as page_id, r.`editor_user_id` as author_id,
                    r.`payload_snapshot`, r.`revision_note` as change_summary, r.`created_at`,
                    u.`name` as author_name, u.`email` as author_email
             FROM `content_revisions` r
             LEFT JOIN `users` u ON u.`id` = r.`editor_user_id`
             WHERE r.`entity_type` = 'pages' AND r.`entity_id` = :page_id
             ORDER BY r.`id` ASC"
        );
        $stmt->execute(['page_id' => $pageId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        $revisions = [];
        $revIndex = 1;
        foreach ($rows as $row) {
            $snapshot = json_decode((string)$row['payload_snapshot'], true) ?: [];
            $revisions[] = [
                'id'              => (int)$row['id'],
                'page_id'         => (int)$row['page_id'],
                'revision_number' => $snapshot['revision_number'] ?? $revIndex,
                'author_id'       => (int)$row['author_id'],
                'author_name'     => $row['author_name'] ?? 'System',
                'title'           => $snapshot['title'] ?? '',
                'change_summary'  => $row['change_summary'] ?? '',
                'created_at'      => $row['created_at'],
                'snapshot'        => $snapshot,
            ];
            $revIndex++;
        }

        return $revisions;
    }

    /**
     * Rolls back a page to a previous content revision.
     * Creates a NEW revision representing the rollback, strictly preserving historical immutability.
     */
    public function rollbackRevision(int $pageId, int $targetRevisionNumber, int $userId): array
    {
        $revisions = $this->listRevisions($pageId);
        $targetRev = null;

        foreach ($revisions as $rev) {
            if ((int)$rev['revision_number'] === $targetRevisionNumber) {
                $targetRev = $rev;
                break;
            }
        }

        if ($targetRev === null) {
            throw new NotFoundException("Revision [{$targetRevisionNumber}] not found for page [{$pageId}].");
        }

        $snapshot = $targetRev['snapshot'];
        $title = $snapshot['title'] ?? 'Restored Page';
        $metaTitle = $snapshot['meta_title'] ?? $title;
        $metaDesc = $snapshot['meta_description'] ?? '';
        $contentStructure = $snapshot['content_structure'] ?? [];

        // Apply restored state
        return $this->updatePage($pageId, [
            'title'            => $title,
            'meta_title'       => $metaTitle,
            'meta_description' => $metaDesc,
            'content_json'     => $contentStructure,
            'change_summary'   => "Rollback to revision #{$targetRevisionNumber}",
        ], $userId);
    }

    private function insertRevisionInternal(
        \PDO $pdo,
        int $pageId,
        int $revisionNumber,
        string $title,
        string $metaTitle,
        string $metaDescription,
        array $contentStructure,
        string $note,
        int $userId
    ): int {
        $snapshot = [
            'revision_number'   => $revisionNumber,
            'page_id'           => $pageId,
            'title'             => $title,
            'meta_title'        => $metaTitle,
            'meta_description'  => $metaDescription,
            'content_structure' => $contentStructure,
            'created_at'        => gmdate('Y-m-d H:i:s'),
        ];

        $snapshotJson = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $stmt = $pdo->prepare(
            "INSERT INTO `content_revisions` 
             (`entity_type`, `entity_id`, `editor_user_id`, `payload_snapshot`, `revision_note`, `created_at`) 
             VALUES 
             ('pages', :pid, :uid, :snapshot, :note, CURRENT_TIMESTAMP)"
        );
        $stmt->execute([
            'pid'      => $pageId,
            'uid'      => $userId,
            'snapshot' => $snapshotJson,
            'note'     => substr($note, 0, 255),
        ]);
        $stmt->closeCursor();

        return (int)$pdo->lastInsertId();
    }

    private function formatPageRecord(array $row): array
    {
        $content = json_decode((string)$row['content_json'], true) ?: [];

        return [
            'id'               => (int)$row['id'],
            'slug'             => (string)$row['slug'],
            'title'            => (string)$row['title'],
            'meta_title'       => (string)$row['meta_title'],
            'meta_description' => (string)$row['meta_description'],
            'content_json'     => $content,
            'is_published'     => (int)$row['is_published'] === 1,
            'status'           => $content['status'] ?? ((int)$row['is_published'] === 1 ? 'published' : 'draft'),
            'published_at'     => $row['published_at'],
            'deleted_at'       => $content['deleted_at'] ?? null,
            'created_at'       => $row['created_at'],
            'updated_at'       => $row['updated_at'],
        ];
    }

    public function calculateEtag(array $page): string
    {
        $hashInput = ($page['id'] ?? 0) . '|'
            . ($page['slug'] ?? '') . '|'
            . ($page['updated_at'] ?? '') . '|'
            . ($page['title'] ?? '') . '|'
            . md5(json_encode($page['content_json'] ?? []));

        return '"' . hash('sha256', $hashInput) . '"';
    }
}

