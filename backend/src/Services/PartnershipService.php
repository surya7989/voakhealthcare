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
use Voak\Health\Database\Connection;
use Voak\Health\Security\AuditLogger;
use Voak\Health\Security\HtmlSanitizer;
use Voak\Health\Security\RateLimiter;

/**
 * Strategic Partnership & Alliance CRM Service.
 * Implements:
 * - ADM-011 / FR-ADM-011 / TEST-ADM-011 (Institutional Partnerships Pipeline)
 * - FR-WF-002 / TEST-WF-002 (Partnership Pipeline Directed Acyclic Graph State Machine)
 * - FR-PUB-020 / TEST-PUB-020 (Public Alliance Intake & Tiering)
 * - SYS-005 (Abuse Prevention: IP Rate Limiting, Honeypot Filtering, Turnstile Defense)
 * - ADR-008 (Audit Trail Integrity & Cryptographic Chain Reconciliation)
 */
class PartnershipService
{
    // Canonical MySQL 8.0 Enum Statuses (Migration 17)
    public const STATUS_NEW = 'NEW';
    public const STATUS_EVALUATION = 'EVALUATION';
    public const STATUS_DISCUSSION = 'DISCUSSION';
    public const STATUS_LEGAL_REVIEW = 'LEGAL_REVIEW';
    public const STATUS_ACTIVE_ALLIANCE = 'ACTIVE_ALLIANCE';
    public const STATUS_DECLINED = 'DECLINED';

    public const ALL_STATUSES = [
        self::STATUS_NEW,
        self::STATUS_EVALUATION,
        self::STATUS_DISCUSSION,
        self::STATUS_LEGAL_REVIEW,
        self::STATUS_ACTIVE_ALLIANCE,
        self::STATUS_DECLINED,
    ];

    /**
     * Canonical DAG state transition rules (FR-WF-002).
     */
    public const VALID_TRANSITIONS = [
        self::STATUS_NEW             => [self::STATUS_EVALUATION, self::STATUS_DECLINED],
        self::STATUS_EVALUATION      => [self::STATUS_DISCUSSION, self::STATUS_DECLINED],
        self::STATUS_DISCUSSION     => [self::STATUS_LEGAL_REVIEW, self::STATUS_DECLINED],
        self::STATUS_LEGAL_REVIEW    => [self::STATUS_ACTIVE_ALLIANCE, self::STATUS_DECLINED],
        self::STATUS_ACTIVE_ALLIANCE => [self::STATUS_DECLINED],
        self::STATUS_DECLINED        => [],
    ];

    /**
     * Aliases mapping specification stage names to canonical database statuses.
     */
    public const STATUS_ALIASES = [
        'NEW_INQUIRY'          => self::STATUS_NEW,
        'DISCOVERY_SCHEDULED'  => self::STATUS_DISCUSSION,
        'NEGOTIATING'          => self::STATUS_LEGAL_REVIEW,
        'NEGOTIATION'          => self::STATUS_LEGAL_REVIEW,
        'NDA_SENT'             => self::STATUS_LEGAL_REVIEW,
        'PARTNERED'            => self::STATUS_ACTIVE_ALLIANCE,
        'ACTIVE'               => self::STATUS_ACTIVE_ALLIANCE,
        'REJECTED'             => self::STATUS_DECLINED,
    ];

    private Connection $connection;
    private AuditLogger $auditLogger;
    private HtmlSanitizer $sanitizer;
    private RateLimiter $rateLimiter;
    private RbacManager $rbacManager;

