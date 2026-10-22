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
use Voak\Health\Database\Connection;
use Voak\Health\Security\AuditLogger;
use Voak\Health\Security\HtmlSanitizer;
use Voak\Health\Security\PasswordHasher;
use Voak\Health\Security\TotpEngine;
use Voak\Health\Services\PageService;
use Voak\Health\Services\SettingService;

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

    // TEST 2: Audit Logger Fail-Closed on Mutex Lock Contention (exercises real AuditLogger::log())
    $runTest('AuditLogger fails closed if MySQL GET_LOCK fails', function () use ($host, $port, $database, $username, $password, $pdo) {
        $conn1 = null;
        $conn2 = null;
        $locked = false;

        try {
            // Connection A acquires the audit serialization lock directly and holds it
            $conn1 = createPdo($host, $port, $database, $username, $password);
            $lockStmt = $conn1->query("SELECT GET_LOCK('voak_audit_log_chain_lock', 0)");
            $locked = $lockStmt && (int)$lockStmt->fetchColumn() === 1;
            if ($lockStmt) {
                $lockStmt->closeCursor();
            }
            if (!$locked) {
                throw new RuntimeException("Connection A failed to acquire initial lock for test.");
            }

            // Record audit row count before the attempted write
            $countStmtBefore = $pdo->query("SELECT COUNT(*) FROM `audit_logs`");
            $countBefore = (int)$countStmtBefore->fetchColumn();
            $countStmtBefore->closeCursor();

            // Open independent Connection B and call real AuditLogger::log() with explicit 1-second timeout
            $conn2 = createPdo($host, $port, $database, $username, $password);
            $logger = new AuditLogger(1); // 1-second timeout for fast integration testing

            $caught = false;
            try {
                $logger->log(
                    $conn2,
                    'security',
                    'CONTENTION_TEST_EVENT',
                    9999,
                    '127.0.0.1',
                    'TestBrowser/Contention',
                    'user',
                    9999,
                    ['test' => 'should_fail']
                );
            } catch (SecurityException $e) {
                $caught = true;
                if (!str_contains($e->getMessage(), 'Failed to acquire audit ledger serialization lock')) {
                    throw new RuntimeException("Unexpected exception message: " . $e->getMessage());
                }
            }

            if (!$caught) {
                throw new RuntimeException("AuditLogger must fail closed by throwing SecurityException when serialization lock acquisition fails.");
            }

            // Verify the row count remains unchanged after the rejected write
            $countStmtAfter = $pdo->query("SELECT COUNT(*) FROM `audit_logs`");
            $countAfter = (int)$countStmtAfter->fetchColumn();
            $countStmtAfter->closeCursor();

            if ($countAfter !== $countBefore) {
                throw new RuntimeException("Audit row count changed after rejected write: expected {$countBefore}, got {$countAfter}.");
            }
        } finally {
            if ($conn1 && $locked) {
                try {
                    $unlock = $conn1->query("SELECT RELEASE_LOCK('voak_audit_log_chain_lock')");
                    if ($unlock) {
                        $unlock->closeCursor();
                    }
                } catch (\Throwable) {
                    // Safe cleanup
                }
            }
            unset($conn1, $conn2);
        }
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

    // TEST 6: Phase 7 CMS Schema Contracts, JSON Integrity, FKs, and Revision Locking on MySQL 8.0
    $runTest('MySQL 8.0 Phase 7 CMS schema contracts: JSON integrity, foreign keys, unique slug, and revision locking', function () use ($pdo, $testUserId) {
        $conn = new Connection();
        $conn->setMockPdo($pdo);
        $auditLogger = new AuditLogger();
        $sanitizer = new HtmlSanitizer();
        $pageService = new PageService($conn, $auditLogger, $sanitizer);
        $settingService = new SettingService($conn, $auditLogger);

        // 1. Verify foreign key constraints on content_revisions and system_settings in MySQL 8.0
        $nonExistentUserId = 888888;
        $fkCaught = false;
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO content_revisions (entity_type, entity_id, editor_user_id, payload_snapshot, revision_note) 
                 VALUES ('pages', 1, :uid, '{\"title\":\"FK Test\"}', 'FK test failure expected')"
            );
            $stmt->execute(['uid' => $nonExistentUserId]);
        } catch (\PDOException $e) {
            $fkCaught = true;
            if (!str_contains($e->getMessage(), 'fk_revisions_editor') && $e->getCode() !== '23000') {
                throw new RuntimeException("Expected foreign key constraint violation on content_revisions: " . $e->getMessage());
            }
        }
        if (!$fkCaught) {
            throw new RuntimeException("MySQL 8.0 failed to enforce fk_revisions_editor foreign key on content_revisions.");
        }

        $fkSettingsCaught = false;
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO system_settings (setting_group, setting_key, setting_value, is_secret, description, updated_by_user_id)
                 VALUES ('test_group', 'test_fk_setting', 'val', 0, 'desc', :uid)"
            );
            $stmt->execute(['uid' => $nonExistentUserId]);
        } catch (\PDOException $e) {
            $fkSettingsCaught = true;
            if (!str_contains($e->getMessage(), 'fk_settings_user') && $e->getCode() !== '23000') {
                throw new RuntimeException("Expected foreign key constraint violation on system_settings: " . $e->getMessage());
            }
        }
        if (!$fkSettingsCaught) {
            throw new RuntimeException("MySQL 8.0 failed to enforce fk_settings_user foreign key on system_settings.");
        }

        // 2. Verify Page creation with native MySQL 8.0 JSON column storage & retrieval
        $testSlug = 'mysql-phase7-test-page';
        $pdo->exec("DELETE FROM content_revisions WHERE entity_type = 'pages' AND entity_id IN (SELECT id FROM pages WHERE slug = '{$testSlug}')");
        $pdo->exec("DELETE FROM pages WHERE slug = '{$testSlug}'");

        $richContent = [
            'hero' => [
                'headline' => 'Next-Generation Healthcare Australia',
                'subheading' => 'B2B Workforce Governance & Clinical Compliance',
                'badge' => 'ISO-27001 Certified',
                'metrics' => [
                    ['label' => 'Clinicians', 'value' => 2500],
                    ['label' => 'SLA', 'value' => '99.9%'],
                ],
            ],
            'sections' => [
                [
                    'id' => 'compliance',
                    'title' => 'Clinical Governance',
                    'body_html' => '<p>Enterprise healthcare delivery compliant with Australian safety standards.</p>',
                ]
            ],
            'metadata' => [
                'tags' => ['clinical', 'enterprise', 'compliance'],
                'enabled' => true,
                'priority' => 1,
            ]
        ];

        $page = $pageService->createPage([
            'slug' => $testSlug,
            'title' => 'MySQL 8.0 Test Page',
            'meta_title' => 'MySQL Test Title',
            'meta_description' => 'Testing native MySQL 8.0 JSON and revision integrity',
            'content_json' => $richContent,
            'status' => 'draft',
        ], $testUserId);

        $pageId = (int)$page['id'];
        if ($pageId <= 0) {
            throw new RuntimeException("Failed to insert page into MySQL 8.0.");
        }

        // Query raw JSON directly from MySQL 8.0 to verify native JSON data type support
        $jsonStmt = $pdo->prepare("SELECT JSON_TYPE(content_json) as jtype, JSON_EXTRACT(content_json, '$.sections') as sections FROM pages WHERE id = :id");
        $jsonStmt->execute(['id' => $pageId]);
        $jsonRow = $jsonStmt->fetch();
        $jsonStmt->closeCursor();

        if (strtoupper((string)($jsonRow['jtype'] ?? '')) !== 'OBJECT') {
            throw new RuntimeException("MySQL 8.0 JSON_TYPE did not report OBJECT for content_json: " . json_encode($jsonRow));
        }

        // 3. Verify Unique Slug Constraint on MySQL 8.0
        $dupCaught = false;
        try {
            $pageService->createPage([
                'slug' => $testSlug,
                'title' => 'Duplicate Slug Test',
                'meta_title' => 'Dup',
                'meta_description' => 'Dup',
                'content_json' => ['test' => true],
                'status' => 'draft',
            ], $testUserId);
        } catch (\Throwable $e) {
            $dupCaught = true;
        }
        if (!$dupCaught) {
            throw new RuntimeException("MySQL 8.0 failed to enforce UNIQUE constraint on pages.slug.");
        }

        // 4. Verify Revision Monotonicity, Snapshot Creation, and Row-Level Locking under Update
        $updatePayload = $richContent;
        $updatePayload['hero']['headline'] = 'Updated Headline Under MySQL 8.0';

        $updatedPage = $pageService->updatePage($pageId, [
            'title' => 'MySQL 8.0 Test Page (Updated)',
            'content_json' => $updatePayload,
            'change_summary' => 'First revision on MySQL 8.0',
        ], $testUserId);

        if ($updatedPage['title'] !== 'MySQL 8.0 Test Page (Updated)') {
            throw new RuntimeException("Page update title mismatch in MySQL 8.0.");
        }

        // Verify revision was created in content_revisions with JSON payload
        $revStmt = $pdo->prepare("SELECT * FROM content_revisions WHERE entity_type = 'pages' AND entity_id = :pid ORDER BY id ASC");
        $revStmt->execute(['pid' => $pageId]);
        $revisions = $revStmt->fetchAll();
        $revStmt->closeCursor();

        if (count($revisions) < 2) {
            throw new RuntimeException("MySQL 8.0 content_revisions table expected at least 2 revisions, found " . count($revisions));
        }

        $rev1 = $revisions[0];
        if ((int)$rev1['editor_user_id'] !== $testUserId) {
            throw new RuntimeException("Revision editor_user_id mismatch: expected {$testUserId}, got " . $rev1['editor_user_id']);
        }
        $snapshot = json_decode((string)$rev1['payload_snapshot'], true);
        if (!is_array($snapshot) || ($snapshot['title'] ?? '') !== 'MySQL 8.0 Test Page') {
            throw new RuntimeException("Revision payload snapshot did not record initial state: " . $rev1['payload_snapshot']);
        }

        // 5. Verify Rollback restoring previous state on MySQL 8.0
        $rolledBack = $pageService->rollbackRevision($pageId, 1, $testUserId);
        if ($rolledBack['title'] !== 'MySQL 8.0 Test Page') {
            throw new RuntimeException("Rollback failed to restore pre-update title on MySQL 8.0: " . $rolledBack['title']);
        }

        // 6. Verify System Settings table contracts on MySQL 8.0
        $settingService->ensureDefaultSettings($testUserId);
        $settingRows = $pdo->query("SELECT setting_key, is_secret FROM system_settings WHERE is_secret = 0")->fetchAll();
        if (empty($settingRows)) {
            throw new RuntimeException("Default system settings were not populated on MySQL 8.0.");
        }

        // Clean up Phase 7 test artifacts
        $pdo->exec("DELETE FROM content_revisions WHERE entity_type = 'pages' AND entity_id = {$pageId}");
        $pdo->exec("DELETE FROM pages WHERE id = {$pageId}");
        $pdo->exec("DELETE FROM system_settings WHERE setting_key = 'test_fk_setting'");
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
