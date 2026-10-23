<?php

declare(strict_types=1);

namespace Voak\Health\Services;

use Voak\Health\Core\Exceptions\ValidationException;
use Voak\Health\Database\Connection;
use Voak\Health\Security\AuditLogger;

/**
 * Administrative Audit Ledger Service (Module 19 / ADM-019 / FR-ADM-019).
 * 
 * Provides:
 * - Read-only query desk for immutable cryptographically chained audit events.
 * - Multi-criteria filtering, pagination, and single event inspection with diffs.
 * - Mathematical SHA-256 chain verification from genesis hash to head.
 * - Formula-injection hardened CSV export with UTF-8 BOM for compliance audits.
 * - Access and export telemetry logging.
 * - Strictly zero mutation (UPDATE/DELETE) capabilities.
 */
class AuditService
{
    private Connection $connection;
    private AuditLogger $logger;

    public function __construct(Connection $connection, ?AuditLogger $logger = null)
    {
        $this->connection = $connection;
        $this->logger = $logger ?? new AuditLogger();
    }

    /**
     * Queries audit logs with pagination and multi-criteria filters.
     *
     * @param array<string, mixed> $filters
     * @return array{records: list<array<string, mixed>>, pagination: array{current_page: int, per_page: int, total_records: int, total_pages: int}}
     */
    public function queryLogs(array $filters = []): array
    {
        $pdo = $this->connection->getPdo();

        $page = max(1, (int)($filters['page'] ?? 1));
        $perPage = min(100, max(1, (int)($filters['per_page'] ?? 20)));
        $offset = ($page - 1) * $perPage;

        $where = [];
        $params = [];

        // 1. Filter by Domain
        if (!empty($filters['domain'])) {
            $domain = trim((string)$filters['domain']);
            if (str_contains($domain, ',')) {
                $domains = array_filter(array_map('trim', explode(',', $domain)));
                $placeholders = [];
                foreach ($domains as $i => $d) {
                    $key = "domain_{$i}";
                    $placeholders[] = ":{$key}";
                    $params[$key] = $d;
                }
                if (!empty($placeholders)) {
                    $where[] = "a.domain IN (" . implode(', ', $placeholders) . ")";
                }
            } else {
                $where[] = "a.domain = :domain";
                $params['domain'] = $domain;
            }
        }

        // 2. Filter by Action
        if (!empty($filters['action'])) {
            $action = trim((string)$filters['action']);
            if (str_contains($action, '%')) {
                $where[] = "a.action LIKE :action";
                $params['action'] = $action;
            } else {
                $where[] = "a.action = :action";
                $params['action'] = $action;
            }
        }

        // 3. Filter by User ID
        if (isset($filters['user_id']) && $filters['user_id'] !== '' && $filters['user_id'] !== null) {
            $where[] = "a.user_id = :user_id";
            $params['user_id'] = (int)$filters['user_id'];
        }

        // 4. Filter by Actor (Name, Email, or User ID)
        if (!empty($filters['actor'])) {
            $actor = trim((string)$filters['actor']);
            if (is_numeric($actor)) {
                $where[] = "(a.user_id = :actor_id OR u.name LIKE :actor_like OR u.email LIKE :actor_like)";
                $params['actor_id'] = (int)$actor;
                $params['actor_like'] = "%{$actor}%";
            } else {
                $where[] = "(u.name LIKE :actor OR u.email LIKE :actor)";
                $params['actor'] = "%{$actor}%";
            }
        }

        // 5. Filter by Entity Type
        if (!empty($filters['entity_type'])) {
            $where[] = "a.entity_type = :entity_type";
            $params['entity_type'] = trim((string)$filters['entity_type']);
        }

        // 6. Filter by Entity ID
        if (isset($filters['entity_id']) && $filters['entity_id'] !== '' && $filters['entity_id'] !== null) {
            $where[] = "a.entity_id = :entity_id";
            $params['entity_id'] = (int)$filters['entity_id'];
        }

        // 7. Filter by IP Address
        if (!empty($filters['ip_address'])) {
            $ip = trim((string)$filters['ip_address']);
            $where[] = "a.ip_address LIKE :ip_address";
            $params['ip_address'] = "%{$ip}%";
        }

        // 8. Filter by Date Range (start_date / end_date)
        if (!empty($filters['start_date'])) {
            $startDate = trim((string)$filters['start_date']);
            if (strlen($startDate) === 10) {
                $startDate .= ' 00:00:00';
            }
            $where[] = "a.created_at >= :start_date";
            $params['start_date'] = $startDate;
        }

        if (!empty($filters['end_date'])) {
            $endDate = trim((string)$filters['end_date']);
            if (strlen($endDate) === 10) {
                $endDate .= ' 23:59:59';
            }
            $where[] = "a.created_at <= :end_date";
            $params['end_date'] = $endDate;
        }

        // 9. Generic Search Query
        if (!empty($filters['search'])) {
            $search = trim((string)$filters['search']);
            $where[] = "(a.action LIKE :search OR a.domain LIKE :search OR a.ip_address LIKE :search OR a.user_agent LIKE :search OR a.diff_json LIKE :search)";
            $params['search'] = "%{$search}%";
        }

        $whereClause = !empty($where) ? "WHERE " . implode(' AND ', $where) : "";

        // Count Total
        $countSql = "SELECT COUNT(*) FROM audit_logs a LEFT JOIN users u ON a.user_id = u.id {$whereClause}";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $totalRecords = (int)$countStmt->fetchColumn();
        $countStmt->closeCursor();

        if ($totalRecords === 0) {
            return [
                'records' => [],
                'pagination' => [
                    'current_page'  => $page,
                    'per_page'      => $perPage,
                    'total_records' => 0,
                    'total_pages'   => 0,
                ],
            ];
        }

        // Fetch Page Data
        $dataSql = "SELECT a.*, u.name AS actor_name, u.email AS actor_email 
                    FROM audit_logs a 
                    LEFT JOIN users u ON a.user_id = u.id 
                    {$whereClause} 
                    ORDER BY a.id DESC 
                    LIMIT {$perPage} OFFSET {$offset}";

        $dataStmt = $pdo->prepare($dataSql);
        $dataStmt->execute($params);

        $rows = $dataStmt->fetchAll(\PDO::FETCH_ASSOC);
        $dataStmt->closeCursor();

        $records = [];
        foreach ($rows as $row) {
            $diff = null;
            if ($row['diff_json'] !== null && $row['diff_json'] !== '') {
                try {
                    $diff = json_decode((string)$row['diff_json'], true, 512, JSON_THROW_ON_ERROR);
                } catch (\Throwable) {
                    $diff = null;
                }
            }

            $records[] = [
                'id'             => (int)$row['id'],
                'prev_row_hash'  => (string)$row['prev_row_hash'],
                'row_hash'       => (string)$row['row_hash'],
                'domain'         => (string)$row['domain'],
                'action'         => (string)$row['action'],
                'user_id'        => $row['user_id'] !== null ? (int)$row['user_id'] : null,
                'actor_name'     => $row['actor_name'] !== null ? (string)$row['actor_name'] : null,
                'actor_email'    => $row['actor_email'] !== null ? (string)$row['actor_email'] : null,
                'ip_address'     => (string)$row['ip_address'],
                'user_agent'     => (string)$row['user_agent'],
                'entity_type'    => $row['entity_type'] !== null ? (string)$row['entity_type'] : null,
                'entity_id'      => $row['entity_id'] !== null ? (int)$row['entity_id'] : null,
                'diff'           => $diff,
                'created_at'     => (string)$row['created_at'],
            ];
        }

        return [
            'records' => $records,
            'pagination' => [
                'current_page'  => $page,
                'per_page'      => $perPage,
                'total_records' => $totalRecords,
                'total_pages'   => (int)ceil($totalRecords / $perPage),
            ],
        ];
    }