    public function __construct(
        Connection $connection,
        AuditLogger $auditLogger,
        HtmlSanitizer $sanitizer,
        ?RateLimiter $rateLimiter = null,
        ?RbacManager $rbacManager = null
    ) {
        $this->connection = $connection;
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
     * Submits a public partnership enquiry with multi-layer abuse defense (SYS-005, FR-PUB-020).
     *
     * @param array<string, mixed> $payload
     * @return array{status: string, reference_code: string, message: string, id?: int}
     */
    public function submitPartnership(
        array $payload,
        string $ipAddress = '127.0.0.1',
        string $userAgent = 'Public/Web'
    ): array {
        // 1. Honeypot check (SYS-005): Silently discard bot submissions
        if (!empty($payload['hp_company_field']) || !empty($payload['honeypot']) || !empty($payload['website'])) {
            $fakeRef = 'PTN-' . gmdate('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
            return [
                'status'         => 'success',
                'reference_code' => $fakeRef,
                'message'        => "Thank you for proposing a strategic alliance with VOAK Health Care. Your inquiry reference is {$fakeRef}.",
            ];
        }

        // 2. IP Rate Limiting (SYS-005): Maximum 5 submissions per hour per IP
        $rateLimitKey = 'ptn_submit_' . $ipAddress;
        $rateLimit = $this->rateLimiter->hit($rateLimitKey, 5, 3600);
        if (!$rateLimit['allowed']) {
            throw new HttpException(
                429,
                'Submission limit exceeded. You may only submit 5 partnership enquiries per hour.',
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

        $corporateEmail = trim((string)($payload['corporate_email'] ?? $payload['email'] ?? ''));
        if ($corporateEmail === '' || !filter_var($corporateEmail, FILTER_VALIDATE_EMAIL)) {
            $errors['corporate_email'] = 'A valid corporate email address is required.';
        }

        $contactPhone = trim((string)($payload['contact_phone'] ?? $payload['phone'] ?? ''));
        if ($contactPhone === '' || !preg_match('/^\+?[0-9\s\-\(\)\.]{7,25}$/', $contactPhone)) {
            $errors['contact_phone'] = 'A valid contact phone number is required (7–25 digits, optional E.164 + prefix).';
        }

        $allianceType = trim((string)($payload['alliance_type'] ?? $payload['tier'] ?? ''));
        if ($allianceType === '' || mb_strlen($allianceType) > 50) {
            $errors['alliance_type'] = 'Alliance type / organization tier is required (max 50 characters).';
        }

        $proposedScope = trim((string)($payload['proposed_scope'] ?? $payload['scope'] ?? ''));
        if ($proposedScope === '' || mb_strlen($proposedScope) < 10) {
            $errors['proposed_scope'] = 'Proposed scope of collaboration must be at least 10 characters.';
        }

        if (!empty($errors)) {
            throw new ValidationException($errors, 'Partnership enquiry validation failed.', 'ERR_VALIDATION');
        }

        // 4. Input Sanitization
        $safeOrgName       = $this->sanitizer->sanitizePlainText($orgName);
        $safeContactName   = $this->sanitizer->sanitizePlainText($contactName);
        $safeEmail         = strtolower(trim($corporateEmail));
        $safePhone         = $this->sanitizer->sanitizePlainText($contactPhone);
        $safeAllianceType  = $this->sanitizer->sanitizePlainText($allianceType);
        $safeProposedScope = $this->sanitizer->sanitizePlainText($proposedScope);

        $pdo = $this->connection->getPdo();
        $referenceCode = $this->generateReferenceCode($pdo);

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                "INSERT INTO `partnership_inquiries` (
                    `reference_code`, `organization_name`, `contact_name`, `corporate_email`,
                    `contact_phone`, `alliance_type`, `proposed_scope`, `status`,
                    `internal_notes`, `ip_address`, `created_at`, `updated_at`
                ) VALUES (
                    :ref, :org, :contact, :email,
                    :phone, :alliance, :scope, 'NEW',
                    '[]', :ip, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                )"
            );

            $stmt->execute([
                'ref'      => $referenceCode,
                'org'      => $safeOrgName,
                'contact'  => $safeContactName,
                'email'    => $safeEmail,
                'phone'    => $safePhone,
                'alliance' => $safeAllianceType,
                'scope'    => $safeProposedScope,
                'ip'       => $ipAddress,
            ]);
            $stmt->closeCursor();

            $insertId = (int)$pdo->lastInsertId();

            // Stage auto-acknowledgement in email_queue
            $this->stageAcknowledgementEmail($pdo, $insertId, $safeEmail, $safeContactName, $safeOrgName, $referenceCode);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e instanceof HttpException) {
                throw $e;
            }
            throw new DatabaseException("Failed to persist partnership inquiry: " . $e->getMessage(), [], $e);
        }

        // Cryptographic audit log (PTN_INQUIRY_SUBMITTED) - outside transaction with compensating rollback
        try {
            $this->auditLogger->log(
                $pdo,
                'PARTNERSHIP',
                'PTN_INQUIRY_SUBMITTED',
                null,
                $ipAddress,
                $userAgent,
                'partnership_inquiries',
                $insertId,
                [
                    'reference_code'    => $referenceCode,
                    'organization_name' => $safeOrgName,
                    'alliance_type'     => $safeAllianceType,
                ]
            );
        } catch (\Throwable $e) {
            $delStmt = $pdo->prepare("DELETE FROM `partnership_inquiries` WHERE `id` = :id");
            $delStmt->execute(['id' => $insertId]);
            $delStmt->closeCursor();

            throw new DatabaseException("Compensating rollback triggered: Audit logging failed for partnership inquiry: " . $e->getMessage(), [], $e);
        }

        return [
            'status'         => 'success',
            'reference_code' => $referenceCode,
            'id'             => $insertId,
            'message'        => "Your partnership inquiry has been logged successfully. Reference code: {$referenceCode}.",
        ];
    }

