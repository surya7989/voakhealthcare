<?php

declare(strict_types=1);

/**
 * CLI Runner for Cryptographic Audit Hash Chain Verification (ADM-019 / TEST-ADM-019).
 * Verifies mathematical integrity of running SHA-256 hash chains across all audit records.
 *
 * Usage:
 *   php backend/cli/verify_audit_chain.php [--env=/path/to/.env]
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Voak\Health\Core\App;
use Voak\Health\Services\AuditService;

// Ensure CLI execution only
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Error: verify_audit_chain.php may only be executed via CLI.\n");
    exit(1);
}

$options = getopt('', ['env::', 'sqlite::', 'help']);

if (isset($options['help'])) {
    echo "VOAK HEALTH CARE — Cryptographic Audit Chain Verification CLI (ADM-019 / TEST-ADM-019)\n";
    echo "Usage:\n";
    echo "  php backend/cli/verify_audit_chain.php [--env=/path/to/.env] [--sqlite=/path/to/db.sqlite] [--help]\n";
    exit(0);
}

$envPath = isset($options['env']) ? (string)$options['env'] : null;

try {
    if (isset($options['sqlite']) && !empty($options['sqlite'])) {
        $sqlitePath = (string)$options['sqlite'];
        $pdo = new \PDO("sqlite:{$sqlitePath}");
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $connection = new \Voak\Health\Database\Connection();
        $connection->setMockPdo($pdo);
        $auditLogger = new \Voak\Health\Security\AuditLogger();
        $auditService = new AuditService($connection, $auditLogger);
    } else {
        $app = App::bootstrap($envPath);
        /** @var AuditService $auditService */
        $auditService = $app->getContainer()->get(AuditService::class);
    }

    echo "======================================================================\n";
    echo "VOAK HEALTH CARE — CRYPTOGRAPHIC AUDIT CHAIN VERIFICATION (TEST-ADM-019)\n";
    echo "======================================================================\n";
    echo "Timestamp: " . gmdate('Y-m-d H:i:s') . " UTC\n\n";

    $startTime = microtime(true);
    $report = $auditService->verifyChainIntegrity();
    $elapsed = round((microtime(true) - $startTime) * 1000, 2);

    echo "Total Records Verified: " . $report['total_records'] . "\n";
    echo "Genesis Hash:           " . $report['genesis_hash'] . "\n";
    echo "Head Hash:              " . $report['head_hash'] . "\n";
    echo "Verification Time:      {$elapsed} ms\n";

    if ($report['valid']) {
        echo "\n[PASS] AUDIT CHAIN INTEGRITY 100% VERIFIED: ZERO TAMPERING DETECTED\n";
        echo "======================================================================\n";
        exit(0);
    } else {
        fwrite(STDERR, "\n[FAIL] TAMPERING DETECTED IN AUDIT LEDGER!\n");
        fwrite(STDERR, "  Tampered At Record ID: " . ($report['tampered_at_id'] ?? 'unknown') . "\n");
        fwrite(STDERR, "  Error Detail:          " . ($report['error'] ?? 'Unknown hash mismatch') . "\n");
        echo "======================================================================\n";
        exit(1);
    }
} catch (\Throwable $e) {
    fwrite(STDERR, "CRITICAL ERROR executing audit chain verification: " . $e->getMessage() . "\n");
    exit(2);
}
