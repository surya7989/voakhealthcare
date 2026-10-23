<?php

declare(strict_types=1);

namespace Voak\Health\Services;

use Voak\Health\Auth\RbacManager;
use Voak\Health\Core\Exceptions\BadRequestException;
use Voak\Health\Core\Exceptions\DatabaseException;
use Voak\Health\Core\Exceptions\NotFoundException;
use Voak\Health\Core\Exceptions\ValidationException;
use Voak\Health\Database\Connection;
use Voak\Health\Security\AuditLogger;

/**
 * Authoritative SEO Engine, Dynamic XML Sitemap & Automated 301 Redirect Architecture (Module 17).
 *
 * Implements:
 * - ADM-017 / ADM-018 / FR-ADM-018: Global and route-level SEO meta management, length warnings, sitemap invalidation
 * - FR-WF-006 / TEST-WF-006: Safe slug renaming & 301 redirect engine with circular loop prevention and 1-hop chain flattening
 * - FR-SEO-001 / TEST-SEO-001: Hybrid semantic pre-rendering fallback (#ssr-fallback)
 * - FR-SEO-002 / TEST-SEO-002: Authentic HTTP 404 emission with robots noindex directive
 * - FR-SEO-003 / TEST-SEO-003: Dynamic XML sitemap generation (/sitemap.xml) with W3C ISO 8601 lastmod dates
 * - FR-SEO-004 / TEST-SEO-004: Structured data (JSON-LD) schemas for Organization, Service, Article, FAQPage, JobPosting
 */
class SeoService
{
    /**
     * Reserved routes that cannot be used as redirect sources or destinations.
     */
    public const RESERVED_ROUTES = [
        '/',
        '/admin',
        '/api',
        '/sitemap.xml',
        '/robots.txt',
        '/health',
        '/favicon.ico',
    ];

    /**
     * Recommended character limits for SEO metadata.
     */
    public const TITLE_RECOMMENDED_MIN = 30;
    public const TITLE_RECOMMENDED_MAX = 60;
    public const TITLE_ABSOLUTE_MAX = 70;

    public const DESC_RECOMMENDED_MIN = 70;
    public const DESC_RECOMMENDED_MAX = 155;
    public const DESC_ABSOLUTE_MAX = 160;

    private Connection $connection;
    private AuditLogger $auditLogger;
    private ?RbacManager $rbacManager;
    private ?string $cacheDir;
    private string $baseUrl;

    public function __construct(
        Connection $connection,
        AuditLogger $auditLogger,
        ?RbacManager $rbacManager = null,
        ?string $cacheDir = null,
        string $baseUrl = 'https://voakhealthcare.com'
    ) {
        $this->connection = $connection;
        $this->auditLogger = $auditLogger;
        $this->rbacManager = $rbacManager;
        $this->cacheDir = $cacheDir !== null ? rtrim($cacheDir, '/\\') : null;
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * Normalizes a URL path:
     * - Trims whitespace
     * - Replaces backslashes with slashes
     * - Ensures leading slash
     * - Lowercases path
     * - Removes trailing slashes (except for root '/')
     * - Collapses multiple consecutive slashes
     */
    public static function normalizePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '/';
        }

