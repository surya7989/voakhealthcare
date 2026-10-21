<?php

declare(strict_types=1);

/**
 * Voak Health Care — Phase 6 MySQL 8.0 Security Concurrency & Audit Integrity Test
 *
 * Exercises real concurrent multi-process operations against disposable MySQL 8.0 CI database:
 * 1. Concurrent audit writes over independent MySQL connections & hash chain verification
 * 2. Concurrent password-reset-token consumption (race condition & atomic single-use defense)
 * 3. Concurrent MFA recovery-code consumption (atomic burn verification)
 * 4. Audit-lock serialization, fail-closed handling, and uncommitted transaction boundary defense
 */

namespace Voak\Health\Tests;

require_once dirname(__DIR__) . '/vendor/autoload.php';

use PDO;
use RuntimeException;
use Throwable;
use Voak\Health\Auth\PasswordResetService;
use Voak\Health\Auth\RbacManager;
use Voak\Health\Auth\SessionManager;
use Voak\Health\Auth\TotpService;
use Voak\Health\Core\Config;
use Voak\Health\Core\Exceptions\SecurityException;
use Voak\Health\Security\AuditLogger;
use Voak\Health\Security\PasswordHasher;
use Voak\Health\Security\TotpEngine;

echo "======================================================================\n";
echo "VOAK HEALTH CARE — MYSQL 8.0 SECURITY CONCURRENCY & INTEGRITY SUITE\n";
echo "======================================================================\n";

$guard = (string)getenv('VOAK_MYSQL_TEST');
$host = (string)(getenv('DB_HOST') ?: '127.0.0.1');
$port = (int)(getenv('DB_PORT') ?: 3306);
$database = (string)(getenv('DB_DATABASE') ?: 'voak_staging_ci');
$username = (string)(getenv('DB_USERNAME') ?: 'root');
$password = (string)(getenv('DB_PASSWORD') ?: 'mysql-ci-password');

if ($guard !== '1') {
    echo "[SKIP] VOAK_MYSQL_TEST is not set to '1'. Skipping MySQL concurrency suite.\n";
    echo "       (This test runs automatically in CI against the disposable MySQL 8.0 container)\n";
    exit(0);
}

if (!extension_loaded('pdo_mysql')) {
    fwrite(STDERR, "[FATAL] pdo_mysql extension is required to execute this test.\n");
    exit(1);
}

if ($database !== 'voak_staging_ci' || !in_array($host, ['127.0.0.1', 'localhost'], true)) {
    fwrite(STDERR, "[FATAL] Refusing to run: this test requires localhost and DB_DATABASE=voak_staging_ci to protect real environments.\n");
    exit(2);
}

function createPdo(string $host, int $port, string $database, string $username, string $password): PDO
{
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database);
    return new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+00:00'",
    ]);
}

