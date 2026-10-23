<?php

declare(strict_types=1);

namespace Voak\Health\Services;

use Voak\Health\Auth\RbacManager;
use Voak\Health\Core\Exceptions\BadRequestException;
use Voak\Health\Core\Exceptions\ConflictException;
use Voak\Health\Core\Exceptions\NotFoundException;
use Voak\Health\Core\Exceptions\ValidationException;
use Voak\Health\Database\Connection;
use Voak\Health\Security\AuditLogger;
use Voak\Health\Security\HtmlSanitizer;

/**
 * Enterprise Case Studies & Social Proof Service (Module 15).
 *
 * Implements:
 * - ADM-015 / FR-ADM-016: Case Studies & Social Proof Manager with Mandatory Consent Release Guard
 * - FR-PUB-013 / TEST-PUB-013: Verified Case Studies Directory & Metrics Presentation
 * - TEST-ADM-016: Publication blocked without verified client consent
 * - FR-WF-005: CMS Publishing State Machine
 */
class CaseStudyService
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
     * Normalizes and validates case study slug.
     */
    public function normalizeSlug(string $slug, ?int $ignoreId = null): string
    {
        $normalized = strtolower(trim($slug));
        $normalized = (string)preg_replace('/[^a-z0-9-]+/', '-', $normalized);
        $normalized = (string)preg_replace('/-+/', '-', $normalized);
        $normalized = trim($normalized, '-');

        if ($normalized === '' || !preg_match(self::SLUG_REGEX, $normalized)) {
            throw new ValidationException(['slug' => 'Case study slug must contain only lowercase alphanumeric characters and single hyphens.']);
        }

        if (strlen($normalized) > 150) {
            $normalized = substr($normalized, 0, 150);
            $normalized = rtrim($normalized, '-');
        }

        $pdo = $this->connection->getPdo();
        $query = "SELECT id FROM case_studies WHERE slug = :slug";
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

            throw new ConflictException("Unable to generate unique slug for case study.");
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
            $rawSlug = 'case-study-' . bin2hex(random_bytes(3));
        }

        return $this->normalizeSlug($rawSlug, $ignoreId);
    }

    /**
     * Normalizes outcome metrics into a valid JSON string.
     */
    private function normalizeOutcomeMetrics(mixed $metrics): string
    {
        if (is_string($metrics)) {
            $decoded = json_decode($metrics, true);
            if (is_array($decoded)) {
                $metrics = $decoded;
            } else {
                $metrics = [];
            }
        }

        if (!is_array($metrics)) {
            return json_encode([]);
        }

        $clean = [];
        foreach ($metrics as $item) {
            if (is_array($item)) {
                $clean[] = [
                    'metric'      => strip_tags(trim((string)($item['metric'] ?? $item['value'] ?? ''))),
                    'label'       => strip_tags(trim((string)($item['label'] ?? $item['description'] ?? ''))),
                    'description' => strip_tags(trim((string)($item['description'] ?? ''))),
                ];
            } elseif (is_string($item)) {
                $clean[] = [
                    'metric' => strip_tags(trim($item)),
                    'label'  => '',
                ];
            }
        }

        return json_encode(array_values($clean));
    }

    /**
     * Lists case studies for admin management with filters and pagination.
     */
    public function getCaseStudies(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $pdo = $this->connection->getPdo();
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $conditions = [];
        $params = [];

        if (!empty($filters['sector']) || !empty($filters['client_sector'])) {
            $conditions[] = "c.client_sector = :sector";
            $params['sector'] = (string)($filters['sector'] ?? $filters['client_sector']);
        }

        if (isset($filters['is_published']) && $filters['is_published'] !== '' && $filters['is_published'] !== null) {
            $conditions[] = "c.is_published = :is_published";
            $params['is_published'] = (int)$filters['is_published'];
        }

        if (isset($filters['consent_verified']) && $filters['consent_verified'] !== '' && $filters['consent_verified'] !== null) {
            $conditions[] = "c.consent_verified = :consent_verified";
            $params['consent_verified'] = (int)$filters['consent_verified'];
        }

        if (!empty($filters['search'])) {
            $term = '%' . trim((string)$filters['search']) . '%';
            $conditions[] = "(c.title LIKE :search1 OR c.client_sector LIKE :search2 OR c.challenge_summary LIKE :search3)";
            $params['search1'] = $term;
            $params['search2'] = $term;
            $params['search3'] = $term;
        }

        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

        // Total count
        $countSql = "SELECT COUNT(*) FROM case_studies c {$whereClause}";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        $countStmt->closeCursor();

        // Data rows
        $sql = "SELECT c.*
                FROM case_studies c
                {$whereClause}
                ORDER BY c.created_at DESC
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

        $items = array_map(function (array $row): array {
            $row['id'] = (int)$row['id'];
            $row['consent_verified'] = (int)$row['consent_verified'];
            $row['is_published'] = (int)$row['is_published'];
            $row['outcome_metrics'] = json_decode($row['outcome_metrics_json'] ?? '[]', true) ?? [];
            return $row;
        }, $rows);

        return [
            'case_studies' => $items,
            'items'        => $items,
            'meta'         => [
                'total'       => $total,
                'page'        => $page,
                'per_page'    => $perPage,
                'total_pages' => (int)ceil($total / $perPage),
            ],
        ];
    }

    /**
     * Gets single case study by ID.
     */
    public function getCaseStudyById(int $id): array
    {
        $pdo = $this->connection->getPdo();
        $stmt = $pdo->prepare("SELECT * FROM case_studies WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if (!$row) {
            throw new NotFoundException("Case study with ID {$id} was not found.");
        }

        $row['id'] = (int)$row['id'];
        $row['consent_verified'] = (int)$row['consent_verified'];
        $row['is_published'] = (int)$row['is_published'];
        $row['outcome_metrics'] = json_decode($row['outcome_metrics_json'] ?? '[]', true) ?? [];

        return $row;
    }

    /**
     * Creates a new case study.
     * Enforces mandatory consent verification gate before publishing.
     */
    public function createCaseStudy(array $data, int $editorUserId): array
    {
        $errors = [];

        $title = trim((string)($data['title'] ?? ''));
        if ($title === '') {
            $errors['title'] = 'Case study title is required.';
        } elseif (strlen($title) > 200) {
            $errors['title'] = 'Title cannot exceed 200 characters.';
        }

        $clientSector = trim((string)($data['client_sector'] ?? $data['sector'] ?? ''));
        if ($clientSector === '') {
            $errors['client_sector'] = 'Client sector is required.';
        } elseif (strlen($clientSector) > 100) {
            $errors['client_sector'] = 'Client sector cannot exceed 100 characters.';
        }

        $challenge = trim((string)($data['challenge_summary'] ?? $data['challenge'] ?? $data['problem'] ?? ''));
        if ($challenge === '') {
            $errors['challenge_summary'] = 'Challenge summary is required.';
        }

        $intervention = trim((string)($data['intervention_summary'] ?? $data['intervention'] ?? ''));
        if ($intervention === '') {
            $errors['intervention_summary'] = 'Intervention summary is required.';
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        // Slug
        if (!empty($data['slug'])) {
            $slug = $this->normalizeSlug((string)$data['slug']);
        } else {
            $slug = $this->generateSlugFromTitle($title);
        }

        $consentVerified = !empty($data['consent_verified']) || !empty($data['client_consent_verified']) ? 1 : 0;
        $isPublished = !empty($data['is_published']) ? 1 : 0;

        // MANDATORY CONSENT RELEASE GUARD (FR-ADM-016 / TEST-ADM-016)
        if ($isPublished === 1 && $consentVerified !== 1) {
            throw new ValidationException([
                'consent_verified' => 'Cannot publish case study: verified client legal consent release is required.',
            ]);
        }

        $publishedAt = $isPublished === 1
            ? (!empty($data['published_at']) ? (string)$data['published_at'] : gmdate('Y-m-d H:i:s'))
            : null;

        $outcomeMetricsJson = $this->normalizeOutcomeMetrics(
            $data['outcome_metrics'] ?? $data['outcome_metrics_json'] ?? $data['metrics'] ?? []
        );

        // Sanitize content
        $sanitizedChallenge = $this->sanitizer->sanitize($challenge);
        $sanitizedIntervention = $this->sanitizer->sanitize($intervention);

        $pdo = $this->connection->getPdo();
        $stmt = $pdo->prepare(
            "INSERT INTO case_studies (
                title, slug, client_sector, challenge_summary, intervention_summary,
                outcome_metrics_json, consent_verified, is_published, published_at,
                created_at, updated_at
            ) VALUES (
                :title, :slug, :client_sector, :challenge_summary, :intervention_summary,
                :outcome_metrics_json, :consent_verified, :is_published, :published_at,
                CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            )"
        );

        $stmt->execute([
            'title'                => $title,
            'slug'                 => $slug,
            'client_sector'        => $clientSector,
            'challenge_summary'    => $sanitizedChallenge,
            'intervention_summary' => $sanitizedIntervention,
            'outcome_metrics_json' => $outcomeMetricsJson,
            'consent_verified'     => $consentVerified,
            'is_published'         => $isPublished,
            'published_at'         => $publishedAt,
        ]);

        $id = (int)$pdo->lastInsertId();
        $stmt->closeCursor();

        $this->auditLogger->log(
            $pdo,
            'case_studies',
            'CASE_STUDY_CREATED',
            $editorUserId,
            '127.0.0.1',
            'Admin/CaseStudyService',
            'case_studies',
            $id,
            [
                'title'            => $title,
                'slug'             => $slug,
                'client_sector'    => $clientSector,
                'consent_verified' => $consentVerified,
                'is_published'     => $isPublished,
            ]
        );

        return $this->getCaseStudyById($id);
    }

    /**
     * Updates an existing case study.
     * Enforces mandatory consent verification gate before publishing.
     */
    public function updateCaseStudy(int $id, array $data, int $editorUserId): array
    {
        $current = $this->getCaseStudyById($id);
        $pdo = $this->connection->getPdo();

        $errors = [];

        $title = isset($data['title']) ? trim((string)$data['title']) : $current['title'];
        if ($title === '') {
            $errors['title'] = 'Case study title cannot be empty.';
        } elseif (strlen($title) > 200) {
            $errors['title'] = 'Title cannot exceed 200 characters.';
        }

        $clientSector = isset($data['client_sector']) || isset($data['sector'])
            ? trim((string)($data['client_sector'] ?? $data['sector']))
            : $current['client_sector'];
        if ($clientSector === '') {
            $errors['client_sector'] = 'Client sector cannot be empty.';
        } elseif (strlen($clientSector) > 100) {
            $errors['client_sector'] = 'Client sector cannot exceed 100 characters.';
        }

        $challenge = isset($data['challenge_summary']) || isset($data['challenge']) || isset($data['problem'])
            ? trim((string)($data['challenge_summary'] ?? $data['challenge'] ?? $data['problem']))
            : $current['challenge_summary'];
        if ($challenge === '') {
            $errors['challenge_summary'] = 'Challenge summary cannot be empty.';
        }

        $intervention = isset($data['intervention_summary']) || isset($data['intervention'])
            ? trim((string)($data['intervention_summary'] ?? $data['intervention']))
            : $current['intervention_summary'];
        if ($intervention === '') {
            $errors['intervention_summary'] = 'Intervention summary cannot be empty.';
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        // Slug
        if (isset($data['slug']) && $data['slug'] !== $current['slug']) {
            $slug = $this->normalizeSlug((string)$data['slug'], $id);
        } else {
            $slug = $current['slug'];
        }

        $consentVerified = isset($data['consent_verified']) || isset($data['client_consent_verified'])
            ? (!empty($data['consent_verified'] ?? $data['client_consent_verified']) ? 1 : 0)
            : $current['consent_verified'];

        $isPublished = isset($data['is_published'])
            ? (!empty($data['is_published']) ? 1 : 0)
            : $current['is_published'];

        // MANDATORY CONSENT RELEASE GUARD (FR-ADM-016 / TEST-ADM-016)
        if ($isPublished === 1 && $consentVerified !== 1) {
            throw new ValidationException([
                'consent_verified' => 'Cannot publish case study: verified client legal consent release is required.',
            ]);
        }

        $publishedAt = $current['published_at'];
        if ($isPublished === 1 && $publishedAt === null) {
            $publishedAt = gmdate('Y-m-d H:i:s');
        } elseif (isset($data['published_at'])) {
            $publishedAt = $data['published_at'];
        }

        $outcomeMetricsJson = isset($data['outcome_metrics']) || isset($data['outcome_metrics_json']) || isset($data['metrics'])
            ? $this->normalizeOutcomeMetrics($data['outcome_metrics'] ?? $data['outcome_metrics_json'] ?? $data['metrics'])
            : $current['outcome_metrics_json'];

        $sanitizedChallenge = $this->sanitizer->sanitize($challenge);
        $sanitizedIntervention = $this->sanitizer->sanitize($intervention);

        $stmt = $pdo->prepare(
            "UPDATE case_studies SET
                title = :title,
                slug = :slug,
                client_sector = :client_sector,
                challenge_summary = :challenge_summary,
                intervention_summary = :intervention_summary,
                outcome_metrics_json = :outcome_metrics_json,
                consent_verified = :consent_verified,
                is_published = :is_published,
                published_at = :published_at,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id"
        );

        $stmt->execute([
            'title'                => $title,
            'slug'                 => $slug,
            'client_sector'        => $clientSector,
            'challenge_summary'    => $sanitizedChallenge,
            'intervention_summary' => $sanitizedIntervention,
            'outcome_metrics_json' => $outcomeMetricsJson,
            'consent_verified'     => $consentVerified,
            'is_published'         => $isPublished,
            'published_at'         => $publishedAt,
            'id'                   => $id,
        ]);
        $stmt->closeCursor();

        $auditAction = ($current['is_published'] === 0 && $isPublished === 1)
            ? 'CASE_STUDY_PUBLISHED'
            : 'CASE_STUDY_UPDATED';

        $this->auditLogger->log(
            $pdo,
            'case_studies',
            $auditAction,
            $editorUserId,
            '127.0.0.1',
            'Admin/CaseStudyService',
            'case_studies',
            $id,
            [
                'previous' => [
                    'title'            => $current['title'],
                    'slug'             => $current['slug'],
                    'consent_verified' => $current['consent_verified'],
                    'is_published'     => $current['is_published'],
                ],
                'updated'  => [
                    'title'            => $title,
                    'slug'             => $slug,
                    'consent_verified' => $consentVerified,
                    'is_published'     => $isPublished,
                ],
            ]
        );

        return $this->getCaseStudyById($id);
    }

    /**
     * Deletes / archives a case study.
     */
    public function deleteCaseStudy(int $id, int $editorUserId): void
    {
        $current = $this->getCaseStudyById($id);
        $pdo = $this->connection->getPdo();

        $stmt = $pdo->prepare("DELETE FROM case_studies WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $stmt->closeCursor();

        $this->auditLogger->log(
            $pdo,
            'case_studies',
            'CASE_STUDY_ARCHIVED',
            $editorUserId,
            '127.0.0.1',
            'Admin/CaseStudyService',
            'case_studies',
            $id,
            [
                'title' => $current['title'],
                'slug'  => $current['slug'],
            ]
        );
    }

    /**
     * Public feed of verified published case studies.
     * Enforces strictly: is_published = 1 AND consent_verified = 1 (TEST-PUB-013).
     */
    public function getVerifiedPublishedCaseStudies(array $filters = [], int $page = 1, int $perPage = 12): array
    {
        $pdo = $this->connection->getPdo();
        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage));
        $offset = ($page - 1) * $perPage;

        $conditions = ["c.is_published = 1", "c.consent_verified = 1"];
        $params = [];

        if (!empty($filters['sector']) || !empty($filters['client_sector'])) {
            $conditions[] = "c.client_sector = :sector";
            $params['sector'] = (string)($filters['sector'] ?? $filters['client_sector']);
        }

        if (!empty($filters['search'])) {
            $term = '%' . trim((string)$filters['search']) . '%';
            $conditions[] = "(c.title LIKE :search1 OR c.client_sector LIKE :search2 OR c.challenge_summary LIKE :search3)";
            $params['search1'] = $term;
            $params['search2'] = $term;
            $params['search3'] = $term;
        }

        $whereClause = 'WHERE ' . implode(' AND ', $conditions);

        // Count
        $countSql = "SELECT COUNT(*) FROM case_studies c {$whereClause}";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        $countStmt->closeCursor();

        $sql = "SELECT c.id, c.title, c.slug, c.client_sector, c.challenge_summary,
                       c.intervention_summary, c.outcome_metrics_json, c.published_at, c.created_at
                FROM case_studies c
                {$whereClause}
                ORDER BY c.published_at DESC, c.id DESC
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

        $items = array_map(function (array $row): array {
            $row['id'] = (int)$row['id'];
            $row['outcome_metrics'] = json_decode($row['outcome_metrics_json'] ?? '[]', true) ?? [];
            unset($row['outcome_metrics_json']);
            return $row;
        }, $rows);

        $etagInput = $total . '-' . implode('-', array_column($items, 'id')) . '-' . implode('-', array_column($items, 'published_at'));
        $etag = '"' . sha1($etagInput) . '"';

        return [
            'case_studies' => $items,
            'items'        => $items,
            'etag'         => $etag,
            'meta'         => [
                'total'       => $total,
                'page'        => $page,
                'per_page'    => $perPage,
                'total_pages' => (int)ceil($total / $perPage),
            ],
        ];
    }

    /**
     * Public detail view for single case study.
     * Enforces strictly: is_published = 1 AND consent_verified = 1.
     * Throws 404 if draft, unverified, or not found (TEST-PUB-013).
     */
    public function getVerifiedPublishedBySlug(string $slug): array
    {
        $pdo = $this->connection->getPdo();
        $stmt = $pdo->prepare(
            "SELECT * FROM case_studies
             WHERE slug = :slug AND is_published = 1 AND consent_verified = 1"
        );
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if (!$row) {
            throw new NotFoundException("Case study '{$slug}' was not found or has not been verified for publication.");
        }

        $row['id'] = (int)$row['id'];
        $row['outcome_metrics'] = json_decode($row['outcome_metrics_json'] ?? '[]', true) ?? [];
        unset($row['outcome_metrics_json']);

        $etag = '"' . sha1($row['id'] . '-' . $row['updated_at'] . '-' . $row['slug']) . '"';

        return [
            'case_study' => $row,
            'etag'       => $etag,
        ];
    }

    /**
     * CSV Export with formula injection defense.
     */
    public function exportCsv(array $filters = [], ?int $editorUserId = null): string
    {
        $cases = $this->getCaseStudies($filters, 1, 1000)['case_studies'];

        $fp = fopen('php://memory', 'r+');
        fputcsv($fp, ['ID', 'Title', 'Slug', 'Client Sector', 'Consent Verified', 'Publish Status', 'Published Date', 'Created Date']);

        foreach ($cases as $c) {
            $status = $c['is_published'] === 1 ? 'PUBLISHED' : 'DRAFT';
            $consent = $c['consent_verified'] === 1 ? 'YES' : 'NO';
            fputcsv($fp, [
                $c['id'],
                $this->escapeCsvValue((string)$c['title']),
                $this->escapeCsvValue((string)$c['slug']),
                $this->escapeCsvValue((string)$c['client_sector']),
                $consent,
                $status,
                $c['published_at'] ?? '',
                $c['created_at'],
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