        $path = str_replace('\\', '/', $path);
        $path = (string)preg_replace('#/+#', '/', $path);

        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        $path = strtolower($path);

        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        return $path;
    }

    // =========================================================================
    // SECTION 1: URL REDIRECT ENGINE (FR-WF-006 / TEST-WF-006)
    // =========================================================================

    /**
     * Resolves an incoming request path against active 301/302 redirects.
     * Atomically increments the hit count and updates last_accessed_at timestamp.
     *
     * @return array{id: int, source_path: string, target_path: string, http_status_code: int}|null
     */
    public function resolveRedirect(string $requestUri): ?array
    {
        $parsedPath = parse_url($requestUri, PHP_URL_PATH);
        $path = self::normalizePath(is_string($parsedPath) ? $parsedPath : $requestUri);

        // Reserved routes or API endpoints never redirect
        if (in_array($path, self::RESERVED_ROUTES, true) ||
            str_starts_with($path, '/api') ||
            str_starts_with($path, '/admin')
        ) {
            return null;
        }

        try {
            $pdo = $this->connection->getPdo();
            $stmt = $pdo->prepare(
                "SELECT `id`, `source_path`, `target_path`, `http_status_code`
                 FROM `url_redirects`
                 WHERE `source_path` = :source AND `is_active` = 1
                 LIMIT 1"
            );
            $stmt->execute(['source' => $path]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            if (!$row) {
                return null;
            }

            // Atomically record hit
            $updateStmt = $pdo->prepare(
                "UPDATE `url_redirects`
                 SET `hit_count` = `hit_count` + 1,
                     `last_accessed_at` = CURRENT_TIMESTAMP
                 WHERE `id` = :id"
            );
            $updateStmt->execute(['id' => (int)$row['id']]);
            $updateStmt->closeCursor();

            return [
                'id'               => (int)$row['id'],
                'source_path'      => (string)$row['source_path'],
                'target_path'      => (string)$row['target_path'],
                'http_status_code' => (int)$row['http_status_code'],
            ];
        } catch (\Throwable $e) {
            // Fail open: if database is unreachable or table not present, do not disrupt request pipeline
            return null;
        }
    }

    /**
     * Registers a new or updated redirect rule.
     * Enforces:
     * 1. Reserved route validation (cannot redirect reserved system paths)
     * 2. Self-redirect prevention (source != target)
     * 3. Circular redirect detection (A -> B -> A)
     * 4. Multi-hop chain flattening (A -> B and B -> C results in A -> C)
     */
    public function registerRedirect(
        string $source,
        string $target,
        int $statusCode = 301,
        ?int $userId = null,
        string $ip = '127.0.0.1',
        string $userAgent = 'CLI/SeoService'
    ): array {
        $sourceNorm = self::normalizePath($source);
        $targetNorm = self::normalizePath($target);

        // 1. Validate self-redirect
        if ($sourceNorm === $targetNorm) {
            throw new ValidationException([
                'target_path' => 'Target path cannot be identical to source path (self-redirect).',
            ]);
        }

        // 2. Validate HTTP status code
        if (!in_array($statusCode, [301, 302, 307, 308], true)) {
            throw new ValidationException([
                'http_status_code' => 'HTTP status code must be a valid redirect code (301, 302, 307, 308).',
            ]);
        }

        // 3. Reserved route validation
        if (in_array($sourceNorm, self::RESERVED_ROUTES, true) ||
            str_starts_with($sourceNorm, '/admin') ||
            str_starts_with($sourceNorm, '/api')
        ) {
            throw new ValidationException([
                'source_path' => "Cannot redirect reserved system path: {$sourceNorm}",
            ]);
        }

        if (str_starts_with($targetNorm, '/api')) {
            throw new ValidationException([
                'target_path' => "Cannot redirect web traffic directly to internal API endpoint: {$targetNorm}",
            ]);
        }

        $pdo = $this->connection->getPdo();

        // 4. Circular Loop Detection
        // If traversing existing redirects starting from $targetNorm leads back to $sourceNorm,
        // registering $sourceNorm -> $targetNorm would complete an infinite redirect cycle!
        $visited = [$sourceNorm => true];
        $cursor = $targetNorm;
        while ($cursor !== null) {
            if (isset($visited[$cursor])) {
                throw new ValidationException([
                    'target_path' => "Circular redirect loop detected: redirecting {$sourceNorm} to {$targetNorm} would create an infinite redirect loop.",
                ]);
            }
            $visited[$cursor] = true;

            $checkStmt = $pdo->prepare(
                "SELECT `target_path` FROM `url_redirects` WHERE `source_path` = :src AND `is_active` = 1 LIMIT 1"
            );
            $checkStmt->execute(['src' => $cursor]);
            $nextTarget = $checkStmt->fetchColumn();
            $checkStmt->closeCursor();

            $cursor = is_string($nextTarget) ? $nextTarget : null;
        }

        // 5. Flattening redirect chains to 1 hop (FR-WF-006):
        // Case A: If $targetNorm is already an active source in url_redirects (e.g. B -> C exists, and we add A -> B),
        // resolve $resolvedTarget directly to C so A -> C (1 hop).
        $targetLookup = $pdo->prepare(
            "SELECT `target_path` FROM `url_redirects` WHERE `source_path` = :target AND `is_active` = 1 LIMIT 1"
        );
        $targetLookup->execute(['target' => $targetNorm]);
        $existingNextHop = $targetLookup->fetchColumn();
        $targetLookup->closeCursor();

        $resolvedTarget = is_string($existingNextHop) && $existingNextHop !== '' ? $existingNextHop : $targetNorm;

        // Case B: If any existing redirects point to $sourceNorm as their target (e.g. X -> A exists, and now A -> B is registered),
        // update all prior redirects to point directly to $resolvedTarget in 1 hop!
        $flattenPriorStmt = $pdo->prepare(
            "UPDATE `url_redirects`
             SET `target_path` = :new_target
             WHERE `target_path` = :old_target"
        );
        $flattenPriorStmt->execute([
            'new_target' => $resolvedTarget,
            'old_target' => $sourceNorm,
        ]);
        $flattenPriorStmt->closeCursor();

        // 6. Insert or update the redirect in `url_redirects`
        $findStmt = $pdo->prepare("SELECT `id` FROM `url_redirects` WHERE `source_path` = :source LIMIT 1");
        $findStmt->execute(['source' => $sourceNorm]);
        $existingId = $findStmt->fetchColumn();
        $findStmt->closeCursor();

        if ($existingId !== false) {
            $redirectId = (int)$existingId;
            $updateStmt = $pdo->prepare(
                "UPDATE `url_redirects`
                 SET `target_path` = :target,
                     `http_status_code` = :status,
                     `is_active` = 1
                 WHERE `id` = :id"
            );
            $updateStmt->execute([
                'target' => $resolvedTarget,
                'status' => $statusCode,
                'id'     => $redirectId,
            ]);
            $updateStmt->closeCursor();
        } else {
            $insertStmt = $pdo->prepare(
                "INSERT INTO `url_redirects` (`source_path`, `target_path`, `http_status_code`, `is_active`, `hit_count`, `created_at`)
                 VALUES (:source, :target, :status, 1, 0, CURRENT_TIMESTAMP)"
            );
            $insertStmt->execute([
                'source' => $sourceNorm,
                'target' => $resolvedTarget,
                'status' => $statusCode,
            ]);
            $redirectId = (int)$pdo->lastInsertId();
            $insertStmt->closeCursor();
        }

        // Invalidate sitemap / SEO cache
        $this->invalidateCache();

        // Cryptographic Audit Logging
        $this->auditLogger->log(
            $pdo,
            'seo',
            'REDIRECT_REGISTERED',
            $userId,
            $ip,
            $userAgent,
            'url_redirects',
            $redirectId,
            [
                'source_path'      => $sourceNorm,
                'target_path'      => $resolvedTarget,
                'http_status_code' => $statusCode,
                'action'           => $existingId !== false ? 'updated' : 'created',
            ]
        );

        return [
            'id'               => $redirectId,
            'source_path'      => $sourceNorm,
            'target_path'      => $resolvedTarget,
            'http_status_code' => $statusCode,
            'is_active'        => 1,
        ];
    }

    /**
     * Lists redirects with pagination and optional search filter.
     */
    public function listRedirects(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $pdo = $this->connection->getPdo();
        $conditions = [];
        $params = [];

        if (!empty($filters['search'])) {
            $term = '%' . trim((string)$filters['search']) . '%';
            $conditions[] = "(`source_path` LIKE :search1 OR `target_path` LIKE :search2)";
            $params['search1'] = $term;
            $params['search2'] = $term;
        }

        if (isset($filters['is_active'])) {
            $conditions[] = "`is_active` = :is_active";
            $params['is_active'] = (int)$filters['is_active'];
        }

        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

        // Total count
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM `url_redirects` {$whereClause}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        $countStmt->closeCursor();

        // Fetch rows
        $sql = "SELECT `id`, `source_path`, `target_path`, `http_status_code`, `is_active`, `hit_count`, `last_accessed_at`, `created_at`
                FROM `url_redirects`
                {$whereClause}
                ORDER BY `created_at` DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        $items = array_map(function (array $r): array {
            return [
                'id'               => (int)$r['id'],
                'source_path'      => (string)$r['source_path'],
                'target_path'      => (string)$r['target_path'],
                'http_status_code' => (int)$r['http_status_code'],
                'is_active'        => (int)$r['is_active'],
                'hit_count'        => (int)$r['hit_count'],
                'last_accessed_at' => $r['last_accessed_at'],
                'created_at'       => $r['created_at'],
            ];
        }, $rows);

        return [
            'redirects' => $items,
            'items'     => $items,
            'meta'      => [
                'total'       => $total,
                'page'        => $page,
                'per_page'    => $perPage,
                'total_pages' => (int)ceil($total / $perPage),
            ],
        ];
    }

    /**
     * Retrieves a single redirect by ID.
     */
    public function getRedirect(int $id): array
    {
        $pdo = $this->connection->getPdo();
        $stmt = $pdo->prepare("SELECT * FROM `url_redirects` WHERE `id` = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if (!$row) {
            throw new NotFoundException("Redirect with ID {$id} not found.");
        }

        return [
            'id'               => (int)$row['id'],
            'source_path'      => (string)$row['source_path'],
            'target_path'      => (string)$row['target_path'],
            'http_status_code' => (int)$row['http_status_code'],
            'is_active'        => (int)$row['is_active'],
            'hit_count'        => (int)$row['hit_count'],
            'last_accessed_at' => $row['last_accessed_at'],
            'created_at'       => $row['created_at'],
        ];
    }

    /**
     * Deletes a redirect rule by ID.
     */
    public function deleteRedirect(
        int $id,
        ?int $userId = null,
        string $ip = '127.0.0.1',
        string $userAgent = 'CLI/SeoService'
    ): bool {
        $existing = $this->getRedirect($id);

        $pdo = $this->connection->getPdo();
        $stmt = $pdo->prepare("DELETE FROM `url_redirects` WHERE `id` = :id");
        $stmt->execute(['id' => $id]);
        $stmt->closeCursor();

        $this->invalidateCache();

        $this->auditLogger->log(
            $pdo,
            'seo',
            'REDIRECT_DELETED',
            $userId,
            $ip,
            $userAgent,
            'url_redirects',
            $id,
            [
                'source_path' => $existing['source_path'],
                'target_path' => $existing['target_path'],
            ]
        );

        return true;
    }

    // =========================================================================
    // SECTION 2: DYNAMIC XML SITEMAP GENERATION (FR-SEO-003 / TEST-SEO-003)
    // =========================================================================

    /**
     * Dynamically generates the complete XML sitemap for Google and search crawlers.
     * Guaranteed to:
     * 1. Output valid W3C XML with xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
     * 2. Include all active public static routes and published entities
     * 3. Strictly exclude draft services, unpublished articles, unverified case studies, and /admin /api routes
     * 4. Provide accurate ISO 8601 (YYYY-MM-DD) lastmod dates
     */
    public function generateSitemapXml(): string
    {
        $cachedPath = $this->getSitemapCachePath();
        if ($cachedPath !== null && file_exists($cachedPath)) {
            $content = file_get_contents($cachedPath);
            if (is_string($content) && $content !== '') {
                return $content;
            }
        }

        $pdo = $this->connection->getPdo();
        $urls = [];

        // 1. Core static marketing and institutional routes
        $staticRoutes = [
            ['path' => '/',                 'priority' => '1.0', 'changefreq' => 'weekly'],
            ['path' => '/about',            'priority' => '0.8', 'changefreq' => 'monthly'],
            ['path' => '/services',         'priority' => '0.9', 'changefreq' => 'weekly'],
            ['path' => '/industries',       'priority' => '0.9', 'changefreq' => 'weekly'],
            ['path' => '/insights',         'priority' => '0.8', 'changefreq' => 'weekly'],
            ['path' => '/case-studies',     'priority' => '0.8', 'changefreq' => 'monthly'],
            ['path' => '/careers',          'priority' => '0.8', 'changefreq' => 'weekly'],
            ['path' => '/faqs',             'priority' => '0.7', 'changefreq' => 'monthly'],
            ['path' => '/contact',          'priority' => '0.8', 'changefreq' => 'monthly'],
            ['path' => '/partnerships',     'priority' => '0.7', 'changefreq' => 'monthly'],
            ['path' => '/proposals',        'priority' => '0.7', 'changefreq' => 'monthly'],
            ['path' => '/privacy-policy',   'priority' => '0.3', 'changefreq' => 'yearly'],
            ['path' => '/terms-of-service', 'priority' => '0.3', 'changefreq' => 'yearly'],
        ];

        $today = gmdate('Y-m-d');
        foreach ($staticRoutes as $r) {
            $urls[$r['path']] = [
                'loc'        => $this->baseUrl . $r['path'],
                'lastmod'    => $today,
                'changefreq' => $r['changefreq'],
                'priority'   => $r['priority'],
            ];
        }

        // 2. Published static CMS pages (from pages table)
        try {
            $stmt = $pdo->query(
                "SELECT `slug`, `updated_at`, `published_at`, `created_at`
                 FROM `pages`
                 WHERE `is_published` = 1"
            );
            if ($stmt) {
                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $slug = trim((string)$row['slug']);
                    $path = '/' . ltrim($slug, '/');
                    $date = $this->formatLastmodDate($row['updated_at'] ?? $row['published_at'] ?? $row['created_at']);
                    $urls[$path] = [
                        'loc'        => $this->baseUrl . $path,
                        'lastmod'    => $date,
                        'changefreq' => 'monthly',
                        'priority'   => '0.8',
                    ];
                }
                $stmt->closeCursor();
            }
        } catch (\Throwable $e) {
            // Degrade gracefully if table doesn't exist in minimal test context
        }

        // 3. Published Services (from services table)
        try {
            $stmt = $pdo->query(
                "SELECT `slug`, `updated_at`, `created_at`
                 FROM `services`
                 WHERE `is_published` = 1"
            );
            if ($stmt) {
                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $slug = trim((string)$row['slug']);
                    $path = '/services/' . $slug;
                    $date = $this->formatLastmodDate($row['updated_at'] ?? $row['created_at']);
                    $urls[$path] = [
                        'loc'        => $this->baseUrl . $path,
                        'lastmod'    => $date,
                        'changefreq' => 'weekly',
                        'priority'   => '0.8',
                    ];
                }
                $stmt->closeCursor();
            }
        } catch (\Throwable $e) {
        }

        // 4. Published Industries (from industries table)
        try {
            $stmt = $pdo->query(
                "SELECT `slug`, `updated_at`, `created_at`
                 FROM `industries`
                 WHERE `is_published` = 1"
            );
            if ($stmt) {
                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $slug = trim((string)$row['slug']);
                    $path = '/industries/' . $slug;
                    $date = $this->formatLastmodDate($row['updated_at'] ?? $row['created_at']);
                    $urls[$path] = [
                        'loc'        => $this->baseUrl . $path,
                        'lastmod'    => $date,
                        'changefreq' => 'weekly',
                        'priority'   => '0.8',
                    ];
                }
                $stmt->closeCursor();
            }
        } catch (\Throwable $e) {
        }

        // 5. Published Insights & Articles (from articles table)
        try {
            $stmt = $pdo->query(
                "SELECT `slug`, `updated_at`, `published_at`, `created_at`
                 FROM `articles`
                 WHERE `is_published` = 1"
            );
            if ($stmt) {
                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $slug = trim((string)$row['slug']);
                    $path = '/insights/' . $slug;
                    $date = $this->formatLastmodDate($row['updated_at'] ?? $row['published_at'] ?? $row['created_at']);
                    $urls[$path] = [
                        'loc'        => $this->baseUrl . $path,
                        'lastmod'    => $date,
                        'changefreq' => 'weekly',
                        'priority'   => '0.7',
                    ];
                }
                $stmt->closeCursor();
            }
        } catch (\Throwable $e) {
        }

        // 6. Published Case Studies with verified client consent (from case_studies table)
        try {
            $stmt = $pdo->query(
                "SELECT `slug`, `updated_at`, `published_at`, `created_at`
                 FROM `case_studies`
                 WHERE `is_published` = 1 AND `consent_verified` = 1"
            );
            if ($stmt) {
                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $slug = trim((string)$row['slug']);
                    $path = '/case-studies/' . $slug;
                    $date = $this->formatLastmodDate($row['updated_at'] ?? $row['published_at'] ?? $row['created_at']);
                    $urls[$path] = [
                        'loc'        => $this->baseUrl . $path,
                        'lastmod'    => $date,
                        'changefreq' => 'monthly',
                        'priority'   => '0.7',
                    ];
                }
                $stmt->closeCursor();
            }
        } catch (\Throwable $e) {
        }

        // 7. Active Job Openings (from job_postings table)
        try {
            $stmt = $pdo->query(
                "SELECT `slug`, `updated_at`, `created_at`
                 FROM `job_postings`
                 WHERE `status` = 'open'"
            );
            if ($stmt) {
                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $slug = trim((string)$row['slug']);
                    $path = '/careers/' . $slug;
                    $date = $this->formatLastmodDate($row['updated_at'] ?? $row['created_at']);
                    $urls[$path] = [
                        'loc'        => $this->baseUrl . $path,
                        'lastmod'    => $date,
                        'changefreq' => 'weekly',
                        'priority'   => '0.7',
                    ];
                }
                $stmt->closeCursor();
            }
        } catch (\Throwable $e) {
        }

        // Build XML output
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
        foreach ($urls as $entry) {
            $loc = htmlspecialchars($entry['loc'], ENT_XML1, 'UTF-8');
            $lastmod = htmlspecialchars($entry['lastmod'], ENT_XML1, 'UTF-8');
            $changefreq = htmlspecialchars($entry['changefreq'], ENT_XML1, 'UTF-8');
            $priority = htmlspecialchars($entry['priority'], ENT_XML1, 'UTF-8');

            $xml .= "  <url>\n";
            $xml .= "    <loc>{$loc}</loc>\n";
            $xml .= "    <lastmod>{$lastmod}</lastmod>\n";
            $xml .= "    <changefreq>{$changefreq}</changefreq>\n";
            $xml .= "    <priority>{$priority}</priority>\n";
            $xml .= "  </url>\n";
        }
        $xml .= "</urlset>\n";

        // Save to cache file if cache dir configured
        if ($cachedPath !== null) {
            $dir = dirname($cachedPath);
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            @file_put_contents($cachedPath, $xml, LOCK_EX);
        }

        return $xml;
    }

    /**
     * Forces immediate cache invalidation and rebuild of /sitemap.xml.
     */
    public function rebuildSitemap(
        ?int $userId = null,
        string $ip = '127.0.0.1',
        string $userAgent = 'CLI/SeoService'
    ): string {
        $this->invalidateCache();
        $xml = $this->generateSitemapXml();

        $pdo = $this->connection->getPdo();
        $this->auditLogger->log(
            $pdo,
            'seo',
            'SITEMAP_REBUILT',
            $userId,
            $ip,
            $userAgent,
            'system',
            null,
            ['action' => 'rebuild_xml_sitemap']
        );

        return $xml;
    }

    // =========================================================================
    // SECTION 3: ROUTE-LEVEL SEO & METADATA ADMINISTRATION (ADM-017 / FR-ADM-018)
    // =========================================================================

    /**
     * Returns route SEO metadata list with character length guidance indicators.
     */
    public function getRouteSeoList(): array
    {
        $pdo = $this->connection->getPdo();
        $routes = [];

        // Fetch pages
        try {
            $stmt = $pdo->query(
                "SELECT `id`, `slug`, `title`, `meta_title`, `meta_description`, `is_published`, `updated_at`
                 FROM `pages`
                 ORDER BY `slug` ASC"
            );
            if ($stmt) {
                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $slug = (string)$row['slug'];
                    $path = '/' . ltrim($slug, '/');
                    $routes[] = $this->formatRouteSeoItem(
                        'page',
                        (int)$row['id'],
                        $path,
                        (string)$row['title'],
                        (string)$row['meta_title'],
                        (string)$row['meta_description'],
                        (int)$row['is_published'],
                        $row['updated_at']
                    );
                }
                $stmt->closeCursor();
            }
        } catch (\Throwable $e) {
        }

        return $routes;
    }

    /**
     * Updates route SEO metadata (meta_title, meta_description) on a page entity.
     */
    public function updateRouteSeo(
        int|string $pageIdOrSlug,
        array $data,
        ?int $userId = null,
        string $ip = '127.0.0.1',
        string $userAgent = 'CLI/SeoService'
    ): array {
        $metaTitle = trim((string)($data['meta_title'] ?? ''));
        $metaDesc = trim((string)($data['meta_description'] ?? ''));

        if ($metaTitle === '') {
            throw new ValidationException(['meta_title' => 'Meta title is required.']);
        }

        if (strlen($metaTitle) > 150) {
            throw new ValidationException(['meta_title' => 'Meta title cannot exceed 150 characters.']);
        }

        if (strlen($metaDesc) > 255) {
            throw new ValidationException(['meta_description' => 'Meta description cannot exceed 255 characters.']);
        }

        $pdo = $this->connection->getPdo();

        if (is_numeric($pageIdOrSlug)) {
            $stmt = $pdo->prepare("SELECT * FROM `pages` WHERE `id` = :id LIMIT 1");
            $stmt->execute(['id' => (int)$pageIdOrSlug]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM `pages` WHERE `slug` = :slug LIMIT 1");
            $stmt->execute(['slug' => (string)$pageIdOrSlug]);
        }
        $existing = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if (!$existing) {
            throw new NotFoundException("Route SEO page record not found.");
        }

        $pageId = (int)$existing['id'];

        $update = $pdo->prepare(
            "UPDATE `pages`
             SET `meta_title` = :title,
                 `meta_description` = :desc,
                 `updated_at` = CURRENT_TIMESTAMP
             WHERE `id` = :id"
        );
        $update->execute([
            'title' => $metaTitle,
            'desc'  => $metaDesc,
            'id'    => $pageId,
        ]);
        $update->closeCursor();

        $this->invalidateCache();

        $this->auditLogger->log(
            $pdo,
            'seo',
            'SEO_METADATA_UPDATED',
            $userId,
            $ip,
            $userAgent,
            'pages',
            $pageId,
            [
                'previous' => [
                    'meta_title'       => $existing['meta_title'],
                    'meta_description' => $existing['meta_description'],
                ],
                'updated' => [
                    'meta_title'       => $metaTitle,
                    'meta_description' => $metaDesc,
                ],
            ]
        );

        return $this->formatRouteSeoItem(
            'page',
            $pageId,
            '/' . ltrim((string)$existing['slug'], '/'),
            (string)$existing['title'],
            $metaTitle,
            $metaDesc,
            (int)$existing['is_published'],
            gmdate('Y-m-d H:i:s')
        );
    }

    // =========================================================================
    // SECTION 4: STRUCTURED DATA (JSON-LD) SCHEMAS (FR-SEO-004 / TEST-SEO-004)
    // =========================================================================

    /**
     * Generates Schema.org compliant JSON-LD structured data for public pages.
     */
    public function getStructuredData(string $route, array $context = []): array
    {
        $normalized = self::normalizePath($route);

        // 1. Organization Schema (Base for Homepage and Platform Identity)
        $orgSchema = [
            '@context'    => 'https://schema.org',
            '@type'       => 'MedicalOrganization',
            'name'        => 'VOAK Health Care',
            'url'         => $this->baseUrl,
            'logo'        => $this->baseUrl . '/assets/logo.png',
            'description' => 'Enterprise B2B Healthcare Operations, Clinical Workforce Solutions and Facility Management across Australia.',
            'telephone'   => '+61-1800-VOAK-HC',
            'contactPoint' => [
                '@type'       => 'ContactPoint',
                'telephone'   => '+61-1800-VOAK-HC',
                'contactType' => 'Customer Service',
                'areaServed'  => 'AU',
                'availableLanguage' => 'English',
            ],
            'address' => [
                '@type'           => 'PostalAddress',
                'addressLocality' => 'Sydney',
                'addressRegion'   => 'NSW',
                'addressCountry'  => 'AU',
            ],
        ];

        // 2. Service Schema
        if (str_starts_with($normalized, '/services/') || ($context['type'] ?? '') === 'service') {
            return [
                '@context'    => 'https://schema.org',
                '@type'       => 'MedicalBusiness',
                'name'        => (string)($context['title'] ?? 'Healthcare Staffing & Operations'),
                'description' => (string)($context['description'] ?? $context['summary'] ?? 'Comprehensive healthcare staffing and operational services.'),
                'url'         => $this->baseUrl . $normalized,
                'provider'    => [
                    '@type' => 'MedicalOrganization',
                    'name'  => 'VOAK Health Care',
                    'url'   => $this->baseUrl,
                ],
                'areaServed'  => 'AU',
            ];
        }

        // 3. Article Schema
        if (str_starts_with($normalized, '/insights/') || ($context['type'] ?? '') === 'article') {
            $authorName = (string)($context['author_name'] ?? 'VOAK Clinical Leadership Team');
            $datePublished = (string)($context['published_at'] ?? gmdate('Y-m-d'));
            $dateModified = (string)($context['updated_at'] ?? $datePublished);

            return [
                '@context'         => 'https://schema.org',
                '@type'            => 'Article',
                'headline'         => (string)($context['title'] ?? 'Healthcare Insights & Industry Analysis'),
                'description'      => (string)($context['summary'] ?? $context['description'] ?? ''),
                'url'              => $this->baseUrl . $normalized,
                'datePublished'    => substr($datePublished, 0, 10),
                'dateModified'     => substr($dateModified, 0, 10),
                'author'           => [
                    '@type' => 'Person',
                    'name'  => $authorName,
                ],
                'publisher'        => [
                    '@type' => 'Organization',
                    'name'  => 'VOAK Health Care',
                    'logo'  => [
                        '@type' => 'ImageObject',
                        'url'   => $this->baseUrl . '/assets/logo.png',
                    ],
                ],
                'mainEntityOfPage' => [
                    '@type' => 'WebPage',
                    '@id'   => $this->baseUrl . $normalized,
                ],
            ];
        }

        // 4. FAQPage Schema
        if ($normalized === '/faqs' || ($context['type'] ?? '') === 'faq') {
            $rawFaqs = $context['faqs'] ?? [];
            $mainEntity = [];
            foreach ($rawFaqs as $f) {
                if (!empty($f['question']) && !empty($f['answer'])) {
                    $mainEntity[] = [
                        '@type'          => 'Question',
                        'name'           => strip_tags((string)$f['question']),
                        'acceptedAnswer' => [
                            '@type' => 'Answer',
                            'text'  => strip_tags((string)$f['answer']),
                        ],
                    ];
                }
            }

            return [
                '@context'   => 'https://schema.org',
                '@type'      => 'FAQPage',
                'mainEntity' => $mainEntity,
            ];
        }

        // 5. JobPosting Schema
        if (str_starts_with($normalized, '/careers/') && $normalized !== '/careers') {
            return [
                '@context'    => 'https://schema.org',
                '@type'       => 'JobPosting',
                'title'       => (string)($context['title'] ?? 'Clinical Healthcare Professional'),
                'description' => strip_tags((string)($context['description'] ?? 'Clinical healthcare position with VOAK Health Care.')),
                'datePosted'  => substr((string)($context['created_at'] ?? gmdate('Y-m-d')), 0, 10),
                'hiringOrganization' => [
                    '@type'  => 'Organization',
                    'name'   => 'VOAK Health Care',
                    'sameAs' => $this->baseUrl,
                ],
                'jobLocation' => [
                    '@type'   => 'Place',
                    'address' => [
                        '@type'          => 'PostalAddress',
                        'addressCountry' => 'AU',
                    ],
                ],
            ];
        }

        // Default: Organization schema
        return $orgSchema;
    }

    // =========================================================================
    // SECTION 5: HYBRID SEMANTIC PRE-RENDERING FALLBACK (FR-SEO-001 / ADR-005)
    // =========================================================================

    /**
     * Renders semantic pre-rendered HTML inside <article id="ssr-fallback"> for search crawlers.
     * Content is strictly escaped using htmlspecialchars to prevent any possibility of XSS.
     */
    public function renderSsrFallback(string $route, array $context = []): string
    {
        $title = htmlspecialchars((string)($context['title'] ?? 'VOAK Health Care'), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
        $summary = htmlspecialchars((string)($context['summary'] ?? $context['description'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
        $body = htmlspecialchars((string)($context['body'] ?? $context['content'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
        $canonical = htmlspecialchars($this->baseUrl . self::normalizePath($route), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');

        $jsonLd = $this->getStructuredData($route, $context);
        $jsonLdString = json_encode($jsonLd, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $html = "<!DOCTYPE html>\n<html lang=\"en\">\n<head>\n";
        $html .= "    <meta charset=\"UTF-8\">\n";
        $html .= "    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n";
        $html .= "    <title>{$title} | VOAK Health Care</title>\n";
        if ($summary !== '') {
            $html .= "    <meta name=\"description\" content=\"{$summary}\">\n";
            $html .= "    <meta property=\"og:description\" content=\"{$summary}\">\n";
        }
        $html .= "    <meta property=\"og:title\" content=\"{$title}\">\n";
        $html .= "    <meta property=\"og:url\" content=\"{$canonical}\">\n";
        $html .= "    <meta property=\"og:type\" content=\"website\">\n";
        $html .= "    <link rel=\"canonical\" href=\"{$canonical}\">\n";
        $html .= "    <script type=\"application/ld+json\">{$jsonLdString}</script>\n";
        $html .= "</head>\n<body>\n";
        $html .= "    <div id=\"root\"></div>\n";
        $html .= "    <article id=\"ssr-fallback\" class=\"max-w-7xl mx-auto px-4 py-12\">\n";
        $html .= "        <h1 class=\"text-4xl font-bold mb-4\">{$title}</h1>\n";
        if ($summary !== '') {
            $html .= "        <p class=\"text-lg text-gray-700 mb-6\">{$summary}</p>\n";
        }
        if ($body !== '') {
            $html .= "        <div class=\"prose max-w-none text-gray-800\">{$body}</div>\n";
        }
        $html .= "    </article>\n";
        $html .= "</body>\n</html>\n";

        return $html;
    }

    /**
     * Renders authentic 404 response HTML with robots noindex directive (FR-SEO-002 / TEST-SEO-002).
     */
    public function render404Page(string $requestedUri): string
    {
        $escapedUri = htmlspecialchars($requestedUri, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');

        $html = "<!DOCTYPE html>\n<html lang=\"en\">\n<head>\n";
        $html .= "    <meta charset=\"UTF-8\">\n";
        $html .= "    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n";
        $html .= "    <meta name=\"robots\" content=\"noindex, nofollow\">\n";
        $html .= "    <title>404 Not Found | VOAK Health Care</title>\n";
        $html .= "</head>\n<body>\n";
        $html .= "    <div id=\"root\"></div>\n";
        $html .= "    <article id=\"ssr-fallback\" class=\"max-w-2xl mx-auto px-4 py-16 text-center\">\n";
        $html .= "        <h1 class=\"text-5xl font-bold text-gray-900 mb-4\">Page Not Found (404)</h1>\n";
        $html .= "        <p class=\"text-lg text-gray-600 mb-6\">The requested resource <code>{$escapedUri}</code> could not be found or has been permanently moved.</p>\n";
        $html .= "        <p><a href=\"/\" class=\"text-blue-600 underline\">Return to Homepage</a></p>\n";
        $html .= "    </article>\n";
        $html .= "</body>\n</html>\n";

        return $html;
    }

    // =========================================================================
    // SECTION 6: INTERNAL HELPERS & CACHE MANAGEMENT
    // =========================================================================

    /**
     * Invalidates the cached sitemap XML and pre-rendered SEO snapshots.
     */
    public function invalidateCache(): void
    {
        $cachedPath = $this->getSitemapCachePath();
        if ($cachedPath !== null && file_exists($cachedPath)) {
            @unlink($cachedPath);
        }
    }

    private function getSitemapCachePath(): ?string
    {
        if ($this->cacheDir === null) {
            return null;
        }
        return $this->cacheDir . '/sitemap.xml';
    }

    private function formatLastmodDate(?string $timestamp): string
    {
        if ($timestamp === null || $timestamp === '') {
            return gmdate('Y-m-d');
        }
        $time = strtotime($timestamp);
        return $time !== false ? gmdate('Y-m-d', $time) : gmdate('Y-m-d');
    }

    private function formatRouteSeoItem(
        string $type,
        int $id,
        string $route,
        string $name,
        string $metaTitle,
        string $metaDesc,
        int $isPublished,
        ?string $updatedAt
    ): array {
        $titleLen = mb_strlen($metaTitle, 'UTF-8');
        $descLen = mb_strlen($metaDesc, 'UTF-8');

        $titleStatus = 'optimal';
        if ($titleLen > self::TITLE_ABSOLUTE_MAX) {
            $titleStatus = 'exceeded';
        } elseif ($titleLen > self::TITLE_RECOMMENDED_MAX || $titleLen < self::TITLE_RECOMMENDED_MIN) {
            $titleStatus = 'warning';
        }

        $descStatus = 'optimal';
        if ($descLen > self::DESC_ABSOLUTE_MAX) {
            $descStatus = 'exceeded';
        } elseif ($descLen > self::DESC_RECOMMENDED_MAX || $descLen < self::DESC_RECOMMENDED_MIN) {
            $descStatus = 'warning';
        }

        return [
            'id'               => $id,
            'type'             => $type,
            'route'            => $route,
            'name'             => $name,
            'meta_title'       => $metaTitle,
            'meta_description' => $metaDesc,
            'is_published'     => $isPublished,
            'title_char_count' => $titleLen,
            'desc_char_count'  => $descLen,
            'title_status'     => $titleStatus,
            'desc_status'      => $descStatus,
            'last_modified'    => $updatedAt,
        ];
    }
}
