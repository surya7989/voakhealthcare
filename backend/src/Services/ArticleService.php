<?php

declare(strict_types=1);

namespace Voak\Health\Services;

use Voak\Health\Auth\RbacManager;
use Voak\Health\Core\Exceptions\BadRequestException;
use Voak\Health\Core\Exceptions\ConflictException;
use Voak\Health\Core\Exceptions\DatabaseException;
use Voak\Health\Core\Exceptions\NotFoundException;
use Voak\Health\Core\Exceptions\ValidationException;
use Voak\Health\Database\Connection;
use Voak\Health\Security\AuditLogger;
use Voak\Health\Security\HtmlSanitizer;

/**
 * Insights & Thought Leadership Articles Service (Module 14).
 *
 * Implements:
 * - ADM-016 / FR-ADM-017 / TEST-ADM-017: Editorial article management with auto-calculated reading time, tags, slug collision handling
 * - FR-PUB-016 / TEST-PUB-016: Search & categorization
 * - FR-PUB-017 / TEST-PUB-017: Article reader, sanitization, related articles
 * - FR-WF-005: CMS Publishing State Machine
 */
class ArticleService
{
    private const SLUG_REGEX = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    private Connection $connection;
    private AuditLogger $auditLogger;
    private HtmlSanitizer $sanitizer;
    private ?RbacManager $rbacManager;

    public function __construct(
        Connection $connection,
        AuditLogger $auditLogger,
        HtmlSanitizer $sanitizer,
        ?RbacManager $rbacManager = null
    ) {
        $this->connection = $connection;
        $this->auditLogger = $auditLogger;
        $this->sanitizer = $sanitizer;
        $this->rbacManager = $rbacManager;
    }

    /**
     * Calculates estimated reading time in minutes based on 200 words per minute.
     * Guaranteed minimum of 1 minute.
     */
    public static function calculateReadingTime(string $content): int
    {
        $withSpaces = preg_replace('/<[^>]*>/', ' ', $content);
        $plainText = trim(strip_tags((string)$withSpaces));
        if ($plainText === '') {
            return 1;
        }

        $words = preg_split('/\s+/', $plainText, -1, PREG_SPLIT_NO_EMPTY);
        $wordCount = is_array($words) ? count($words) : 0;

        return max(1, (int)ceil($wordCount / 200));
    }

    /**
     * Normalizes and validates article slug.
     */
    public function normalizeSlug(string $slug, ?int $ignoreId = null): string
    {
        $normalized = strtolower(trim($slug));
        $normalized = (string)preg_replace('/[^a-z0-9-]+/', '-', $normalized);
        $normalized = (string)preg_replace('/-+/', '-', $normalized);
        $normalized = trim($normalized, '-');

        if ($normalized === '' || !preg_match(self::SLUG_REGEX, $normalized)) {
            throw new ValidationException(['slug' => 'Article slug must contain only lowercase alphanumeric characters and single hyphens.']);
        }

        if (strlen($normalized) > 150) {
            $normalized = substr($normalized, 0, 150);
            $normalized = rtrim($normalized, '-');
        }

        // Ensure uniqueness
        $pdo = $this->connection->getPdo();
        $query = "SELECT id FROM articles WHERE slug = :slug";
        if ($ignoreId !== null) {
            $query .= " AND id != :ignore_id";
        }
        $stmt = $pdo->prepare($query);
        $params = ['slug' => $normalized];
        if ($ignoreId !== null) {
            $params['ignore_id'] = $ignoreId;
        }
        $stmt->execute($params);
        $exists = $stmt->fetchColumn() !== false;
        $stmt->closeCursor();

        if ($exists) {
            $baseSlug = $normalized;
            $counter = 2;
            do {
                $candidate = substr($baseSlug, 0, 140) . '-' . $counter;
                $stmt = $pdo->prepare($query);
                $params['slug'] = $candidate;
                $stmt->execute($params);
                $candidateExists = $stmt->fetchColumn() !== false;
                $stmt->closeCursor();
                if (!$candidateExists) {
                    return $candidate;
                }
                $counter++;
            } while ($counter < 100);

            throw new ConflictException("Unable to generate unique slug for article.");
        }

        return $normalized;
    }

