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
 * Enterprise Healthcare Contact Enquiry Management & Lifecycle State Machine Service.
 * Implements:
 * - ADM-010 / FR-ADM-010 / TEST-ADM-010 (Contact CRM & Administrative Triage)
 * - FR-WF-001 / TEST-WF-001 (Directed Acyclic Graph Lifecycle State Machine)
 * - SYS-005 (Abuse Prevention: IP Rate Limiting, Honeypot Filtering, Turnstile Defense)
 * - ADR-008 (Audit Trail Integrity & Cryptographic Chain Reconciliation)
 */
class ContactService
{
    public const STATUS_NEW = 'NEW';
    public const STATUS_TRIAGED = 'TRIAGED';
    public const STATUS_IN_REVIEW = 'IN_REVIEW';
    public const STATUS_CONTACTED = 'CONTACTED';
    public const STATUS_RESOLVED = 'RESOLVED';
    public const STATUS_CLOSED = 'CLOSED';
    public const STATUS_SPAM = 'SPAM';

    public const ALL_STATUSES = [
        self::STATUS_NEW,
        self::STATUS_TRIAGED,
        self::STATUS_IN_REVIEW,
        self::STATUS_CONTACTED,
        self::STATUS_RESOLVED,
        self::STATUS_CLOSED,
        self::STATUS_SPAM,
    ];

