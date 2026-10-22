<?php

declare(strict_types=1);

namespace Voak\Health\Security;

use Voak\Health\Core\Exceptions\SecurityException;

/**
 * Immutable Cryptographically Chained Audit Logging Engine.
 * 
 * Implements running SHA-256 hash chains across all security and domain mutation events
 * using deterministic canonical JSON serialization ('voak-audit-v2').
 *
 * NOTE ON SERIALIZATION VERSIONING & COMPATIBILITY:
 * - Version 'voak-audit-v2' replaces legacy pipe-delimited sprintf serialization with unambiguous,
 *   type-preserving, key-sorted canonical JSON.
 * - This change is intentionally not backward-compatible with legacy delimiter-based hashes.
 * - Any development or non-production database ledgers generated with the legacy delimiter algorithm
 *   must be re-seeded or migrated via non-production scripts; no automatic production data migration
 *   is performed.
 */
class AuditLogger
{
    public const GENESIS_HASH = '0000000000000000000000000000000000000000000000000000000000000000';
    public const AUDIT_SCHEMA_VERSION = 'voak-audit-v2';

    private int $mysqlLockTimeoutSeconds;

    /**
     * @param int $mysqlLockTimeoutSeconds Timeout in seconds when acquiring MySQL named lock (default 10s).
     */
    public function __construct(int $mysqlLockTimeoutSeconds = 10)
    {
        if ($mysqlLockTimeoutSeconds < 0) {
            throw new \InvalidArgumentException("MySQL lock timeout seconds must be non-negative.");
        }
        $this->mysqlLockTimeoutSeconds = $mysqlLockTimeoutSeconds;
    }

    /**
     * Recursively canonicalizes structured data:
     * - Preserves null, empty string, and scalar representations.
     * - Recursively sorts associative array keys alphabetically using SORT_STRING.
     * - Preserves sequential list array index ordering.
     * - Handles empty lists and empty objects deterministically.
     */
    public static function canonicalizeData(mixed $data): mixed
    {
        if (!is_array($data)) {
            if ($data instanceof \stdClass || is_object($data)) {
                $arr = (array)$data;
                ksort($arr, SORT_STRING);
                $result = new \stdClass();
                foreach ($arr as $k => $v) {
                    $result->{(string)$k} = self::canonicalizeData($v);
                }
                return $result;
            }
            return $data;
        }

        if ($data === []) {
            return [];
        }

        if (array_is_list($data)) {
            $result = [];
            foreach ($data as $item) {
                $result[] = self::canonicalizeData($item);
            }
            return $result;
        }

        $sorted = $data;
        ksort($sorted, SORT_STRING);
        $result = [];
        foreach ($sorted as $key => $val) {
            $result[(string)$key] = self::canonicalizeData($val);
        }
        return $result;
    }

