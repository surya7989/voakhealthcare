<?php

declare(strict_types=1);

namespace Voak\Health\Services;

use Voak\Health\Auth\RbacManager;
use Voak\Health\Core\Exceptions\BadRequestException;
use Voak\Health\Core\Exceptions\DatabaseException;
use Voak\Health\Core\Exceptions\ForbiddenException;
use Voak\Health\Core\Exceptions\HttpException;
use Voak\Health\Core\Exceptions\NotFoundException;
use Voak\Health\Core\Exceptions\ValidationException;
use Voak\Health\Core\Http\Response;
use Voak\Health\Database\Connection;
use Voak\Health\Security\AuditLogger;
use Voak\Health\Security\HtmlSanitizer;
use Voak\Health\Security\RateLimiter;

/**
 * Enterprise Request a Proposal (RFP) Desk & Tender Quarantine Service.
 * Implements:
 * - ADM-012 / FR-ADM-012 / TEST-ADM-012 (Enterprise Proposal Review Desk)
 * - FR-WF-003 / TEST-WF-003 (RFP Lifecycle Directed Acyclic Graph State Machine)
 * - FR-PUB-021 / TEST-PUB-021 (Multi-Step RFP Wizard & Tender Attachment)
 * - FR-SEC-003 / TEST-SEC-003 (MIME Magic Byte Inspection & Quarantine Storage)
 * - FR-SEC-004 / TEST-SEC-004 (Authenticated Binary Streaming Proxy)
 * - ADR-007 / ADR-008 (Dual-Enclave Isolation & Audit Ledger Integrity)
 */
class ProposalService
{
    // Canonical MySQL 8.0 Enum Statuses (Migration 18)
    public const STATUS_SUBMITTED = 'SUBMITTED';
    public const STATUS_UNDER_REVIEW = 'UNDER_REVIEW';
    public const STATUS_SCOPING = 'SCOPING';
    public const STATUS_PROPOSAL_SENT = 'PROPOSAL_SENT';
    public const STATUS_ACCEPTED = 'ACCEPTED';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_EXPIRED = 'EXPIRED';

    public const ALL_STATUSES = [
        self::STATUS_SUBMITTED,
        self::STATUS_UNDER_REVIEW,
        self::STATUS_SCOPING,
        self::STATUS_PROPOSAL_SENT,
        self::STATUS_ACCEPTED,
        self::STATUS_REJECTED,
        self::STATUS_EXPIRED,
    ];

    /**
     * Canonical DAG state transition rules (FR-WF-003, TEST-WF-003).
     */
    public const VALID_TRANSITIONS = [
        self::STATUS_SUBMITTED     => [self::STATUS_UNDER_REVIEW, self::STATUS_REJECTED],
        self::STATUS_UNDER_REVIEW  => [self::STATUS_SCOPING, self::STATUS_REJECTED],
        self::STATUS_SCOPING       => [self::STATUS_PROPOSAL_SENT, self::STATUS_REJECTED],
        self::STATUS_PROPOSAL_SENT => [self::STATUS_ACCEPTED, self::STATUS_REJECTED, self::STATUS_EXPIRED],
        self::STATUS_ACCEPTED      => [],
        self::STATUS_REJECTED      => [],
        self::STATUS_EXPIRED       => [],
    ];

    /**
     * Aliases mapping specification stage names to canonical database statuses.
     */
    public const STATUS_ALIASES = [
        'NEW'                 => self::STATUS_SUBMITTED,
        'DRAFTING_PROPOSAL'   => self::STATUS_SCOPING,
        'CONTRACT_EXECUTED'   => self::STATUS_ACCEPTED,
        'DECLINED'            => self::STATUS_REJECTED,
    ];

    private Connection $connection;
    private MediaStorageService $mediaStorageService;
    private AuditLogger $auditLogger;
    private HtmlSanitizer $sanitizer;
    private RateLimiter $rateLimiter;
    private RbacManager $rbacManager;

    public function __construct(
        Connection $connection,
        MediaStorageService $mediaStorageService,
        AuditLogger $auditLogger,
        HtmlSanitizer $sanitizer,
        ?RateLimiter $rateLimiter = null,
        ?RbacManager $rbacManager = null
    ) {
        $this->connection = $connection;
        $this->mediaStorageService = $mediaStorageService;
        $this->auditLogger = $auditLogger;
        $this->sanitizer = $sanitizer;
        $this->rateLimiter = $rateLimiter ?? new RateLimiter();
        $this->rbacManager = $rbacManager ?? new RbacManager();
    }

