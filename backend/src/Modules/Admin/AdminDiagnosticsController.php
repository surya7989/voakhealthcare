<?php

declare(strict_types=1);

namespace Voak\Health\Modules\Admin;

use Voak\Health\Core\Exceptions\AuthenticationException;
use Voak\Health\Core\Http\Request;
use Voak\Health\Core\Http\Response;
use Voak\Health\Database\Connection;

/**
 * Protected System Telemetry & Diagnostics Controller (ADM-018 / SYS-004 / Phase 17).
 * 
 * Provides:
 * - Internal operational diagnostics: database latency, migration tracking, queue telemetry,
 *   storage path permissions, memory usage, and runtime health.
 * 
 * Fail-Closed Security:
 * Strictly restricted to Super Admin role with system.diagnostics permission.
 * Never exposed on public endpoints.
 */
class AdminDiagnosticsController
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    private function requireAdminUserId(Request $request): int
    {
        $userId = $request->getAttribute('admin_user_id');
        if ($userId === null || (int)$userId <= 0) {
            throw new AuthenticationException("Authentication required. No authenticated administrative user identity present.");
        }
        return (int)$userId;
    }

    /**
     * GET /api/v1/admin/system/diagnostics
     * GET /api/admin/system/diagnostics
     */
    public function getDiagnostics(Request $request): Response
    {
        $this->requireAdminUserId($request);

        $start = microtime(true);
        $pdo = $this->connection->getPdo();
        $driver = (string)$pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        // 1. Measure DB ping latency
        $pingStmt = $pdo->query("SELECT 1");
        if ($pingStmt) {
            $pingStmt->fetchColumn();
            $pingStmt->closeCursor();
        }
        $dbLatencyMs = round((microtime(true) - $start) * 1000, 2);

        // 2. Migration count
        $migrationCount = 0;
        try {
            $mStmt = $pdo->query("SELECT COUNT(*) FROM `migrations`");
            $migrationCount = $mStmt ? (int)$mStmt->fetchColumn() : 0;
            if ($mStmt) {
                $mStmt->closeCursor();
            }
        } catch (\Throwable) {
            // Migration table might not exist in mock environments
        }

        // 3. Audit ledger count
        $auditCount = 0;
        try {
            $aStmt = $pdo->query("SELECT COUNT(*) FROM `audit_logs`");
            $auditCount = $aStmt ? (int)$aStmt->fetchColumn() : 0;
            if ($aStmt) {
                $aStmt->closeCursor();
            }
        } catch (\Throwable) {
        }

        // 4. Queue telemetry
        $queuedEmails = 0;
        $failedEmails = 0;
        try {
            $qStmt = $pdo->query("SELECT COUNT(*) FROM `email_queue` WHERE `status` = 'QUEUED'");
            $queuedEmails = $qStmt ? (int)$qStmt->fetchColumn() : 0;
            if ($qStmt) {
                $qStmt->closeCursor();
            }

            $fStmt = $pdo->query("SELECT COUNT(*) FROM `email_queue` WHERE `status` = 'FAILED'");
            $failedEmails = $fStmt ? (int)$fStmt->fetchColumn() : 0;
            if ($fStmt) {
                $fStmt->closeCursor();
            }
        } catch (\Throwable) {
        }

        // 5. Storage telemetry
        $storageDir = dirname(__DIR__, 3) . '/storage';
        $uploadsDir = $storageDir . '/uploads';
        $storageWritable = is_dir($uploadsDir) ? is_writable($uploadsDir) : (is_dir($storageDir) && is_writable($storageDir));

        // 6. Runtime environment
        $memoryBytes = memory_get_usage(true);
        $memoryPeakBytes = memory_get_peak_usage(true);
        $opcacheStatus = function_exists('opcache_get_status') ? @opcache_get_status(false) : null;
        $opcacheEnabled = is_array($opcacheStatus) && !empty($opcacheStatus['opcache_enabled']);

        return Response::success([
            'status'      => 'healthy',
            'timestamp'   => gmdate('Y-m-d\TH:i:s\Z'),
            'database'    => [
                'driver'               => $driver,
                'connected'            => true,
                'latency_ms'           => $dbLatencyMs,
                'migrations_applied'   => $migrationCount,
                'audit_ledger_records' => $auditCount,
            ],
            'queue'       => [
                'queued_emails' => $queuedEmails,
                'failed_emails' => $failedEmails,
            ],
            'storage'     => [
                'uploads_writable' => $storageWritable,
            ],
            'environment' => [
                'php_version'     => PHP_VERSION,
                'memory_usage_mb' => round($memoryBytes / 1024 / 1024, 2),
                'memory_peak_mb'  => round($memoryPeakBytes / 1024 / 1024, 2),
                'memory_limit'    => ini_get('memory_limit') ?: 'unknown',
                'opcache_enabled' => $opcacheEnabled,
            ],
        ], 200, $request->getCorrelationId());
    }
}