    /**
     * Generates a unique slug from title.
     */
    public function generateSlugFromTitle(string $title, ?int $ignoreId = null): string
    {
        $rawSlug = strtolower(trim($title));
        $rawSlug = (string)preg_replace('/[^a-z0-9]+/i', '-', $rawSlug);
        $rawSlug = trim($rawSlug, '-');

        if ($rawSlug === '') {
            $rawSlug = 'article-' . bin2hex(random_bytes(3));
        }

        return $this->normalizeSlug($rawSlug, $ignoreId);
    }

    /**
     * Normalizes tags input into a JSON array of clean strings.
     */
    private function normalizeTags(mixed $tags): string
    {
        if (is_string($tags)) {
            $decoded = json_decode($tags, true);
            if (is_array($decoded)) {
                $tags = $decoded;
            } else {
                $tags = array_map('trim', explode(',', $tags));
            }
        }

        if (!is_array($tags)) {
            return json_encode([]);
        }

        $cleanTags = [];
        foreach ($tags as $tag) {
            if (is_string($tag)) {
                $t = trim(strip_tags($tag));
                if ($t !== '' && !in_array($t, $cleanTags, true)) {
                    $cleanTags[] = $t;
                }
            }
        }

        return json_encode(array_values($cleanTags));
    }

    /**
     * Lists articles for admin management with filters and pagination.
     */
    public function getArticles(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $pdo = $this->connection->getPdo();
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $conditions = [];
        $params = [];

        if (!empty($filters['category'])) {
            $conditions[] = "a.category = :category";
            $params['category'] = (string)$filters['category'];
        }

        if (isset($filters['is_published']) && $filters['is_published'] !== '' && $filters['is_published'] !== null) {
            $conditions[] = "a.is_published = :is_published";
            $params['is_published'] = (int)$filters['is_published'];
        }

        if (!empty($filters['author_id'])) {
            $conditions[] = "a.author_id = :author_id";
            $params['author_id'] = (int)$filters['author_id'];
        }

        if (!empty($filters['search'])) {
            $term = '%' . trim((string)$filters['search']) . '%';
            $conditions[] = "(a.title LIKE :search1 OR a.summary LIKE :search2 OR a.slug LIKE :search3)";
            $params['search1'] = $term;
            $params['search2'] = $term;
            $params['search3'] = $term;
        }

        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

        // Total count
        $countSql = "SELECT COUNT(*) FROM articles a {$whereClause}";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        $countStmt->closeCursor();

        // Data rows
        $sql = "SELECT a.*, u.name AS author_name, u.email AS author_email
                FROM articles a
                LEFT JOIN users u ON u.id = a.author_id
                {$whereClause}
                ORDER BY a.created_at DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue(':' . $key, $val);
        }
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        $articles = array_map(function (array $row): array {
            $row['id'] = (int)$row['id'];
            $row['author_id'] = (int)$row['author_id'];
            $row['featured_image_id'] = $row['featured_image_id'] !== null ? (int)$row['featured_image_id'] : null;
            $row['reading_time_minutes'] = (int)$row['reading_time_minutes'];
            $row['is_published'] = (int)$row['is_published'];
            $row['tags'] = json_decode($row['tags_json'] ?? '[]', true) ?? [];
            return $row;
        }, $rows);