    /**
     * Canonical Directed Acyclic Graph (DAG) state transition rules (FR-WF-001).
     * Any transition not explicitly listed is strictly illegal and returns HTTP 422 ERR_INVALID_TRANSITION.
     */
    public const VALID_TRANSITIONS = [
        self::STATUS_NEW       => [self::STATUS_TRIAGED, self::STATUS_IN_REVIEW, self::STATUS_SPAM],
        self::STATUS_TRIAGED   => [self::STATUS_IN_REVIEW, self::STATUS_CONTACTED, self::STATUS_SPAM],
        self::STATUS_IN_REVIEW => [self::STATUS_CONTACTED, self::STATUS_RESOLVED, self::STATUS_SPAM],
        self::STATUS_CONTACTED => [self::STATUS_RESOLVED, self::STATUS_CLOSED, self::STATUS_SPAM],
        self::STATUS_RESOLVED  => [self::STATUS_CLOSED, self::STATUS_CONTACTED],
        self::STATUS_CLOSED    => [self::STATUS_IN_REVIEW],
        self::STATUS_SPAM      => [self::STATUS_NEW],
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
     * Submits a public contact enquiry with comprehensive multi-layer abuse defense (SYS-005).
     *
     * @param array<string, mixed> $payload
     * @return array{status: string, reference_code: string, message: string, id?: int}
     */
    public function submitInquiry(
        array $payload,
        string $ipAddress = '127.0.0.1',
        string $userAgent = 'Public/Web'
    ): array {
        // 1. Honeypot check (SYS-005): Silently discard bot submissions without DB mutation
        if (!empty($payload['hp_company_field']) || !empty($payload['honeypot'])) {
            $fakeRef = 'CNT-' . gmdate('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
            return [
                'status'         => 'success',
                'reference_code' => $fakeRef,
                'message'        => "Thank you for contacting VOAK Health Care. Your enquiry reference code is {$fakeRef}.",
            ];
        }

        // 2. IP Rate Limiting (SYS-005): Maximum 5 submissions per hour per IP
        $rateLimitKey = 'contact_submit_' . $ipAddress;
        $rateLimit = $this->rateLimiter->hit($rateLimitKey, 5, 3600);
        if (!$rateLimit['allowed']) {
            throw new HttpException(
                429,
                'Submission limit exceeded. You may only submit 5 contact enquiries per hour.',
                'ERR_RATE_LIMITED',
                ['Retry-After' => (string)$rateLimit['retry_after']]
            );
        }

        // 3. Validation & Sanitization
        $errors = [];

        $fullName = trim((string)($payload['full_name'] ?? ''));
        if ($fullName === '' || mb_strlen($fullName) < 2 || mb_strlen($fullName) > 150) {
            $errors['full_name'] = 'Full name is required and must be between 2 and 150 characters.';
        }

        $orgName = trim((string)($payload['organization_name'] ?? ''));
        if ($orgName === '' || mb_strlen($orgName) < 2 || mb_strlen($orgName) > 150) {
            $errors['organization_name'] = 'Organization name is required and must be between 2 and 150 characters.';
        }

        $email = trim((string)($payload['corporate_email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 191) {
            $errors['corporate_email'] = 'A valid corporate email address is required (maximum 191 characters).';
        }

        $phone = trim((string)($payload['contact_phone'] ?? ''));
        if ($phone === '' || !preg_match('/^\+?[0-9\s\-()]{7,25}$/', $phone)) {
            $errors['contact_phone'] = 'A valid contact phone number is required (7 to 25 characters, e.g., +61 2 1234 5678).';
        }

        $details = trim((string)($payload['inquiry_details'] ?? ''));
        if ($details === '' || mb_strlen($details) < 10 || mb_strlen($details) > 5000) {
            $errors['inquiry_details'] = 'Inquiry details are required and must be between 10 and 5000 characters.';
        }

        if (!empty($errors)) {
            throw new ValidationException($errors, 'The contact enquiry form submission failed validation rules.');
        }

        // Sanitize string content
        $cleanFullName = strip_tags($fullName);
        $cleanOrgName = strip_tags($orgName);
        $cleanDetails = strip_tags($details);

        $pdo = $this->connection->getPdo();

        // 4. Generate unique reference code (CNT-YYYYMMDD-XXXX)
        $datePrefix = gmdate('Ymd');
        $referenceCode = '';
        $maxAttempts = 5;
        for ($i = 0; $i < $maxAttempts; $i++) {
            $candidateCode = 'CNT-' . $datePrefix . '-' . strtoupper(bin2hex(random_bytes(2)));
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM contact_inquiries WHERE reference_code = :code");
            $stmt->execute(['code' => $candidateCode]);
            $exists = (int)$stmt->fetchColumn() > 0;
            $stmt->closeCursor();

            if (!$exists) {
                $referenceCode = $candidateCode;
                break;
            }
        }

        if ($referenceCode === '') {
            $referenceCode = 'CNT-' . $datePrefix . '-' . strtoupper(bin2hex(random_bytes(4)));
        }

        // 5. Atomic database insertion & transactional email queueing
        $pdo->beginTransaction();
        $inquiryId = 0;

        try {
            $stmt = $pdo->prepare(
                "INSERT INTO contact_inquiries (
                    reference_code, full_name, organization_name, corporate_email,
                    contact_phone, inquiry_details, status, ip_address, created_at, updated_at
                ) VALUES (
                    :ref, :name, :org, :email,
                    :phone, :details, 'NEW', :ip, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                )"
            );
            $stmt->execute([
                'ref'     => $referenceCode,
                'name'    => $cleanFullName,
                'org'     => $cleanOrgName,
                'email'   => strtolower($email),
                'phone'   => $phone,
                'details' => $cleanDetails,
                'ip'      => $ipAddress,
            ]);
            $inquiryId = (int)$pdo->lastInsertId();
            $stmt->closeCursor();

            // Queue acknowledgement email into email_queue (Migration 25)
            $emailPayload = json_encode([
                'reference_code'    => $referenceCode,
                'full_name'         => $cleanFullName,
                'organization_name' => $cleanOrgName,
                'corporate_email'   => strtolower($email),
                'submitted_at'      => gmdate('Y-m-d H:i:s \U\T\C'),
            ], JSON_THROW_ON_ERROR);

            $queueStmt = $pdo->prepare(
                "INSERT INTO email_queue (
                    template_key, recipient_email, recipient_name, payload_json, status, attempts, max_attempts, created_at
                ) VALUES (
                    'CONTACT_INQUIRY_CONFIRMATION', :recipient_email, :recipient_name, :payload, 'QUEUED', 0, 3, CURRENT_TIMESTAMP
                )"
            );
            $queueStmt->execute([
                'recipient_email' => strtolower($email),
                'recipient_name'  => $cleanFullName,
                'payload'         => $emailPayload,
            ]);
            $queueStmt->closeCursor();

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new DatabaseException("Failed to persist contact enquiry: " . $e->getMessage(), [], $e);
        }

        // 6. Cryptographic audit log (INQUIRY_SUBMITTED) - outside transaction
        try {
            $this->auditLogger->log(
                $pdo,
                'CONTACT',
                'INQUIRY_SUBMITTED',
                null,
                $ipAddress,
                $userAgent,
                'contact_inquiry',
                $inquiryId,
                [
                    'reference_code'    => $referenceCode,
                    'full_name'         => $cleanFullName,
                    'organization_name' => $cleanOrgName,
                    'corporate_email'   => strtolower($email),
                ]
            );
        } catch (\Throwable $e) {
            // Compensating rollback if audit log fails (guarantees ledger integrity)
            $delStmt = $pdo->prepare("DELETE FROM contact_inquiries WHERE id = :id");
            $delStmt->execute(['id' => $inquiryId]);
            $delStmt->closeCursor();

            throw new DatabaseException("Compensating rollback triggered: Audit logging failed for inquiry: " . $e->getMessage(), [], $e);
        }

        return [
            'status'         => 'success',
            'reference_code' => $referenceCode,
            'message'        => "Thank you for contacting VOAK Health Care. Your enquiry reference code is {$referenceCode}.",
            'id'             => $inquiryId,
        ];
    }

    /**
     * Lists contact enquiries with enterprise CRM filtering, status counts, and pagination (FR-ADM-010).
     *
     * @param array<string, mixed> $filters
     * @return array{items: list<array<string, mixed>>, pagination: array<string, int>, counts_by_status: array<string, int>}
     */
    public function listInquiries(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $pdo = $this->connection->getPdo();
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $offset = ($page - 1) * $perPage;

        $whereConditions = [];
        $bindings = [];

        // Status filter
        if (!empty($filters['status'])) {
            $status = strtoupper(trim((string)$filters['status']));
            if ($status !== 'ALL' && in_array($status, self::ALL_STATUSES, true)) {
                $whereConditions[] = "ci.status = :status";
                $bindings['status'] = $status;
            }
        }

        // Search filter (reference_code, full_name, organization_name, corporate_email, contact_phone)
        if (!empty($filters['search'])) {
            $search = trim((string)$filters['search']);
            $whereConditions[] = "(
                ci.reference_code LIKE :search
                OR ci.full_name LIKE :search
                OR ci.organization_name LIKE :search
                OR ci.corporate_email LIKE :search
                OR ci.contact_phone LIKE :search
            )";
            $bindings['search'] = '%' . $search . '%';
        }

        // Assigned user filter
        if (isset($filters['assigned_to']) && $filters['assigned_to'] !== '') {
            if ($filters['assigned_to'] === 'unassigned') {
                $whereConditions[] = "ci.assigned_user_id IS NULL";
            } elseif (is_numeric($filters['assigned_to'])) {
                $whereConditions[] = "ci.assigned_user_id = :assigned_user_id";
                $bindings['assigned_user_id'] = (int)$filters['assigned_to'];
            }
        }

        // Date range filters
        if (!empty($filters['date_from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$filters['date_from'])) {
            $whereConditions[] = "ci.created_at >= :date_from";
            $bindings['date_from'] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$filters['date_to'])) {
            $whereConditions[] = "ci.created_at <= :date_to";
            $bindings['date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        // 1. Total items count
        $countSql = "SELECT COUNT(*) FROM contact_inquiries ci {$whereClause}";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($bindings);
        $totalItems = (int)$countStmt->fetchColumn();
        $countStmt->closeCursor();

        // 2. Fetch paginated records with assignee join
        $sql = "SELECT 
                    ci.id,
                    ci.reference_code,
                    ci.full_name,
                    ci.organization_name,
                    ci.corporate_email,
                    ci.contact_phone,
                    ci.status,
                    ci.assigned_user_id,
                    ci.ip_address,
                    ci.created_at,
                    ci.updated_at,
                    u.name AS assigned_user_name,
                    u.email AS assigned_user_email
                FROM contact_inquiries ci
                LEFT JOIN users u ON u.id = ci.assigned_user_id
                {$whereClause}
                ORDER BY ci.created_at DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $pdo->prepare($sql);
        foreach ($bindings as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue('limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        // 3. Compute status distribution summary counts
        $statusCounts = [
            'ALL'                  => 0,
            self::STATUS_NEW       => 0,
            self::STATUS_TRIAGED   => 0,
            self::STATUS_IN_REVIEW => 0,
            self::STATUS_CONTACTED => 0,
            self::STATUS_RESOLVED  => 0,
            self::STATUS_CLOSED    => 0,
            self::STATUS_SPAM      => 0,
        ];

        $countsStmt = $pdo->query("SELECT status, COUNT(*) as cnt FROM contact_inquiries GROUP BY status");
        if ($countsStmt !== false) {
            while ($row = $countsStmt->fetch(\PDO::FETCH_ASSOC)) {
                $st = (string)$row['status'];
                $cnt = (int)$row['cnt'];
                if (isset($statusCounts[$st])) {
                    $statusCounts[$st] = $cnt;
                }
                $statusCounts['ALL'] += $cnt;
            }
            $countsStmt->closeCursor();
        }

        return [
            'items'            => $items,
            'pagination'       => [
                'current_page' => $page,
                'per_page'     => $perPage,
                'total_items'  => $totalItems,
                'total_pages'  => (int)ceil($totalItems / $perPage),
            ],
            'counts_by_status' => $statusCounts,
        ];
    }

    /**
     * Retrieves single contact enquiry by ID with assigned user and parsed audit notes.
     *
     * @return array<string, mixed>
     */
    public function getInquiryById(int $id): array
    {
        $pdo = $this->connection->getPdo();
        $stmt = $pdo->prepare(
            "SELECT 
                ci.*,
                u.name AS assigned_user_name,
                u.email AS assigned_user_email
            FROM contact_inquiries ci
            LEFT JOIN users u ON u.id = ci.assigned_user_id
            WHERE ci.id = :id"
        );
        $stmt->execute(['id' => $id]);
        $inquiry = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if (!$inquiry) {
            throw new NotFoundException("Contact enquiry [{$id}] not found.");
        }

        $inquiry['notes_history'] = $this->parseNotesHistory($inquiry['internal_notes'] ?? null);

        return $inquiry;
    }

    /**
     * Executes a DAG lifecycle state transition with row locking, resolution note checks, and audit logging.
     *
     * @return array<string, mixed>
     */
    public function updateStatus(
        int $id,
        string $newStatus,
        ?string $note,
        int $adminUserId,
        string $ipAddress = '127.0.0.1',
        string $userAgent = 'Admin/Web'
    ): array {
        $targetStatus = strtoupper(trim($newStatus));
        if (!in_array($targetStatus, self::ALL_STATUSES, true)) {
            throw new ValidationException(
                ['status' => 'Status is not valid.'],
                "Invalid target status [{$targetStatus}]. Valid statuses: " . implode(', ', self::ALL_STATUSES),
                'ERR_VALIDATION'
            );
        }

        $pdo = $this->connection->getPdo();
        $driver = (string)$pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        $pdo->beginTransaction();

        try {
            // Row-level lock against concurrent triage updates
            $selectSql = $driver === 'sqlite'
                ? "SELECT id, status, reference_code, internal_notes FROM contact_inquiries WHERE id = :id"
                : "SELECT id, status, reference_code, internal_notes FROM contact_inquiries WHERE id = :id FOR UPDATE";

            $stmt = $pdo->prepare($selectSql);
            $stmt->execute(['id' => $id]);
            $inquiry = $stmt->fetch(\PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            if (!$inquiry) {
                $pdo->rollBack();
                throw new NotFoundException("Contact enquiry [{$id}] not found.");
            }

            $currentStatus = (string)$inquiry['status'];

            // Check if status is identical (idempotent no-op)
            if ($currentStatus === $targetStatus) {
                // If a note is provided, append the note
                if ($note !== null && trim($note) !== '') {
                    $adminName = $this->getAdminUserName($pdo, $adminUserId);
                    $newNotes = $this->appendNoteText(
                        (string)($inquiry['internal_notes'] ?? ''),
                        $adminName,
                        $adminUserId,
                        trim($note)
                    );
                    $upStmt = $pdo->prepare("UPDATE contact_inquiries SET internal_notes = :notes, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
                    $upStmt->execute(['notes' => $newNotes, 'id' => $id]);
                    $upStmt->closeCursor();
                }
                $pdo->commit();
                return $this->getInquiryById($id);
            }

            // Verify DAG transition legality (FR-WF-001)
            $allowedTargets = self::VALID_TRANSITIONS[$currentStatus] ?? [];
            if (!in_array($targetStatus, $allowedTargets, true)) {
                $pdo->rollBack();
                throw new ValidationException(
                    ['status' => "Transition from {$currentStatus} to {$targetStatus} is illegal."],
                    "Cannot transition contact enquiry from [{$currentStatus}] to [{$targetStatus}]. Allowed transitions: " . implode(', ', $allowedTargets),
                    'ERR_INVALID_TRANSITION'
                );
            }

            // Rule: Transitioning to CLOSED requires an internal resolution note
            $trimmedNote = $note !== null ? trim($note) : '';
            $existingNotes = (string)($inquiry['internal_notes'] ?? '');
            if ($targetStatus === self::STATUS_CLOSED) {
                if ($trimmedNote === '' && trim($existingNotes) === '') {
                    $pdo->rollBack();
                    throw new ValidationException(
                        ['note' => 'Resolution note is required when closing an enquiry.'],
                        "Transition to CLOSED requires a mandatory internal resolution note.",
                        'ERR_VALIDATION'
                    );
                }
            }

            // Append note if provided
            $updatedNotes = $existingNotes;
            if ($trimmedNote !== '') {
                $adminName = $this->getAdminUserName($pdo, $adminUserId);
                $updatedNotes = $this->appendNoteText($existingNotes, $adminName, $adminUserId, $trimmedNote);
            }

            $updateStmt = $pdo->prepare(
                "UPDATE contact_inquiries 
                 SET status = :status, internal_notes = :notes, updated_at = CURRENT_TIMESTAMP 
                 WHERE id = :id"
            );
            $updateStmt->execute([
                'status' => $targetStatus,
                'notes'  => $updatedNotes !== '' ? $updatedNotes : null,
                'id'     => $id,
            ]);
            $updateStmt->closeCursor();

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e instanceof HttpException) {
                throw $e;
            }
            throw new DatabaseException("Failed to update enquiry status: " . $e->getMessage(), [], $e);
        }

        // Cryptographic audit log (INQUIRY_STATUS_CHANGED) - outside transaction
        try {
            $this->auditLogger->log(
                $pdo,
                'CONTACT',
                'INQUIRY_STATUS_CHANGED',
                $adminUserId,
                $ipAddress,
                $userAgent,
                'contact_inquiry',
                $id,
                [
                    'reference_code' => $inquiry['reference_code'],
                    'old_status'     => $currentStatus,
                    'new_status'     => $targetStatus,
                    'note_appended'  => $trimmedNote !== '',
                ]
            );
        } catch (\Throwable $e) {
            // Fail-closed compensating rollback: revert state transition if audit logging fails
            $revertStmt = $pdo->prepare(
                "UPDATE contact_inquiries SET status = :status, internal_notes = :notes, updated_at = CURRENT_TIMESTAMP WHERE id = :id"
            );
            $revertStmt->execute([
                'status' => $currentStatus,
                'notes'  => $inquiry['internal_notes'],
                'id'     => $id,
            ]);
            $revertStmt->closeCursor();

            throw new DatabaseException("Compensating rollback triggered: Audit logging failed for status change: " . $e->getMessage(), [], $e);
        }

        return $this->getInquiryById($id);
    }

    /**
     * Appends an internal CRM audit note to a contact enquiry.
     *
     * @return array<string, mixed>
     */
    public function addNote(
        int $id,
        string $note,
        int $adminUserId,
        string $ipAddress = '127.0.0.1',
        string $userAgent = 'Admin/Web'
    ): array {
        $trimmedNote = trim($note);
        if ($trimmedNote === '') {
            throw new ValidationException(
                ['note' => 'Note cannot be empty.'],
                "Note content cannot be empty.",
                'ERR_VALIDATION'
            );
        }

        $pdo = $this->connection->getPdo();
        $inquiry = $this->getInquiryById($id);

        $adminName = $this->getAdminUserName($pdo, $adminUserId);
        $existingNotes = (string)($inquiry['internal_notes'] ?? '');
        $updatedNotes = $this->appendNoteText($existingNotes, $adminName, $adminUserId, $trimmedNote);

        $stmt = $pdo->prepare(
            "UPDATE contact_inquiries SET internal_notes = :notes, updated_at = CURRENT_TIMESTAMP WHERE id = :id"
        );
        $stmt->execute(['notes' => $updatedNotes, 'id' => $id]);
        $stmt->closeCursor();

        // Audit log (INQUIRY_NOTE_ADDED)
        try {
            $this->auditLogger->log(
                $pdo,
                'CONTACT',
                'INQUIRY_NOTE_ADDED',
                $adminUserId,
                $ipAddress,
                $userAgent,
                'contact_inquiry',
                $id,
                [
                    'reference_code' => $inquiry['reference_code'],
                    'note_preview'   => mb_substr($trimmedNote, 0, 100),
                ]
            );
        } catch (\Throwable $e) {
            // Compensating rollback
            $revertStmt = $pdo->prepare("UPDATE contact_inquiries SET internal_notes = :notes WHERE id = :id");
            $revertStmt->execute(['notes' => $existingNotes !== '' ? $existingNotes : null, 'id' => $id]);
            $revertStmt->closeCursor();

            throw new DatabaseException("Compensating rollback triggered: Audit logging failed for note addition: " . $e->getMessage(), [], $e);
        }

        return $this->getInquiryById($id);
    }

    /**
     * Assigns a contact enquiry to an administrative team member.
     *
     * @return array<string, mixed>
     */
    public function assignInquiry(
        int $id,
        ?int $assignedUserId,
        int $adminUserId,
        string $ipAddress = '127.0.0.1',
        string $userAgent = 'Admin/Web'
    ): array {
        $pdo = $this->connection->getPdo();
        $inquiry = $this->getInquiryById($id);

        $targetUserName = null;
        if ($assignedUserId !== null) {
            $userCheck = $pdo->prepare("SELECT id, name FROM users WHERE id = :uid AND is_active = 1");
            $userCheck->execute(['uid' => $assignedUserId]);
            $userRow = $userCheck->fetch(\PDO::FETCH_ASSOC);
            $userCheck->closeCursor();

            if (!$userRow) {
                throw new ValidationException(
                    ['assigned_user_id' => 'Invalid assigned user.'],
                    "Target assigned user [{$assignedUserId}] does not exist or is inactive.",
                    'ERR_VALIDATION'
                );
            }
            $targetUserName = (string)$userRow['name'];
        }

        $prevAssignee = $inquiry['assigned_user_id'] !== null ? (int)$inquiry['assigned_user_id'] : null;

        $stmt = $pdo->prepare(
            "UPDATE contact_inquiries SET assigned_user_id = :assignee, updated_at = CURRENT_TIMESTAMP WHERE id = :id"
        );
        $stmt->execute(['assignee' => $assignedUserId, 'id' => $id]);
        $stmt->closeCursor();

        // Audit log (INQUIRY_ASSIGNED)
        try {
            $this->auditLogger->log(
                $pdo,
                'CONTACT',
                'INQUIRY_ASSIGNED',
                $adminUserId,
                $ipAddress,
                $userAgent,
                'contact_inquiry',
                $id,
                [
                    'reference_code'     => $inquiry['reference_code'],
                    'previous_assignee'  => $prevAssignee,
                    'new_assignee'       => $assignedUserId,
                    'assigned_user_name' => $targetUserName,
                ]
            );
        } catch (\Throwable $e) {
            // Compensating rollback
            $revert = $pdo->prepare("UPDATE contact_inquiries SET assigned_user_id = :assignee WHERE id = :id");
            $revert->execute(['assignee' => $prevAssignee, 'id' => $id]);
            $revert->closeCursor();

            throw new DatabaseException("Compensating rollback triggered: Audit logging failed for assignment: " . $e->getMessage(), [], $e);
        }

        return $this->getInquiryById($id);
    }

    /**
     * Permanently deletes a contact enquiry. Restricted to Super Admin role.
     */
    public function deleteInquiry(
        int $id,
        int $adminUserId,
        string $ipAddress = '127.0.0.1',
        string $userAgent = 'Admin/Web'
    ): bool {
        $pdo = $this->connection->getPdo();

        // Check least-privilege RBAC: deletion requires Super Admin permission
        if (!$this->rbacManager->hasPermission($pdo, $adminUserId, 'contact:delete')) {
            throw new ForbiddenException(
                "Access denied. Only Super Administrators are authorized to permanently delete contact inquiries.",
                'ERR_FORBIDDEN'
            );
        }

        $inquiry = $this->getInquiryById($id);

        $stmt = $pdo->prepare("DELETE FROM contact_inquiries WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $stmt->closeCursor();

        // Audit log (INQUIRY_DELETED)
        try {
            $this->auditLogger->log(
                $pdo,
                'CONTACT',
                'INQUIRY_DELETED',
                $adminUserId,
                $ipAddress,
                $userAgent,
                'contact_inquiry',
                $id,
                [
                    'reference_code'    => $inquiry['reference_code'],
                    'full_name'         => $inquiry['full_name'],
                    'organization_name' => $inquiry['organization_name'],
                    'corporate_email'   => $inquiry['corporate_email'],
                ]
            );
        } catch (\Throwable $e) {
            // Compensating re-insertion if audit logging fails
            $reinsert = $pdo->prepare(
                "INSERT INTO contact_inquiries (
                    id, reference_code, full_name, organization_name, corporate_email,
                    contact_phone, inquiry_details, status, assigned_user_id, internal_notes,
                    ip_address, created_at, updated_at
                ) VALUES (
                    :id, :ref, :name, :org, :email,
                    :phone, :details, :status, :assignee, :notes,
                    :ip, :created, :updated
                )"
            );
            $reinsert->execute([
                'id'       => $inquiry['id'],
                'ref'      => $inquiry['reference_code'],
                'name'     => $inquiry['full_name'],
                'org'      => $inquiry['organization_name'],
                'email'    => $inquiry['corporate_email'],
                'phone'    => $inquiry['contact_phone'],
                'details'  => $inquiry['inquiry_details'],
                'status'   => $inquiry['status'],
                'assignee' => $inquiry['assigned_user_id'],
                'notes'    => $inquiry['internal_notes'],
                'ip'       => $inquiry['ip_address'],
                'created'  => $inquiry['created_at'],
                'updated'  => $inquiry['updated_at'],
            ]);
            $reinsert->closeCursor();

            throw new DatabaseException("Compensating rollback triggered: Audit logging failed for inquiry deletion: " . $e->getMessage(), [], $e);
        }

        return true;
    }

    /**
     * Exports contact enquiries in RFC 4180 CSV format with audit trail logging.
     *
     * @param array<string, mixed> $filters
     */
    public function exportCsv(
        array $filters,
        int $adminUserId,
        string $ipAddress = '127.0.0.1',
        string $userAgent = 'Admin/Web'
    ): string {
        $pdo = $this->connection->getPdo();

        // RBAC Check
        if (!$this->rbacManager->hasPermission($pdo, $adminUserId, 'contact:export_csv')) {
            throw new ForbiddenException(
                "Access denied. You lack the 'contact:export_csv' permission required to download inquiry exports.",
                'ERR_FORBIDDEN'
            );
        }

        $whereConditions = [];
        $bindings = [];

        if (!empty($filters['status'])) {
            $status = strtoupper(trim((string)$filters['status']));
            if ($status !== 'ALL' && in_array($status, self::ALL_STATUSES, true)) {
                $whereConditions[] = "ci.status = :status";
                $bindings['status'] = $status;
            }
        }

        if (!empty($filters['search'])) {
            $whereConditions[] = "(
                ci.reference_code LIKE :search
                OR ci.full_name LIKE :search
                OR ci.organization_name LIKE :search
                OR ci.corporate_email LIKE :search
            )";
            $bindings['search'] = '%' . trim((string)$filters['search']) . '%';
        }

        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        $sql = "SELECT 
                    ci.reference_code,
                    ci.status,
                    ci.created_at,
                    ci.full_name,
                    ci.organization_name,
                    ci.corporate_email,
                    ci.contact_phone,
                    ci.inquiry_details,
                    ci.internal_notes,
                    u.name AS assigned_user_name
                FROM contact_inquiries ci
                LEFT JOIN users u ON u.id = ci.assigned_user_id
                {$whereClause}
                ORDER BY ci.created_at DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($bindings);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        $output = fopen('php://temp', 'r+');
        if ($output === false) {
            throw new DatabaseException("Failed to allocate temporary buffer for CSV export.");
        }

        // Output UTF-8 BOM for Microsoft Excel compatibility
        fwrite($output, "\xEF\xBB\xBF");

        // Write RFC 4180 header row
        fputcsv($output, [
            'Reference Code',
            'Status',
            'Created At (UTC)',
            'Full Name',
            'Organization',
            'Corporate Email',
            'Contact Phone',
            'Assigned To',
            'Inquiry Details',
            'Internal Notes',
        ]);

        foreach ($rows as $row) {
            fputcsv($output, [
                $row['reference_code'],
                $row['status'],
                $row['created_at'],
                $row['full_name'],
                $row['organization_name'],
                $row['corporate_email'],
                $row['contact_phone'],
                $row['assigned_user_name'] ?? 'Unassigned',
                $row['inquiry_details'],
                $row['internal_notes'] ?? '',
            ]);
        }

        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        // Audit log (INQUIRY_EXPORTED)
        $this->auditLogger->log(
            $pdo,
            'CONTACT',
            'INQUIRY_EXPORTED',
            $adminUserId,
            $ipAddress,
            $userAgent,
            'contact_inquiry',
            null,
            [
                'record_count' => count($rows),
                'filters'      => $filters,
            ]
        );

        return $csvContent !== false ? $csvContent : '';
    }

    /**
     * Formats and appends note text entry in chronological format.
     */
    private function appendNoteText(string $existingNotes, string $authorName, int $authorId, string $newNote): string
    {
        $timestamp = gmdate('Y-m-d H:i:s \U\T\C');
        $entry = "[{$timestamp}] ({$authorName} #{$authorId}): {$newNote}";

        if (trim($existingNotes) === '') {
            return $entry;
        }

        return rtrim($existingNotes) . "\n\n" . $entry;
    }

    /**
     * Resolves admin user name from users table.
     */
    private function getAdminUserName(\PDO $pdo, int $userId): string
    {
        $stmt = $pdo->prepare("SELECT name FROM users WHERE id = :id");
        $stmt->execute(['id' => $userId]);
        $name = $stmt->fetchColumn();
        $stmt->closeCursor();

        return $name !== false && trim((string)$name) !== '' ? (string)$name : "Admin {$userId}";
    }

    /**
     * Parses unstructured internal_notes text into structured audit history objects.
     *
     * @return list<array{timestamp: string, author: string, note: string}>
     */
    public function parseNotesHistory(?string $notesText): array
    {
        if ($notesText === null || trim($notesText) === '') {
            return [];
        }

        $entries = [];
        $blocks = preg_split('/\n\s*\n/', trim($notesText));

        if (!is_array($blocks)) {
            return [];
        }

        foreach ($blocks as $block) {
            $trimmed = trim($block);
            if ($trimmed === '') {
                continue;
            }

            if (preg_match('/^\[(.*?)\]\s*\((.*?)\):\s*(.*)$/s', $trimmed, $matches)) {
                $entries[] = [
                    'timestamp' => trim($matches[1]),
                    'author'    => trim($matches[2]),
                    'note'      => trim($matches[3]),
                ];
            } else {
                $entries[] = [
                    'timestamp' => 'Unknown',
                    'author'    => 'System',
                    'note'      => $trimmed,
                ];
            }
        }

        return $entries;
    }
}