    /**
     * Builds the deterministic canonical JSON representation of an audit row for SHA-256 hashing.
     * Used by both writer (log) and verifier (verifyChain) to guarantee identical serialization.
     *
     * @param array<string, mixed>|list<mixed>|null $diff
     */
    public static function buildCanonicalPayload(
        string $prevHash,
        string $domain,
        string $action,
        ?int $userId,
        string $ipAddress,
        string $userAgent,
        ?string $entityType,
        ?int $entityId,
        mixed $diff,
        string $createdAt
    ): string {
        $record = [
            'version'     => self::AUDIT_SCHEMA_VERSION,
            'prev_hash'   => $prevHash,
            'domain'      => $domain,
            'action'      => $action,
            'user_id'     => $userId,
            'ip_address'  => $ipAddress,
            'user_agent'  => $userAgent,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'diff'        => self::canonicalizeData($diff),
            'created_at'  => $createdAt,
        ];

        return json_encode(
            $record,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * Appends an immutable audit event to the cryptographic ledger.
     *
     * @param array<string, mixed>|list<mixed>|null $diff
     * @return array{id: int, prev_hash: string, row_hash: string}
     */
    public function log(
        \PDO $pdo,
        string $domain,
        string $action,
        ?int $userId = null,
        string $ipAddress = '127.0.0.1',
        string $userAgent = 'CLI/Internal',
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $diff = null
    ): array {
        // Security Rule: The cryptographic hash chain requires strictly serialized, durable commits.
        // If an audit record were appended within an uncommitted outer transaction, releasing the serialization
        // lock would expose a race where a concurrent worker selects a stale predecessor hash or the audit row
        // is rolled back by the outer transaction. Therefore, log() requires non-transactional invocation so it
        // can commit its own transaction before releasing the serialization lock.
        if ($pdo->inTransaction()) {
            throw new SecurityException(
                "Cannot append chained audit record within an active uncommitted transaction: serialization lock cannot be safely released before commit."
            );
        }

        $driver = (string)$pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $lockAcquired = false;
        $txStarted = false;

        if ($driver === 'mysql') {
            $lockStmt = $pdo->query("SELECT GET_LOCK('voak_audit_log_chain_lock', {$this->mysqlLockTimeoutSeconds})");
            $lockAcquired = $lockStmt && (int)$lockStmt->fetchColumn() === 1;
            if ($lockStmt) {
                $lockStmt->closeCursor();
            }
            if (!$lockAcquired) {
                throw new SecurityException("Failed to acquire audit ledger serialization lock.");
            }
        }

        try {
            $pdo->beginTransaction();
            $txStarted = true;

            // 1. Fetch latest row_hash as predecessor under lock
            $stmt = $pdo->query("SELECT `row_hash` FROM `audit_logs` ORDER BY `id` DESC LIMIT 1");
            $prevHash = $stmt ? (string)($stmt->fetchColumn() ?: self::GENESIS_HASH) : self::GENESIS_HASH;
            if ($stmt) {
                $stmt->closeCursor();
            }

            // Normalize fields prior to hashing and insertion so hash corresponds exactly to persisted values
            $normDomain = substr(trim($domain), 0, 50);
            $normAction = substr(trim($action), 0, 100);
            $normIp = substr(trim($ipAddress), 0, 45);
            $normUa = substr(trim($userAgent), 0, 255);
            $normEntityType = $entityType !== null ? substr(trim($entityType), 0, 50) : null;
            $normUserId = $userId;
            $normEntityId = $entityId;
            $createdAt = gmdate('Y-m-d H:i:s');

            // Canonicalize diff data structure
            $diffCanonical = $diff !== null ? self::canonicalizeData($diff) : null;
            $diffJson = $diffCanonical !== null
                ? json_encode($diffCanonical, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                : null;

            // 2. Compute cryptographically bound SHA-256 hash using deterministic canonical JSON
            $payloadToHash = self::buildCanonicalPayload(
                $prevHash,
                $normDomain,
                $normAction,
                $normUserId,
                $normIp,
                $normUa,
                $normEntityType,
                $normEntityId,
                $diffCanonical,
                $createdAt
            );
            $rowHash = hash('sha256', $payloadToHash);

            // 3. Insert record into audit_logs table
            $insertStmt = $pdo->prepare(
                "INSERT INTO `audit_logs` 
                 (`prev_row_hash`, `row_hash`, `domain`, `action`, `user_id`, `ip_address`, `user_agent`, `entity_type`, `entity_id`, `diff_json`, `created_at`) 
                 VALUES 
                 (:prev, :hash, :domain, :action, :uid, :ip, :ua, :etype, :eid, :diff, :created_at)"
            );

            $insertStmt->execute([
                'prev'       => $prevHash,
                'hash'       => $rowHash,
                'domain'     => $normDomain,
                'action'     => $normAction,
                'uid'        => $normUserId,
                'ip'         => $normIp,
                'ua'         => $normUa,
                'etype'      => $normEntityType,
                'eid'        => $normEntityId,
                'diff'       => $diffJson,
                'created_at' => $createdAt,
            ]);
            $insertStmt->closeCursor();

            $id = (int)$pdo->lastInsertId();

            // Commit transaction before releasing the lock so the newly inserted row is durable and visible to subsequent lock holders
            $pdo->commit();
            $txStarted = false;

            return [
                'id'        => $id,
                'prev_hash' => $prevHash,
                'row_hash'  => $rowHash,
            ];
        } catch (\Throwable $e) {
            if ($txStarted && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        } finally {
            if ($driver === 'mysql' && $lockAcquired) {
                try {
                    $unlock = $pdo->query("SELECT RELEASE_LOCK('voak_audit_log_chain_lock')");
                    if ($unlock) {
                        $unlock->closeCursor();
                    }
                } catch (\Throwable) {
                    // Safe cleanup
                }
            }
        }
    }

    /**
     * Mathematically verifies the entire cryptographic hash chain in audit_logs.
     * Returns true if all hashes are valid and untampered; false if tampering is detected.
     */
    public function verifyChain(\PDO $pdo): bool
    {
        $stmt = $pdo->query("SELECT * FROM `audit_logs` ORDER BY `id` ASC");
        if (!$stmt) {
            return true;
        }

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if (empty($rows)) {
            return true;
        }

        $expectedPrev = self::GENESIS_HASH;

        foreach ($rows as $row) {
            // Verify link to predecessor
            if (!hash_equals($expectedPrev, (string)$row['prev_row_hash'])) {
                return false;
            }

            // Normalize diff from database
            $diffCanonical = null;
            if ($row['diff_json'] !== null && $row['diff_json'] !== '') {
                $decoded = json_decode((string)$row['diff_json'], true, 512, JSON_THROW_ON_ERROR);
                $diffCanonical = self::canonicalizeData($decoded);
            }

            // Normalize created_at to Y-m-d H:i:s format (trim subseconds if provided by driver)
            $createdAt = (string)$row['created_at'];
            if (strlen($createdAt) > 19) {
                $createdAt = substr($createdAt, 0, 19);
            }

            $userId = $row['user_id'] !== null ? (int)$row['user_id'] : null;
            $entityId = $row['entity_id'] !== null ? (int)$row['entity_id'] : null;
            $entityType = $row['entity_type'] !== null ? (string)$row['entity_type'] : null;

            // Recompute row hash using identical canonical builder
            $canonicalPayload = self::buildCanonicalPayload(
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

            $calculated = hash('sha256', $canonicalPayload);
            if (!hash_equals($calculated, (string)$row['row_hash'])) {
                return false;
            }

            $expectedPrev = (string)$row['row_hash'];
        }

        return true;
    }
}
