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
 * Enterprise Healthcare Industries & Verticals Management Service.
 * Manages customer healthcare sectors, regulatory frameworks, pain points,
 * display sequencing, automated 301 SEO redirects on slug rename,
 * and relational service_industry_mappings.
 */
class IndustryService
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
     */
    public function validateSlug(string $slug): string
    {
        $trimmed = trim($slug);

        if ($trimmed === '') {
            throw new BadRequestException("Slug cannot be empty.");
        }

        // Canonical contract: lowercase only
        if ($trimmed !== strtolower($trimmed)) {
            throw new BadRequestException("Slug must be lowercase alphanumeric with hyphens.");
        }

        if (strlen($trimmed) < 3 || strlen($trimmed) > 100) {
            throw new BadRequestException("Slug must be between 3 and 100 characters.");
        }

        if (!preg_match(self::SLUG_REGEX, $trimmed)) {
            throw new BadRequestException("Invalid slug format. Use lowercase alphanumeric with single hyphens (e.g., 'residential-aged-care').");
        }

        if (in_array($trimmed, self::RESERVED_SLUGS, true)) {
            throw new BadRequestException("Slug '{$trimmed}' is a reserved system keyword.");
        }

        return $trimmed;
    }

    /**
     * Seeds initial 6 canonical industry verticals if table is empty.
     */
    public function ensureDefaultIndustries(int $systemUserId = 1): int
    {
        $pdo = $this->connection->getPdo();
        $stmt = $pdo->query("SELECT COUNT(*) FROM `industries`");
        $count = $stmt ? (int)$stmt->fetchColumn() : 0;
        if ($stmt) {
            $stmt->closeCursor();
        }

        if ($count > 0) {
            return 0;
        }

        $defaults = [
            [
                'title' => 'Hospitals & Health Services',
                'slug' => 'hospitals-health-services',
                'summary' => 'Public and private tertiary hospital networks and clinical day surgeries requiring rapid workforce deployment and strict accreditation assurance.',
                'regulatory_burden_specs' => [
                    'governing_body' => 'Australian Commission on Safety and Quality in Health Care (ACSQHC)',
                    'standards' => [
                        'National Safety and Quality Health Service (NSQHS) Standards 1–8',
                        'Clinical Governance Standard and Sentinel Event Reporting (SAC 1 & 2)',
                        'Hand Hygiene Australia and National Infection Control Frameworks',
                    ],
                    'scope' => 'Acute care, emergency departments, surgical wards, and intensive care units.',
                ],
                'pain_points' => [
                    'Severe emergency department surges and inpatient bed-block pressure',
                    'Specialist clinical nurse vacancies in ICU, CCU, and perioperative wards',
                    'Accreditation vulnerability during unannounced NSQHS compliance surveillance audits',
                    'High agency labor costs driven by fragmented, reactive procurement processes',
                ],
                'display_order' => 1,
                'service_ids' => [1, 2, 3, 4, 5, 6],
            ],
            [
                'title' => 'Residential Aged Care Facilities',
                'slug' => 'residential-aged-care',
                'summary' => 'Residential aged care homes and retirement communities navigating direct-care minute targets, 24/7 RN mandates, and quality audits.',
                'regulatory_burden_specs' => [
                    'governing_body' => 'Aged Care Quality and Safety Commission (ACQSC)',
                    'standards' => [
                        'Aged Care Quality Standards (Standards 1–8)',
                        'Mandatory 215 direct care minutes per resident per day (including 44 RN minutes)',
                        'Mandatory 24/7 Registered Nurse on-site coverage legislation',
                    ],
                    'scope' => 'Residential care facilities, dementia-specific care wings, and multi-facility providers.',
                ],
                'pain_points' => [
                    'Meeting the non-negotiable 24/7 on-site Registered Nurse requirement',
                    'Consistently achieving the mandatory 215 direct-care minute threshold',
                    'High staff churn and administrative fatigue in verifying clinician compliance',
                    'Risk of formal sanction and non-compliance notices from unannounced ACQSC site visits',
                ],
                'display_order' => 2,
                'service_ids' => [1, 2, 3, 4, 5, 6],
            ],
            [
                'title' => 'Primary Care & General Practice',
                'slug' => 'primary-care-general-practice',
                'summary' => 'Medical centres, GP clinics, and urgent care networks requiring dependable medical administrative operations and practice nursing.',
                'regulatory_burden_specs' => [
                    'governing_body' => 'Royal Australian College of General Practitioners (RACGP)',
                    'standards' => [
                        'RACGP Standards for General Practices (5th edition)',
                        'Medicare Benefits Schedule (MBS) billing integrity guidelines',
                        'National Vaccine Storage Guidelines (Strive for 5) cold-chain compliance',
                    ],
                    'scope' => 'General practices, bulk-billing medical centres, and urgent outpatient clinics.',
                ],
                'pain_points' => [
                    'Chronic receptionist turnover causing front-desk congestion and patient friction',
                    'Complex Medicare billing rejections and practitioner compliance risk',
                    'Shortage of certified practice nurses for chronic disease care plans',
                    'Administrative strain preparing clinics for RACGP 5th edition triennial accreditation',
                ],
                'display_order' => 3,
                'service_ids' => [1, 2, 3, 4, 6],
            ],
            [
                'title' => 'Allied Health Networks',
                'slug' => 'allied-health-networks',
                'summary' => 'Multi-disciplinary physiotherapy, occupational therapy, and rehabilitation networks delivering NDIS and private clinical services.',
                'regulatory_burden_specs' => [
                    'governing_body' => 'National Disability Insurance Agency (NDIA) & AHPRA',
                    'standards' => [
                        'NDIS Quality and Safeguards Commission Practice Standards',
                        'AHPRA Allied Health National Boards Registration Standards',
                        'Mandatory NDIS Worker Screening Checks and incident reporting',
                    ],
                    'scope' => 'Multi-site physiotherapy, occupational therapy, speech pathology, and rehab clinics.',
                ],
                'pain_points' => [
                    'Time-consuming NDIS reporting and service booking billing reconciliation',
                    'High non-billable administrative burden on therapists reducing clinical hours',
                    'Difficulty recruiting credentialed allied health clinicians in high-demand areas',
                    'Managing clinician schedules and travel across distributed multi-clinic hubs',
                ],
                'display_order' => 4,
                'service_ids' => [1, 3, 4, 6],
            ],
            [
                'title' => 'Diagnostic & Pathology Centers',
                'slug' => 'diagnostic-pathology-centers',
                'summary' => 'Clinical laboratories and diagnostic imaging networks requiring high-turnaround specimen logistics and certified medical technicians.',
                'regulatory_burden_specs' => [
                    'governing_body' => 'National Association of Testing Authorities (NATA) & RCPA',
                    'standards' => [
                        'ISO 15189 Medical Laboratories Quality and Competence Standards',
                        'NATA / RCPA Joint Accreditation Criteria',
                        'Diagnostic Imaging Accreditation Scheme (DIAS) standards',
                    ],
                    'scope' => 'Pathology laboratories, medical imaging centers, ultrasound and MRI clinics.',
                ],
                'pain_points' => [
                    'Maintaining strict turnaround time SLAs for critical and stat pathology specimens',
                    'Severe national shortage of qualified pathology technicians and radiographers',
                    'Stringent calibration and document audit standards required for NATA compliance',
                    'Managing high-volume patient intake concierge and billing documentation',
                ],
                'display_order' => 5,
                'service_ids' => [1, 2, 3, 4, 5],
            ],
            [
                'title' => 'Corporate & Occupational Health',
                'slug' => 'corporate-occupational-health',
                'summary' => 'Industrial sites, mining operations, and corporate campuses requiring workplace health surveillance, vaccination, and fit-for-work testing.',
                'regulatory_burden_specs' => [
                    'governing_body' => 'Safe Work Australia & Comcare',
                    'standards' => [
                        'Work Health and Safety (WHS) Act 2011 and State Regulations',
                        'National Standard for Health Assessment of Rail Safety Workers',
                        'Occupational Noise Management Standard AS/NZS 1269',
                    ],
                    'scope' => 'Industrial manufacturing, mining camps, corporate enterprises, and transport hubs.',
                ],
                'pain_points' => [
                    'Deploying qualified occupational health nurses to remote and regional industrial locations',
                    'Coordinating large-scale, multi-state annual workplace flu and health screening programs',
                    'Rising workers compensation costs driven by delayed early musculoskeletal triage',
                    'Bottlenecks in pre-employment medical clearances delaying workforce onboarding',
                ],
                'display_order' => 6,
                'service_ids' => [1, 4, 6],
            ],
        ];

        $pdo->beginTransaction();
        try {
            $insert = $pdo->prepare(
                "INSERT INTO `industries` 
                 (`title`, `slug`, `summary`, `regulatory_burden_specs`, `pain_points_json`, `is_published`, `display_order`, `created_at`, `updated_at`)
                 VALUES 
                 (:title, :slug, :summary, :reg, :pain, 1, :order, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
            );

            $driver = (string)$pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
            $mappingSql = $driver === 'mysql'
                ? "INSERT IGNORE INTO `service_industry_mappings` (`service_id`, `industry_id`, `created_at`) VALUES (:sid, :iid, CURRENT_TIMESTAMP)"
                : "INSERT OR IGNORE INTO `service_industry_mappings` (`service_id`, `industry_id`, `created_at`) VALUES (:sid, :iid, CURRENT_TIMESTAMP)";
            $insertMapping = $pdo->prepare($mappingSql);

            foreach ($defaults as $ind) {
                $insert->execute([
                    'title'   => $ind['title'],
                    'slug'    => $ind['slug'],
                    'summary' => $ind['summary'],
                    'reg'     => json_encode($ind['regulatory_burden_specs'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'pain'    => json_encode($ind['pain_points'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'order'   => $ind['display_order'],
                ]);
                $industryId = (int)$pdo->lastInsertId();

                if (!empty($ind['service_ids'])) {
                    foreach ($ind['service_ids'] as $svcId) {
                        try {
                            $insertMapping->execute([
                                'sid' => (int)$svcId,
                                'iid' => $industryId,
                            ]);
                        } catch (\Throwable) {
                            // If service doesn't exist yet, ignore
                        }
                    }
                }
            }
            $insert->closeCursor();
            $insertMapping->closeCursor();
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
     * Retrieves all published industries for public presentation.
     *
     * @return array{industries: list<array<string, mixed>>, etag: string}
     */
    public function getPublishedIndustries(): array
    {
        $this->ensureDefaultIndustries();
        $pdo = $this->connection->getPdo();

        $stmt = $pdo->query(
            "SELECT `id`, `title`, `slug`, `summary`, `regulatory_burden_specs`, `pain_points_json`, `is_published`, `display_order`, `created_at`, `updated_at` 
             FROM `industries` 
             WHERE `is_published` = 1 
             ORDER BY `display_order` ASC, `id` ASC"
        );
        $rows = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
        if ($stmt) {
            $stmt->closeCursor();
        }

        $industries = array_map([$this, 'formatIndustryRecord'], $rows);
        $etag = $this->calculateEtag($industries);

        return [
            'industries' => $industries,
            'etag'       => $etag,
        ];
    }

    /**
     * Retrieves a published industry by slug with mapped services for public presentation.
     */
    public function getPublishedIndustryBySlug(string $slug): ?array
    {
        $cleanSlug = $this->validateSlug($slug);
        $this->ensureDefaultIndustries();
        $pdo = $this->connection->getPdo();

        $stmt = $pdo->prepare(
            "SELECT * FROM `industries` WHERE `slug` = :slug AND `is_published` = 1 LIMIT 1"
        );
        $stmt->execute(['slug' => $cleanSlug]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if (!$row) {
            return null;
        }

        $industry = $this->formatIndustryRecord($row);

        // Fetch associated published services
        $svcStmt = $pdo->prepare(
            "SELECT s.`id`, s.`title`, s.`slug`, s.`tagline`, s.`display_order` 
             FROM `services` s
             INNER JOIN `service_industry_mappings` sim ON s.`id` = sim.`service_id`
             WHERE sim.`industry_id` = :id AND s.`is_published` = 1
             ORDER BY s.`display_order` ASC, s.`id` ASC"
        );
        $svcStmt->execute(['id' => (int)$industry['id']]);
        $industry['services'] = $svcStmt->fetchAll(\PDO::FETCH_ASSOC);
        $svcStmt->closeCursor();

        $industry['etag'] = $this->calculateEtag($industry);

        return $industry;
    }

    /**
     * Calculates deterministic SHA-256 ETag.
     */
    public function calculateEtag(mixed $data): string
    {
        $canonical = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $hash = hash('sha256', (string)$canonical);
        return '"' . $hash . '"';
    }

    /**
     * Lists all industries for administrative management.
     */
    public function listIndustries(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $this->ensureDefaultIndustries();
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
            $where[] = "(`title` LIKE :s1 OR `summary` LIKE :s2 OR `slug` LIKE :s3)";
            $params['s1'] = $search;
            $params['s2'] = $search;
            $params['s3'] = $search;
        }

        $whereClause = implode(' AND ', $where);

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM `industries` WHERE {$whereClause}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        $countStmt->closeCursor();

        $dataStmt = $pdo->prepare(
            "SELECT * FROM `industries` 
             WHERE {$whereClause} 
             ORDER BY `display_order` ASC, `id` ASC 
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $dataStmt->execute($params);
        $rows = $dataStmt->fetchAll(\PDO::FETCH_ASSOC);
        $dataStmt->closeCursor();

        $industries = array_map([$this, 'formatIndustryRecord'], $rows);

        return [
            'industries' => $industries,
            'pagination' => [
                'total'       => $total,
                'page'        => $page,
                'per_page'    => $perPage,
                'total_pages' => (int)ceil($total / $perPage),
            ],
        ];
    }

    /**
     * Retrieves industry by ID for administrative inspection.
     */
    public function getIndustryById(int $id): ?array
    {
        $pdo = $this->connection->getPdo();
        $stmt = $pdo->prepare("SELECT * FROM `industries` WHERE `id` = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if (!$row) {
            return null;
        }

        $industry = $this->formatIndustryRecord($row);

        // Fetch mapped service IDs and summaries
        $mapStmt = $pdo->prepare(
            "SELECT s.`id`, s.`title`, s.`slug`, s.`is_published` 
             FROM `services` s
             INNER JOIN `service_industry_mappings` sim ON s.`id` = sim.`service_id`
             WHERE sim.`industry_id` = :id
             ORDER BY s.`display_order` ASC, s.`id` ASC"
        );
        $mapStmt->execute(['id' => $id]);
        $services = $mapStmt->fetchAll(\PDO::FETCH_ASSOC);
        $mapStmt->closeCursor();

        $industry['services'] = $services;
        $industry['service_ids'] = array_map(fn($s) => (int)$s['id'], $services);

        return $industry;
    }

    /**
     * Finds active URL redirect for a given source path.
     */
    public function findRedirect(string $sourcePath): ?array
    {
        $pdo = $this->connection->getPdo();
        $normalized = '/' . ltrim(trim($sourcePath), '/');

        $stmt = $pdo->prepare(
            "SELECT `id`, `source_path`, `target_path`, `http_status_code`, `is_active` 
             FROM `url_redirects` 
             WHERE `source_path` = :src AND `is_active` = 1 
             LIMIT 1"
        );
        $stmt->execute(['src' => $normalized]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if ($row) {
            $upd = $pdo->prepare("UPDATE `url_redirects` SET `hit_count` = `hit_count` + 1, `last_accessed_at` = CURRENT_TIMESTAMP WHERE `id` = :id");
            $upd->execute(['id' => $row['id']]);
            $upd->closeCursor();
            return $row;
        }

        return null;
    }

    /**
     * Records or updates a 301 URL redirect entry.
     */
    public function recordRedirect(string $sourcePath, string $targetPath, int $statusCode = 301): void
    {
        $pdo = $this->connection->getPdo();
        $source = '/' . ltrim(trim($sourcePath), '/');
        $target = '/' . ltrim(trim($targetPath), '/');

        if ($source === $target) {
            return;
        }

        $stmt = $pdo->prepare("SELECT `id` FROM `url_redirects` WHERE `source_path` = :src LIMIT 1");
        $stmt->execute(['src' => $source]);
        $existingId = $stmt->fetchColumn();
        $stmt->closeCursor();

        if ($existingId !== false) {
            $update = $pdo->prepare(
                "UPDATE `url_redirects` 
                 SET `target_path` = :tgt, `http_status_code` = :code, `is_active` = 1 
                 WHERE `id` = :id"
            );
            $update->execute([
                'tgt'  => $target,
                'code' => $statusCode,
                'id'   => (int)$existingId,
            ]);
            $update->closeCursor();
        } else {
            $insert = $pdo->prepare(
                "INSERT INTO `url_redirects` 
                 (`source_path`, `target_path`, `http_status_code`, `is_active`, `hit_count`, `created_at`)
                 VALUES 
                 (:src, :tgt, :code, 1, 0, CURRENT_TIMESTAMP)"
            );
            $insert->execute([
                'src'  => $source,
                'tgt'  => $target,
                'code' => $statusCode,
            ]);
            $insert->closeCursor();
        }
    }

    /**
     * Creates a new healthcare industry vertical.
     */
    public function createIndustry(array $data, int $userId): array
    {
        $pdo = $this->connection->getPdo();

        $title = $this->cleanTitle((string)($data['title'] ?? $data['name'] ?? ''));
        if ($title === '' || strlen($title) > 150) {
            throw new ValidationException("Title is required and must not exceed 150 characters.");
        }

        $slugInput = trim((string)($data['slug'] ?? ''));
        if ($slugInput === '') {
            $slugInput = $this->slugify($title);
        }
        $slug = $this->validateSlug($slugInput);

        // Check uniqueness
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `industries` WHERE `slug` = :slug");
        $stmt->execute(['slug' => $slug]);
        if ((int)$stmt->fetchColumn() > 0) {
            $stmt->closeCursor();
            throw new BadRequestException("Industry with slug '{$slug}' already exists.");
        }
        $stmt->closeCursor();

        $summaryRaw = (string)($data['summary'] ?? '');
        $summary = $this->sanitizer->sanitize($summaryRaw);

        $regRaw = $data['regulatory_burden_specs'] ?? $data['regulatory_frameworks'] ?? [];
        $painRaw = $data['pain_points_json'] ?? $data['pain_points'] ?? $data['challenges'] ?? [];

        $regData = is_array($regRaw) ? $regRaw : (json_decode((string)$regRaw, true) ?: []);
        $painData = is_array($painRaw) ? $painRaw : (json_decode((string)$painRaw, true) ?: []);

        $regJson = json_encode($this->sanitizer->sanitizePayload($regData), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $painJson = json_encode($this->sanitizer->sanitizePayload($painData), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $isPublished = !empty($data['is_published']) ? 1 : 0;
        $displayOrder = isset($data['display_order']) ? max(0, (int)$data['display_order']) : 0;

        $serviceIds = is_array($data['service_ids'] ?? null) ? array_map('intval', $data['service_ids']) : [];

        $pdo->beginTransaction();
        try {
            $insert = $pdo->prepare(
                "INSERT INTO `industries` 
                 (`title`, `slug`, `summary`, `regulatory_burden_specs`, `pain_points_json`, `is_published`, `display_order`, `created_at`, `updated_at`) 
                 VALUES 
                 (:title, :slug, :summary, :reg, :pain, :pub, :ord, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
            );
            $insert->execute([
                'title'   => $title,
                'slug'    => $slug,
                'summary' => $summary,
                'reg'     => $regJson,
                'pain'    => $painJson,
                'pub'     => $isPublished,
                'ord'     => $displayOrder,
            ]);
            $industryId = (int)$pdo->lastInsertId();
            $insert->closeCursor();

            $this->syncServiceMappings($pdo, $industryId, $serviceIds);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        // Chained Audit Logging
        try {
            $this->auditLogger->log(
                $pdo,
                'industries',
                'INDUSTRY_CREATED',
                $userId,
                '127.0.0.1',
                'CMS/IndustryService',
                'industries',
                $industryId,
                ['title' => $title, 'slug' => $slug, 'is_published' => $isPublished]
            );
        } catch (\Throwable $auditEx) {
            // Fail-closed compensating rollback
            try {
                $pdo->beginTransaction();
                $del = $pdo->prepare("DELETE FROM `service_industry_mappings` WHERE `industry_id` = :id");
                $del->execute(['id' => $industryId]);
                $del->closeCursor();
                $delInd = $pdo->prepare("DELETE FROM `industries` WHERE `id` = :id");
                $delInd->execute(['id' => $industryId]);
                $delInd->closeCursor();
                $pdo->commit();
            } catch (\Throwable) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            }
            throw new SecurityException("Failed to append immutable audit ledger event: " . $auditEx->getMessage(), 'ERR_AUDIT_LOG_FAILED');
        }

        return $this->getIndustryById($industryId) ?? [];
    }

    /**
     * Updates an existing industry vertical.
     * Automatically registers a 301 URL redirect in url_redirects if slug is modified.
     */
    public function updateIndustry(int $id, array $data, int $userId): array
    {
        $pdo = $this->connection->getPdo();
        $driver = (string)$pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $lockSql = "SELECT * FROM `industries` WHERE `id` = :id" . ($driver === 'mysql' ? " FOR UPDATE" : "");

        $pdo->beginTransaction();
        try {
            $lockStmt = $pdo->prepare($lockSql);
            $lockStmt->execute(['id' => $id]);
            $current = $lockStmt->fetch(\PDO::FETCH_ASSOC);
            $lockStmt->closeCursor();

            if (!$current) {
                $pdo->rollBack();
                throw new NotFoundException("Industry [{$id}] not found.");
            }

            $currentSlug = (string)$current['slug'];
            $newSlug = $currentSlug;
            $slugChanged = false;

            if (isset($data['slug']) && trim((string)$data['slug']) !== '') {
                $candidateSlug = $this->validateSlug((string)$data['slug']);
                if ($candidateSlug !== $currentSlug) {
                    $check = $pdo->prepare("SELECT COUNT(*) FROM `industries` WHERE `slug` = :slug AND `id` != :id");
                    $check->execute(['slug' => $candidateSlug, 'id' => $id]);
                    if ((int)$check->fetchColumn() > 0) {
                        $check->closeCursor();
                        $pdo->rollBack();
                        throw new BadRequestException("Industry slug '{$candidateSlug}' is already taken.");
                    }
                    $check->closeCursor();
                    $newSlug = $candidateSlug;
                    $slugChanged = true;
                }
            }

            $title = isset($data['title']) || isset($data['name'])
                ? $this->cleanTitle((string)($data['title'] ?? $data['name']))
                : (string)$current['title'];

            if ($title === '' || strlen($title) > 150) {
                $pdo->rollBack();
                throw new ValidationException("Title is required and must not exceed 150 characters.");
            }

            $summary = isset($data['summary'])
                ? $this->sanitizer->sanitize((string)$data['summary'])
                : (string)$current['summary'];

            $regRaw = $data['regulatory_burden_specs'] ?? $data['regulatory_frameworks'] ?? null;
            $regData = $regRaw !== null
                ? (is_array($regRaw) ? $regRaw : (json_decode((string)$regRaw, true) ?: []))
                : (json_decode((string)$current['regulatory_burden_specs'], true) ?: []);

            $painRaw = $data['pain_points_json'] ?? $data['pain_points'] ?? $data['challenges'] ?? null;
            $painData = $painRaw !== null
                ? (is_array($painRaw) ? $painRaw : (json_decode((string)$painRaw, true) ?: []))
                : (json_decode((string)$current['pain_points_json'], true) ?: []);

            $regJson = json_encode($this->sanitizer->sanitizePayload($regData), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $painJson = json_encode($this->sanitizer->sanitizePayload($painData), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $isPublished = isset($data['is_published']) ? (!empty($data['is_published']) ? 1 : 0) : (int)$current['is_published'];
            $displayOrder = isset($data['display_order']) ? max(0, (int)$data['display_order']) : (int)$current['display_order'];

            $updateStmt = $pdo->prepare(
                "UPDATE `industries` 
                 SET `title` = :title, 
                     `slug` = :slug, 
                     `summary` = :summary, 
                     `regulatory_burden_specs` = :reg, 
                     `pain_points_json` = :pain, 
                     `is_published` = :pub, 
                     `display_order` = :ord, 
                     `updated_at` = CURRENT_TIMESTAMP 
                 WHERE `id` = :id"
            );
            $updateStmt->execute([
                'title'   => $title,
                'slug'    => $newSlug,
                'summary' => $summary,
                'reg'     => $regJson,
                'pain'    => $painJson,
                'pub'     => $isPublished,
                'ord'     => $displayOrder,
                'id'      => $id,
            ]);
            $updateStmt->closeCursor();

            if (isset($data['service_ids']) && is_array($data['service_ids'])) {
                $this->syncServiceMappings($pdo, $id, array_map('intval', $data['service_ids']));
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        // If slug changed, record 301 URL redirect
        if ($slugChanged) {
            $this->recordRedirect('/industries/' . $currentSlug, '/industries/' . $newSlug, 301);
        }

        // Audit Logging
        try {
            $diff = [
                'title'       => ['old' => $current['title'], 'new' => $title],
                'slug'        => ['old' => $currentSlug, 'new' => $newSlug],
                'is_published'=> ['old' => (int)$current['is_published'], 'new' => $isPublished],
            ];
            $this->auditLogger->log(
                $pdo,
                'industries',
                'INDUSTRY_UPDATED',
                $userId,
                '127.0.0.1',
                'CMS/IndustryService',
                'industries',
                $id,
                $diff
            );

            if ($slugChanged) {
                $this->auditLogger->log(
                    $pdo,
                    'industries',
                    'INDUSTRY_SLUG_CHANGED',
                    $userId,
                    '127.0.0.1',
                    'CMS/IndustryService',
                    'industries',
                    $id,
                    ['old_slug' => $currentSlug, 'new_slug' => $newSlug]
                );
            }
        } catch (\Throwable $auditEx) {
            // Compensating rollback
            try {
                $pdo->beginTransaction();
                $restore = $pdo->prepare(
                    "UPDATE `industries` 
                     SET `title` = :title, 
                         `slug` = :slug, 
                         `summary` = :summary, 
                         `regulatory_burden_specs` = :reg, 
                         `pain_points_json` = :pain, 
                         `is_published` = :pub, 
                         `display_order` = :ord, 
                         `updated_at` = :upd 
                     WHERE `id` = :id"
                );
                $restore->execute([
                    'title'   => $current['title'],
                    'slug'    => $current['slug'],
                    'summary' => $current['summary'],
                    'reg'     => $current['regulatory_burden_specs'],
                    'pain'    => $current['pain_points_json'],
                    'pub'     => (int)$current['is_published'],
                    'ord'     => (int)$current['display_order'],
                    'upd'     => $current['updated_at'],
                    'id'      => $id,
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

        return $this->getIndustryById($id) ?? [];
    }

    /**
     * Toggles industry publication state.
     */
    public function updateIndustryStatus(int $id, int|bool $isPublished, int $userId): array
    {
        $pdo = $this->connection->getPdo();
        $pubState = !empty($isPublished) ? 1 : 0;
        $driver = (string)$pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $lockSql = "SELECT `id`, `slug`, `title`, `is_published` FROM `industries` WHERE `id` = :id" . ($driver === 'mysql' ? " FOR UPDATE" : "");

        $pdo->beginTransaction();
        try {
            $lockStmt = $pdo->prepare($lockSql);
            $lockStmt->execute(['id' => $id]);
            $current = $lockStmt->fetch(\PDO::FETCH_ASSOC);
            $lockStmt->closeCursor();

            if (!$current) {
                $pdo->rollBack();
                throw new NotFoundException("Industry [{$id}] not found.");
            }

            $update = $pdo->prepare("UPDATE `industries` SET `is_published` = :pub, `updated_at` = CURRENT_TIMESTAMP WHERE `id` = :id");
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
                'industries',
                'INDUSTRY_STATUS_CHANGED',
                $userId,
                '127.0.0.1',
                'CMS/IndustryService',
                'industries',
                $id,
                ['old_status' => (int)$current['is_published'], 'new_status' => $pubState]
            );
        } catch (\Throwable $auditEx) {
            try {
                $pdo->beginTransaction();
                $rev = $pdo->prepare("UPDATE `industries` SET `is_published` = :pub WHERE `id` = :id");
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

        return $this->getIndustryById($id) ?? [];
    }

    /**
     * Batch reorders industries display sequence.
     *
     * @param array<int|array{id: int, display_order?: int}> $orderMappings
     */
    public function reorderIndustries(array $orderMappings, int $userId): array
    {
        $pdo = $this->connection->getPdo();

        if (empty($orderMappings)) {
            return $this->listIndustries()['industries'];
        }

        $pdo->beginTransaction();
        try {
            $update = $pdo->prepare("UPDATE `industries` SET `display_order` = :ord, `updated_at` = CURRENT_TIMESTAMP WHERE `id` = :id");
            $orderIndex = 1;
            foreach ($orderMappings as $item) {
                if (is_int($item) || (is_string($item) && is_numeric($item))) {
                    $indId = (int)$item;
                    $order = $orderIndex++;
                } elseif (is_array($item)) {
                    $indId = (int)($item['id'] ?? 0);
                    $order = isset($item['display_order']) ? max(0, (int)$item['display_order']) : $orderIndex++;
                } else {
                    continue;
                }

                if ($indId > 0) {
                    $update->execute(['ord' => $order, 'id' => $indId]);
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
                'industries',
                'INDUSTRIES_REORDERED',
                $userId,
                '127.0.0.1',
                'CMS/IndustryService',
                'industries',
                0,
                ['count' => count($orderMappings)]
            );
        } catch (\Throwable $auditEx) {
            throw new SecurityException("Failed to append immutable audit ledger event: " . $auditEx->getMessage(), 'ERR_AUDIT_LOG_FAILED');
        }

        return $this->listIndustries()['industries'];
    }

    /**
     * Deletes an industry vertical.
     */
    public function deleteIndustry(int $id, int $userId): bool
    {
        $pdo = $this->connection->getPdo();
        $driver = (string)$pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $lockSql = "SELECT * FROM `industries` WHERE `id` = :id" . ($driver === 'mysql' ? " FOR UPDATE" : "");

        $pdo->beginTransaction();
        try {
            $lockStmt = $pdo->prepare($lockSql);
            $lockStmt->execute(['id' => $id]);
            $current = $lockStmt->fetch(\PDO::FETCH_ASSOC);
            $lockStmt->closeCursor();

            if (!$current) {
                $pdo->rollBack();
                throw new NotFoundException("Industry [{$id}] not found.");
            }

            $pdo->exec("DELETE FROM `service_industry_mappings` WHERE `industry_id` = {$id}");
            $pdo->exec("DELETE FROM `industries` WHERE `id` = {$id}");

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
                'industries',
                'INDUSTRY_DELETED',
                $userId,
                '127.0.0.1',
                'CMS/IndustryService',
                'industries',
                $id,
                ['title' => $current['title'], 'slug' => $current['slug']]
            );
        } catch (\Throwable $auditEx) {
            // Compensating restore
            try {
                $pdo->beginTransaction();
                $restore = $pdo->prepare(
                    "INSERT INTO `industries` 
                     (`id`, `title`, `slug`, `summary`, `regulatory_burden_specs`, `pain_points_json`, `is_published`, `display_order`, `created_at`, `updated_at`)
                     VALUES 
                     (:id, :title, :slug, :summary, :reg, :pain, :pub, :ord, :created, :updated)"
                );
                $restore->execute([
                    'id'      => (int)$current['id'],
                    'title'   => $current['title'],
                    'slug'    => $current['slug'],
                    'summary' => $current['summary'],
                    'reg'     => $current['regulatory_burden_specs'],
                    'pain'    => $current['pain_points_json'],
                    'pub'     => (int)$current['is_published'],
                    'ord'     => (int)$current['display_order'],
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

    private function syncServiceMappings(\PDO $pdo, int $industryId, array $serviceIds): void
    {
        $del = $pdo->prepare("DELETE FROM `service_industry_mappings` WHERE `industry_id` = :iid");
        $del->execute(['iid' => $industryId]);
        $del->closeCursor();

        if (empty($serviceIds)) {
            return;
        }

        $driver = (string)$pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $insertSql = "INSERT OR IGNORE INTO `service_industry_mappings` (`service_id`, `industry_id`, `created_at`) VALUES (:sid, :iid, CURRENT_TIMESTAMP)";
        if ($driver === 'mysql') {
            $insertSql = "INSERT IGNORE INTO `service_industry_mappings` (`service_id`, `industry_id`, `created_at`) VALUES (:sid, :iid, CURRENT_TIMESTAMP)";
        }
        $insert = $pdo->prepare($insertSql);

        foreach ($serviceIds as $sid) {
            $sid = (int)$sid;
            if ($sid > 0) {
                try {
                    $insert->execute(['sid' => $sid, 'iid' => $industryId]);
                } catch (\Throwable) {
                    // Ignore non-existent foreign key in tests if services table is sparse
                }
            }
        }
        $insert->closeCursor();
    }

    private function formatIndustryRecord(array $row): array
    {
        $regData = json_decode((string)($row['regulatory_burden_specs'] ?? '[]'), true) ?: [];
        $painData = json_decode((string)($row['pain_points_json'] ?? '[]'), true) ?: [];

        return [
            'id'                      => (int)$row['id'],
            'title'                   => $row['title'],
            'slug'                    => $row['slug'],
            'summary'                 => $row['summary'],
            'regulatory_burden_specs' => $regData,
            'pain_points'             => $painData,
            'is_published'            => isset($row['is_published']) ? (int)$row['is_published'] : 1,
            'display_order'           => (int)$row['display_order'],
            'created_at'              => $row['created_at'],
            'updated_at'              => $row['updated_at'],
        ];
    }

    private function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/i', '-', $text) ?? '';
        return trim($text, '-');
    }

    private function cleanTitle(string $raw): string
    {
        $stripped = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $raw) ?? '';
        return trim(strip_tags($stripped));
    }
}