    /**
     * Normalizes a given status string against canonical database enum values.
     */
    public static function normalizeStatus(string $status): string
    {
        $upper = strtoupper(trim($status));
        if (isset(self::STATUS_ALIASES[$upper])) {
            return self::STATUS_ALIASES[$upper];
        }
        return $upper;
    }

    /**
     * Submits a multi-step Request a Proposal (RFP) wizard entry with quarantined file attachment (FR-PUB-021).
     *
     * @param array<string, mixed> $payload
     * @param array<string, mixed>|null $file Raw $_FILES array entry or null
     * @return array{status: string, reference_code: string, message: string, id?: int}
     */
    public function submitProposal(
        array $payload,
        ?array $file = null,
        string $ipAddress = '127.0.0.1',
        string $userAgent = 'Public/Web'
    ): array {
        // 1. Honeypot check (SYS-005): Silently discard bot submissions
        if (!empty($payload['hp_company_field']) || !empty($payload['honeypot']) || !empty($payload['website'])) {
            $fakeRef = 'RFP-' . gmdate('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
            return [
                'status'         => 'success',
                'reference_code' => $fakeRef,
                'message'        => "Thank you for submitting an RFP with VOAK Health Care. Reference code: {$fakeRef}.",
            ];
        }

        // 2. IP Rate Limiting (SYS-005): Maximum 5 submissions per hour per IP
        $rateLimitKey = 'rfp_submit_' . $ipAddress;
        $rateLimit = $this->rateLimiter->hit($rateLimitKey, 5, 3600);
        if (!$rateLimit['allowed']) {
            throw new HttpException(
                429,
                'Submission limit exceeded. You may only submit 5 RFP proposals per hour.',
                'ERR_RATE_LIMITED',
                ['Retry-After' => (string)$rateLimit['retry_after']]
            );
        }

        // 3. Validation
        $errors = [];

        $orgName = trim((string)($payload['organization_name'] ?? ''));
        if ($orgName === '' || mb_strlen($orgName) < 2 || mb_strlen($orgName) > 150) {
            $errors['organization_name'] = 'Organization name is required (2–150 characters).';
        }

        $contactName = trim((string)($payload['contact_name'] ?? ''));
        if ($contactName === '' || mb_strlen($contactName) < 2 || mb_strlen($contactName) > 150) {
            $errors['contact_name'] = 'Contact name is required (2–150 characters).';
        }

        $contactEmail = trim((string)($payload['contact_email'] ?? $payload['email'] ?? ''));
        if ($contactEmail === '' || !filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
            $errors['contact_email'] = 'A valid contact email address is required.';
        }

        $contactPhone = trim((string)($payload['contact_phone'] ?? $payload['phone'] ?? ''));
        if ($contactPhone === '' || !preg_match('/^\+?[0-9\s\-\(\)\.]{7,25}$/', $contactPhone)) {
            $errors['contact_phone'] = 'A valid contact phone number is required (7–25 digits, optional E.164 + prefix).';
        }

        $facilityType = trim((string)($payload['facility_type'] ?? ''));
        if ($facilityType === '' || mb_strlen($facilityType) > 100) {
            $errors['facility_type'] = 'Facility type profile is required (max 100 characters).';
        }

        $timeline = trim((string)($payload['timeline'] ?? ''));
        if ($timeline === '' || mb_strlen($timeline) > 50) {
            $errors['timeline'] = 'Delivery timeline is required (max 50 characters).';
        }

        $budgetBand = trim((string)($payload['budget_band'] ?? ''));
        if ($budgetBand === '' || mb_strlen($budgetBand) > 50) {
            $errors['budget_band'] = 'Estimated budget band is required (max 50 characters).';
        }

        if (!empty($errors)) {
            throw new ValidationException($errors, 'RFP submission validation failed.', 'ERR_VALIDATION');
        }

        // 4. File Quarantine Processing (FR-SEC-003, TEST-SEC-003)
        $quarantinedPath = '';
        $originalFileName = '';
        $fileSizeBytes = 0;

        if ($file !== null && !empty($file['tmp_name']) && is_uploaded_file($file['tmp_name']) || ($file !== null && !empty($file['tmp_name']) && file_exists($file['tmp_name']))) {
            $quarantineResult = $this->mediaStorageService->quarantineDocument($file, 'tender');
            $quarantinedPath  = $quarantineResult['quarantined_file_path'];
            $originalFileName = $quarantineResult['original_file_name'];
            $fileSizeBytes    = $quarantineResult['file_size_bytes'];
        }

        // 5. Sanitization
        $safeOrgName      = $this->sanitizer->sanitizePlainText($orgName);
        $safeContactName  = $this->sanitizer->sanitizePlainText($contactName);
        $safeEmail        = strtolower(trim($contactEmail));
        $safePhone        = $this->sanitizer->sanitizePlainText($contactPhone);
        $safeFacilityType = $this->sanitizer->sanitizePlainText($facilityType);
        $safeTimeline     = $this->sanitizer->sanitizePlainText($timeline);
        $safeBudgetBand   = $this->sanitizer->sanitizePlainText($budgetBand);

        $pdo = $this->connection->getPdo();
        $referenceCode = $this->generateReferenceCode($pdo);

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                "INSERT INTO `proposals` (
                    `reference_code`, `organization_name`, `contact_name`, `contact_email`,
                    `contact_phone`, `facility_type`, `timeline`, `budget_band`,
                    `quarantined_file_path`, `original_file_name`, `file_size_bytes`,
                    `status`, `created_at`, `updated_at`
                ) VALUES (
                    :ref, :org, :contact, :email,
                    :phone, :facility, :timeline, :budget,
                    :qpath, :orig_name, :size,
                    'SUBMITTED', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                )"
            );