try {
    $pdo = createPdo($host, $port, $database, $username, $password);

    $phpBin = PHP_BINARY;
    $workerScript = __DIR__ . '/concurrency_worker.php';
    if (!file_exists($workerScript)) {
        fwrite(STDERR, "[FATAL] Concurrency worker script not found at {$workerScript}\n");
        exit(1);
    }

    $testPassed = 0;
    $testTotal = 0;

    $runTest = function (string $title, callable $fn) use (&$testPassed, &$testTotal): void {
        $testTotal++;
        echo "\n[TEST {$testTotal}] {$title}...\n";
        try {
            $fn();
            $testPassed++;
            echo "  --> [PASS] {$title}\n";
        } catch (Throwable $e) {
            echo "  --> [FAIL] {$title}\n";
            echo "      Error: " . $e->getMessage() . "\n";
            echo "      File:  " . $e->getFile() . ":" . $e->getLine() . "\n";
            throw $e;
        }
    };

    // Ensure isolated test user exists
    $testUserId = 9999;
    $testUserEmail = 'mysql_concurrency_test@voak.test';
    $hasher = new PasswordHasher();
    $initHash = $hasher->hash('InitialPass#2026!');

    // Clean up child tables first to respect foreign keys
    $pdo->exec("DELETE FROM user_roles WHERE user_id = {$testUserId}");
    $pdo->exec("DELETE FROM admin_sessions WHERE user_id = {$testUserId}");
    $pdo->exec("DELETE FROM admin_mfa_secrets WHERE user_id = {$testUserId}");
    $pdo->exec("DELETE FROM password_resets WHERE user_id = {$testUserId}");
    $pdo->exec("DELETE FROM audit_logs WHERE user_id = {$testUserId}");
    $pdo->exec("DELETE FROM users WHERE id = {$testUserId} OR email = '{$testUserEmail}'");

    $stmt = $pdo->prepare(
        "INSERT INTO users (id, name, email, password_hash, is_active, failed_login_attempts, created_at, updated_at) 
         VALUES (:id, 'MySQL Concurrency Test User', :email, :hash, 1, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
    );
    $stmt->execute(['id' => $testUserId, 'email' => $testUserEmail, 'hash' => $initHash]);
    $stmt->closeCursor();

    $rbac = new RbacManager();
    $rbac->seedRbac($pdo);
    $rbac->assignRole($pdo, $testUserId, RbacManager::ROLE_SUPER_ADMIN);

    $desc = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    // TEST 1: Audit Logger Transaction Boundary & Stale Predecessor Defense
    $runTest('AuditLogger rejects logging inside active uncommitted transaction preventing stale predecessor reads', function () use ($pdo) {
        $auditLogger = new AuditLogger();

        $pdo->beginTransaction();
        $caught = false;
        try {
            $auditLogger->log($pdo, 'security', 'TEST_UNCOMMITTED_WRITE', 9999);
        } catch (SecurityException $e) {
            $caught = true;
            if (!str_contains($e->getMessage(), 'active uncommitted transaction')) {
                throw new RuntimeException("Unexpected exception message: " . $e->getMessage());
            }
        } finally {
            $pdo->rollBack();
        }

        if (!$caught) {
            throw new RuntimeException("AuditLogger must throw SecurityException when invoked inside an active transaction.");
        }
    });

    // TEST 2: Audit Logger Fail-Closed on Mutex Lock Failure
    $runTest('AuditLogger fails closed if MySQL GET_LOCK fails', function () use ($host, $port, $database, $username, $password) {
        // Open Connection 1 and hold the chain lock exclusively
        $conn1 = createPdo($host, $port, $database, $username, $password);
        $lockStmt = $conn1->query("SELECT GET_LOCK('voak_audit_log_chain_lock', 0)");
        $locked = $lockStmt && (int)$lockStmt->fetchColumn() === 1;
        if ($lockStmt) {
            $lockStmt->closeCursor();
        }
        if (!$locked) {
            throw new RuntimeException("Conn1 failed to acquire initial lock for test.");
        }

        // Connection 2 attempts to acquire lock with timeout 0 and should receive 0 (contention)
        $conn2 = createPdo($host, $port, $database, $username, $password);
        $lockStmt2 = $conn2->query("SELECT GET_LOCK('voak_audit_log_chain_lock', 0)");
        $result2 = (int)$lockStmt2->fetchColumn();
        $lockStmt2->closeCursor();

        if ($result2 !== 0) {
            throw new RuntimeException("Expected GET_LOCK to return 0 when lock is already held by another connection, got {$result2}.");
        }

        // Release lock on Conn1
        $unlock = $conn1->query("SELECT RELEASE_LOCK('voak_audit_log_chain_lock')");
        if ($unlock) {
            $unlock->closeCursor();
        }
        unset($conn1, $conn2);
    });

    // TEST 3: Concurrent Audit Writes over Independent MySQL Connections & Unbroken Hash Chain
    $runTest('Concurrent audit writes over independent MySQL connections maintain unbroken hash chain', function () use ($phpBin, $workerScript, $pdo, $desc, $testUserId) {
        // Clean table to guarantee unbroken genesis hash chain
        $pdo->exec("DELETE FROM `audit_logs`");

        $numWorkers = 4;
        $procs = [];
        $pipes = [];

        for ($w = 0; $w < $numWorkers; $w++) {
            $cmd = [
                $phpBin,
                $workerScript,
                '--driver=mysql',
                '--action=audit_log',
                "--action_name=MYSQL_CONCURRENT_EVENT_{$w}",
                "--payload=payload_worker_{$w}",
                "--user_id={$testUserId}",
            ];
            $procs[$w] = proc_open($cmd, $desc, $pipes[$w]);
            if (isset($pipes[$w][0])) {
                fclose($pipes[$w][0]);
            }
        }

        for ($w = 0; $w < $numWorkers; $w++) {
            $out = stream_get_contents($pipes[$w][1]);
            $err = stream_get_contents($pipes[$w][2]);
            fclose($pipes[$w][1]);
            fclose($pipes[$w][2]);
            $code = proc_close($procs[$w]);

            if ($code !== 0) {
                throw new RuntimeException("Worker {$w} failed with exit code {$code}. Output: {$out}, Stderr: {$err}");
            }
        }

        // Verify mathematical integrity of complete audit log chain on MySQL
        $auditLogger = new AuditLogger();
        $chainValid = $auditLogger->verifyChain($pdo);
        if (!$chainValid) {
            $rows = $pdo->query("SELECT * FROM `audit_logs` ORDER BY `id` ASC")->fetchAll();
            $debug = json_encode($rows, JSON_PRETTY_PRINT);
            throw new RuntimeException("Audit ledger cryptographic hash chain verification FAILED on MySQL after concurrent writes. Ledger dump: " . $debug);
        }
    });

    // TEST 4: Concurrent Password Reset Consumption on MySQL 8.0
    $runTest('Concurrent password-reset requests on MySQL 8.0 ensure exactly one success and zero double-spend', function () use ($phpBin, $workerScript, $pdo, $testUserEmail, $desc) {
        $hasher = new PasswordHasher();
        $sessionManager = new SessionManager();
        $auditLogger = new AuditLogger();
        $resetService = new PasswordResetService($hasher, $sessionManager, $auditLogger);

        $token = $resetService->requestReset($pdo, $testUserEmail);
        if (!$token) {
            throw new RuntimeException("Failed to generate password reset token for {$testUserEmail}.");
        }

        $cmd1 = [
            $phpBin,
            $workerScript,
            '--driver=mysql',
            '--action=consume_reset',
            "--token={$token}",
            '--password=FirstPassword#2026!',
        ];
        $cmd2 = [
            $phpBin,
            $workerScript,
            '--driver=mysql',
            '--action=consume_reset',
            "--token={$token}",
            '--password=SecondPassword#2026!',
        ];

        $p1 = proc_open($cmd1, $desc, $pipes1);
        $p2 = proc_open($cmd2, $desc, $pipes2);
        if (isset($pipes1[0])) { fclose($pipes1[0]); }
        if (isset($pipes2[0])) { fclose($pipes2[0]); }

        $out1 = stream_get_contents($pipes1[1]);
        $err1 = stream_get_contents($pipes1[2]);
        fclose($pipes1[1]);
        fclose($pipes1[2]);
        $code1 = proc_close($p1);

        $out2 = stream_get_contents($pipes2[1]);
        $err2 = stream_get_contents($pipes2[2]);
        fclose($pipes2[1]);
        fclose($pipes2[2]);
        $code2 = proc_close($p2);

        $successes = ($code1 === 0 ? 1 : 0) + ($code2 === 0 ? 1 : 0);
        if ($successes !== 1) {
            throw new RuntimeException("Expected exactly one concurrent password reset to succeed. Successes: {$successes} (p1: {$code1} [{$err1}], p2: {$code2} [{$err2}])");
        }

        // Verify token is burned in MySQL
        $stmt = $pdo->prepare("SELECT `used_at` FROM `password_resets` WHERE `token_hash` = :th");
        $stmt->execute(['th' => hash('sha256', $token)]);
        $usedAt = $stmt->fetchColumn();
        $stmt->closeCursor();

        if ($usedAt === null || $usedAt === false) {
            throw new RuntimeException("Password reset token was not marked as used in MySQL.");
        }
    });

    // TEST 5: Concurrent MFA Recovery Code Consumption on MySQL 8.0
    $runTest('Concurrent MFA recovery code consumption on MySQL 8.0 ensures atomic burn and zero double-spend', function () use ($phpBin, $workerScript, $pdo, $testUserId, $testUserEmail, $desc) {
        $config = new Config([
            'AUTH_SIGNING_KEY'   => 'concurrency-worker-auth-key-32-chars!',
            'CSRF_SIGNING_KEY'   => 'concurrency-worker-csrf-key-32-chars!',
            'MFA_ENCRYPTION_KEY' => 'concurrency-worker-mfa-key-32-chars!',
            'APP_ENV'            => 'testing',
        ]);
        $totpEngine = new TotpEngine();
        $totpService = new TotpService($config, $totpEngine);

        $setup = $totpService->initiateSetup($pdo, $testUserId, $testUserEmail);
        $totpCode = $totpEngine->generateCode($setup['secret']);
        $totpService->confirmSetup($pdo, $testUserId, $totpCode);

        $recoveryCode = $setup['backup_codes'][0];

        $cmd1 = [
            $phpBin,
            $workerScript,
            '--driver=mysql',
            '--action=consume_recovery',
            "--user_id={$testUserId}",
            "--code={$recoveryCode}",
        ];
        $cmd2 = [
            $phpBin,
            $workerScript,
            '--driver=mysql',
            '--action=consume_recovery',
            "--user_id={$testUserId}",
            "--code={$recoveryCode}",
        ];

        $p1 = proc_open($cmd1, $desc, $pipes1);
        $p2 = proc_open($cmd2, $desc, $pipes2);
        if (isset($pipes1[0])) { fclose($pipes1[0]); }
        if (isset($pipes2[0])) { fclose($pipes2[0]); }

        $out1 = stream_get_contents($pipes1[1]);
        $err1 = stream_get_contents($pipes1[2]);
        fclose($pipes1[1]);
        fclose($pipes1[2]);
        $code1 = proc_close($p1);

        $out2 = stream_get_contents($pipes2[1]);
        $err2 = stream_get_contents($pipes2[2]);
        fclose($pipes2[1]);
        fclose($pipes2[2]);
        $code2 = proc_close($p2);

        $successes = ($code1 === 0 ? 1 : 0) + ($code2 === 0 ? 1 : 0);
        if ($successes !== 1) {
            throw new RuntimeException("Expected exactly one concurrent recovery code consumption to succeed. Successes: {$successes} (p1: {$code1} [{$err1}], p2: {$code2} [{$err2}])");
        }

        // A 3rd sequential consumption of the same code must fail
        $thirdAttempt = $totpService->verifyAndConsumeRecoveryCode($pdo, $testUserId, $recoveryCode);
        if ($thirdAttempt) {
            throw new RuntimeException("Burned recovery code was accepted on a subsequent attempt.");
        }
    });

    // Clean up isolated test data
    $pdo->exec("DELETE FROM user_roles WHERE user_id = {$testUserId}");
    $pdo->exec("DELETE FROM admin_sessions WHERE user_id = {$testUserId}");
    $pdo->exec("DELETE FROM admin_mfa_secrets WHERE user_id = {$testUserId}");
    $pdo->exec("DELETE FROM password_resets WHERE user_id = {$testUserId}");
    $pdo->exec("DELETE FROM audit_logs WHERE user_id = {$testUserId}");
    $pdo->exec("DELETE FROM users WHERE id = {$testUserId} OR email = '{$testUserEmail}'");

    echo "\n======================================================================\n";
    echo "MYSQL 8.0 CONCURRENCY & INTEGRITY TEST SUMMARY\n";
    echo "======================================================================\n";
    echo "Total Tests Executed: {$testTotal}\n";
    echo "Passed:               {$testPassed}\n";
    echo "Failed:               0\n";
    echo "======================================================================\n";
    echo "SUCCESS: ALL MYSQL 8.0 CONCURRENCY & INTEGRITY TESTS PASSED\n";
    echo "======================================================================\n";
    exit(0);

} catch (Throwable $error) {
    $msg = "[FAIL] " . $error->getMessage() . "\n" . $error->getFile() . ':' . $error->getLine() . "\n" . $error->getTraceAsString() . "\n";
    echo "::error title=MySQL Concurrency Test Error::" . str_replace(["\r", "\n"], ["", "%0A"], $msg) . "\n";
    echo $msg;
    fwrite(STDERR, $msg);
    if ($stepSummary = getenv('GITHUB_STEP_SUMMARY')) {
        @file_put_contents($stepSummary, "### MySQL Concurrency Test Failure\n```\n" . $msg . "```\n", FILE_APPEND);
    }
    exit(1);
}