    /**
     * Lists partnership inquiries with filtering and pagination.
     *
     * @param array<string, mixed> $filters
     * @return array{items: array<int, array<string, mixed>>, pagination: array<string, int>}
     */
    public function listPartnerships(array $filters = [], int $page = 1, int $perPage = 20): array
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

        if (!empty($filters['alliance_type'])) {
            $where[] = "p.`alliance_type` = :alliance_type";
            $params['alliance_type'] = (string)$filters['alliance_type'];
        }

        if (!empty($filters['assigned_to'])) {
            $where[] = "p.`assigned_user_id` = :assigned_user_id";
            $params['assigned_user_id'] = (int)$filters['assigned_to'];
        }

        if (!empty($filters['search'])) {
            $searchTerm = '%' . trim((string)$filters['search']) . '%';
            $where[] = "(p.`organization_name` LIKE :s1 OR p.`contact_name` LIKE :s2 OR p.`corporate_email` LIKE :s3 OR p.`reference_code` LIKE :s4)";
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
        $countSql = "SELECT COUNT(*) FROM `partnership_inquiries` p {$whereClause}";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        $countStmt->closeCursor();

        // Fetch page
        $sql = "SELECT p.*, u.name AS assigned_user_name, u.email AS assigned_user_email
                FROM `partnership_inquiries` p
                LEFT JOIN `users` u ON p.`assigned_user_id` = u.`id`
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
            $items[] = $this->formatPartnershipRow($row);
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
     * Retrieves a single partnership inquiry by ID.
     *
     * @return array<string, mixed>
     */
    public function getPartnership(int $id): array
    {
        $pdo = $this->connection->getPdo();
        $stmt = $pdo->prepare(
            "SELECT p.*, u.name AS assigned_user_name, u.email AS assigned_user_email
             FROM `partnership_inquiries` p
             LEFT JOIN `users` u ON p.`assigned_user_id` = u.`id`
             WHERE p.`id` = :id"
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if (!$row) {
            throw new NotFoundException("Partnership inquiry [{$id}] not found.");
        }

        return $this->formatPartnershipRow($row);
    }

    /**
     * Updates the workflow stage of a partnership inquiry with FSM validation (FR-WF-002, TEST-WF-002).
     *
     * @return array<string, mixed>
     */
    public function updateStage(
        int $id,
        string $targetStage,
        ?string $notes = null,
        ?int $assignedUserId = null,
        int $adminUserId = 1,
        string $ipAddress = '127.0.0.1',
        string $userAgent = 'Admin/Web'
    ): array {
        $normalizedTarget = self::normalizeStatus($targetStage);

        if (!in_array($normalizedTarget, self::ALL_STATUSES, true)) {
            throw new ValidationException(
                "Invalid status [{$targetStage}]. Must be one of: " . implode(', ', self::ALL_STATUSES),
                [],
                'ERR_INVALID_TRANSITION'
            );
        }

        $pdo = $this->connection->getPdo();

        try {
            $pdo->beginTransaction();

            // Row-level lock (driver-aware SELECT ... FOR UPDATE)
            $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
            $lockSql = $driver === 'sqlite'
                ? "SELECT * FROM `partnership_inquiries` WHERE `id` = :id"
                : "SELECT * FROM `partnership_inquiries` WHERE `id` = :id FOR UPDATE";

            $stmt = $pdo->prepare($lockSql);
            $stmt->execute(['id' => $id]);
            $current = $stmt->fetch(\PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            if (!$current) {
                throw new NotFoundException("Partnership inquiry [{$id}] not found.");
            }

            $currentStatus = (string)$current['status'];

            // Self transition is a no-op update
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

            // Append internal note if provided
            $internalNotes = $this->parseNotes((string)($current['internal_notes'] ?? '[]'));
            if ($notes !== null && trim($notes) !== '') {
                $authorName = $this->getAdminUserName($pdo, $adminUserId);
                $internalNotes[] = [
                    'id'          => uniqid('note_', true),
                    'author_id'   => $adminUserId,
                    'author_name' => $authorName,
                    'stage_from'  => $currentStatus,
                    'stage_to'    => $normalizedTarget,
                    'note'        => $this->sanitizer->sanitizePlainText(trim($notes)),
                    'created_at'  => gmdate('Y-m-d H:i:s'),
                ];
            }

            $encodedNotes = json_encode($internalNotes, JSON_UNESCAPED_SLASHES);

            $updateFields = [
                '`status` = :status',
                '`internal_notes` = :notes',
                '`updated_at` = CURRENT_TIMESTAMP',
            ];
            $params = [
                'id'     => $id,
                'status' => $normalizedTarget,
                'notes'  => $encodedNotes,
            ];

            if ($assignedUserId !== null) {
                if ($assignedUserId > 0) {
                    $this->verifyUserExists($pdo, $assignedUserId);
                    $updateFields[] = '`assigned_user_id` = :assigned_user_id';
                    $params['assigned_user_id'] = $assignedUserId;
                } else {
                    $updateFields[] = '`assigned_user_id` = NULL';
                }
            }

            $updateSql = "UPDATE `partnership_inquiries` SET " . implode(', ', $updateFields) . " WHERE `id` = :id";
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
            throw new DatabaseException("Failed to update partnership stage: " . $e->getMessage(), [], $e);
        }

        // Cryptographic audit log outside transaction with compensating rollback
        $auditAction = match ($normalizedTarget) {
            self::STATUS_DISCUSSION      => 'PTN_DISCOVERY_SCHEDULED',
            self::STATUS_LEGAL_REVIEW    => 'PTN_STAGE_NEGOTIATING',
            self::STATUS_ACTIVE_ALLIANCE => 'PTN_PARTNERED_FINALIZED',
            self::STATUS_DECLINED        => 'PTN_INQUIRY_DECLINED',
            default                      => 'PTN_STATUS_CHANGED',
        };

        try {
            $this->auditLogger->log(
                $pdo,
                'PARTNERSHIP',
                $auditAction,
                $adminUserId,
                $ipAddress,
                $userAgent,
                'partnership_inquiries',
                $id,
                [
                    'previous_status' => $currentStatus,
                    'new_status'      => $normalizedTarget,
                    'notes'           => $notes,
                    'assigned_to'     => $assignedUserId,
                ]
            );
        } catch (\Throwable $e) {
            $revertStmt = $pdo->prepare(
                "UPDATE `partnership_inquiries` SET `status` = :status, `internal_notes` = :notes, `assigned_user_id` = :assigned_user_id, `updated_at` = CURRENT_TIMESTAMP WHERE `id` = :id"
            );
            $revertStmt->execute([
                'status'           => $current['status'],
                'notes'            => $current['internal_notes'],
                'assigned_user_id' => $current['assigned_user_id'],
                'id'               => $id,
            ]);
            $revertStmt->closeCursor();

            throw new DatabaseException("Compensating rollback triggered: Audit logging failed for status change: " . $e->getMessage(), [], $e);
        }

        return $this->getPartnership($id);
    }

    /**
     * Appends an internal CRM note to the partnership inquiry.
     *
     * @return array<string, mixed>
     */
    public function addNote(
        int $id,
        string $noteText,
        int $adminUserId,
        string $ipAddress = '127.0.0.1',
        string $userAgent = 'Admin/Web'
    ): array {
        $cleanNote = trim($noteText);
        if ($cleanNote === '' || mb_strlen($cleanNote) < 2) {
            throw new ValidationException(['note' => 'Note text must be at least 2 characters.'], 'Validation failed.', 'ERR_VALIDATION');
        }

        $pdo = $this->connection->getPdo();

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT `id`, `internal_notes`, `status` FROM `partnership_inquiries` WHERE `id` = :id");
            $stmt->execute(['id' => $id]);
            $current = $stmt->fetch(\PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            if (!$current) {
                throw new NotFoundException("Partnership inquiry [{$id}] not found.");
            }

            $internalNotes = $this->parseNotes((string)($current['internal_notes'] ?? '[]'));
            $authorName = $this->getAdminUserName($pdo, $adminUserId);

            $internalNotes[] = [
                'id'          => uniqid('note_', true),
                'author_id'   => $adminUserId,
                'author_name' => $authorName,
                'note'        => $this->sanitizer->sanitizePlainText($cleanNote),
                'stage'       => $current['status'],
                'created_at'  => gmdate('Y-m-d H:i:s'),
            ];

            $encoded = json_encode($internalNotes, JSON_UNESCAPED_SLASHES);

            $upStmt = $pdo->prepare(
                "UPDATE `partnership_inquiries` SET `internal_notes` = :notes, `updated_at` = CURRENT_TIMESTAMP WHERE `id` = :id"
            );
            $upStmt->execute(['notes' => $encoded, 'id' => $id]);
            $upStmt->closeCursor();

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e instanceof HttpException) {
                throw $e;
            }
            throw new DatabaseException("Failed to add note: " . $e->getMessage(), [], $e);
        }

        // Cryptographic audit log outside transaction with compensating rollback
        try {
            $this->auditLogger->log(
                $pdo,
                'PARTNERSHIP',
                'PTN_NOTE_ADDED',
                $adminUserId,
                $ipAddress,
                $userAgent,
                'partnership_inquiries',
                $id,
                ['note_snippet' => mb_substr($cleanNote, 0, 100)]
            );
        } catch (\Throwable $e) {
            $revertStmt = $pdo->prepare(
                "UPDATE `partnership_inquiries` SET `internal_notes` = :notes, `updated_at` = CURRENT_TIMESTAMP WHERE `id` = :id"
            );
            $revertStmt->execute(['notes' => $current['internal_notes'], 'id' => $id]);
            $revertStmt->closeCursor();

            throw new DatabaseException("Compensating rollback triggered: Audit logging failed for note: " . $e->getMessage(), [], $e);
        }

        return $this->getPartnership($id);
    }

    /**
     * Assigns the partnership inquiry to an administrative user.
     *
     * @return array<string, mixed>
     */
    public function assign(
        int $id,
        int $assigneeId,
        int $adminUserId,
        string $ipAddress = '127.0.0.1',
        string $userAgent = 'Admin/Web'
    ): array {
        $pdo = $this->connection->getPdo();
        $this->verifyUserExists($pdo, $assigneeId);

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT `id`, `assigned_user_id` FROM `partnership_inquiries` WHERE `id` = :id");
            $stmt->execute(['id' => $id]);
            $current = $stmt->fetch(\PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            if (!$current) {
                throw new NotFoundException("Partnership inquiry [{$id}] not found.");
            }

            $upStmt = $pdo->prepare(
                "UPDATE `partnership_inquiries` SET `assigned_user_id` = :uid, `updated_at` = CURRENT_TIMESTAMP WHERE `id` = :id"
            );
            $upStmt->execute(['uid' => $assigneeId, 'id' => $id]);
            $upStmt->closeCursor();

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e instanceof HttpException) {
                throw $e;
            }
            throw new DatabaseException("Failed to assign partnership lead: " . $e->getMessage(), [], $e);
        }

        // Cryptographic audit log outside transaction with compensating rollback
        try {
            $this->auditLogger->log(
                $pdo,
                'PARTNERSHIP',
                'PTN_ASSIGNED',
                $adminUserId,
                $ipAddress,
                $userAgent,
                'partnership_inquiries',
                $id,
                [
                    'previous_assignee' => $current['assigned_user_id'],
                    'new_assignee'      => $assigneeId,
                ]
            );
        } catch (\Throwable $e) {
            $revertStmt = $pdo->prepare(
                "UPDATE `partnership_inquiries` SET `assigned_user_id` = :uid, `updated_at` = CURRENT_TIMESTAMP WHERE `id` = :id"
            );
            $revertStmt->execute(['uid' => $current['assigned_user_id'], 'id' => $id]);
            $revertStmt->closeCursor();

            throw new DatabaseException("Compensating rollback triggered: Audit logging failed for assign: " . $e->getMessage(), [], $e);
        }

        return $this->getPartnership($id);
    }

    /**
     * Exports partnership inquiry records to an RFC 4180 CSV string with CSV injection neutralization.
     *
     * @param array<string, mixed> $filters
     */
    public function exportCsv(
        array $filters = [],
        int $adminUserId = 1,
        string $ipAddress = '127.0.0.1',
        string $userAgent = 'Admin/Web'
    ): string {
        $list = $this->listPartnerships($filters, 1, 10000);
        $items = $list['items'];

        $fp = fopen('php://temp', 'r+');
        if ($fp === false) {
            throw new DatabaseException("Failed to open temporary stream for CSV export.");
        }

        // Header row
        fputcsv($fp, [
            'Reference Code',
            'Organization Name',
            'Contact Name',
            'Corporate Email',
            'Contact Phone',
            'Alliance Type',
            'Status',
            'Assigned Lead',
            'Proposed Scope',
            'Created At',
        ]);

        foreach ($items as $item) {
            fputcsv($fp, [
                $this->sanitizeCsvField((string)($item['reference_code'] ?? '')),
                $this->sanitizeCsvField((string)($item['organization_name'] ?? '')),
                $this->sanitizeCsvField((string)($item['contact_name'] ?? '')),
                $this->sanitizeCsvField((string)($item['corporate_email'] ?? '')),
                $this->sanitizeCsvField((string)($item['contact_phone'] ?? '')),
                $this->sanitizeCsvField((string)($item['alliance_type'] ?? '')),
                (string)($item['status'] ?? ''),
                $this->sanitizeCsvField((string)($item['assigned_user_name'] ?? 'Unassigned')),
                $this->sanitizeCsvField((string)($item['proposed_scope'] ?? '')),
                (string)($item['created_at'] ?? ''),
            ]);
        }

        rewind($fp);
        $csv = (string)stream_get_contents($fp);
        fclose($fp);

        // Audit logging outside transaction
        $pdo = $this->connection->getPdo();
        $this->auditLogger->log(
            $pdo,
            'PARTNERSHIP',
            'PTN_EXPORTED',
            $adminUserId,
            $ipAddress,
            $userAgent,
            'partnership_inquiries',
            null,
            ['records_count' => count($items), 'filters' => $filters]
        );

        return $csv;
    }

    /**
     * Deletes a partnership inquiry record (Super Admin only).
     */
    public function deletePartnership(
        int $id,
        int $adminUserId,
        string $ipAddress = '127.0.0.1',
        string $userAgent = 'Admin/Web'
    ): bool {
        $pdo = $this->connection->getPdo();

        // RBAC check: Only Super Admin can hard-delete records
        if (!$this->rbacManager->hasPermission($pdo, $adminUserId, 'partner:delete')) {
            throw new ForbiddenException("Access denied. Only Super Administrators can purge partnership inquiries.");
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT `id`, `reference_code` FROM `partnership_inquiries` WHERE `id` = :id");
            $stmt->execute(['id' => $id]);
            $current = $stmt->fetch(\PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            if (!$current) {
                throw new NotFoundException("Partnership inquiry [{$id}] not found.");
            }

            $delStmt = $pdo->prepare("DELETE FROM `partnership_inquiries` WHERE `id` = :id");
            $delStmt->execute(['id' => $id]);
            $delStmt->closeCursor();

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e instanceof HttpException) {
                throw $e;
            }
            throw new DatabaseException("Failed to delete partnership inquiry: " . $e->getMessage(), [], $e);
        }

        // Cryptographic audit log outside transaction with compensating rollback
        try {
            $this->auditLogger->log(
                $pdo,
                'PARTNERSHIP',
                'PTN_DELETED',
                $adminUserId,
                $ipAddress,
                $userAgent,
                'partnership_inquiries',
                $id,
                ['reference_code' => $current['reference_code']]
            );
        } catch (\Throwable $e) {
            $ins = $pdo->prepare(
                "INSERT INTO `partnership_inquiries` (id, reference_code, organization_name, contact_name, corporate_email, contact_phone, alliance_type, proposed_scope, status, assigned_user_id, internal_notes, ip_address, created_at, updated_at) VALUES (:id, :ref, :org, :cname, :cemail, :cphone, :atype, :scope, :status, :assigned, :notes, :ip, :created, :updated)"
            );
            $ins->execute([
                'id'       => $current['id'],
                'ref'      => $current['reference_code'],
                'org'      => $current['organization_name'],
                'cname'    => $current['contact_name'],
                'cemail'   => $current['corporate_email'],
                'cphone'   => $current['contact_phone'],
                'atype'    => $current['alliance_type'],
                'scope'    => $current['proposed_scope'],
                'status'   => $current['status'],
                'assigned' => $current['assigned_user_id'],
                'notes'    => $current['internal_notes'],
                'ip'       => $current['ip_address'],
                'created'  => $current['created_at'],
                'updated'  => $current['updated_at'],
            ]);
            $ins->closeCursor();

            throw new DatabaseException("Compensating rollback triggered: Audit logging failed for deletion: " . $e->getMessage(), [], $e);
        }

        return true;
    }

    /**
     * Neutralizes spreadsheet formula injection characters (=, +, -, @) according to OWASP.
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
     * Generates a unique reference code: PTN-YYYYMMDD-XXXX.
     */
    private function generateReferenceCode(\PDO $pdo): string
    {
        $datePrefix = 'PTN-' . gmdate('Ymd') . '-';
        for ($i = 0; $i < 10; $i++) {
            $candidate = $datePrefix . strtoupper(bin2hex(random_bytes(2)));
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM `partnership_inquiries` WHERE `reference_code` = :ref");
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
        int $inquiryId,
        string $email,
        string $contactName,
        string $orgName,
        string $referenceCode
    ): void {
        $payload = json_encode([
            'inquiry_id'     => $inquiryId,
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
                'PARTNERSHIP_INQUIRY_CONFIRMATION', :email, :name, :payload,
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
     * @return array<string, mixed>
     */
    private function formatPartnershipRow(array $row): array
    {
        $notes = $this->parseNotes((string)($row['internal_notes'] ?? '[]'));

        return [
            'id'                  => (int)$row['id'],
            'reference_code'      => (string)$row['reference_code'],
            'organization_name'   => (string)$row['organization_name'],
            'contact_name'        => (string)$row['contact_name'],
            'corporate_email'     => (string)$row['corporate_email'],
            'contact_phone'       => (string)$row['contact_phone'],
            'alliance_type'       => (string)$row['alliance_type'],
            'proposed_scope'      => (string)$row['proposed_scope'],
            'status'              => (string)$row['status'],
            'assigned_user_id'    => $row['assigned_user_id'] !== null ? (int)$row['assigned_user_id'] : null,
            'assigned_user_name'  => $row['assigned_user_name'] ?? null,
            'assigned_user_email' => $row['assigned_user_email'] ?? null,
            'internal_notes'      => $notes,
            'created_at'          => (string)$row['created_at'],
            'updated_at'          => (string)$row['updated_at'],
        ];
    }

    /**
     * Parses an internal_notes JSON string into an array.
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseNotes(string $rawNotes): array
    {
        if (trim($rawNotes) === '') {
            return [];
        }
        $decoded = json_decode($rawNotes, true);
        return is_array($decoded) ? $decoded : [];
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
            throw new ValidationException(['assigned_user_id' => "Assigned user [{$userId}] does not exist."], 'Validation failed.', 'ERR_VALIDATION');
        }

        if ((int)$user['is_active'] !== 1) {
            throw new ValidationException(['assigned_user_id' => "Assigned user [{$userId}] is suspended or inactive."], 'Validation failed.', 'ERR_VALIDATION');
        }
    }

    /**
     * Retrieves admin user full name.
     */
    private function getAdminUserName(\PDO $pdo, int $adminUserId): string
    {
        $stmt = $pdo->prepare("SELECT `name` FROM `users` WHERE `id` = :id");
        $stmt->execute(['id' => $adminUserId]);
        $name = $stmt->fetchColumn();
        $stmt->closeCursor();
        return $name !== false && trim((string)$name) !== '' ? (string)$name : "Admin #{$adminUserId}";
    }
}
