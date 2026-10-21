<?php

declare(strict_types=1);

namespace Voak\Health\Security;

/**
 * Immutable Cryptographically Chained Audit Logging Engine.
 * Implements running SHA-256 hash chains across all security and domain mutation events.
 */
class AuditLogger
{
    public const GENESIS_HASH = '0000000000000000000000000000000000000000000000000000000000000000';

    /**
     * Appends an immutable audit event to the cryptographic ledger.
     *
     * @param array<string, mixed>|null $diff
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
        // 1. Fetch latest row_hash as predecessor
        $stmt = $pdo->query("SELECT `row_hash` FROM `audit_logs` ORDER BY `id` DESC LIMIT 1");
        $prevHash = $stmt ? (string)($stmt->fetchColumn() ?: self::GENESIS_HASH) : self::GENESIS_HASH;
        if ($stmt) {
            $stmt->closeCursor();
        }

        $createdAt = gmdate('Y-m-d H:i:s');
        $diffJson = $diff !== null ? json_encode($diff, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;

        // 2. Compute cryptographically bound SHA-256 hash
        $payloadToHash = sprintf(
            '%s|%s|%s|%s|%s|%s|%s|%s',
            $prevHash,
            $domain,
            $action,
            $userId !== null ? (string)$userId : '0',
            $ipAddress,
            $entityType ?? '',
            $entityId !== null ? (string)$entityId : '0',
            $diffJson ?? ''
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
            'domain'     => substr($domain, 0, 50),
            'action'     => substr($action, 0, 100),
            'uid'        => $userId,
            'ip'         => substr($ipAddress, 0, 45),
            'ua'         => substr($userAgent, 0, 255),
            'etype'      => $entityType !== null ? substr($entityType, 0, 50) : null,
            'eid'        => $entityId,
            'diff'       => $diffJson,
            'created_at' => $createdAt,
        ]);
        $insertStmt->closeCursor();

        $id = (int)$pdo->lastInsertId();

        return [
            'id'        => $id,
            'prev_hash' => $prevHash,
            'row_hash'  => $rowHash,
        ];
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

            // Recompute row hash
            $payloadToHash = sprintf(
                '%s|%s|%s|%s|%s|%s|%s|%s',
                $row['prev_row_hash'],
                $row['domain'],
                $row['action'],
                $row['user_id'] !== null ? (string)$row['user_id'] : '0',
                $row['ip_address'],
                $row['entity_type'] ?? '',
                $row['entity_id'] !== null ? (string)$row['entity_id'] : '0',
                $row['diff_json'] ?? ''
            );

            $calculated = hash('sha256', $payloadToHash);
            if (!hash_equals($calculated, (string)$row['row_hash'])) {
                return false;
            }

            $expectedPrev = (string)$row['row_hash'];
        }

        return true;
    }
}
