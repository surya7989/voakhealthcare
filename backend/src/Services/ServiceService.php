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
 * Enterprise Services Catalog Management Service.
 * Manages B2B clinical service pillars, deliverables, SLA metrics,
 * display sequencing, and automated 301 SEO redirects on slug rename.
 */
class ServiceService
{
    private const SLUG_REGEX = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';
    private const RESERVED_SLUGS = [
        'api', 'admin', 'auth', 'login', 'logout', 'dashboard', 'public',
        'pages', 'homepage', 'services', 'industries', 'settings', 'media',
        'enquiries', 'proposals', 'careers', 'about', 'contact', 'terms',
        'privacy', 'health',
    ];

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

        if (strlen($trimmed) < 3 || strlen($trimmed) > 100) {
            throw new BadRequestException("Slug must be between 3 and 100 characters.");
        }

        if (!preg_match(self::SLUG_REGEX, $trimmed)) {
            throw new BadRequestException("Invalid slug format. Use lowercase alphanumeric with single hyphens (e.g., 'clinical-workforce-solutions').");
        }

        if (in_array($trimmed, self::RESERVED_SLUGS, true)) {
            throw new BadRequestException("Slug '{$trimmed}' is a reserved system keyword.");
        }

        return $trimmed;
    }

    /**
     * Seeds initial 6 canonical service pillars if table is empty.
     */
    public function ensureDefaultServices(int $systemUserId = 1): int
    {
        $pdo = $this->connection->getPdo();
        $stmt = $pdo->query("SELECT COUNT(*) FROM `services`");
        $count = $stmt ? (int)$stmt->fetchColumn() : 0;
        if ($stmt) {
            $stmt->closeCursor();
        }

        if ($count > 0) {
            return 0;
        }

        $defaults = [
            [
                'title' => 'Clinical Workforce Solutions',
                'slug' => 'clinical-workforce-solutions',
                'tagline' => 'Rapid deployment of credentialed nurses and specialist clinical teams across acute and aged care.',
                'description' => '<p>Comprehensive clinical and locum workforce solutions tailored for Australian healthcare institutions. We provide rapid deployment of AHPRA-registered nurses, clinical nurse specialists, and allied health professionals with rigorous clinical governance and credentialing compliance.</p>',
                'deliverables' => [
                    'Rapid 24/7 surge staffing and locum placement',
                    '100% verified AHPRA registration and working with children checks',
                    'Dedicated clinical governance and escalation framework',
                    'Integrated shift scheduling and digital timesheet verification',
                ],
                'sla_metrics' => [
                    'urgent_shift_fill_rate' => '>= 98.5%',
                    'credential_audit_compliance' => '100% Verified',
                    'escalation_response_window' => '< 15 Minutes',
                ],
                'display_order' => 1,
            ],
            [
                'title' => 'Healthcare Facility Operations',
                'slug' => 'healthcare-facility-operations',
                'tagline' => 'Integrated infection control, environmental hygiene, and facility management for healthcare environments.',
                'description' => '<p>Integrated healthcare environmental services meeting strict NSQHS infection control standards. Comprehensive clinical cleaning, specialized waste management, linen logistics, and biomedical asset management for clinical institutions.</p>',
                'deliverables' => [
                    'Hospital-grade terminal cleaning and infection control protocols',
                    'Clinical and hazardous waste management compliance',
                    'Specialized linen and sterile supply logistics',
                    'Facility maintenance and biomedical compliance tracking',
                ],
                'sla_metrics' => [
                    'infection_control_audit' => '>= 99.2%',
                    'critical_environmental_response' => '< 30 Minutes',
                    'preventative_maintenance' => '100% On-Schedule',
                ],
                'display_order' => 2,
            ],
            [
                'title' => 'Health Administration & Medical Support',
                'slug' => 'health-administration-support',
                'tagline' => 'Turnkey medical reception, clinical ward clerks, billing, and health records management.',
                'description' => '<p>End-to-end medical administrative support designed to streamline clinic workflows and hospital ward administration. Experienced medical receptionists, ward clerks, MBS/PBS billing specialists, and clinical transcriptionists.</p>',
                'deliverables' => [
                    'Specialized medical reception and patient concierge',
                    'Clinical ward clerk and departmental coordination',
                    'MBS, DVA and private health fund billing optimization',
                    'Electronic medical records (EMR) and transcription services',
                ],
                'sla_metrics' => [
                    'billing_accuracy_rate' => '>= 99.8%',
                    'call_answer_response' => '< 20 Seconds',
                    'record_processing_turnaround' => '< 2 Hours',
                ],
                'display_order' => 3,
            ],
            [
                'title' => 'Healthcare Compliance & Clinical Governance',
                'slug' => 'clinical-compliance-governance',
                'tagline' => 'NSQHS accreditation readiness, clinical audit frameworks, and AHPRA credentialing assurance.',
                'description' => '<p>Expert clinical governance advisory and regulatory readiness consulting for healthcare operators. We prepare facilities for NSQHS and Aged Care Quality Standards accreditation audits, conduct independent clinical incident reviews, and manage comprehensive staff credentialing.</p>',
                'deliverables' => [
                    'NSQHS and Aged Care Quality Standards audit readiness',
                    'Clinical risk assessments and sentinel event investigations',
                    'Ongoing clinical scope of practice and credentialing audits',
                    'Policy framework drafting and clinical workflow standardization',
                ],
                'sla_metrics' => [
                    'audit_readiness_scorecard' => '100% Compliant',
                    'incident_review_turnaround' => '< 48 Hours',
                    'policy_review_cycle' => 'Annual Mandatory Verification',
                ],
                'display_order' => 4,
            ],
            [
                'title' => 'Healthcare Logistics & Supply Chain Support',
                'slug' => 'healthcare-logistics-supply',
                'tagline' => 'Optimized medical consumable procurement, point-of-care inventory replenishment, and PPE distribution.',
                'description' => '<p>Reliable healthcare supply chain management ensuring clinical teams always have required consumables, pharmaceuticals, and personal protective equipment. Point-of-care stock tracking, automated re-ordering thresholds, and cold-chain integrity assurance.</p>',
                'deliverables' => [
                    'Critical clinical supplies inventory management',
                    'Cold-chain temperature-controlled logistics',
                    'Emergency PPE and consumable stockpile maintenance',
                    'Multi-site healthcare distribution and replenishment',
                ],
                'sla_metrics' => [
                    'order_fulfillment_accuracy' => '>= 99.5%',
                    'emergency_stock_delivery' => '< 4 Hours',
                    'cold_chain_audit_compliance' => '100% Logged',
                ],
                'display_order' => 5,
            ],
            [
                'title' => 'Healthcare Consulting & Operational Advisory',
                'slug' => 'healthcare-consulting-advisory',
                'tagline' => 'Clinical workforce roster optimization, cost-containment reviews, and digital workflow transformation.',
                'description' => '<p>Strategic advisory services for executive healthcare leadership. We specialize in nursing roster optimization, agency spend reduction, digital clinical pathway implementation, and bed-management efficiency across complex health networks.</p>',
                'deliverables' => [
                    'Agency labor spend reduction and roster optimization',
                    'Clinical workflow and patient journey bottleneck analysis',
                    'Digital health and EMR integration advisory',
                    'Capacity planning and bed turnaround efficiency reviews',
                ],
                'sla_metrics' => [
                    'agency_spend_optimization' => '10-25% Reduction',
                    'advisory_milestone_delivery' => '100% On-Time',
                    'executive_satisfaction_rating' => '>= 95%',
                ],
                'display_order' => 6,
            ],
        ];

        $pdo->beginTransaction();
        try {
            $insert = $pdo->prepare(
                "INSERT INTO `services` 
                 (`title`, `slug`, `tagline`, `description`, `deliverables_json`, `sla_metrics_json`, `is_published`, `display_order`, `created_at`, `updated_at`)
                 VALUES 
                 (:title, :slug, :tagline, :desc, :deliv, :sla, 1, :order, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
            );

            foreach ($defaults as $svc) {
                $insert->execute([
                    'title'   => $svc['title'],
                    'slug'    => $svc['slug'],
                    'tagline' => $svc['tagline'],
                    'desc'    => $svc['description'],
                    'deliv'   => json_encode($svc['deliverables'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'sla'     => json_encode($svc['sla_metrics'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'order'   => $svc['display_order'],
                ]);
            }
            $insert->closeCursor();
            $pdo->commit();
            return count($defaults);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Retrieves all published services for public presentation.
     *
     * @return array{services: list<array<string, mixed>>, etag: string}
     */
    public function getPublishedServices(): array
    {
        $this->ensureDefaultServices();
        $pdo = $this->connection->getPdo();

        $stmt = $pdo->query(
            "SELECT `id`, `title`, `slug`, `tagline`, `description`, `deliverables_json`, `sla_metrics_json`, `is_published`, `display_order`, `created_at`, `updated_at` 
             FROM `services` 
             WHERE `is_published` = 1 
             ORDER BY `display_order` ASC, `id` ASC"
        );
        $rows = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
        if ($stmt) {
            $stmt->closeCursor();
        }

        $services = array_map([$this, 'formatServiceRecord'], $rows);
        $etag = $this->calculateEtag($services);

        return [
            'services' => $services,
            'etag'     => $etag,
        ];
    }

    /**
     * Retrieves a published service by slug for public presentation.
     */
    public function getPublishedServiceBySlug(string $slug): ?array
    {
        $cleanSlug = $this->validateSlug($slug);
        $this->ensureDefaultServices();
        $pdo = $this->connection->getPdo();

        $stmt = $pdo->prepare(
            "SELECT * FROM `services` WHERE `slug` = :slug AND `is_published` = 1 LIMIT 1"
        );
        $stmt->execute(['slug' => $cleanSlug]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if (!$row) {
            return null;
        }

        $service = $this->formatServiceRecord($row);

        // Fetch mapped industries
        $indStmt = $pdo->prepare(
            "SELECT `industry_id` FROM `service_industry_mappings` WHERE `service_id` = :id"
        );
        $indStmt->execute(['id' => (int)$service['id']]);
        $service['industry_ids'] = array_map('intval', $indStmt->fetchAll(\PDO::FETCH_COLUMN));
        $indStmt->closeCursor();

        $service['etag'] = $this->calculateEtag($service);

        return $service;
    }

    /**
     * Inspects url_redirects table for an active 301 redirect.
     */
    public function findRedirect(string $sourcePath): ?array
    {
        $pdo = $this->connection->getPdo();
        $normPath = '/' . ltrim(trim($sourcePath), '/');

        $stmt = $pdo->prepare(
            "SELECT `id`, `source_path`, `target_path`, `http_status_code` 
             FROM `url_redirects` 
             WHERE `source_path` = :path AND `is_active` = 1 
             LIMIT 1"
        );
        $stmt->execute(['path' => $normPath]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if (!$row) {
            return null;
        }

        // Increment hit count asynchronously
        try {
            $up = $pdo->prepare("UPDATE `url_redirects` SET `hit_count` = `hit_count` + 1, `last_accessed_at` = CURRENT_TIMESTAMP WHERE `id` = :id");
            $up->execute(['id' => (int)$row['id']]);
            $up->closeCursor();
        } catch (\Throwable) {
            // Non-blocking telemetry
        }

        return $row;
    }

    /**
     * Records or updates a 301 URL redirect entry.
     */
    public function recordRedirect(string $sourcePath, string $targetPath, int $statusCode = 301): void
    {
        $pdo = $this->connection->getPdo();
        $src = '/' . ltrim(trim($sourcePath), '/');
        $tgt = '/' . ltrim(trim($targetPath), '/');

        if ($src === $tgt) {
            return;
        }

        // Check if redirect for source exists
        $check = $pdo->prepare("SELECT `id` FROM `url_redirects` WHERE `source_path` = :src");
        $check->execute(['src' => $src]);
        $existingId = $check->fetchColumn();
        $check->closeCursor();

        if ($existingId !== false) {
            $update = $pdo->prepare(
                "UPDATE `url_redirects` 
                 SET `target_path` = :tgt, `http_status_code` = :code, `is_active` = 1 
                 WHERE `id` = :id"
            );
            $update->execute(['tgt' => $tgt, 'code' => $statusCode, 'id' => (int)$existingId]);
            $update->closeCursor();
        } else {
            $insert = $pdo->prepare(
                "INSERT INTO `url_redirects` (`source_path`, `target_path`, `http_status_code`, `is_active`, `hit_count`, `created_at`) 
                 VALUES (:src, :tgt, :code, 1, 0, CURRENT_TIMESTAMP)"
            );
            $insert->execute(['src' => $src, 'tgt' => $tgt, 'code' => $statusCode]);
            $insert->closeCursor();
        }
    }

    /**
     * Calculates deterministic SHA-256 ETag in standard quoted format.
     */
    public function calculateEtag(array $data): string
    {
        $hash = hash('sha256', (string)json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return '"' . $hash . '"';
    }

    /**
     * Lists all services for administrative management.
     */
    public function listServices(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $this->ensureDefaultServices();
        $pdo = $this->connection->getPdo();

        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $where = ["1=1"];
        $params = [];

        if (isset($filters['is_published']) && $filters['is_published'] !== '' && $filters['is_published'] !== null) {
            $where[] = "`is_published` = :pub";
            $params['pub'] = (int)$filters['is_published'];
        }

        if (!empty($filters['search'])) {
            $search = '%' . trim((string)$filters['search']) . '%';
            $where[] = "(`title` LIKE :s1 OR `tagline` LIKE :s2 OR `slug` LIKE :s3)";
            $params['s1'] = $search;
            $params['s2'] = $search;
            $params['s3'] = $search;
        }

        $whereClause = implode(' AND ', $where);

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM `services` WHERE {$whereClause}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        $countStmt->closeCursor();

        $dataStmt = $pdo->prepare(
            "SELECT * FROM `services` 
             WHERE {$whereClause} 
             ORDER BY `display_order` ASC, `id` ASC 
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $dataStmt->execute($params);
        $rows = $dataStmt->fetchAll(\PDO::FETCH_ASSOC);
        $dataStmt->closeCursor();

        $services = array_map([$this, 'formatServiceRecord'], $rows);

        return [
            'services'   => $services,
            'pagination' => [
                'total'        => $total,
                'page'         => $page,
                'per_page'     => $perPage,
                'total_pages'  => (int)ceil($total / $perPage),
            ],
        ];
    }

    /**
     * Retrieves service by ID for administrative inspection.
     */
    public function getServiceById(int $id): ?array
    {
        $pdo = $this->connection->getPdo();
        $stmt = $pdo->prepare("SELECT * FROM `services` WHERE `id` = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if (!$row) {
            return null;
        }

        $service = $this->formatServiceRecord($row);

        $indStmt = $pdo->prepare("SELECT `industry_id` FROM `service_industry_mappings` WHERE `service_id` = :id");
        $indStmt->execute(['id' => $id]);
        $service['industry_ids'] = array_map('intval', $indStmt->fetchAll(\PDO::FETCH_COLUMN));
        $indStmt->closeCursor();

        return $service;
    }

    /**
     * Creates a new service offering.
     */
    public function createService(array $data, int $userId): array
    {
        $pdo = $this->connection->getPdo();

        $title = trim((string)($data['title'] ?? ''));
        if ($title === '' || strlen($title) > 150) {
            throw new ValidationException("Title is required and must not exceed 150 characters.");
        }

        $slugInput = trim((string)($data['slug'] ?? ''));
        if ($slugInput === '') {
            $slugInput = $this->slugify($title);
        }
        $slug = $this->validateSlug($slugInput);

        // Check uniqueness
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `services` WHERE `slug` = :slug");
        $stmt->execute(['slug' => $slug]);
        if ((int)$stmt->fetchColumn() > 0) {
            $stmt->closeCursor();
            throw new BadRequestException("Service with slug '{$slug}' already exists.");
        }
        $stmt->closeCursor();

        $tagline = trim((string)($data['tagline'] ?? ''));
        if (strlen($tagline) > 255) {
            $tagline = substr($tagline, 0, 255);
        }

        $descriptionRaw = (string)($data['description'] ?? '');
        $description = $this->sanitizer->sanitize($descriptionRaw);

        $deliverables = is_array($data['deliverables'] ?? null) ? $data['deliverables'] : (is_array($data['deliverables_json'] ?? null) ? $data['deliverables_json'] : []);
        $slaMetrics = is_array($data['sla_metrics'] ?? null) ? $data['sla_metrics'] : (is_array($data['sla_metrics_json'] ?? null) ? $data['sla_metrics_json'] : []);

        $delivJson = json_encode($this->sanitizer->sanitizePayload($deliverables), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $slaJson = json_encode($this->sanitizer->sanitizePayload($slaMetrics), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $isPublished = !empty($data['is_published']) ? 1 : 0;
        $displayOrder = isset($data['display_order']) ? max(0, (int)$data['display_order']) : 0;

        $industryIds = is_array($data['industry_ids'] ?? null) ? array_map('intval', $data['industry_ids']) : [];

        $pdo->beginTransaction();
        try {
            $insert = $pdo->prepare(
                "INSERT INTO `services` 
                 (`title`, `slug`, `tagline`, `description`, `deliverables_json`, `sla_metrics_json`, `is_published`, `display_order`, `created_at`, `updated_at`) 
                 VALUES 
                 (:title, :slug, :tagline, :desc, :deliv, :sla, :pub, :ord, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
            );
            $insert->execute([
                'title'   => $title,
                'slug'    => $slug,
                'tagline' => $tagline,
                'desc'    => $description,
                'deliv'   => $delivJson,
                'sla'     => $slaJson,
                'pub'     => $isPublished,
                'ord'     => $displayOrder,
            ]);
            $insert->closeCursor();

            $serviceId = (int)$pdo->lastInsertId();

            if (!empty($industryIds)) {
                $mapInsert = $pdo->prepare("INSERT INTO `service_industry_mappings` (`service_id`, `industry_id`, `created_at`) VALUES (:sid, :iid, CURRENT_TIMESTAMP)");
                foreach ($industryIds as $indId) {
                    if ($indId > 0) {
                        $mapInsert->execute(['sid' => $serviceId, 'iid' => $indId]);
                    }
                }
                $mapInsert->closeCursor();
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        // Cryptographic audit log outside transaction with compensating rollback defense
        try {
            $this->auditLogger->log(
                $pdo,
                'services',
                'SERVICE_CREATED',
                $userId,
                '127.0.0.1',
                'CMS/ServiceService',
                'services',
                $serviceId,
                ['title' => $title, 'slug' => $slug, 'is_published' => $isPublished]
            );
        } catch (\Throwable $auditEx) {
            // Compensating rollback: purge un-audited created service and mappings
            try {
                $pdo->beginTransaction();
                $pdo->exec("DELETE FROM `service_industry_mappings` WHERE `service_id` = {$serviceId}");
                $pdo->exec("DELETE FROM `services` WHERE `id` = {$serviceId}");
                $pdo->commit();
            } catch (\Throwable) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            }
            throw new SecurityException("Failed to append immutable audit ledger event: " . $auditEx->getMessage(), 'ERR_AUDIT_LOG_FAILED');
        }

        return $this->getServiceById($serviceId) ?? [];
    }

    /**
     * Updates an existing service offering.
     * Automatically registers a 301 URL redirect in url_redirects if slug is modified.
     */
    public function updateService(int $id, array $data, int $userId): array
    {
        $pdo = $this->connection->getPdo();
        $driver = (string)$pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $lockSql = "SELECT * FROM `services` WHERE `id` = :id" . ($driver === 'mysql' ? " FOR UPDATE" : "");

        $pdo->beginTransaction();
        try {
            $lockStmt = $pdo->prepare($lockSql);
            $lockStmt->execute(['id' => $id]);
            $current = $lockStmt->fetch(\PDO::FETCH_ASSOC);
            $lockStmt->closeCursor();

            if (!$current) {
                $pdo->rollBack();
                throw new NotFoundException("Service [{$id}] not found.");
            }

            $currentSlug = (string)$current['slug'];
            $newSlug = $currentSlug;
            $slugChanged = false;

            if (isset($data['slug']) && trim((string)$data['slug']) !== '') {
                $candidateSlug = $this->validateSlug((string)$data['slug']);
                if ($candidateSlug !== $currentSlug) {
                    $check = $pdo->prepare("SELECT COUNT(*) FROM `services` WHERE `slug` = :slug AND `id` != :id");
                    $check->execute(['slug' => $candidateSlug, 'id' => $id]);
                    if ((int)$check->fetchColumn() > 0) {
                        $check->closeCursor();
                        $pdo->rollBack();
                        throw new BadRequestException("Service slug '{$candidateSlug}' is already taken.");
                    }
                    $check->closeCursor();
                    $newSlug = $candidateSlug;
                    $slugChanged = true;
                }
            }

            $title = isset($data['title']) ? trim((string)$data['title']) : (string)$current['title'];
            if ($title === '' || strlen($title) > 150) {
                $pdo->rollBack();
                throw new ValidationException("Title is required and must not exceed 150 characters.");
            }

            $tagline = isset($data['tagline']) ? trim((string)$data['tagline']) : (string)$current['tagline'];
            if (strlen($tagline) > 255) {
                $tagline = substr($tagline, 0, 255);
            }

            $description = isset($data['description'])
                ? $this->sanitizer->sanitize((string)$data['description'])
                : (string)$current['description'];

            $deliverables = isset($data['deliverables']) || isset($data['deliverables_json'])
                ? (is_array($data['deliverables'] ?? null) ? $data['deliverables'] : (is_array($data['deliverables_json'] ?? null) ? $data['deliverables_json'] : []))
                : json_decode((string)$current['deliverables_json'], true);

            $slaMetrics = isset($data['sla_metrics']) || isset($data['sla_metrics_json'])
                ? (is_array($data['sla_metrics'] ?? null) ? $data['sla_metrics'] : (is_array($data['sla_metrics_json'] ?? null) ? $data['sla_metrics_json'] : []))
                : json_decode((string)$current['sla_metrics_json'], true);

            $delivJson = json_encode($this->sanitizer->sanitizePayload($deliverables ?? []), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $slaJson = json_encode($this->sanitizer->sanitizePayload($slaMetrics ?? []), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $isPublished = isset($data['is_published']) ? (!empty($data['is_published']) ? 1 : 0) : (int)$current['is_published'];
            $displayOrder = isset($data['display_order']) ? max(0, (int)$data['display_order']) : (int)$current['display_order'];

            $update = $pdo->prepare(
                "UPDATE `services` 
                 SET `title` = :title, `slug` = :slug, `tagline` = :tagline, `description` = :desc,
                     `deliverables_json` = :deliv, `sla_metrics_json` = :sla, `is_published` = :pub,
                     `display_order` = :ord, `updated_at` = CURRENT_TIMESTAMP
                 WHERE `id` = :id"
            );
            $update->execute([
                'title'   => $title,
                'slug'    => $newSlug,
                'tagline' => $tagline,
                'desc'    => $description,
                'deliv'   => $delivJson,
                'sla'     => $slaJson,
                'pub'     => $isPublished,
                'ord'     => $displayOrder,
                'id'      => $id,
            ]);
            $update->closeCursor();

            // Automated 301 Redirect Generation on Slug Rename (FR-WF-006)
            if ($slugChanged) {
                $this->recordRedirect('/services/' . $currentSlug, '/services/' . $newSlug, 301);
            }

            // Sync industry mappings if provided
            if (isset($data['industry_ids']) && is_array($data['industry_ids'])) {
                $pdo->exec("DELETE FROM `service_industry_mappings` WHERE `service_id` = {$id}");
                $mapInsert = $pdo->prepare("INSERT INTO `service_industry_mappings` (`service_id`, `industry_id`, `created_at`) VALUES (:sid, :iid, CURRENT_TIMESTAMP)");
                foreach ($data['industry_ids'] as $indId) {
                    $iid = (int)$indId;
                    if ($iid > 0) {
                        $mapInsert->execute(['sid' => $id, 'iid' => $iid]);
                    }
                }
                $mapInsert->closeCursor();
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        // Cryptographic audit log outside transaction with compensating rollback defense
        try {
            if ($slugChanged) {
                $this->auditLogger->log(
                    $pdo,
                    'services',
                    'SERVICE_SLUG_CHANGED',
                    $userId,
                    '127.0.0.1',
                    'CMS/ServiceService',
                    'services',
                    $id,
                    ['old_slug' => $currentSlug, 'new_slug' => $newSlug]
                );
            }

            $this->auditLogger->log(
                $pdo,
                'services',
                'SERVICE_UPDATED',
                $userId,
                '127.0.0.1',
                'CMS/ServiceService',
                'services',
                $id,
                ['title' => $title, 'slug' => $newSlug, 'is_published' => $isPublished]
            );
        } catch (\Throwable $auditEx) {
            // Compensating rollback: restore previous record state
            try {
                $pdo->beginTransaction();
                $revert = $pdo->prepare(
                    "UPDATE `services` 
                     SET `title` = :title, `slug` = :slug, `tagline` = :tagline, `description` = :desc,
                         `deliverables_json` = :deliv, `sla_metrics_json` = :sla, `is_published` = :pub,
                         `display_order` = :ord
                     WHERE `id` = :id"
                );
                $revert->execute([
                    'title'   => $current['title'],
                    'slug'    => $current['slug'],
                    'tagline' => $current['tagline'],
                    'desc'    => $current['description'],
                    'deliv'   => $current['deliverables_json'],
                    'sla'     => $current['sla_metrics_json'],
                    'pub'     => $current['is_published'],
                    'ord'     => $current['display_order'],
                    'id'      => $id,
                ]);
                $revert->closeCursor();
                $pdo->commit();
            } catch (\Throwable) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            }
            throw new SecurityException("Failed to append immutable audit ledger event: " . $auditEx->getMessage(), 'ERR_AUDIT_LOG_FAILED');
        }

        return $this->getServiceById($id) ?? [];
    }

    /**
     * Toggles service publication state.
     */
    public function updateServiceStatus(int $id, int|bool $isPublished, int $userId): array
    {
        $pdo = $this->connection->getPdo();
        $pubState = !empty($isPublished) ? 1 : 0;
        $driver = (string)$pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $lockSql = "SELECT `id`, `slug`, `title`, `is_published` FROM `services` WHERE `id` = :id" . ($driver === 'mysql' ? " FOR UPDATE" : "");

        $pdo->beginTransaction();
        try {
            $lockStmt = $pdo->prepare($lockSql);
            $lockStmt->execute(['id' => $id]);
            $current = $lockStmt->fetch(\PDO::FETCH_ASSOC);
            $lockStmt->closeCursor();

            if (!$current) {
                $pdo->rollBack();
                throw new NotFoundException("Service [{$id}] not found.");
            }

            $update = $pdo->prepare("UPDATE `services` SET `is_published` = :pub, `updated_at` = CURRENT_TIMESTAMP WHERE `id` = :id");
            $update->execute(['pub' => $pubState, 'id' => $id]);
            $update->closeCursor();

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        try {
            $this->auditLogger->log(
                $pdo,
                'services',
                'SERVICE_STATUS_CHANGED',
                $userId,
                '127.0.0.1',
                'CMS/ServiceService',
                'services',
                $id,
                ['old_status' => (int)$current['is_published'], 'new_status' => $pubState]
            );
        } catch (\Throwable $auditEx) {
            try {
                $pdo->beginTransaction();
                $rev = $pdo->prepare("UPDATE `services` SET `is_published` = :pub WHERE `id` = :id");
                $rev->execute(['pub' => (int)$current['is_published'], 'id' => $id]);
                $rev->closeCursor();
                $pdo->commit();
            } catch (\Throwable) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            }
            throw new SecurityException("Failed to append immutable audit ledger event: " . $auditEx->getMessage(), 'ERR_AUDIT_LOG_FAILED');
        }

        return $this->getServiceById($id) ?? [];
    }

    /**
     * Batch reorders services sequence.
     *
     * @param array<int, array{id: int, display_order: int}> $orderMappings
     */
    public function reorderServices(array $orderMappings, int $userId): array
    {
        $pdo = $this->connection->getPdo();

        if (empty($orderMappings)) {
            return $this->listServices()['services'];
        }

        $pdo->beginTransaction();
        try {
            $update = $pdo->prepare("UPDATE `services` SET `display_order` = :ord, `updated_at` = CURRENT_TIMESTAMP WHERE `id` = :id");
            $orderIndex = 1;
            foreach ($orderMappings as $item) {
                if (is_int($item) || (is_string($item) && is_numeric($item))) {
                    $svcId = (int)$item;
                    $order = $orderIndex++;
                } elseif (is_array($item)) {
                    $svcId = (int)($item['id'] ?? 0);
                    $order = isset($item['display_order']) ? max(0, (int)$item['display_order']) : $orderIndex++;
                } else {
                    continue;
                }

                if ($svcId > 0) {
                    $update->execute(['ord' => $order, 'id' => $svcId]);
                }
            }
            $update->closeCursor();
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        try {
            $this->auditLogger->log(
                $pdo,
                'services',
                'SERVICES_REORDERED',
                $userId,
                '127.0.0.1',
                'CMS/ServiceService',
                'services',
                0,
                ['count' => count($orderMappings)]
            );
        } catch (\Throwable $auditEx) {
            throw new SecurityException("Failed to append immutable audit ledger event: " . $auditEx->getMessage(), 'ERR_AUDIT_LOG_FAILED');
        }

        return $this->listServices()['services'];
    }

    /**
     * Deletes a service offering.
     */
    public function deleteService(int $id, int $userId): bool
    {
        $pdo = $this->connection->getPdo();
        $driver = (string)$pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $lockSql = "SELECT * FROM `services` WHERE `id` = :id" . ($driver === 'mysql' ? " FOR UPDATE" : "");

        $pdo->beginTransaction();
        try {
            $lockStmt = $pdo->prepare($lockSql);
            $lockStmt->execute(['id' => $id]);
            $current = $lockStmt->fetch(\PDO::FETCH_ASSOC);
            $lockStmt->closeCursor();

            if (!$current) {
                $pdo->rollBack();
                throw new NotFoundException("Service [{$id}] not found.");
            }

            $pdo->exec("DELETE FROM `service_industry_mappings` WHERE `service_id` = {$id}");
            $pdo->exec("DELETE FROM `services` WHERE `id` = {$id}");

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        try {
            $this->auditLogger->log(
                $pdo,
                'services',
                'SERVICE_DELETED',
                $userId,
                '127.0.0.1',
                'CMS/ServiceService',
                'services',
                $id,
                ['title' => $current['title'], 'slug' => $current['slug']]
            );
        } catch (\Throwable $auditEx) {
            // Compensating restore
            try {
                $pdo->beginTransaction();
                $restore = $pdo->prepare(
                    "INSERT INTO `services` 
                     (`id`, `title`, `slug`, `tagline`, `description`, `deliverables_json`, `sla_metrics_json`, `is_published`, `display_order`, `created_at`, `updated_at`)
                     VALUES 
                     (:id, :title, :slug, :tagline, :desc, :deliv, :sla, :pub, :ord, :created, :updated)"
                );
                $restore->execute([
                    'id'      => $current['id'],
                    'title'   => $current['title'],
                    'slug'    => $current['slug'],
                    'tagline' => $current['tagline'],
                    'desc'    => $current['description'],
                    'deliv'   => $current['deliverables_json'],
                    'sla'     => $current['sla_metrics_json'],
                    'pub'     => $current['is_published'],
                    'ord'     => $current['display_order'],
                    'created' => $current['created_at'],
                    'updated' => $current['updated_at'],
                ]);
                $restore->closeCursor();
                $pdo->commit();
            } catch (\Throwable) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            }
            throw new SecurityException("Failed to append immutable audit ledger event: " . $auditEx->getMessage(), 'ERR_AUDIT_LOG_FAILED');
        }

        return true;
    }

    private function formatServiceRecord(array $row): array
    {
        $deliverables = json_decode((string)($row['deliverables_json'] ?? '[]'), true) ?: [];
        $slaMetrics = json_decode((string)($row['sla_metrics_json'] ?? '[]'), true) ?: [];

        return [
            'id'                => (int)$row['id'],
            'title'             => $row['title'],
            'slug'              => $row['slug'],
            'tagline'           => $row['tagline'],
            'description'       => $row['description'],
            'deliverables'      => $deliverables,
            'sla_metrics'       => $slaMetrics,
            'is_published'      => isset($row['is_published']) ? (int)$row['is_published'] : 1,
            'display_order'     => (int)$row['display_order'],
            'created_at'        => $row['created_at'],
            'updated_at'        => $row['updated_at'],
        ];
    }

    private function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/i', '-', $text) ?? '';
        return trim($text, '-');
    }
}