        return [
            'articles' => $articles,
            'items'    => $articles,
            'meta'     => [
                'total'        => $total,
                'page'         => $page,
                'per_page'     => $perPage,
                'total_pages'  => (int)ceil($total / $perPage),
            ],
        ];
    }

    /**
     * Gets single article by ID.
     */
    public function getArticleById(int $id): array
    {
        $pdo = $this->connection->getPdo();
        $stmt = $pdo->prepare(
            "SELECT a.*, u.name AS author_name, u.email AS author_email
             FROM articles a
             LEFT JOIN users u ON u.id = a.author_id
             WHERE a.id = :id"
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if (!$row) {
            throw new NotFoundException("Article with ID {$id} was not found.");
        }

        $row['id'] = (int)$row['id'];
        $row['author_id'] = (int)$row['author_id'];
        $row['featured_image_id'] = $row['featured_image_id'] !== null ? (int)$row['featured_image_id'] : null;
        $row['reading_time_minutes'] = (int)$row['reading_time_minutes'];
        $row['is_published'] = (int)$row['is_published'];
        $row['tags'] = json_decode($row['tags_json'] ?? '[]', true) ?? [];

        return $row;
    }

    /**
     * Creates a new article.
     */
    public function createArticle(array $data, int $authorUserId): array
    {
        $errors = [];

        $title = trim((string)($data['title'] ?? ''));
        if ($title === '') {
            $errors['title'] = 'Article title is required.';
        } elseif (strlen($title) > 200) {
            $errors['title'] = 'Article title cannot exceed 200 characters.';
        }

        $summary = trim((string)($data['summary'] ?? ''));
        if ($summary === '') {
            $errors['summary'] = 'Article summary is required.';
        }

        $content = trim((string)($data['content_html'] ?? $data['content'] ?? ''));
        if ($content === '') {
            $errors['content_html'] = 'Article content is required.';
        }

        $category = trim((string)($data['category'] ?? 'General'));
        if (strlen($category) > 50) {
            $errors['category'] = 'Category cannot exceed 50 characters.';
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        // Slug resolution
        if (!empty($data['slug'])) {
            $slug = $this->normalizeSlug((string)$data['slug']);
        } else {
            $slug = $this->generateSlugFromTitle($title);
        }

        // Auto-calculate reading time
        $readingTime = self::calculateReadingTime($content);

        // Sanitize HTML
        $sanitizedHtml = $this->sanitizer->sanitize($content);
        $sanitizedSummary = strip_tags($summary);

        $tagsJson = $this->normalizeTags($data['tags'] ?? $data['tags_json'] ?? []);

        $isPublished = !empty($data['is_published']) ? 1 : 0;
        $publishedAt = $isPublished === 1
            ? (!empty($data['published_at']) ? (string)$data['published_at'] : gmdate('Y-m-d H:i:s'))
            : null;

        $featuredImageId = !empty($data['featured_image_id']) ? (int)$data['featured_image_id'] : null;

        $pdo = $this->connection->getPdo();

        // Verify author exists
        $userCheck = $pdo->prepare("SELECT id FROM users WHERE id = :uid");
        $userCheck->execute(['uid' => $authorUserId]);
        if (!$userCheck->fetchColumn()) {
            $userCheck->closeCursor();
            throw new ValidationException(['author_id' => "Author user with ID {$authorUserId} does not exist."]);
        }
        $userCheck->closeCursor();

        // Verify featured image exists if provided
        if ($featuredImageId !== null) {
            $imgCheck = $pdo->prepare("SELECT id FROM media_assets WHERE id = :mid");
            $imgCheck->execute(['mid' => $featuredImageId]);
            if (!$imgCheck->fetchColumn()) {
                $imgCheck->closeCursor();
                throw new ValidationException(['featured_image_id' => "Media asset with ID {$featuredImageId} does not exist."]);
            }
            $imgCheck->closeCursor();
        }

        $stmt = $pdo->prepare(
            "INSERT INTO articles (
                title, slug, summary, content_html, featured_image_id, author_id,
                category, tags_json, reading_time_minutes, is_published, published_at,
                created_at, updated_at
            ) VALUES (
                :title, :slug, :summary, :content_html, :featured_image_id, :author_id,
                :category, :tags_json, :reading_time_minutes, :is_published, :published_at,
                CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            )"
        );

        $stmt->execute([
            'title'                => $title,
            'slug'                 => $slug,
            'summary'              => $sanitizedSummary,
            'content_html'         => $sanitizedHtml,
            'featured_image_id'    => $featuredImageId,
            'author_id'            => $authorUserId,
            'category'             => $category,
            'tags_json'            => $tagsJson,
            'reading_time_minutes' => $readingTime,
            'is_published'         => $isPublished,
            'published_at'         => $publishedAt,
        ]);

        $articleId = (int)$pdo->lastInsertId();
        $stmt->closeCursor();

        // Audit Logging
        $this->auditLogger->log(
            $pdo,
            'articles',
            'ARTICLE_CREATED',
            $authorUserId,
            '127.0.0.1',
            'Admin/ArticleService',
            'articles',
            $articleId,
            [
                'title'                => $title,
                'slug'                 => $slug,
                'category'             => $category,
                'reading_time_minutes' => $readingTime,
                'is_published'         => $isPublished,
            ]
        );

        return $this->getArticleById($articleId);
    }

    /**
     * Updates an existing article.
     */
    public function updateArticle(int $id, array $data, int $editorUserId): array
    {
        $current = $this->getArticleById($id);
        $pdo = $this->connection->getPdo();

        $errors = [];

        $title = isset($data['title']) ? trim((string)$data['title']) : $current['title'];
        if ($title === '') {
            $errors['title'] = 'Article title cannot be empty.';
        } elseif (strlen($title) > 200) {
            $errors['title'] = 'Article title cannot exceed 200 characters.';
        }

        $summary = isset($data['summary']) ? trim((string)$data['summary']) : $current['summary'];
        if ($summary === '') {
            $errors['summary'] = 'Article summary cannot be empty.';
        }

        $content = isset($data['content_html']) || isset($data['content'])
            ? trim((string)($data['content_html'] ?? $data['content']))
            : $current['content_html'];
        if ($content === '') {
            $errors['content_html'] = 'Article content cannot be empty.';
        }

        $category = isset($data['category']) ? trim((string)$data['category']) : $current['category'];
        if (strlen($category) > 50) {
            $errors['category'] = 'Category cannot exceed 50 characters.';
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        // Slug resolution
        if (isset($data['slug']) && $data['slug'] !== $current['slug']) {
            $slug = $this->normalizeSlug((string)$data['slug'], $id);
        } else {
            $slug = $current['slug'];
        }

        // Reading time recalculated
        $readingTime = self::calculateReadingTime($content);

        // Sanitize HTML
        $sanitizedHtml = $this->sanitizer->sanitize($content);
        $sanitizedSummary = strip_tags($summary);

        $tagsJson = isset($data['tags']) || isset($data['tags_json'])
            ? $this->normalizeTags($data['tags'] ?? $data['tags_json'])
            : $current['tags_json'];

        $isPublished = isset($data['is_published'])
            ? (!empty($data['is_published']) ? 1 : 0)
            : $current['is_published'];

        $publishedAt = $current['published_at'];
        if ($isPublished === 1 && $publishedAt === null) {
            $publishedAt = gmdate('Y-m-d H:i:s');
        } elseif (isset($data['published_at'])) {
            $publishedAt = $data['published_at'];
        }

        $featuredImageId = array_key_exists('featured_image_id', $data)
            ? (!empty($data['featured_image_id']) ? (int)$data['featured_image_id'] : null)
            : $current['featured_image_id'];

        if ($featuredImageId !== null) {
            $imgCheck = $pdo->prepare("SELECT id FROM media_assets WHERE id = :mid");
            $imgCheck->execute(['mid' => $featuredImageId]);
            if (!$imgCheck->fetchColumn()) {
                $imgCheck->closeCursor();
                throw new ValidationException(['featured_image_id' => "Media asset with ID {$featuredImageId} does not exist."]);
            }
            $imgCheck->closeCursor();
        }

        $stmt = $pdo->prepare(
            "UPDATE articles SET
                title = :title,
                slug = :slug,
                summary = :summary,
                content_html = :content_html,
                featured_image_id = :featured_image_id,
                category = :category,
                tags_json = :tags_json,
                reading_time_minutes = :reading_time_minutes,
                is_published = :is_published,
                published_at = :published_at,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id"
        );

        $stmt->execute([
            'title'                => $title,
            'slug'                 => $slug,
            'summary'              => $sanitizedSummary,
            'content_html'         => $sanitizedHtml,
            'featured_image_id'    => $featuredImageId,
            'category'             => $category,
            'tags_json'            => $tagsJson,
            'reading_time_minutes' => $readingTime,
            'is_published'         => $isPublished,
            'published_at'         => $publishedAt,
            'id'                   => $id,
        ]);
        $stmt->closeCursor();

        // Audit Logging
        $auditAction = ($current['is_published'] === 0 && $isPublished === 1)
            ? 'ARTICLE_PUBLISHED'
            : 'ARTICLE_UPDATED';

        $this->auditLogger->log(
            $pdo,
            'articles',
            $auditAction,
            $editorUserId,
            '127.0.0.1',
            'Admin/ArticleService',
            'articles',
            $id,
            [
                'previous' => [
                    'title'        => $current['title'],
                    'slug'         => $current['slug'],
                    'is_published' => $current['is_published'],
                ],
                'updated'  => [
                    'title'        => $title,
                    'slug'         => $slug,
                    'is_published' => $isPublished,
                ],
            ]
        );

        return $this->getArticleById($id);
    }

    /**
     * Deletes / archives an article.
     */
    public function deleteArticle(int $id, int $editorUserId): void
    {
        $current = $this->getArticleById($id);
        $pdo = $this->connection->getPdo();

        $stmt = $pdo->prepare("DELETE FROM articles WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $stmt->closeCursor();

        $this->auditLogger->log(
            $pdo,
            'articles',
            'ARTICLE_DELETED',
            $editorUserId,
            '127.0.0.1',
            'Admin/ArticleService',
            'articles',
            $id,
            [
                'title' => $current['title'],
                'slug'  => $current['slug'],
            ]
        );
    }

    /**
     * Public feed of published articles with optional category, tag, or search term.
     */
    public function getPublishedArticles(array $filters = [], int $page = 1, int $perPage = 12): array
    {
        $pdo = $this->connection->getPdo();
        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage));
        $offset = ($page - 1) * $perPage;

        $conditions = ["a.is_published = 1"];
        $params = [];

        if (!empty($filters['category'])) {
            $conditions[] = "a.category = :category";
            $params['category'] = (string)$filters['category'];
        }

        if (!empty($filters['tag'])) {
            $conditions[] = "a.tags_json LIKE :tag";
            $params['tag'] = '%' . trim((string)$filters['tag']) . '%';
        }

        if (!empty($filters['search'])) {
            $term = '%' . trim((string)$filters['search']) . '%';
            $conditions[] = "(a.title LIKE :search1 OR a.summary LIKE :search2 OR a.content_html LIKE :search3)";
            $params['search1'] = $term;
            $params['search2'] = $term;
            $params['search3'] = $term;
        }

        $whereClause = 'WHERE ' . implode(' AND ', $conditions);

        // Count total
        $countSql = "SELECT COUNT(*) FROM articles a {$whereClause}";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        $countStmt->closeCursor();

        $sql = "SELECT a.id, a.title, a.slug, a.summary, a.category, a.tags_json,
                       a.reading_time_minutes, a.published_at, a.created_at,
                       a.featured_image_id, u.name AS author_name,
                       m.file_path AS featured_image_path, m.alt_text AS featured_image_alt
                FROM articles a
                LEFT JOIN users u ON u.id = a.author_id
                LEFT JOIN media_assets m ON m.id = a.featured_image_id
                {$whereClause}
                ORDER BY a.published_at DESC, a.id DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue(':' . $key, $val);
        }
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        $articles = array_map(function (array $row): array {
            $row['id'] = (int)$row['id'];
            $row['reading_time_minutes'] = (int)$row['reading_time_minutes'];
            $row['tags'] = json_decode($row['tags_json'] ?? '[]', true) ?? [];
            unset($row['tags_json']);
            return $row;
        }, $rows);

        // ETag calculation
        $etagInput = $total . '-' . implode('-', array_column($articles, 'id')) . '-' . implode('-', array_column($articles, 'published_at'));
        $etag = '"' . sha1($etagInput) . '"';

        return [
            'articles' => $articles,
            'items'    => $articles,
            'etag'     => $etag,
            'meta'     => [
                'total'       => $total,
                'page'        => $page,
                'per_page'    => $perPage,
                'total_pages' => (int)ceil($total / $perPage),
            ],
        ];
    }

    /**
     * Public detail view by slug.
     * Returns full article payload and 3 related articles.
     * Throws 404 if draft or non-existent (TEST-PUB-017).
     */
    public function getPublishedBySlug(string $slug): array
    {
        $pdo = $this->connection->getPdo();

        $stmt = $pdo->prepare(
            "SELECT a.*, u.name AS author_name,
                    m.file_path AS featured_image_path, m.alt_text AS featured_image_alt
             FROM articles a
             LEFT JOIN users u ON u.id = a.author_id
             LEFT JOIN media_assets m ON m.id = a.featured_image_id
             WHERE a.slug = :slug AND a.is_published = 1"
        );
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if (!$row) {
            throw new NotFoundException("Article '{$slug}' was not found or is unpublished.");
        }

        $row['id'] = (int)$row['id'];
        $row['reading_time_minutes'] = (int)$row['reading_time_minutes'];
        $row['tags'] = json_decode($row['tags_json'] ?? '[]', true) ?? [];
        unset($row['tags_json']);

        // Fetch 3 related articles from same category
        $relatedStmt = $pdo->prepare(
            "SELECT a.id, a.title, a.slug, a.summary, a.category, a.reading_time_minutes, a.published_at,
                    m.file_path AS featured_image_path, m.alt_text AS featured_image_alt
             FROM articles a
             LEFT JOIN media_assets m ON m.id = a.featured_image_id
             WHERE a.category = :category AND a.id != :id AND a.is_published = 1
             ORDER BY a.published_at DESC
             LIMIT 3"
        );
        $relatedStmt->execute(['category' => $row['category'], 'id' => $row['id']]);
        $related = $relatedStmt->fetchAll(\PDO::FETCH_ASSOC);
        $relatedStmt->closeCursor();

        $relatedArticles = array_map(function (array $r): array {
            $r['id'] = (int)$r['id'];
            $r['reading_time_minutes'] = (int)$r['reading_time_minutes'];
            return $r;
        }, $related);

        $row['related_articles'] = $relatedArticles;

        $etag = '"' . sha1($row['id'] . '-' . $row['updated_at'] . '-' . $row['slug']) . '"';

        return [
            'article' => $row,
            'etag'    => $etag,
        ];
    }

    /**
     * CSV Export with formula injection defense.
     */
    public function exportCsv(array $filters = [], ?int $editorUserId = null): string
    {
        $articles = $this->getArticles($filters, 1, 1000)['articles'];

        $fp = fopen('php://memory', 'r+');
        fputcsv($fp, ['ID', 'Title', 'Slug', 'Category', 'Author', 'Reading Time (Min)', 'Status', 'Published Date', 'Created Date']);

        foreach ($articles as $a) {
            $status = $a['is_published'] === 1 ? 'PUBLISHED' : 'DRAFT';
            fputcsv($fp, [
                $a['id'],
                $this->escapeCsvValue((string)$a['title']),
                $this->escapeCsvValue((string)$a['slug']),
                $this->escapeCsvValue((string)$a['category']),
                $this->escapeCsvValue((string)($a['author_name'] ?? 'Admin')),
                $a['reading_time_minutes'],
                $status,
                $a['published_at'] ?? '',
                $a['created_at'],
            ]);
        }

        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);

        return (string)$csv;
    }

    private function escapeCsvValue(string $val): string
    {
        if (preg_match('/^[=+\-@]/', $val)) {
            return "'" . $val;
        }
        return $val;
    }
}
