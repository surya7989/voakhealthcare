<?php

declare(strict_types=1);

namespace Voak\Health\Services;

use Voak\Health\Auth\RbacManager;
use Voak\Health\Core\Exceptions\ConflictException;
use Voak\Health\Core\Exceptions\DatabaseException;
use Voak\Health\Core\Exceptions\HttpException;
use Voak\Health\Core\Exceptions\NotFoundException;
use Voak\Health\Core\Exceptions\ValidationException;
use Voak\Health\Core\Http\Response;
use Voak\Health\Database\Connection;
use Voak\Health\Security\AuditLogger;
use Voak\Health\Security\HtmlSanitizer;
use Voak\Health\Security\RateLimiter;

/**
 * Careers Requisitions & Talent Acquisition Service (Module 13).
 *
 * Implements:
 * - ADM-013: Careers Requisitions & Clinical Vacancy Management (FR-ADM-014, TEST-ADM-014)
 * - ADM-014: Candidate Applications Review Desk & Quarantined CV Streaming (FR-ADM-015, TEST-ADM-015, TEST-SEC-004)
 * - Candidate Recruitment Finite State Machine (FR-WF-004, TEST-WF-004)
 * - Candidate Privacy & 6-Month Data Retention Disposal (FR-SEC-004)
 */
class CareerService
{
    private Connection $connection;
    private MediaStorageService $mediaStorageService;
    private AuditLogger $auditLogger;
    private HtmlSanitizer $sanitizer;
    private ?EmailService $emailService;
    private RateLimiter $rateLimiter;
    private ?RbacManager $rbacManager;

    /**
     * Canonical employment types matching database ENUM.
     */
    public const EMPLOYMENT_TYPES = [
        'FULL_TIME',
        'PART_TIME',
        'CASUAL',
        'CONTRACT',
    ];

    /**
     * Canonical Candidate Recruitment State Machine Transition Map (FR-WF-004 / TEST-WF-004).
     *
     * Permitted forward sequence:
     * SUBMITTED -> REVIEWING -> SCREENING -> INTERVIEWING -> OFFER -> HIRED
     *
     * Any non-terminal state may branch to REJECTED or WITHDRAWN.
     */
    public const VALID_TRANSITIONS = [
        'SUBMITTED' => [
            'REVIEWING' => true,
            'REJECTED'  => true,
            'WITHDRAWN' => true,
        ],
        'REVIEWING' => [
            'SCREENING' => true,
            'REJECTED'  => true,
            'WITHDRAWN' => true,
        ],
        'SCREENING' => [
            'INTERVIEWING' => true,
            'REJECTED'     => true,
            'WITHDRAWN'    => true,
        ],
        'INTERVIEWING' => [
            'OFFER'     => true,
            'REJECTED'  => true,
            'WITHDRAWN' => true,
        ],
        'OFFER' => [
            'HIRED'     => true,
            'REJECTED'  => true,
            'WITHDRAWN' => true,
        ],
        'HIRED' => [
            'WITHDRAWN' => true,
        ],
        'REJECTED' => [],
        'WITHDRAWN' => [],
    ];

    /**
     * Status aliases to reconcile documentation variants.
     */
    public const STATUS_ALIASES = [
        'NEW'            => 'SUBMITTED',
        'REVIEWED'       => 'REVIEWING',
        'SHORTLISTED'    => 'SCREENING',
        'OFFER_EXTENDED' => 'OFFER',
    ];

    public function __construct(
        Connection $connection,
        MediaStorageService $mediaStorageService,
        AuditLogger $auditLogger,
        HtmlSanitizer $sanitizer,
        ?EmailService $emailService = null,
        ?RateLimiter $rateLimiter = null,
        ?RbacManager $rbacManager = null
    ) {
        $this->connection = $connection;
        $this->mediaStorageService = $mediaStorageService;
        $this->auditLogger = $auditLogger;
        $this->sanitizer = $sanitizer;
        $this->emailService = $emailService;
        $this->rateLimiter = $rateLimiter ?? new RateLimiter();
        $this->rbacManager = $rbacManager;
    }

    // =========================================================================
    // SECTION 1: JOB VACANCY & REQUISITIONS MANAGEMENT (FR-ADM-014 / TEST-ADM-014)
    // =========================================================================

    /**
     * Lists job postings with administrative filtering, pagination, and applicant metrics.
     *
     * @param array<string, mixed> $filters
     * @return array{items: array<int, array<string, mixed>>, pagination: array<string, int>}
     */
    public function listJobs(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $pdo = $this->connection->getPdo();
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $whereClauses = [];
        $params = [];

        if (isset($filters['is_active']) && $filters['is_active'] !== '' && $filters['is_active'] !== 'all') {
            $whereClauses[] = 'jp.is_active = :is_active';
            $params['is_active'] = (int)$filters['is_active'] === 1 ? 1 : 0;
        }

        if (!empty($filters['department'])) {
            $whereClauses[] = 'jp.department = :department';
            $params['department'] = trim((string)$filters['department']);
        }

        if (!empty($filters['employment_type'])) {
            $whereClauses[] = 'jp.employment_type = :employment_type';
            $params['employment_type'] = strtoupper(trim((string)$filters['employment_type']));
        }

        if (!empty($filters['search']) || !empty($filters['q'])) {
            $search = '%' . trim((string)($filters['search'] ?? $filters['q'])) . '%';
            $whereClauses[] = '(jp.title LIKE :s1 OR jp.department LIKE :s2 OR jp.location LIKE :s3 OR jp.summary LIKE :s4)';
            $params['s1'] = $search;
            $params['s2'] = $search;
            $params['s3'] = $search;
            $params['s4'] = $search;
        }

        $whereSql = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

        // Count total matching
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM `job_postings` jp {$whereSql}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        $countStmt->closeCursor();

        // Fetch paginated rows with applicant counts
        $sql = "SELECT jp.*,
                       u.name AS creator_name,
                       (SELECT COUNT(*) FROM `career_applications` ca WHERE ca.job_id = jp.id) AS applicants_count
                FROM `job_postings` jp
                LEFT JOIN `users` u ON u.id = jp.created_by_user_id
                {$whereSql}
                ORDER BY jp.created_at DESC
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

        $items = array_map([$this, 'formatJobRecord'], $rows ?: []);

        return [
            'items'      => $items,
            'pagination' => [
                'total'        => $total,
                'page'         => $page,
                'per_page'     => $perPage,
                'total_pages'  => (int)ceil($total / $perPage),
            ],
        ];
    }