            $stmt->execute([
                'ref'       => $referenceCode,
                'org'       => $safeOrgName,
                'contact'   => $safeContactName,
                'email'     => $safeEmail,
                'phone'     => $safePhone,
                'facility'  => $safeFacilityType,
                'timeline'  => $safeTimeline,
                'budget'    => $safeBudgetBand,
                'qpath'     => $quarantinedPath,
                'orig_name' => $originalFileName,
                'size'      => $fileSizeBytes,
            ]);
            $stmt->closeCursor();

            $proposalId = (int)$pdo->lastInsertId();

            // Link selected services to proposal_services junction table
            $serviceIds = $payload['service_ids'] ?? [];
            if (is_string($serviceIds)) {
                $serviceIds = array_filter(array_map('intval', explode(',', $serviceIds)));
            } elseif (is_array($serviceIds)) {
                $serviceIds = array_filter(array_map('intval', $serviceIds));
            }

            if (!empty($serviceIds)) {
                $this->linkProposalServices($pdo, $proposalId, $serviceIds);
            }

            // Stage auto-acknowledgement email
            $this->stageAcknowledgementEmail($pdo, $proposalId, $safeEmail, $safeContactName, $safeOrgName, $referenceCode);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e instanceof HttpException) {
                throw $e;
            }
            throw new DatabaseException("Failed to persist proposal: " . $e->getMessage(), [], $e);
        }

        // Cryptographic audit log (RFP_SUBMITTED) - outside transaction with compensating rollback
        try {
            $this->auditLogger->log(
                $pdo,
                'PROPOSAL',
                'RFP_SUBMITTED',
                null,
                $ipAddress,
                $userAgent,
                'proposals',
                $proposalId,
                [
                    'reference_code'     => $referenceCode,
                    'organization_name'  => $safeOrgName,
                    'facility_type'      => $safeFacilityType,
                    'has_tender_file'    => $quarantinedPath !== '',
                    'file_size_bytes'    => $fileSizeBytes,
                    'service_ids'        => array_values($serviceIds),
                ]
            );
        } catch (\Throwable $e) {
            if ($quarantinedPath !== '' && file_exists($quarantinedPath)) {
                @unlink($quarantinedPath);
            }
            $delJunc = $pdo->prepare("DELETE FROM `proposal_services` WHERE `proposal_id` = :id");
            $delJunc->execute(['id' => $proposalId]);
            $delJunc->closeCursor();

            $delStmt = $pdo->prepare("DELETE FROM `proposals` WHERE `id` = :id");
            $delStmt->execute(['id' => $proposalId]);
            $delStmt->closeCursor();

            throw new DatabaseException("Compensating rollback triggered: Audit logging failed for proposal: " . $e->getMessage(), [], $e);
        }

        return [
            'status'         => 'success',
            'reference_code' => $referenceCode,
            'id'             => $proposalId,
            'message'        => "Your RFP proposal has been submitted successfully. Reference code: {$referenceCode}.",
        ];
    }

    /**
     * Lists proposals with filtering, pagination, and mapped service IDs.
     *
     * @param array<string, mixed> $filters
     * @return array{items: array<int, array<string, mixed>>, pagination: array<string, int>}
     */
    public function listProposals(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $pdo = $this->connection->getPdo();
        $where = [];
        $params = [];

        if (!empty($filters['status'])) {
            $norm = self::normalizeStatus((string)$filters['status']);
            $where[] = "p.`status` = :status";
            $params['status'] = $norm;
        }

        if (!empty($filters['facility_type'])) {
            $where[] = "p.`facility_type` = :facility_type";
            $params['facility_type'] = (string)$filters['facility_type'];
        }

        if (!empty($filters['assigned_lead_id'])) {
            $where[] = "p.`assigned_lead_id` = :assigned_lead_id";
            $params['assigned_lead_id'] = (int)$filters['assigned_lead_id'];
        }

        if (!empty($filters['search'])) {
            $searchTerm = '%' . trim((string)$filters['search']) . '%';
            $where[] = "(p.`organization_name` LIKE :s1 OR p.`contact_name` LIKE :s2 OR p.`contact_email` LIKE :s3 OR p.`reference_code` LIKE :s4)";
            $params['s1'] = $searchTerm;
            $params['s2'] = $searchTerm;
            $params['s3'] = $searchTerm;
            $params['s4'] = $searchTerm;
        }

        if (!empty($filters['date_from'])) {
            $where[] = "p.`created_at` >= :date_from";
            $params['date_from'] = (string)$filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = "p.`created_at` <= :date_to";
            $params['date_to'] = (string)$filters['date_to'] . ' 23:59:59';
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // Count total
        $countSql = "SELECT COUNT(*) FROM `proposals` p {$whereClause}";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        $countStmt->closeCursor();

        // Fetch page
        $sql = "SELECT p.*, u.name AS assigned_lead_name, u.email AS assigned_lead_email
                FROM `proposals` p
                LEFT JOIN `users` u ON p.`assigned_lead_id` = u.`id`
                {$whereClause}
                ORDER BY p.`created_at` DESC, p.`id` DESC
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

        $items = [];
        foreach ($rows as $row) {
            $proposalId = (int)$row['id'];
            $services = $this->getProposalServices($pdo, $proposalId);
            $items[] = $this->formatProposalRow($row, $services);
        }

        $totalPages = $total > 0 ? (int)ceil($total / $perPage) : 1;

        return [
            'items'      => $items,
            'pagination' => [
                'total'        => $total,
                'page'         => $page,
                'per_page'     => $perPage,
                'total_pages'  => $totalPages,
            ],
        ];
    }

    /**
     * Retrieves a single proposal by ID.
     *
     * @return array<string, mixed>
     */
    public function getProposal(int $id): array
    {
        $pdo = $this->connection->getPdo();
        $stmt = $pdo->prepare(
            "SELECT p.*, u.name AS assigned_lead_name, u.email AS assigned_lead_email
             FROM `proposals` p
             LEFT JOIN `users` u ON p.`assigned_lead_id` = u.`id`
             WHERE p.`id` = :id"
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if (!$row) {
            throw new NotFoundException("Proposal [{$id}] not found.");
        }

        $services = $this->getProposalServices($pdo, $id);
        return $this->formatProposalRow($row, $services);
    }

    /**
     * Updates proposal workflow status with FSM checks (FR-WF-003, TEST-WF-003).
     *
     * @return array<string, mixed>
     */
    public function updateStatus(
        int $id,
        string $targetStatus,
        ?float $estimatedCommercialValue = null,
        ?string $notes = null,
        ?int $assignedLeadId = null,
        int $adminUserId = 1,
        string $ipAddress = '127.0.0.1',
        string $userAgent = 'Admin/Web'
    ): array {
        $normalizedTarget = self::normalizeStatus($targetStatus);

        if (!in_array($normalizedTarget, self::ALL_STATUSES, true)) {
            throw new ValidationException(
                "Invalid status [{$targetStatus}]. Must be one of: " . implode(', ', self::ALL_STATUSES),
                [],
                'ERR_INVALID_TRANSITION'
            );
        }

        $pdo = $this->connection->getPdo();

        try {
            $pdo->beginTransaction();

            $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
            $lockSql = $driver === 'sqlite'
                ? "SELECT * FROM `proposals` WHERE `id` = :id"
                : "SELECT * FROM `proposals` WHERE `id` = :id FOR UPDATE";

            $stmt = $pdo->prepare($lockSql);
            $stmt->execute(['id' => $id]);
            $current = $stmt->fetch(\PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            if (!$current) {
                throw new NotFoundException("Proposal [{$id}] not found.");
            }

            $currentStatus = (string)$current['status'];

            if ($currentStatus !== $normalizedTarget) {
                $allowedTargets = self::VALID_TRANSITIONS[$currentStatus] ?? [];
                if (!in_array($normalizedTarget, $allowedTargets, true)) {
                    throw new ValidationException(
                        "Invalid workflow transition from [{$currentStatus}] to [{$normalizedTarget}].",
                        [
                            'current_status'  => $currentStatus,
                            'target_status'   => $normalizedTarget,
                            'allowed_targets' => $allowedTargets,
                        ],
                        'ERR_INVALID_TRANSITION'
                    );
                }
            }

            $updateFields = [
                '`status` = :status',
                '`updated_at` = CURRENT_TIMESTAMP',
            ];
            $params = [
                'id'     => $id,
                'status' => $normalizedTarget,
            ];

            if ($estimatedCommercialValue !== null) {
                $updateFields[] = '`estimated_commercial_value` = :ecv';
                $params['ecv'] = $estimatedCommercialValue;
            }

            if ($assignedLeadId !== null) {
                if ($assignedLeadId > 0) {
                    $this->verifyUserExists($pdo, $assignedLeadId);
                    $updateFields[] = '`assigned_lead_id` = :lead_id';
                    $params['lead_id'] = $assignedLeadId;
                } else {
                    $updateFields[] = '`assigned_lead_id` = NULL';
                }
            }

            $updateSql = "UPDATE `proposals` SET " . implode(', ', $updateFields) . " WHERE `id` = :id";
            $upStmt = $pdo->prepare($updateSql);
            $upStmt->execute($params);
            $upStmt->closeCursor();

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e instanceof HttpException) {
                throw $e;
            }
            throw new DatabaseException("Failed to update proposal status: " . $e->getMessage(), [], $e);
        }

        // Audit log outside transaction with compensating rollback
        $auditAction = match ($normalizedTarget) {
            self::STATUS_UNDER_REVIEW  => 'RFP_UNDER_REVIEW',
            self::STATUS_SCOPING       => 'RFP_DRAFTING_COMMENCED',
            self::STATUS_PROPOSAL_SENT => 'RFP_PROPOSAL_DISPATCHED',
            self::STATUS_ACCEPTED      => 'RFP_CONTRACT_EXECUTED',
            self::STATUS_REJECTED      => 'RFP_DECLINED_SCOPE',
            default                    => 'RFP_STATUS_CHANGED',
        };

        try {
            $this->auditLogger->log(
                $pdo,
                'PROPOSAL',
                $auditAction,
                $adminUserId,
                $ipAddress,
                $userAgent,
                'proposals',
                $id,
                [
                    'previous_status'            => $currentStatus,
                    'new_status'                 => $normalizedTarget,
                    'notes'                      => $notes,
                    'estimated_commercial_value' => $estimatedCommercialValue,
                    'assigned_lead_id'           => $assignedLeadId,
                ]
            );
        } catch (\Throwable $e) {
            $revertStmt = $pdo->prepare(
                "UPDATE `proposals` SET `status` = :status, `estimated_commercial_value` = :ecv, `assigned_lead_id` = :lead, `updated_at` = CURRENT_TIMESTAMP WHERE `id` = :id"
            );
            $revertStmt->execute([
                'status' => $current['status'],
                'ecv'    => $current['estimated_commercial_value'],
                'lead'   => $current['assigned_lead_id'],
                'id'     => $id,
            ]);
            $revertStmt->closeCursor();

            throw new DatabaseException("Compensating rollback triggered: Audit logging failed for status change: " . $e->getMessage(), [], $e);
        }

        return $this->getProposal($id);
    }

    /**
     * Assigns a proposal lead specialist.
     *
     * @return array<string, mixed>
     */
    public function assignLead(
        int $id,
        int $leadId,
        int $adminUserId,
        string $ipAddress = '127.0.0.1',
        string $userAgent = 'Admin/Web'
    ): array {
        $pdo = $this->connection->getPdo();
        $this->verifyUserExists($pdo, $leadId);

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT `id`, `assigned_lead_id` FROM `proposals` WHERE `id` = :id");
            $stmt->execute(['id' => $id]);
            $current = $stmt->fetch(\PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            if (!$current) {
                throw new NotFoundException("Proposal [{$id}] not found.");
            }

            $upStmt = $pdo->prepare(
                "UPDATE `proposals` SET `assigned_lead_id` = :lead_id, `updated_at` = CURRENT_TIMESTAMP WHERE `id` = :id"
            );
            $upStmt->execute(['lead_id' => $leadId, 'id' => $id]);
            $upStmt->closeCursor();

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e instanceof HttpException) {
                throw $e;
            }
            throw new DatabaseException("Failed to assign proposal lead: " . $e->getMessage(), [], $e);
        }

        // Cryptographic audit log outside transaction with compensating rollback
        try {
            $this->auditLogger->log(
                $pdo,
                'PROPOSAL',
                'RFP_ASSIGNED',
                $adminUserId,
                $ipAddress,
                $userAgent,
                'proposals',
                $id,
                [
                    'previous_lead' => $current['assigned_lead_id'],
                    'new_lead'      => $leadId,
                ]
            );
        } catch (\Throwable $e) {
            $revertStmt = $pdo->prepare(
                "UPDATE `proposals` SET `assigned_lead_id` = :lead_id, `updated_at` = CURRENT_TIMESTAMP WHERE `id` = :id"
            );
            $revertStmt->execute([
                'lead_id' => $current['assigned_lead_id'],
                'id'      => $id,
            ]);
            $revertStmt->closeCursor();

            throw new DatabaseException("Compensating rollback triggered: Audit logging failed for assign lead: " . $e->getMessage(), [], $e);
        }

        return $this->getProposal($id);
    }

    /**
     * Streams the quarantined tender attachment safely to an authorized administrative user (FR-SEC-004, TEST-ADM-012).
     */
    public function streamTenderAttachment(int $id, int $adminUserId, string $ipAddress = '127.0.0.1'): Response
    {
        $pdo = $this->connection->getPdo();
        $stmt = $pdo->prepare("SELECT `id`, `quarantined_file_path`, `original_file_name` FROM `proposals` WHERE `id` = :id");
        $stmt->execute(['id' => $id]);
        $proposal = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if (!$proposal) {
            throw new NotFoundException("Proposal [{$id}] not found.");
        }

        $filePath = (string)($proposal['quarantined_file_path'] ?? '');
        $fileName = (string)($proposal['original_file_name'] ?? '');

        if ($filePath === '' || !file_exists($filePath)) {
            throw new NotFoundException("No tender document attached to proposal [{$id}].");
        }

        return $this->mediaStorageService->streamQuarantinedFile(
            $filePath,
            $fileName,
            'application/pdf',
            $adminUserId,
            'RFP_DOCUMENT_DOWNLOADED',
            'proposals',
            $id,
            $ipAddress
        );
    }

    /**
     * Exports proposal records to an RFC 4180 CSV string.
     *
     * @param array<string, mixed> $filters
     */
    public function exportCsv(
        array $filters = [],
        int $adminUserId = 1,
        string $ipAddress = '127.0.0.1',
        string $userAgent = 'Admin/Web'
    ): string {
        $list = $this->listProposals($filters, 1, 10000);
        $items = $list['items'];

        $fp = fopen('php://temp', 'r+');
        if ($fp === false) {
            throw new DatabaseException("Failed to open temporary stream for CSV export.");
        }

        fputcsv($fp, [
            'Reference Code',
            'Organization Name',
            'Contact Name',
            'Contact Email',
            'Contact Phone',
            'Facility Type',
            'Timeline',
            'Budget Band',
            'Status',
            'Commercial Value',
            'Assigned Lead',
            'Original File Name',
            'Created At',
        ]);

        foreach ($items as $item) {
            fputcsv($fp, [
                $this->sanitizeCsvField((string)($item['reference_code'] ?? '')),
                $this->sanitizeCsvField((string)($item['organization_name'] ?? '')),
                $this->sanitizeCsvField((string)($item['contact_name'] ?? '')),
                $this->sanitizeCsvField((string)($item['contact_email'] ?? '')),
                $this->sanitizeCsvField((string)($item['contact_phone'] ?? '')),
                $this->sanitizeCsvField((string)($item['facility_type'] ?? '')),
                $this->sanitizeCsvField((string)($item['timeline'] ?? '')),
                $this->sanitizeCsvField((string)($item['budget_band'] ?? '')),
                (string)($item['status'] ?? ''),
                $item['estimated_commercial_value'] !== null ? number_format((float)$item['estimated_commercial_value'], 2, '.', '') : '',
                $this->sanitizeCsvField((string)($item['assigned_lead_name'] ?? 'Unassigned')),
                $this->sanitizeCsvField((string)($item['original_file_name'] ?? '')),
                (string)($item['created_at'] ?? ''),
            ]);
        }

        rewind($fp);
        $csv = (string)stream_get_contents($fp);
        fclose($fp);

        $pdo = $this->connection->getPdo();
        $this->auditLogger->log(
            $pdo,
            'PROPOSAL',
            'RFP_EXPORTED',
            $adminUserId,
            $ipAddress,
            $userAgent,
            'proposals',
            null,
            ['records_count' => count($items), 'filters' => $filters]
        );

        return $csv;
    }

    /**
     * Deletes a proposal record, cascades junction entries, and removes quarantined file (Super Admin only).
     */
    public function deleteProposal(
        int $id,
        int $adminUserId,
        string $ipAddress = '127.0.0.1',
        string $userAgent = 'Admin/Web'
    ): bool {
        $pdo = $this->connection->getPdo();

        if (!$this->rbacManager->hasPermission($pdo, $adminUserId, 'proposal:delete')) {
            throw new ForbiddenException("Access denied. Only Super Administrators can purge RFP proposals.");
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT `id`, `reference_code`, `quarantined_file_path` FROM `proposals` WHERE `id` = :id");
            $stmt->execute(['id' => $id]);
            $current = $stmt->fetch(\PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            if (!$current) {
                throw new NotFoundException("Proposal [{$id}] not found.");
            }

            // Remove junction rows
            $delJunc = $pdo->prepare("DELETE FROM `proposal_services` WHERE `proposal_id` = :id");
            $delJunc->execute(['id' => $id]);
            $delJunc->closeCursor();

            // Remove proposal row
            $delStmt = $pdo->prepare("DELETE FROM `proposals` WHERE `id` = :id");
            $delStmt->execute(['id' => $id]);
            $delStmt->closeCursor();

            // Remove quarantined file if present
            $filePath = (string)($current['quarantined_file_path'] ?? '');
            if ($filePath !== '' && file_exists($filePath)) {
                @unlink($filePath);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e instanceof HttpException) {
                throw $e;
            }
            throw new DatabaseException("Failed to delete proposal: " . $e->getMessage(), [], $e);
        }

        // Cryptographic audit log outside transaction
        try {
            $this->auditLogger->log(
                $pdo,
                'PROPOSAL',
                'RFP_DELETED',
                $adminUserId,
                $ipAddress,
                $userAgent,
                'proposals',
                $id,
                ['reference_code' => $current['reference_code']]
            );
        } catch (\Throwable $e) {
            throw new DatabaseException("Audit logging failed after deletion: " . $e->getMessage(), [], $e);
        }

        return true;
    }

    /**
     * Neutralizes spreadsheet formula injection characters (=, +, -, @).
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
     * Links selected services to a proposal.
     *
     * @param array<int> $serviceIds
     */
    private function linkProposalServices(\PDO $pdo, int $proposalId, array $serviceIds): void
    {
        $stmt = $pdo->prepare(
            "INSERT INTO `proposal_services` (`proposal_id`, `service_id`, `created_at`)
             VALUES (:proposal_id, :service_id, CURRENT_TIMESTAMP)"
        );

        foreach (array_unique($serviceIds) as $sid) {
            if ($sid <= 0) {
                continue;
            }
            try {
                $stmt->execute([
                    'proposal_id' => $proposalId,
                    'service_id'  => $sid,
                ]);
            } catch (\PDOException) {
                // Ignore duplicate or nonexistent foreign key constraints gracefully in non-fatal contexts
            }
        }
        $stmt->closeCursor();
    }

    /**
     * Retrieves mapped services for a proposal.
     *
     * @return array<int, array{id: int, title: string}>
     */
    private function getProposalServices(\PDO $pdo, int $proposalId): array
    {
        $stmt = $pdo->prepare(
            "SELECT ps.`service_id` AS id, s.`title`
             FROM `proposal_services` ps
             LEFT JOIN `services` s ON ps.`service_id` = s.`id`
             WHERE ps.`proposal_id` = :id"
        );
        $stmt->execute(['id' => $proposalId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        $services = [];
        foreach ($rows as $row) {
            $services[] = [
                'id'    => (int)$row['id'],
                'title' => (string)($row['title'] ?? "Service #{$row['id']}"),
            ];
        }
        return $services;
    }

    /**
     * Generates a unique reference code: RFP-YYYYMMDD-XXXX.
     */
    private function generateReferenceCode(\PDO $pdo): string
    {
        $datePrefix = 'RFP-' . gmdate('Ymd') . '-';
        for ($i = 0; $i < 10; $i++) {
            $candidate = $datePrefix . strtoupper(bin2hex(random_bytes(2)));
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM `proposals` WHERE `reference_code` = :ref");
            $stmt->execute(['ref' => $candidate]);
            $count = (int)$stmt->fetchColumn();
            $stmt->closeCursor();
            if ($count === 0) {
                return $candidate;
            }
        }
        return $datePrefix . strtoupper(bin2hex(random_bytes(3)));
    }

    /**
     * Stages an auto-acknowledgement email record in email_queue.
     */
    private function stageAcknowledgementEmail(
        \PDO $pdo,
        int $proposalId,
        string $email,
        string $contactName,
        string $orgName,
        string $referenceCode
    ): void {
        $payload = json_encode([
            'proposal_id'    => $proposalId,
            'reference_code' => $referenceCode,
            'contact_name'   => $contactName,
            'org_name'       => $orgName,
            'submitted_at'   => gmdate('Y-m-d H:i:s \U\T\C'),
        ], JSON_THROW_ON_ERROR);

        $stmt = $pdo->prepare(
            "INSERT INTO `email_queue` (
                `template_key`, `recipient_email`, `recipient_name`, `payload_json`,
                `status`, `attempts`, `max_attempts`, `created_at`
            ) VALUES (
                'PROPOSAL_SUBMISSION_CONFIRMATION', :email, :name, :payload,
                'QUEUED', 0, 3, CURRENT_TIMESTAMP
            )"
        );
        $stmt->execute([
            'email'   => $email,
            'name'    => $contactName,
            'payload' => $payload,
        ]);
        $stmt->closeCursor();
    }

    /**
     * Formats a raw database row into an API-safe array.
     *
     * @param array<string, mixed> $row
     * @param array<int, array{id: int, title: string}> $services
     * @return array<string, mixed>
     */
    private function formatProposalRow(array $row, array $services = []): array
    {
        return [
            'id'                         => (int)$row['id'],
            'reference_code'             => (string)$row['reference_code'],
            'organization_name'          => (string)$row['organization_name'],
            'contact_name'               => (string)$row['contact_name'],
            'contact_email'              => (string)$row['contact_email'],
            'contact_phone'              => (string)$row['contact_phone'],
            'facility_type'              => (string)$row['facility_type'],
            'timeline'                   => (string)$row['timeline'],
            'budget_band'                => (string)$row['budget_band'],
            'has_tender_file'            => !empty($row['quarantined_file_path']),
            'original_file_name'         => (string)($row['original_file_name'] ?? ''),
            'file_size_bytes'            => (int)($row['file_size_bytes'] ?? 0),
            'status'                     => (string)$row['status'],
            'estimated_commercial_value' => $row['estimated_commercial_value'] !== null ? (float)$row['estimated_commercial_value'] : null,
            'assigned_lead_id'           => $row['assigned_lead_id'] !== null ? (int)$row['assigned_lead_id'] : null,
            'assigned_lead_name'         => $row['assigned_lead_name'] ?? null,
            'assigned_lead_email'        => $row['assigned_lead_email'] ?? null,
            'services'                   => $services,
            'created_at'                 => (string)$row['created_at'],
            'updated_at'                 => (string)$row['updated_at'],
        ];
    }

    /**
     * Verifies that an administrative user exists and is active.
     */
    private function verifyUserExists(\PDO $pdo, int $userId): void
    {
        $stmt = $pdo->prepare("SELECT `id`, `is_active` FROM `users` WHERE `id` = :id");
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if (!$user) {
            throw new ValidationException(['assigned_lead_id' => "Assigned lead [{$userId}] does not exist."], 'Validation failed.', 'ERR_VALIDATION');
        }

        if ((int)$user['is_active'] !== 1) {
            throw new ValidationException(['assigned_lead_id' => "Assigned lead [{$userId}] is suspended or inactive."], 'Validation failed.', 'ERR_VALIDATION');
        }
    }
}