    /**
     * Retrieves a single audit log event by ID with full diff details.
     *
     * @return array<string, mixed>|null
     */
    public function getLogById(int $id): ?array
    {
        $pdo = $this->connection->getPdo();
        $stmt = $pdo->prepare(
            "SELECT a.*, u.name AS actor_name, u.email AS actor_email 
             FROM audit_logs a 
             LEFT JOIN users u ON a.user_id = u.id 
             WHERE a.id = :id 
             LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if (!$row) {
            return null;
        }

        $diff = null;
        if ($row['diff_json'] !== null && $row['diff_json'] !== '') {
            try {
                $diff = json_decode((string)$row['diff_json'], true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                $diff = null;
            }
        }

        return [
            'id'             => (int)$row['id'],
            'prev_row_hash'  => (string)$row['prev_row_hash'],
            'row_hash'       => (string)$row['row_hash'],
            'domain'         => (string)$row['domain'],
            'action'         => (string)$row['action'],
            'user_id'        => $row['user_id'] !== null ? (int)$row['user_id'] : null,
            'actor_name'     => $row['actor_name'] !== null ? (string)$row['actor_name'] : null,
            'actor_email'    => $row['actor_email'] !== null ? (string)$row['actor_email'] : null,
            'ip_address'     => (string)$row['ip_address'],
            'user_agent'     => (string)$row['user_agent'],
            'entity_type'    => $row['entity_type'] !== null ? (string)$row['entity_type'] : null,
            'entity_id'      => $row['entity_id'] !== null ? (int)$row['entity_id'] : null,
            'diff'           => $diff,
            'created_at'     => (string)$row['created_at'],
        ];
    }

    /**
     * Mathematically verifies the entire cryptographic SHA-256 hash chain in audit_logs.
     *
     * @return array{
     *     valid: bool,
     *     total_records: int,
     *     genesis_hash: string,
     *     head_hash: string,
     *     tampered_at_id: ?int,
     *     error: ?string
     * }
     */
    public function verifyChainIntegrity(): array
    {
        $pdo = $this->connection->getPdo();
        $stmt = $pdo->query("SELECT * FROM `audit_logs` ORDER BY `id` ASC");
        if (!$stmt) {
            return [
                'valid'          => true,
                'total_records'  => 0,
                'genesis_hash'   => AuditLogger::GENESIS_HASH,
                'head_hash'      => AuditLogger::GENESIS_HASH,
                'tampered_at_id' => null,
                'error'          => null,
            ];
        }

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        $totalRecords = count($rows);
        if ($totalRecords === 0) {
            return [
                'valid'          => true,
                'total_records'  => 0,
                'genesis_hash'   => AuditLogger::GENESIS_HASH,
                'head_hash'      => AuditLogger::GENESIS_HASH,
                'tampered_at_id' => null,
                'error'          => null,
            ];
        }

        $expectedPrev = AuditLogger::GENESIS_HASH;

        foreach ($rows as $row) {
            $rowId = (int)$row['id'];

            // 1. Verify link to predecessor hash
            if (!hash_equals($expectedPrev, (string)$row['prev_row_hash'])) {
                return [
                    'valid'          => false,
                    'total_records'  => $totalRecords,
                    'genesis_hash'   => AuditLogger::GENESIS_HASH,
                    'head_hash'      => $expectedPrev,
                    'tampered_at_id' => $rowId,
                    'error'          => "Cryptographic chain broken at record ID {$rowId}: prev_row_hash does not match predecessor hash.",
                ];
            }

            // 2. Canonicalize diff data structure
            $diffCanonical = null;
            if ($row['diff_json'] !== null && $row['diff_json'] !== '') {
                try {
                    $decoded = json_decode((string)$row['diff_json'], true, 512, JSON_THROW_ON_ERROR);
                    $diffCanonical = AuditLogger::canonicalizeData($decoded);
                } catch (\Throwable $e) {
                    return [
                        'valid'          => false,
                        'total_records'  => $totalRecords,
                        'genesis_hash'   => AuditLogger::GENESIS_HASH,
                        'head_hash'      => $expectedPrev,
                        'tampered_at_id' => $rowId,
                        'error'          => "Malformed diff_json at record ID {$rowId}: " . $e->getMessage(),
                    ];
                }
            }

            // 3. Normalize timestamp (trim driver subseconds if present)
            $createdAt = (string)$row['created_at'];
            if (strlen($createdAt) > 19) {
                $createdAt = substr($createdAt, 0, 19);
            }

            $userId = $row['user_id'] !== null ? (int)$row['user_id'] : null;
            $entityId = $row['entity_id'] !== null ? (int)$row['entity_id'] : null;
            $entityType = $row['entity_type'] !== null ? (string)$row['entity_type'] : null;

            // 4. Recompute canonical SHA-256 hash
            $canonicalPayload = AuditLogger::buildCanonicalPayload(
                (string)$row['prev_row_hash'],
                (string)$row['domain'],
                (string)$row['action'],
                $userId,
                (string)$row['ip_address'],
                (string)($row['user_agent'] ?? ''),
                $entityType,
                $entityId,
                $diffCanonical,
                $createdAt
            );

            $calculatedHash = hash('sha256', $canonicalPayload);
            if (!hash_equals($calculatedHash, (string)$row['row_hash'])) {
                return [
                    'valid'          => false,
                    'total_records'  => $totalRecords,
                    'genesis_hash'   => AuditLogger::GENESIS_HASH,
                    'head_hash'      => $expectedPrev,
                    'tampered_at_id' => $rowId,
                    'error'          => "Hash validation failed at record ID {$rowId}: computed hash does not match stored row_hash.",
                ];
            }

            $expectedPrev = (string)$row['row_hash'];
        }

        return [
            'valid'          => true,
            'total_records'  => $totalRecords,
            'genesis_hash'   => AuditLogger::GENESIS_HASH,
            'head_hash'      => $expectedPrev,
            'tampered_at_id' => null,
            'error'          => null,
        ];
    }

    /**
     * Exports audit logs to RFC 4180 CSV with formula injection defense and UTF-8 BOM.
     *
     * @param array<string, mixed> $filters
     * @return string
     */
    public function exportCsv(array $filters = []): string
    {
        // Limit export to 5000 records maximum per export
        $filters['page'] = 1;
        $filters['per_page'] = 5000;

        $results = $this->queryLogs($filters);
        $records = $results['records'];

        $fp = fopen('php://temp', 'r+');
        if ($fp === false) {
            throw new \RuntimeException("Failed to allocate memory stream for CSV export.");
        }

        // 1. Write UTF-8 Byte Order Mark (BOM)
        fwrite($fp, "\xEF\xBB\xBF");

        // 2. Write CSV Header
        $headers = [
            'Log ID',
            'Timestamp (UTC)',
            'Actor ID',
            'Actor Name',
            'Actor Email',
            'Domain',
            'Action',
            'Entity Type',
            'Entity ID',
            'IP Address',
            'User Agent',
            'Previous Hash',
            'Row Hash',
        ];
        fputcsv($fp, $headers);

        // 3. Write Data Rows with Formula Injection Protection
        foreach ($records as $r) {
            $row = [
                $this->sanitizeCsvCell((string)$r['id']),
                $this->sanitizeCsvCell((string)$r['created_at']),
                $this->sanitizeCsvCell($r['user_id'] !== null ? (string)$r['user_id'] : 'System'),
                $this->sanitizeCsvCell((string)($r['actor_name'] ?? 'System/Internal')),
                $this->sanitizeCsvCell((string)($r['actor_email'] ?? 'internal@voakhealthcare.com')),
                $this->sanitizeCsvCell((string)$r['domain']),
                $this->sanitizeCsvCell((string)$r['action']),
                $this->sanitizeCsvCell((string)($r['entity_type'] ?? '')),
                $this->sanitizeCsvCell($r['entity_id'] !== null ? (string)$r['entity_id'] : ''),
                $this->sanitizeCsvCell((string)$r['ip_address']),
                $this->sanitizeCsvCell((string)$r['user_agent']),
                $this->sanitizeCsvCell((string)$r['prev_row_hash']),
                $this->sanitizeCsvCell((string)$r['row_hash']),
            ];
            fputcsv($fp, $row);
        }

        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);

        return $csv !== false ? $csv : '';
    }

    /**
     * Records access telemetry event when an administrator views or queries the audit logs.
     */
    public function logAccess(?int $actorUserId, string $ipAddress, string $userAgent): void
    {
        $pdo = $this->connection->getPdo();
        try {
            $this->logger->log(
                $pdo,
                'AUDIT',
                'AUDIT_LOGS_ACCESSED',
                $actorUserId,
                $ipAddress,
                $userAgent,
                'audit_logs',
                null,
                ['event' => 'search_or_list']
            );
        } catch (\Throwable) {
            // Fail open on read telemetry logging to not block administrative visibility
        }
    }

    /**
     * Records export telemetry event when an administrator exports the audit logs.
     */
    public function logExport(?int $actorUserId, string $ipAddress, string $userAgent, int $recordCount): void
    {
        $pdo = $this->connection->getPdo();
        try {
            $this->logger->log(
                $pdo,
                'AUDIT',
                'AUDIT_LOGS_EXPORTED',
                $actorUserId,
                $ipAddress,
                $userAgent,
                'audit_logs',
                null,
                ['record_count' => $recordCount]
            );
        } catch (\Throwable) {
            // Fail open on export telemetry
        }
    }

    /**
     * Sanitizes CSV cell to prevent spreadsheet formula injection attacks (CSV Injection).
     * Prefixes characters '=', '+', '-', '@', '\t', '\r' with an apostrophe.
     */
    private function sanitizeCsvCell(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $firstChar = $value[0];
        if (in_array($firstChar, ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $value;
        }

        return $value;
    }
}