    /**
     * Lists active published jobs for the public careers board.
     *
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function listPublishedJobs(array $filters = []): array
    {
        $pdo = $this->connection->getPdo();
        $whereClauses = ['is_active = 1', '(closing_date IS NULL OR closing_date >= CURRENT_DATE)'];
        $params = [];

        if (!empty($filters['department'])) {
            $whereClauses[] = 'department = :department';
            $params['department'] = trim((string)$filters['department']);
        }

        if (!empty($filters['employment_type'])) {
            $whereClauses[] = 'employment_type = :employment_type';
            $params['employment_type'] = strtoupper(trim((string)$filters['employment_type']));
        }

        $whereSql = 'WHERE ' . implode(' AND ', $whereClauses);
        $sql = "SELECT * FROM `job_postings` {$whereSql} ORDER BY created_at DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return array_map([$this, 'formatJobRecord'], $rows ?: []);
    }

    /**
     * Retrieves single job posting by ID.
     */
    public function getJobById(int $id): ?array
    {
        $pdo = $this->connection->getPdo();
        $stmt = $pdo->prepare(
            "SELECT jp.*,
                    u.name AS creator_name,
                    (SELECT COUNT(*) FROM `career_applications` ca WHERE ca.job_id = jp.id) AS applicants_count
             FROM `job_postings` jp
             LEFT JOIN `users` u ON u.id = jp.created_by_user_id
             WHERE jp.id = :id"
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return $row ? $this->formatJobRecord($row) : null;
    }

    /**
     * Retrieves single active published job by slug.
     */
    public function getPublishedJobBySlug(string $slug): ?array
    {
        $pdo = $this->connection->getPdo();
        $stmt = $pdo->prepare(
            "SELECT * FROM `job_postings`
             WHERE `slug` = :slug
               AND `is_active` = 1
               AND (`closing_date` IS NULL OR `closing_date` >= CURRENT_DATE)"
        );
        $stmt->execute(['slug' => strtolower(trim($slug))]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return $row ? $this->formatJobRecord($row) : null;
    }

    /**
     * Creates a new job vacancy posting (FR-ADM-014).
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createJob(
        array $data,
        int $adminUserId,
        ?string $clientIp = '127.0.0.1',
        ?string $userAgent = 'Admin/Web'
    ): array {
        $validated = $this->validateJobData($data);

        $pdo = $this->connection->getPdo();

        // Enforce unique slug
        $this->ensureSlugAvailable($pdo, $validated['slug']);

        $sql = "INSERT INTO `job_postings` (
                    `title`, `slug`, `department`, `employment_type`, `location`,
                    `summary`, `responsibilities_json`, `requirements_json`,
                    `is_active`, `closing_date`, `created_by_user_id`,
                    `created_at`, `updated_at`
                ) VALUES (
                    :title, :slug, :department, :employment_type, :location,
                    :summary, :responsibilities_json, :requirements_json,
                    :is_active, :closing_date, :created_by_user_id,
                    CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                )";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'title'                 => $validated['title'],
            'slug'                  => $validated['slug'],
            'department'            => $validated['department'],
            'employment_type'       => $validated['employment_type'],
            'location'              => $validated['location'],
            'summary'               => $validated['summary'],
            'responsibilities_json' => json_encode($validated['responsibilities'], JSON_UNESCAPED_SLASHES),
            'requirements_json'     => json_encode($validated['requirements'], JSON_UNESCAPED_SLASHES),
            'is_active'             => $validated['is_active'],
            'closing_date'          => $validated['closing_date'],
            'created_by_user_id'    => $adminUserId,
        ]);
        $newId = (int)$pdo->lastInsertId();
        $stmt->closeCursor();

        // Cryptographic SHA-256 audit log
        $this->auditLogger->log(
            $pdo,
            'RECRUITMENT',
            'JOB_POSTED',
            $adminUserId,
            $clientIp ?? '127.0.0.1',
            $userAgent ?? 'Admin/Web',
            'job_postings',
            $newId,
            [
                'id'              => $newId,
                'title'           => $validated['title'],
                'slug'            => $validated['slug'],
                'department'      => $validated['department'],
                'employment_type' => $validated['employment_type'],
                'is_active'       => $validated['is_active'],
            ]
        );

        $created = $this->getJobById($newId);
        if ($created === null) {
            throw new DatabaseException("Failed to load freshly created job posting.");
        }

        return $created;
    }

    /**
     * Updates an existing job vacancy posting (FR-ADM-014).
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function updateJob(
        int $id,
        array $data,
        int $adminUserId,
        ?string $clientIp = '127.0.0.1',
        ?string $userAgent = 'Admin/Web'
    ): array {
        $existing = $this->getJobById($id);
        if (!$existing) {
            throw new NotFoundException("Job posting [{$id}] not found.");
        }

        $mergedData = array_merge($existing, $data);
        $validated = $this->validateJobData($mergedData, $id);

        $pdo = $this->connection->getPdo();

        // Check slug uniqueness if changed
        if ($validated['slug'] !== $existing['slug']) {
            $this->ensureSlugAvailable($pdo, $validated['slug'], $id);
        }

        $sql = "UPDATE `job_postings`
                SET `title` = :title,
                    `slug` = :slug,
                    `department` = :department,
                    `employment_type` = :employment_type,
                    `location` = :location,
                    `summary` = :summary,
                    `responsibilities_json` = :responsibilities_json,
                    `requirements_json` = :requirements_json,
                    `is_active` = :is_active,
                    `closing_date` = :closing_date,
                    `updated_at` = CURRENT_TIMESTAMP
                WHERE `id` = :id";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'id'                    => $id,
            'title'                 => $validated['title'],
            'slug'                  => $validated['slug'],
            'department'            => $validated['department'],
            'employment_type'       => $validated['employment_type'],
            'location'              => $validated['location'],
            'summary'               => $validated['summary'],
            'responsibilities_json' => json_encode($validated['responsibilities'], JSON_UNESCAPED_SLASHES),
            'requirements_json'     => json_encode($validated['requirements'], JSON_UNESCAPED_SLASHES),
            'is_active'             => $validated['is_active'],
            'closing_date'          => $validated['closing_date'],
        ]);
        $stmt->closeCursor();

        // Cryptographic audit log
        $this->auditLogger->log(
            $pdo,
            'RECRUITMENT',
            'JOB_UPDATED',
            $adminUserId,
            $clientIp ?? '127.0.0.1',
            $userAgent ?? 'Admin/Web',
            'job_postings',
            $id,
            [
                'id'              => $id,
                'title'           => $validated['title'],
                'slug'            => $validated['slug'],
                'department'      => $validated['department'],
                'is_active'       => $validated['is_active'],
                'previous_active' => $existing['is_active'],
            ]
        );

        $updated = $this->getJobById($id);
        if ($updated === null) {
            throw new DatabaseException("Failed to load updated job posting.");
        }

        return $updated;
    }

    /**
     * Deletes a job posting, strictly guarded against active applicant records (TEST-ADM-014).
     *
     * @throws ConflictException If applicant records exist for this job.
     * @throws NotFoundException If job does not exist.
     */
    public function deleteJob(
        int $id,
        int $adminUserId,
        ?string $clientIp = '127.0.0.1',
        ?string $userAgent = 'Admin/Web'
    ): bool {
        $existing = $this->getJobById($id);
        if (!$existing) {
            throw new NotFoundException("Job posting [{$id}] not found.");
        }

        $pdo = $this->connection->getPdo();

        // Referential Integrity Deletion Guard:
        // Must not delete job vacancies that have candidate application records attached.
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM `career_applications` WHERE `job_id` = :job_id");
        $countStmt->execute(['job_id' => $id]);
        $applicantCount = (int)$countStmt->fetchColumn();
        $countStmt->closeCursor();

        if ($applicantCount > 0) {
            throw new ConflictException(
                "Cannot delete job vacancy with active applicant records ({$applicantCount} applications). Please close or archive the posting instead.",
                'ERR_CONFLICT'
            );
        }

        $delStmt = $pdo->prepare("DELETE FROM `job_postings` WHERE `id` = :id");
        $delStmt->execute(['id' => $id]);
        $delStmt->closeCursor();

        // Audit log
        $this->auditLogger->log(
            $pdo,
            'RECRUITMENT',
            'JOB_DELETED',
            $adminUserId,
            $clientIp ?? '127.0.0.1',
            $userAgent ?? 'Admin/Web',
            'job_postings',
            $id,
            [
                'id'         => $id,
                'title'      => $existing['title'],
                'slug'       => $existing['slug'],
                'department' => $existing['department'],
            ]
        );

        return true;
    }

    // =========================================================================
    // SECTION 2: CANDIDATE APPLICATION INTAKE & REVIEW DESK (FR-ADM-015 / TEST-ADM-015)
    // =========================================================================

    /**
     * Ingests a public candidate job application with quarantined resume attachment (FR-ADM-015 / FORM-PUB-04).
     *
     * @param array<string, mixed> $payload
     * @param array<string, mixed>|null $file Raw $_FILES element for resume
     * @return array{status: string, reference_code: string, message: string, id: int, retention_expiry_date: string}
     */
    public function submitApplication(
        array $payload,
        ?array $file,
        ?string $clientIp = '127.0.0.1',
        ?string $userAgent = 'Public/Web'
    ): array {
        // 1. Honeypot check (SYS-005): Drop spam silently without mutating database or queue
        if (!empty($payload['website']) || !empty($payload['fax_number']) || !empty($payload['hp_field']) || !empty($payload['hp_company_field'])) {
            $fakeRef = 'APP-' . gmdate('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
            return [
                'status'                => 'success',
                'reference_code'        => $fakeRef,
                'message'               => "Your application has been received and quarantined securely. Reference code: {$fakeRef}.",
                'id'                    => 0,
                'retention_expiry_date' => date('Y-m-d', strtotime('+6 months')),
            ];
        }

        // 2. IP Rate Limiting: 5 submissions per hour per IP (SYS-005)
        $rateLimitKey = 'career_apply_' . ($clientIp ?? '127.0.0.1');
        $rateLimit = $this->rateLimiter->hit($rateLimitKey, 5, 3600);
        if (!$rateLimit['allowed']) {
            throw new HttpException(
                429,
                'Application limit exceeded. You may only submit 5 career applications per hour.',
                'ERR_RATE_LIMITED',
                ['Retry-After' => (string)$rateLimit['retry_after']]
            );
        }

        // 3. Resolve and validate target job posting
        $jobId = isset($payload['job_id']) ? (int)$payload['job_id'] : null;
        $jobSlug = isset($payload['job_slug']) ? trim((string)$payload['job_slug']) : null;

        $targetJob = null;
        if ($jobId !== null && $jobId > 0) {
            $targetJob = $this->getJobById($jobId);
        } elseif ($jobSlug !== null && $jobSlug !== '') {
            $targetJob = $this->getPublishedJobBySlug($jobSlug);
        }

        if (!$targetJob) {
            throw new ValidationException(
                "The specified job opening is invalid, closed, or does not exist.",
                ['job' => 'Invalid or closed job vacancy specified.'],
                'ERR_VALIDATION'
            );
        }

        if ((int)$targetJob['is_active'] !== 1) {
            throw new ValidationException(
                "This job opening has been closed or is not accepting applications.",
                ['job' => 'Job opening is closed.'],
                'ERR_VALIDATION'
            );
        }

        // 4. Validate applicant fields
        $errors = [];

        $fullName = trim((string)($payload['full_name'] ?? $payload['name'] ?? ''));
        if ($fullName === '' || mb_strlen($fullName) < 2 || mb_strlen($fullName) > 150) {
            $errors['full_name'] = 'Full name is required (2–150 characters).';
        }

        $email = trim((string)($payload['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'A valid email address is required.';
        }

        $phone = trim((string)($payload['phone'] ?? ''));
        if ($phone === '' || mb_strlen($phone) < 8 || mb_strlen($phone) > 25) {
            $errors['phone'] = 'A valid contact phone number is required (8–25 digits).';
        }

        $countryCode = strtoupper(trim((string)($payload['country_code'] ?? 'AU')));
        if (strlen($countryCode) !== 2) {
            $countryCode = 'AU';
        }

        $ahpraNumber = isset($payload['ahpra_number']) && trim((string)$payload['ahpra_number']) !== ''
            ? substr(trim((string)$payload['ahpra_number']), 0, 50)
            : null;

        // 5. Quarantine resume document attachment (FR-SEC-004 / TEST-SEC-004)
        if ($file === null || !is_array($file) || empty($file['tmp_name'])) {
            $errors['resume_file'] = 'A resume document attachment (PDF or DOCX, maximum 10MB) is mandatory.';
        }

        if (!empty($errors)) {
            throw new ValidationException("Application submission validation failed.", $errors, 'ERR_VALIDATION');
        }

        // Execute secure document quarantine via MediaStorageService
        $quarantineResult = $this->mediaStorageService->quarantineDocument($file, 'resume');
        $quarantinedPath = $quarantineResult['quarantined_file_path'];
        $originalFileName = $quarantineResult['original_file_name'];

        // 6. Generate reference code and retention expiry date (+6 months per FR-SEC-004)
        $referenceCode = 'APP-' . gmdate('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $retentionExpiryDate = date('Y-m-d', strtotime('+6 months'));

        $pdo = $this->connection->getPdo();

        $sql = "INSERT INTO `career_applications` (
                    `reference_code`, `job_id`, `full_name`, `email`, `phone`,
                    `country_code`, `ahpra_number`, `quarantined_resume_path`,
                    `original_resume_name`, `status`, `rejection_reason`,
                    `retention_expiry_date`, `created_at`, `updated_at`
                ) VALUES (
                    :reference_code, :job_id, :full_name, :email, :phone,
                    :country_code, :ahpra_number, :quarantined_resume_path,
                    :original_resume_name, 'SUBMITTED', NULL,
                    :retention_expiry_date, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                )";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'reference_code'          => $referenceCode,
            'job_id'                  => (int)$targetJob['id'],
            'full_name'               => $this->sanitizer->sanitizePlainText($fullName),
            'email'                   => strtolower($email),
            'phone'                   => $this->sanitizer->sanitizePlainText($phone),
            'country_code'            => $countryCode,
            'ahpra_number'            => $ahpraNumber !== null ? $this->sanitizer->sanitizePlainText($ahpraNumber) : null,
            'quarantined_resume_path' => $quarantinedPath,
            'original_resume_name'    => $originalFileName,
            'retention_expiry_date'   => $retentionExpiryDate,
        ]);
        $newAppId = (int)$pdo->lastInsertId();
        $stmt->closeCursor();

        // 7. Enqueue confirmation notification email into email_queue (Tier 2 outbox)
        if ($this->emailService !== null) {
            try {
                $this->emailService->enqueue(
                    'tmpl_careers_ack',
                    $email,
                    $fullName,
                    [
                        'first_name'        => explode(' ', $fullName)[0] ?? $fullName,
                        'recipient_name'    => $fullName,
                        'contact_name'      => $fullName,
                        'job_title'         => (string)$targetJob['title'],
                        'department'        => (string)$targetJob['department'],
                        'ref_no'            => $referenceCode,
                        'legal_entity_name' => 'VOAK Health Care Australia',
                        'submission_date'   => date('d M Y'),
                    ]
                );
            } catch (\Throwable $e) {
                // Outbox failure should not crash application intake
            }
        }

        // 8. Cryptographic audit log
        $this->auditLogger->log(
            $pdo,
            'RECRUITMENT',
            'CANDIDATE_APPLICATION_SUBMITTED',
            null,
            $clientIp ?? '127.0.0.1',
            $userAgent ?? 'Public/Web',
            'career_applications',
            $newAppId,
            [
                'id'             => $newAppId,
                'reference_code' => $referenceCode,
                'job_id'         => (int)$targetJob['id'],
                'job_title'      => (string)$targetJob['title'],
                'full_name'      => $fullName,
                'email'          => $email,
            ]
        );

        return [
            'status'                => 'success',
            'reference_code'        => $referenceCode,
            'message'               => "Your application for [{$targetJob['title']}] has been received and quarantined securely. Reference code: {$referenceCode}.",
            'id'                    => $newAppId,
            'retention_expiry_date' => $retentionExpiryDate,
        ];
    }

    /**
     * Lists candidate applications for administrative review desk (FR-ADM-015).
     *
     * @param array<string, mixed> $filters
     * @return array{items: array<int, array<string, mixed>>, pagination: array<string, int>}
     */
    public function listApplications(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $pdo = $this->connection->getPdo();
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $whereClauses = [];
        $params = [];

        if (!empty($filters['job_id'])) {
            $whereClauses[] = 'ca.job_id = :job_id';
            $params['job_id'] = (int)$filters['job_id'];
        }

        if (!empty($filters['status'])) {
            $status = $this->normalizeStatus(trim((string)$filters['status']));
            $whereClauses[] = 'ca.status = :status';
            $params['status'] = $status;
        }

        if (!empty($filters['search']) || !empty($filters['q'])) {
            $search = '%' . trim((string)($filters['search'] ?? $filters['q'])) . '%';
            $whereClauses[] = '(ca.full_name LIKE :s1 OR ca.email LIKE :s2 OR ca.phone LIKE :s3 OR ca.reference_code LIKE :s4)';
            $params['s1'] = $search;
            $params['s2'] = $search;
            $params['s3'] = $search;
            $params['s4'] = $search;
        }

        if (!empty($filters['retention_expired'])) {
            $whereClauses[] = 'ca.retention_expiry_date <= CURRENT_DATE';
        }

        $whereSql = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

        // Total count
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM `career_applications` ca {$whereSql}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        $countStmt->closeCursor();

        // Paginated rows
        $sql = "SELECT ca.*,
                       jp.title AS job_title,
                       jp.department AS job_department,
                       jp.location AS job_location
                FROM `career_applications` ca
                LEFT JOIN `job_postings` jp ON jp.id = ca.job_id
                {$whereSql}
                ORDER BY ca.created_at DESC
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

        $items = array_map([$this, 'formatApplicationRecord'], $rows ?: []);

        return [
            'items'      => $items,
            'pagination' => [
                'total'        => $total,
                'page'         => $page,
                'per_page'     => $perPage,
                'total_pages'  => (int)ceil($total / $perPage),
            ],
        ];
    }

    /**
     * Retrieves single candidate application by ID.
     */
    public function getApplicationById(int $id): ?array
    {
        $pdo = $this->connection->getPdo();
        $stmt = $pdo->prepare(
            "SELECT ca.*,
                    jp.title AS job_title,
                    jp.department AS job_department,
                    jp.location AS job_location
             FROM `career_applications` ca
             LEFT JOIN `job_postings` jp ON jp.id = ca.job_id
             WHERE ca.id = :id"
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return $row ? $this->formatApplicationRecord($row) : null;
    }

    /**
     * Advances candidate recruitment workflow stage enforcing strict FSM rules (FR-WF-004 / TEST-WF-004).
     *
     * @param int $id
     * @param string $targetStatus
     * @param string|null $feedback Recruiter notes or interview feedback
     * @param string|null $rejectionReason Mandatory if transitioning to REJECTED
     * @param int $adminUserId
     * @param string|null $clientIp
     * @param string|null $userAgent
     * @return array<string, mixed>
     */
    public function advanceCandidateStage(
        int $id,
        string $targetStatus,
        ?string $feedback = null,
        ?string $rejectionReason = null,
        int $adminUserId = 1,
        ?string $clientIp = '127.0.0.1',
        ?string $userAgent = 'Admin/Web'
    ): array {
        $application = $this->getApplicationById($id);
        if (!$application) {
            throw new NotFoundException("Candidate application [{$id}] not found.");
        }

        $currentStatus = (string)$application['status'];
        $normalizedTarget = $this->normalizeStatus($targetStatus);

        // 1. Terminal state check
        if (in_array($currentStatus, ['HIRED', 'REJECTED', 'WITHDRAWN'], true) && $currentStatus === $normalizedTarget) {
            return $application;
        }

        // 2. Validate FSM transition
        $allowedTransitions = self::VALID_TRANSITIONS[$currentStatus] ?? [];
        if (!isset($allowedTransitions[$normalizedTarget])) {
            throw new ValidationException(
                "Invalid candidate recruitment state transition from [{$currentStatus}] to [{$normalizedTarget}]. Sequence must progress: SUBMITTED -> REVIEWING -> SCREENING -> INTERVIEWING -> OFFER -> HIRED.",
                ['status' => "Transition from {$currentStatus} to {$normalizedTarget} is not permitted."],
                'ERR_INVALID_TRANSITION'
            );
        }

        // 3. Mandatory rejection reason validation (TEST-WF-004)
        if ($normalizedTarget === 'REJECTED') {
            $reason = trim((string)$rejectionReason);
            if ($reason === '') {
                throw new ValidationException(
                    "A mandatory rejection reason code or explanation is required when moving a candidate to REJECTED.",
                    ['rejection_reason' => 'Rejection reason is mandatory.'],
                    'ERR_VALIDATION'
                );
            }
        } else {
            $reason = null;
        }

        $pdo = $this->connection->getPdo();

        $sql = "UPDATE `career_applications`
                SET `status` = :status,
                    `rejection_reason` = :rejection_reason,
                    `updated_at` = CURRENT_TIMESTAMP
                WHERE `id` = :id";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'id'               => $id,
            'status'           => $normalizedTarget,
            'rejection_reason' => $reason !== null ? substr($reason, 0, 100) : null,
        ]);
        $stmt->closeCursor();

        // 4. Cryptographic audit log entry
        $this->auditLogger->log(
            $pdo,
            'RECRUITMENT',
            'CANDIDATE_STATUS_UPDATED',
            $adminUserId,
            $clientIp ?? '127.0.0.1',
            $userAgent ?? 'Admin/Web',
            'career_applications',
            $id,
            [
                'id'               => $id,
                'reference_code'   => $application['reference_code'],
                'previous_status'  => $currentStatus,
                'new_status'       => $normalizedTarget,
                'feedback'         => $feedback !== null ? $this->sanitizer->sanitizePlainText($feedback) : null,
                'rejection_reason' => $reason,
            ]
        );

        $updated = $this->getApplicationById($id);
        if ($updated === null) {
            throw new DatabaseException("Failed to reload updated candidate application.");
        }

        return $updated;
    }

    /**
     * Streams candidate resume from quarantined enclave with security headers (FR-ADM-015 / TEST-SEC-004).
     */
    public function streamCandidateResume(
        int $id,
        int $adminUserId,
        ?string $clientIp = '127.0.0.1'
    ): Response {
        $application = $this->getApplicationById($id);
        if (!$application) {
            throw new NotFoundException("Candidate application [{$id}] not found.");
        }

        $filePath = (string)$application['quarantined_resume_path'];
        $fileName = (string)$application['original_resume_name'];

        return $this->mediaStorageService->streamQuarantinedFile(
            $filePath,
            $fileName,
            'application/pdf',
            $adminUserId,
            'RESUME_DOWNLOADED',
            'RECRUITMENT',
            $id,
            $clientIp
        );
    }

    /**
     * Exports candidate applications roster to RFC 4180 CSV with formula injection defense.
     *
     * @param array<string, mixed> $filters
     */
    public function exportApplicationsCsv(
        array $filters = [],
        int $adminUserId = 1,
        ?string $clientIp = '127.0.0.1',
        ?string $userAgent = 'Admin/Web'
    ): string {
        $list = $this->listApplications($filters, 1, 10000);
        $items = $list['items'];

        $fp = fopen('php://temp', 'r+');
        if ($fp === false) {
            throw new DatabaseException("Failed to open temporary stream for CSV export.");
        }

        // Emit UTF-8 BOM
        fputs($fp, "\xEF\xBB\xBF");

        // Headers
        fputcsv($fp, [
            'Reference Code',
            'Job Title',
            'Department',
            'Candidate Name',
            'Email',
            'Phone',
            'Country',
            'AHPRA Registration',
            'Recruitment Stage',
            'Rejection Reason',
            'Retention Expiry',
            'Submission Date',
        ]);

        foreach ($items as $item) {
            fputcsv($fp, [
                $this->sanitizeCsvField((string)($item['reference_code'] ?? '')),
                $this->sanitizeCsvField((string)($item['job_title'] ?? '')),
                $this->sanitizeCsvField((string)($item['job_department'] ?? '')),
                $this->sanitizeCsvField((string)($item['full_name'] ?? '')),
                $this->sanitizeCsvField((string)($item['email'] ?? '')),
                $this->sanitizeCsvField((string)($item['phone'] ?? '')),
                $this->sanitizeCsvField((string)($item['country_code'] ?? '')),
                $this->sanitizeCsvField((string)($item['ahpra_number'] ?? 'N/A')),
                (string)($item['status'] ?? ''),
                $this->sanitizeCsvField((string)($item['rejection_reason'] ?? '')),
                (string)($item['retention_expiry_date'] ?? ''),
                (string)($item['created_at'] ?? ''),
            ]);
        }

        rewind($fp);
        $csv = (string)stream_get_contents($fp);
        fclose($fp);

        // Audit log
        $pdo = $this->connection->getPdo();
        $this->auditLogger->log(
            $pdo,
            'RECRUITMENT',
            'APPLICATIONS_EXPORTED',
            $adminUserId,
            $clientIp ?? '127.0.0.1',
            $userAgent ?? 'Admin/Web',
            'career_applications',
            null,
            ['count' => count($items), 'filters' => $filters]
        );

        return $csv;
    }

    /**
     * GDPR & Australian Privacy Principles Retention Erasure (FR-SEC-004).
     *
     * Finds candidate applications past retention expiry, shreds physical resume file,
     * overwrites PII with [ANONYMIZED], and writes RECRUITMENT_RECORD_ANONYMIZED audit record.
     *
     * @return array{anonymized_count: int, shredded_files_count: int}
     */
    public function cleanupExpiredApplications(?int $adminUserId = null): array
    {
        $pdo = $this->connection->getPdo();

        $stmt = $pdo->prepare(
            "SELECT id, reference_code, quarantined_resume_path
             FROM `career_applications`
             WHERE `retention_expiry_date` <= CURRENT_DATE
               AND `full_name` != '[ANONYMIZED]'"
        );
        $stmt->execute();
        $expiredRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        $anonymizedCount = 0;
        $shreddedFilesCount = 0;

        foreach ($expiredRows as $row) {
            $appId = (int)$row['id'];
            $filePath = (string)$row['quarantined_resume_path'];

            // 1. Shred and unlink physical file if it exists
            if (file_exists($filePath) && is_file($filePath)) {
                $fileSize = filesize($filePath);
                if ($fileSize !== false && $fileSize > 0) {
                    // Overwrite before unlinking
                    @file_put_contents($filePath, str_repeat("\0", min((int)$fileSize, 65536)));
                }
                if (@unlink($filePath)) {
                    $shreddedFilesCount++;
                }
            }

            // 2. Anonymize database record
            $upStmt = $pdo->prepare(
                "UPDATE `career_applications`
                 SET `full_name` = '[ANONYMIZED]',
                     `email` = '[ANONYMIZED]',
                     `phone` = '[ANONYMIZED]',
                     `ahpra_number` = NULL,
                     `updated_at` = CURRENT_TIMESTAMP
                 WHERE `id` = :id"
            );
            $upStmt->execute(['id' => $appId]);
            $upStmt->closeCursor();

            $anonymizedCount++;

            // 3. Log audit event
            $this->auditLogger->log(
                $pdo,
                'RECRUITMENT',
                'RECRUITMENT_RECORD_ANONYMIZED',
                $adminUserId,
                '127.0.0.1',
                'CLI/RetentionTask',
                'career_applications',
                $appId,
                [
                    'id'             => $appId,
                    'reference_code' => (string)$row['reference_code'],
                    'status'         => 'ANONYMIZED',
                ]
            );
        }

        return [
            'anonymized_count'     => $anonymizedCount,
            'shredded_files_count' => $shreddedFilesCount,
        ];
    }

    // =========================================================================
    // SECTION 3: HELPERS & VALIDATION UTILITIES
    // =========================================================================

    /**
     * Validates job requisition fields.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function validateJobData(array $data, ?int $existingId = null): array
    {
        $errors = [];

        $title = trim((string)($data['title'] ?? ''));
        if ($title === '' || mb_strlen($title) < 3 || mb_strlen($title) > 150) {
            $errors['title'] = 'Job title is required (3–150 characters).';
        }

        $department = trim((string)($data['department'] ?? ''));
        if ($department === '' || mb_strlen($department) < 2 || mb_strlen($department) > 100) {
            $errors['department'] = 'Department is required (2–100 characters).';
        }

        $rawType = strtoupper(trim((string)($data['employment_type'] ?? 'FULL_TIME')));
        if (!in_array($rawType, self::EMPLOYMENT_TYPES, true)) {
            $errors['employment_type'] = 'Invalid employment type. Permitted: ' . implode(', ', self::EMPLOYMENT_TYPES) . '.';
        }

        $location = trim((string)($data['location'] ?? ''));
        if ($location === '' || mb_strlen($location) < 2 || mb_strlen($location) > 100) {
            $errors['location'] = 'Location is required (2–100 characters).';
        }

        $summary = trim((string)($data['summary'] ?? ''));
        if ($summary === '' || mb_strlen($summary) < 10) {
            $errors['summary'] = 'Job summary must be at least 10 characters long.';
        }

        // Responsibilities list
        $responsibilities = $data['responsibilities'] ?? $data['responsibilities_json'] ?? [];
        if (is_string($responsibilities)) {
            $decoded = json_decode($responsibilities, true);
            $responsibilities = is_array($decoded) ? $decoded : [$responsibilities];
        }
        if (!is_array($responsibilities) || empty($responsibilities)) {
            $responsibilities = ['General clinical duties and patient care support.'];
        }

        // Requirements list
        $requirements = $data['requirements'] ?? $data['requirements_json'] ?? [];
        if (is_string($requirements)) {
            $decoded = json_decode($requirements, true);
            $requirements = is_array($decoded) ? $decoded : [$requirements];
        }
        if (!is_array($requirements) || empty($requirements)) {
            $requirements = ['Relevant professional registration and clinical qualifications.'];
        }

        // Generate or validate slug
        $rawSlug = isset($data['slug']) && trim((string)$data['slug']) !== ''
            ? strtolower(trim((string)$data['slug']))
            : $this->generateSlug($title);

        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $rawSlug) || strlen($rawSlug) > 100) {
            $errors['slug'] = 'Slug must be lowercase alphanumeric with single hyphens, max 100 chars.';
        }

        // Status or is_active
        $isActive = 1;
        if (isset($data['is_active'])) {
            $isActive = (int)$data['is_active'] === 1 ? 1 : 0;
        } elseif (isset($data['status'])) {
            $st = strtoupper(trim((string)$data['status']));
            $isActive = in_array($st, ['PUBLISHED', 'ACTIVE', 'OPEN'], true) ? 1 : 0;
        }

        // Closing date
        $closingDate = null;
        if (!empty($data['closing_date'])) {
            $cd = trim((string)$data['closing_date']);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $cd)) {
                $closingDate = $cd;
            } else {
                $errors['closing_date'] = 'Closing date must follow YYYY-MM-DD format.';
            }
        }

        if (!empty($errors)) {
            throw new ValidationException("Job vacancy validation failed.", $errors, 'ERR_VALIDATION');
        }

        return [
            'title'            => $this->sanitizer->sanitizePlainText($title),
            'slug'             => $rawSlug,
            'department'       => $this->sanitizer->sanitizePlainText($department),
            'employment_type'  => $rawType,
            'location'         => $this->sanitizer->sanitizePlainText($location),
            'summary'          => $this->sanitizer->sanitizePlainText($summary),
            'responsibilities' => array_values(array_map([$this->sanitizer, 'sanitizePlainText'], array_filter($responsibilities))),
            'requirements'     => array_values(array_map([$this->sanitizer, 'sanitizePlainText'], array_filter($requirements))),
            'is_active'        => $isActive,
            'closing_date'     => $closingDate,
        ];
    }

    /**
     * Ensures slug is unique in job_postings.
     */
    private function ensureSlugAvailable(\PDO $pdo, string $slug, ?int $excludeId = null): void
    {
        $sql = "SELECT COUNT(*) FROM `job_postings` WHERE `slug` = :slug";
        $params = ['slug' => $slug];
        if ($excludeId !== null) {
            $sql .= " AND `id` != :excludeId";
            $params['excludeId'] = $excludeId;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $exists = (int)$stmt->fetchColumn() > 0;
        $stmt->closeCursor();

        if ($exists) {
            throw new ValidationException(
                "A job opening with slug [{$slug}] already exists.",
                ['slug' => "Slug [{$slug}] is already in use."],
                'ERR_VALIDATION'
            );
        }
    }

    /**
     * Generates canonical URL slug from title string.
     */
    private function generateSlug(string $title): string
    {
        $slug = strtolower(trim($title));
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug) ?? '';
        $slug = preg_replace('/[\s-]+/', '-', $slug) ?? '';
        return trim($slug, '-');
    }

    /**
     * Normalizes recruitment stage name to canonical database ENUM value.
     */
    private function normalizeStatus(string $status): string
    {
        $upper = strtoupper(trim($status));
        return self::STATUS_ALIASES[$upper] ?? $upper;
    }

    /**
     * Neutralizes spreadsheet formula injection characters (=, +, -, @, \t, \r).
     */
    private function sanitizeCsvField(string $val): string
    {
        if ($val === '') {
            return '';
        }
        $firstChar = $val[0];
        if (in_array($firstChar, ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $val;
        }
        return $val;
    }

    /**
     * Formats job row with decoded JSON fields and status badge.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function formatJobRecord(array $row): array
    {
        $resp = json_decode((string)($row['responsibilities_json'] ?? '[]'), true);
        $req = json_decode((string)($row['requirements_json'] ?? '[]'), true);

        return [
            'id'               => (int)$row['id'],
            'title'            => (string)$row['title'],
            'slug'             => (string)$row['slug'],
            'department'       => (string)$row['department'],
            'employment_type'  => (string)$row['employment_type'],
            'location'         => (string)$row['location'],
            'summary'          => (string)$row['summary'],
            'responsibilities' => is_array($resp) ? $resp : [],
            'requirements'     => is_array($req) ? $req : [],
            'is_active'        => (int)$row['is_active'],
            'status'           => (int)$row['is_active'] === 1 ? 'PUBLISHED' : 'CLOSED',
            'closing_date'     => $row['closing_date'] ?? null,
            'created_by_user_id' => isset($row['created_by_user_id']) ? (int)$row['created_by_user_id'] : null,
            'creator_name'     => $row['creator_name'] ?? null,
            'applicants_count' => isset($row['applicants_count']) ? (int)$row['applicants_count'] : 0,
            'created_at'       => (string)$row['created_at'],
            'updated_at'       => (string)$row['updated_at'],
        ];
    }

    /**
     * Formats application row with job context.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function formatApplicationRecord(array $row): array
    {
        return [
            'id'                      => (int)$row['id'],
            'reference_code'          => (string)$row['reference_code'],
            'job_id'                  => (int)$row['job_id'],
            'job_title'               => $row['job_title'] ?? null,
            'job_department'          => $row['job_department'] ?? null,
            'job_location'            => $row['job_location'] ?? null,
            'full_name'               => (string)$row['full_name'],
            'email'                   => (string)$row['email'],
            'phone'                   => (string)$row['phone'],
            'country_code'            => (string)$row['country_code'],
            'ahpra_number'            => $row['ahpra_number'] ?? null,
            'original_resume_name'    => (string)$row['original_resume_name'],
            'quarantined_resume_path' => (string)$row['quarantined_resume_path'],
            'status'                  => (string)$row['status'],
            'rejection_reason'        => $row['rejection_reason'] ?? null,
            'retention_expiry_date'   => (string)$row['retention_expiry_date'],
            'created_at'              => (string)$row['created_at'],
            'updated_at'              => (string)$row['updated_at'],
        ];
    }
}
