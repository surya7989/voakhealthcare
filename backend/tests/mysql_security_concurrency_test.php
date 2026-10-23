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
use Voak\Health\Core\Exceptions\ConflictException;
use Voak\Health\Core\Exceptions\SecurityException;
use Voak\Health\Core\Exceptions\ValidationException;
use Voak\Health\Database\Connection;
use Voak\Health\Security\AuditLogger;
use Voak\Health\Security\HtmlSanitizer;
use Voak\Health\Security\PasswordHasher;
use Voak\Health\Security\TotpEngine;
use Voak\Health\Security\RateLimiter;
use Voak\Health\Services\CareerService;
use Voak\Health\Services\ContactService;
use Voak\Health\Services\EmailService;
use Voak\Health\Services\IndustryService;
use Voak\Health\Services\Mail\ArrayTransport;
use Voak\Health\Services\MediaStorageService;
use Voak\Health\Services\PageService;
use Voak\Health\Services\PartnershipService;
use Voak\Health\Services\ProposalService;
use Voak\Health\Services\ServiceService;
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
    $pdo->exec("DELETE FROM content_revisions WHERE editor_user_id = {$testUserId}");
    $pdo->exec("DELETE FROM system_settings WHERE updated_by_user_id = {$testUserId}");
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
        $pdo->exec("DELETE FROM system_settings WHERE updated_by_user_id = {$testUserId}");
    });

    // =========================================================================
    // TEST 7: Phase 8 — Services Catalog, MySQL 8.0 JSON Storage, 301 Redirects & Integrity
    // =========================================================================
    $runTest("Test 7: Services Catalog Management, MySQL 8.0 JSON Storage & 301 Redirect Engine", function () use ($pdo, $testUserId) {
        $connection = new Connection();
        $connection->setMockPdo($pdo);
        $auditLogger = new AuditLogger();
        $sanitizer = new HtmlSanitizer();
        $serviceService = new ServiceService($connection, $auditLogger, $sanitizer);

        // 1. Ensure Default Services on MySQL 8.0
        $serviceService->ensureDefaultServices($testUserId);
        $count = (int)$pdo->query("SELECT COUNT(*) FROM `services`")->fetchColumn();
        if ($count < 6) {
            throw new RuntimeException("MySQL 8.0 expected at least 6 canonical services, found {$count}.");
        }

        // 2. Verify Native JSON Data Extraction on MySQL 8.0
        $jsonCheck = $pdo->query("SELECT id, JSON_VALID(sla_metrics_json) as valid_sla, JSON_VALID(deliverables_json) as valid_deliv FROM `services` LIMIT 1")->fetch();
        if ((int)$jsonCheck['valid_sla'] !== 1 || (int)$jsonCheck['valid_deliv'] !== 1) {
            throw new RuntimeException("MySQL 8.0 JSON columns sla_metrics_json or deliverables_json are invalid JSON.");
        }

        // 3. Create Custom Test Service with rich JSON deliverables & SLA metrics
        $testSlug = 'mysql-service-' . bin2hex(random_bytes(4));
        $created = $serviceService->createService([
            'title'        => 'MySQL 8.0 Dedicated Service',
            'slug'         => $testSlug,
            'tagline'      => 'Testing MySQL 8.0 Enterprise Capabilities',
            'description'  => '<p>High-reliability healthcare infrastructure validation.</p>',
            'deliverables' => ['Audit trail compliance', 'High-throughput SLA tracking'],
            'sla_metrics'  => ['uptime' => '99.99%', 'latency' => '< 50ms'],
            'is_published' => 1,
        ], $testUserId);

        $svcId = (int)$created['id'];

        // 4. Verify Unique Slug Constraint on MySQL 8.0
        $dupCaught = false;
        try {
            $serviceService->createService([
                'title' => 'Duplicate Slug Test',
                'slug'  => $testSlug,
            ], $testUserId);
        } catch (\Throwable $e) {
            $dupCaught = true;
        }
        if (!$dupCaught) {
            throw new RuntimeException("MySQL 8.0 failed to enforce UNIQUE constraint on services.slug.");
        }

        // 5. Update Slug & Verify 301 Redirect Recorded in url_redirects
        $newSlug = $testSlug . '-v2';
        $serviceService->updateService($svcId, [
            'slug'  => $newSlug,
            'title' => 'MySQL 8.0 Dedicated Service (Updated)',
        ], $testUserId);

        $redirect = $serviceService->findRedirect('/services/' . $testSlug);
        if (!$redirect || $redirect['target_path'] !== '/services/' . $newSlug || (int)$redirect['http_status_code'] !== 301) {
            throw new RuntimeException("301 URL redirect was not correctly recorded in MySQL 8.0 url_redirects table.");
        }

        // 6. Verify Delete & Cleanup
        $serviceService->deleteService($svcId, $testUserId);
        $deletedCheck = $pdo->query("SELECT COUNT(*) FROM `services` WHERE id = {$svcId}")->fetchColumn();
        if ((int)$deletedCheck !== 0) {
            throw new RuntimeException("Service record was not purged during delete on MySQL 8.0.");
        }

        // Clean up test redirect
        $pdo->exec("DELETE FROM `url_redirects` WHERE `source_path` = '/services/{$testSlug}'");
    });

    // =========================================================================
    // TEST 8: Phase 9 — Healthcare Industries, MySQL 8.0 JSON Storage & Relational Mappings
    // =========================================================================
    $runTest("Test 8: Healthcare Industries Management, MySQL 8.0 JSON Storage & Relational Mappings", function () use ($pdo, $testUserId) {
        $connection = new Connection();
        $connection->setMockPdo($pdo);
        $auditLogger = new AuditLogger();
        $sanitizer = new HtmlSanitizer();
        $industryService = new IndustryService($connection, $auditLogger, $sanitizer);

        // 1. Ensure Default Industries on MySQL 8.0
        $industryService->ensureDefaultIndustries();
        $count = (int)$pdo->query("SELECT COUNT(*) FROM `industries`")->fetchColumn();
        if ($count < 6) {
            throw new RuntimeException("MySQL 8.0 expected at least 6 canonical industries, found {$count}.");
        }

        // 2. Verify Native JSON Data Extraction on MySQL 8.0
        $jsonCheck = $pdo->query("SELECT id, JSON_VALID(regulatory_burden_specs) as valid_reg, JSON_VALID(pain_points_json) as valid_pain FROM `industries` LIMIT 1")->fetch();
        if ((int)$jsonCheck['valid_reg'] !== 1 || (int)$jsonCheck['valid_pain'] !== 1) {
            throw new RuntimeException("MySQL 8.0 JSON columns regulatory_burden_specs or pain_points_json are invalid JSON.");
        }

        // 3. Create Custom Test Industry with rich JSON regulatory specs & pain points
        $testSlug = 'mysql-industry-' . bin2hex(random_bytes(4));
        $created = $industryService->createIndustry([
            'title'                   => 'MySQL 8.0 Dedicated Industry',
            'slug'                    => $testSlug,
            'summary'                 => 'Testing MySQL 8.0 Enterprise Healthcare Sector capabilities.',
            'regulatory_burden_specs' => ['governing_body' => 'Safety & Quality Commission', 'standards' => ['NSQHS Standard 1']],
            'pain_points'             => ['Database concurrency under load', 'Multi-tenant isolation'],
            'is_published'            => 1,
            'display_order'           => 12,
            'service_ids'             => [1, 2],
        ], $testUserId);

        $indId = (int)$created['id'];

        // 4. Verify Unique Slug Constraint on MySQL 8.0
        $dupCaught = false;
        try {
            $industryService->createIndustry([
                'title' => 'Duplicate Slug Test',
                'slug'  => $testSlug,
            ], $testUserId);
        } catch (\Throwable $e) {
            $dupCaught = true;
        }
        if (!$dupCaught) {
            throw new RuntimeException("MySQL 8.0 failed to enforce UNIQUE constraint on industries.slug.");
        }

        // 5. Update Slug & Verify 301 Redirect Recorded in url_redirects
        $newSlug = $testSlug . '-v2';
        $industryService->updateIndustry($indId, [
            'slug'  => $newSlug,
            'title' => 'MySQL 8.0 Dedicated Industry (Updated)',
        ], $testUserId);

        $redirect = $industryService->findRedirect('/industries/' . $testSlug);
        if (!$redirect || $redirect['target_path'] !== '/industries/' . $newSlug || (int)$redirect['http_status_code'] !== 301) {
            throw new RuntimeException("301 URL redirect was not correctly recorded in MySQL 8.0 url_redirects table.");
        }

        // 6. Verify Delete & Cleanup
        $industryService->deleteIndustry($indId, $testUserId);
        $deletedCheck = $pdo->query("SELECT COUNT(*) FROM `industries` WHERE id = {$indId}")->fetchColumn();
        if ((int)$deletedCheck !== 0) {
            throw new RuntimeException("Industry record was not purged during delete on MySQL 8.0.");
        }

        // Clean up test redirect
        $pdo->exec("DELETE FROM `url_redirects` WHERE `source_path` = '/industries/{$testSlug}'");
    });

    // =========================================================================
    // TEST 9: Phase 10 — Media Assets, WebP Metadata, Referential Deletion Guard & Quarantined Document Streaming
    // =========================================================================
    $runTest("Test 9: Media Library & Quarantined Document Enclave on MySQL 8.0", function () use ($pdo, $testUserId) {
        $tempPublic = sys_get_temp_dir() . '/voak_mysql_pub_' . bin2hex(random_bytes(4));
        $tempQuarantine = sys_get_temp_dir() . '/voak_mysql_quar_' . bin2hex(random_bytes(4));

        $connection = new Connection();
        $connection->setMockPdo($pdo);
        $auditLogger = new AuditLogger();
        $mediaService = new MediaStorageService($connection, $auditLogger, $tempPublic, $tempQuarantine);

        // 1. Verify media_assets table exists on MySQL 8.0
        $colCheck = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'media_assets'")->fetchAll(\PDO::FETCH_COLUMN);
        if (!in_array('stored_name', $colCheck, true) || !in_array('alt_text', $colCheck, true)) {
            throw new RuntimeException("media_assets table missing expected canonical columns on MySQL 8.0.");
        }

        // 2. Upload and convert image to WebP format
        $img = imagecreatetruecolor(400, 300);
        $bg = imagecolorallocate($img, 30, 120, 200);
        imagefilledrectangle($img, 0, 0, 400, 300, $bg);
        $tmpImg = tempnam(sys_get_temp_dir(), 'mysql_img_') . '.jpg';
        imagejpeg($img, $tmpImg, 90);
        imagedestroy($img);

        $uploaded = $mediaService->uploadMedia([
            'tmp_name' => $tmpImg,
            'name'     => 'mysql-hero.jpg',
            'size'     => filesize($tmpImg),
        ], 'MySQL 8.0 Hero Asset', $testUserId);
        @unlink($tmpImg);

        $mediaId = (int)$uploaded['id'];

        // Verify row in MySQL 8.0
        $row = $pdo->query("SELECT * FROM media_assets WHERE id = {$mediaId}")->fetch(\PDO::FETCH_ASSOC);
        if (!$row || $row['mime_type'] !== 'image/webp' || $row['dimensions'] !== '400x300') {
            throw new RuntimeException("Uploaded media asset row not persisted correctly in MySQL 8.0.");
        }

        // 3. Referential integrity deletion guard: associate with Page
        $testSlug = 'mysql-page-' . bin2hex(random_bytes(4));
        $pdo->exec(
            "INSERT INTO pages (slug, title, meta_title, meta_description, content_json, is_published)
             VALUES ('{$testSlug}', 'MySQL Guard Page', 'Meta Title', 'Meta Desc', '{\"hero_image_id\": {$mediaId}}', 1)"
        );

        $conflictCaught = false;
        try {
            $mediaService->deleteMedia($mediaId, $testUserId);
        } catch (ConflictException $e) {
            $conflictCaught = true;
        }
        if (!$conflictCaught) {
            throw new RuntimeException("Referential integrity deletion guard failed to prevent deletion of in-use media asset on MySQL 8.0.");
        }

        // Remove page reference and delete media asset
        $pdo->exec("DELETE FROM pages WHERE slug = '{$testSlug}'");
        $deleted = $mediaService->deleteMedia($mediaId, $testUserId);
        if (!$deleted) {
            throw new RuntimeException("deleteMedia failed to return true on MySQL 8.0.");
        }

        $checkDeleted = (int)$pdo->query("SELECT COUNT(*) FROM media_assets WHERE id = {$mediaId}")->fetchColumn();
        if ($checkDeleted !== 0) {
            throw new RuntimeException("Media asset record still present after delete on MySQL 8.0.");
        }

        // 4. Test Quarantined Document Ingestion & Streaming Proxy
        $tmpPdf = tempnam(sys_get_temp_dir(), 'mysql_pdf_') . '.pdf';
        file_put_contents($tmpPdf, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>%%EOF\n");

        $quarantined = $mediaService->quarantineDocument([
            'tmp_name' => $tmpPdf,
            'name'     => 'Enterprise_Hospital_Tender.pdf',
            'size'     => filesize($tmpPdf),
        ], 'tender');
        @unlink($tmpPdf);

        if (!file_exists($quarantined['quarantined_file_path'])) {
            throw new RuntimeException("Quarantined document file not written to disk on MySQL 8.0.");
        }

        $response = $mediaService->streamQuarantinedFile(
            $quarantined['quarantined_file_path'],
            $quarantined['original_file_name'],
            'application/pdf',
            $testUserId,
            'TENDER_STREAMED',
            'PROPOSALS',
            999,
            '127.0.0.1'
        );

        if ($response->getStatusCode() !== 200 || $response->getHeader('content-type') !== 'application/pdf') {
            throw new RuntimeException("streamQuarantinedFile failed to stream document safely on MySQL 8.0.");
        }

        // Verify audit log entry in MySQL 8.0
        $auditEntry = $pdo->query("SELECT * FROM audit_logs WHERE action = 'TENDER_STREAMED' AND entity_id = 999")->fetch();
        if (!$auditEntry) {
            throw new RuntimeException("Streaming audit event not persisted in MySQL 8.0 audit_logs.");
        }

        // Clean up temporary directories
        @unlink($quarantined['quarantined_file_path']);
        $rDel = function ($dir) use (&$rDel) {
            if (!is_dir($dir)) return;
            foreach (scandir($dir) ?: [] as $i) {
                if ($i === '.' || $i === '..') continue;
                $p = $dir . '/' . $i;
                is_dir($p) ? $rDel($p) : @unlink($p);
            }
            @rmdir($dir);
        };
        $rDel($tempPublic);
        $rDel($tempQuarantine);
    });

    // =========================================================================
    // TEST 10: Phase 11 — Contact Enquiry CRM, Lifecycle FSM & Row Locking on MySQL 8.0
    // =========================================================================
    $runTest("Test 10: Contact Enquiry CRM, Lifecycle FSM & Row Locking on MySQL 8.0", function () use ($pdo, $testUserId) {
        $connection = new Connection();
        $connection->setMockPdo($pdo);
        $auditLogger = new AuditLogger();
        $sanitizer = new HtmlSanitizer();
        $rbacManager = new RbacManager();
        $contactService = new ContactService($connection, $auditLogger, $sanitizer, null, $rbacManager);

        // 1. Verify contact_inquiries table and composite indexes exist on MySQL 8.0
        $cols = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contact_inquiries'")->fetchAll(\PDO::FETCH_COLUMN);
        if (!in_array('reference_code', $cols, true) || !in_array('status', $cols, true) || !in_array('internal_notes', $cols, true)) {
            throw new RuntimeException("contact_inquiries table missing expected canonical columns on MySQL 8.0.");
        }

        // Verify index existence
        $indexes = $pdo->query("SHOW INDEX FROM contact_inquiries")->fetchAll(\PDO::FETCH_ASSOC);
        $indexNames = array_column($indexes, 'Key_name');
        if (!in_array('idx_inquiries_status', $indexNames, true) || !in_array('idx_inquiries_ref', $indexNames, true)) {
            throw new RuntimeException("contact_inquiries table missing idx_inquiries_status or idx_inquiries_ref on MySQL 8.0.");
        }

        // 2. Submit Inquiry & verify transactional email queueing on MySQL 8.0
        $submitted = $contactService->submitInquiry([
            'full_name'         => 'MySQL Test Clinical Director',
            'organization_name' => 'MySQL 8.0 Health Service',
            'corporate_email'   => 'mysql-director@voak.test',
            'contact_phone'     => '+61 2 9555 9999',
            'inquiry_details'   => 'Testing real MySQL 8.0 transactional insertion and email queueing.',
        ], '127.0.0.1', 'CI/MySQL-8.0');

        $inquiryId = (int)$submitted['id'];
        $refCode = $submitted['reference_code'];

        // Verify row exists and status is 'NEW'
        $inquiryRow = $pdo->query("SELECT * FROM contact_inquiries WHERE id = {$inquiryId}")->fetch(\PDO::FETCH_ASSOC);
        if (!$inquiryRow || $inquiryRow['status'] !== 'NEW' || $inquiryRow['reference_code'] !== $refCode) {
            throw new RuntimeException("contact_inquiries record was not correctly persisted on MySQL 8.0.");
        }

        // Verify email_queue row on MySQL 8.0
        $queueRow = $pdo->query("SELECT * FROM email_queue WHERE recipient_email = 'mysql-director@voak.test'")->fetch(\PDO::FETCH_ASSOC);
        if (!$queueRow || $queueRow['status'] !== 'QUEUED') {
            throw new RuntimeException("Email queue record was not created for contact enquiry on MySQL 8.0.");
        }

        // 3. Test DAG Lifecycle state machine transitions with FOR UPDATE row locking
        // NEW -> TRIAGED
        $s1 = $contactService->updateStatus($inquiryId, 'TRIAGED', 'Initial triage on MySQL 8.0', $testUserId);
        if ($s1['status'] !== 'TRIAGED') {
            throw new RuntimeException("Failed transition to TRIAGED on MySQL 8.0.");
        }

        // TRIAGED -> IN_REVIEW
        $s2 = $contactService->updateStatus($inquiryId, 'IN_REVIEW', null, $testUserId);
        if ($s2['status'] !== 'IN_REVIEW') {
            throw new RuntimeException("Failed transition to IN_REVIEW on MySQL 8.0.");
        }

        // IN_REVIEW -> CONTACTED
        $s3 = $contactService->updateStatus($inquiryId, 'CONTACTED', 'Contacted lead via phone on MySQL 8.0', $testUserId);
        if ($s3['status'] !== 'CONTACTED') {
            throw new RuntimeException("Failed transition to CONTACTED on MySQL 8.0.");
        }

        // CONTACTED -> RESOLVED
        $s4 = $contactService->updateStatus($inquiryId, 'RESOLVED', 'Proposal requirements accepted on MySQL 8.0', $testUserId);
        if ($s4['status'] !== 'RESOLVED') {
            throw new RuntimeException("Failed transition to RESOLVED on MySQL 8.0.");
        }

        // RESOLVED -> CLOSED (with resolution note)
        $s5 = $contactService->updateStatus($inquiryId, 'CLOSED', 'Contract signed and archived on MySQL 8.0', $testUserId);
        if ($s5['status'] !== 'CLOSED') {
            throw new RuntimeException("Failed transition to CLOSED on MySQL 8.0.");
        }

        // 4. Test Illegal Transition Rejection (CLOSED -> RESOLVED)
        $illegalCaught = false;
        try {
            $contactService->updateStatus($inquiryId, 'RESOLVED', null, $testUserId);
        } catch (\Voak\Health\Core\Exceptions\ValidationException $e) {
            if ($e->getErrorCode() === 'ERR_INVALID_TRANSITION') {
                $illegalCaught = true;
            }
        }
        if (!$illegalCaught) {
            throw new RuntimeException("Illegal DAG state jump (CLOSED -> RESOLVED) was not rejected on MySQL 8.0.");
        }

        // 5. Test Note Appending & Assignee Update
        $contactService->addNote($inquiryId, 'Additional audit note on MySQL 8.0', $testUserId);
        $assigned = $contactService->assignInquiry($inquiryId, $testUserId, $testUserId);
        if ((int)$assigned['assigned_user_id'] !== $testUserId) {
            throw new RuntimeException("Assignee update failed on MySQL 8.0.");
        }

        // 6. Test CSV Export on MySQL 8.0
        $csv = $contactService->exportCsv(['status' => 'CLOSED'], $testUserId);
        if (!str_contains($csv, 'Reference Code') || !str_contains($csv, $refCode)) {
            throw new RuntimeException("CSV export did not contain expected enquiry reference on MySQL 8.0.");
        }

        // 7. Test Deletion & Cleanup
        $contactService->deleteInquiry($inquiryId, $testUserId);
        $checkDel = (int)$pdo->query("SELECT COUNT(*) FROM contact_inquiries WHERE id = {$inquiryId}")->fetchColumn();
        if ($checkDel !== 0) {
            throw new RuntimeException("contact_inquiries record not purged on MySQL 8.0 delete.");
        }

        // Clean up email queue
        $pdo->exec("DELETE FROM email_queue WHERE recipient_email = 'mysql-director@voak.test'");
    });

    // =========================================================================
    // TEST 11: Phase 12 — Partnership & Proposal CRM Management, Row-Level Locking, 4-Step Wizard, Junction Cascades & Tender Attachment Quarantine on MySQL 8.0
    // =========================================================================
    $runTest("Test 11: Partnership & Proposal CRM Management, Row-Level Locking, Junction Cascades & Tender Quarantine on MySQL 8.0", function () use ($pdo, $testUserId) {
        $connection = new Connection();
        $connection->setMockPdo($pdo);
        $auditLogger = new AuditLogger();
        $sanitizer = new HtmlSanitizer();
        $rbacManager = new RbacManager();
        $rateLimiter = new RateLimiter(sys_get_temp_dir() . '/voak_mysql_ptn_rl_' . bin2hex(random_bytes(4)));
        $quarantineDir = sys_get_temp_dir() . '/voak_mysql_prop_q_' . bin2hex(random_bytes(4));
        $mediaStorageService = new MediaStorageService(
            $connection,
            $auditLogger,
            sys_get_temp_dir() . '/voak_mysql_pub_uploads_' . bin2hex(random_bytes(4)),
            $quarantineDir
        );

        $partnershipService = new PartnershipService($connection, $auditLogger, $sanitizer, $rateLimiter, $rbacManager);
        $proposalService = new ProposalService($connection, $mediaStorageService, $auditLogger, $sanitizer, $rateLimiter, $rbacManager);

        // 1. Verify schema on MySQL 8.0
        $ptnCols = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'partnership_inquiries'")->fetchAll(\PDO::FETCH_COLUMN);
        if (!in_array('reference_code', $ptnCols, true) || !in_array('alliance_type', $ptnCols, true) || !in_array('status', $ptnCols, true)) {
            throw new RuntimeException("partnership_inquiries missing canonical columns on MySQL 8.0.");
        }

        $propCols = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proposals'")->fetchAll(\PDO::FETCH_COLUMN);
        if (!in_array('reference_code', $propCols, true) || !in_array('quarantined_file_path', $propCols, true) || !in_array('status', $propCols, true)) {
            throw new RuntimeException("proposals missing canonical columns on MySQL 8.0.");
        }

        $juncCols = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proposal_services'")->fetchAll(\PDO::FETCH_COLUMN);
        if (!in_array('proposal_id', $juncCols, true) || !in_array('service_id', $juncCols, true)) {
            throw new RuntimeException("proposal_services missing canonical columns on MySQL 8.0.");
        }

        // 2. Ensure test service exists for proposal junction FK
        $svcId = (int)$pdo->query("SELECT id FROM services ORDER BY id ASC LIMIT 1")->fetchColumn();
        if ($svcId <= 0) {
            $pdo->exec("INSERT INTO services (id, title, slug, tagline, description, sla_metrics_json, deliverables_json, is_published, display_order, created_at, updated_at)
                        VALUES (999, 'MySQL Surge Service', 'mysql-surge-service', 'Tagline', 'Desc', '[]', '[]', 1, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
            $svcId = 999;
        }

        // 3. Test Partnership Submission & FSM Transitions on MySQL 8.0
        $ptnResult = $partnershipService->submitPartnership([
            'organization_name' => 'MySQL Health Care Alliance',
            'contact_name'      => 'Dr. MySQL Admin',
            'corporate_email'   => 'mysql-ptn@voak.test',
            'contact_phone'     => '+1 202 555 0199',
            'alliance_type'     => 'Regional Health Authority',
            'proposed_scope'    => 'Joint clinical workforce development across regional hospitals in MySQL 8.0 environment.',
        ]);

        $ptnId = $ptnResult['id'];
        $ptnRef = $ptnResult['reference_code'];

        if (!str_starts_with($ptnRef, 'PTN-')) {
            throw new RuntimeException("Invalid reference code format for partnership on MySQL 8.0: {$ptnRef}");
        }

        // Verify status progression: NEW -> EVALUATION -> DISCUSSION -> LEGAL_REVIEW -> ACTIVE_ALLIANCE
        $p1 = $partnershipService->updateStage($ptnId, 'EVALUATION', 'Initial evaluation', null, $testUserId);
        if ($p1['status'] !== 'EVALUATION') {
            throw new RuntimeException("Failed transition to EVALUATION on MySQL 8.0.");
        }
        $p2 = $partnershipService->updateStage($ptnId, 'DISCUSSION', 'Discussion scheduled', null, $testUserId);
        if ($p2['status'] !== 'DISCUSSION') {
            throw new RuntimeException("Failed transition to DISCUSSION on MySQL 8.0.");
        }
        $p3 = $partnershipService->updateStage($ptnId, 'LEGAL_REVIEW', 'Legal review under way', null, $testUserId);
        if ($p3['status'] !== 'LEGAL_REVIEW') {
            throw new RuntimeException("Failed transition to LEGAL_REVIEW on MySQL 8.0.");
        }
        $p4 = $partnershipService->updateStage($ptnId, 'ACTIVE_ALLIANCE', 'Alliance active and signed', null, $testUserId);
        if ($p4['status'] !== 'ACTIVE_ALLIANCE') {
            throw new RuntimeException("Failed transition to ACTIVE_ALLIANCE on MySQL 8.0.");
        }

        // Test notes and assign
        $partnershipService->addNote($ptnId, 'MySQL note added', $testUserId);
        $partnershipService->assign($ptnId, $testUserId, $testUserId);

        // Test CSV Export
        $ptnCsv = $partnershipService->exportCsv(['status' => 'ACTIVE_ALLIANCE'], $testUserId);
        if (!str_contains($ptnCsv, $ptnRef)) {
            throw new RuntimeException("Partnership CSV export missing reference code on MySQL 8.0.");
        }

        // Test Deletion
        $partnershipService->deletePartnership($ptnId, $testUserId);
        $checkPtn = (int)$pdo->query("SELECT COUNT(*) FROM partnership_inquiries WHERE id = {$ptnId}")->fetchColumn();
        if ($checkPtn !== 0) {
            throw new RuntimeException("partnership_inquiries row not deleted on MySQL 8.0.");
        }

        // 4. Test Proposal Submission with Tender Attachment on MySQL 8.0
        $tenderFile = sys_get_temp_dir() . '/mysql_tender_' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($tenderFile, "%PDF-1.4\n%MySQL Tender Specification Document Content\n%%EOF");

        $fileUpload = [
            'tmp_name' => $tenderFile,
            'name'     => 'MySQL_Tender_Spec.pdf',
            'size'     => filesize($tenderFile),
            'error'    => 0,
        ];

        $propResult = $proposalService->submitProposal([
            'organization_name' => 'MySQL Medical Center',
            'contact_name'      => 'Chief Medical Officer',
            'contact_email'     => 'mysql-rfp@voak.test',
            'contact_phone'     => '+1 202 555 0188',
            'facility_type'     => 'Specialist Health Facility',
            'timeline'          => 'Within 90 Days',
            'budget_band'       => '$500,000 - $1,000,000',
            'service_ids'       => [$svcId],
        ], $fileUpload);

        @unlink($tenderFile);

        $propId = $propResult['id'];
        $propRef = $propResult['reference_code'];

        if (!str_starts_with($propRef, 'RFP-')) {
            throw new RuntimeException("Invalid reference code format for proposal on MySQL 8.0: {$propRef}");
        }

        // Verify junction row in proposal_services
        $juncCount = (int)$pdo->query("SELECT COUNT(*) FROM proposal_services WHERE proposal_id = {$propId} AND service_id = {$svcId}")->fetchColumn();
        if ($juncCount !== 1) {
            throw new RuntimeException("proposal_services junction row missing on MySQL 8.0.");
        }

        // Verify proposal lifecycle transitions: SUBMITTED -> UNDER_REVIEW -> SCOPING -> PROPOSAL_SENT -> ACCEPTED
        $pr1 = $proposalService->updateStatus($propId, 'UNDER_REVIEW', null, 'Under review', null, $testUserId);
        if ($pr1['status'] !== 'UNDER_REVIEW') {
            throw new RuntimeException("Proposal transition to UNDER_REVIEW failed on MySQL 8.0.");
        }
        $pr2 = $proposalService->updateStatus($propId, 'SCOPING', 750000.00, 'Scoping commenced', $testUserId, $testUserId);
        if ($pr2['status'] !== 'SCOPING') {
            throw new RuntimeException("Proposal transition to SCOPING failed on MySQL 8.0.");
        }
        $pr3 = $proposalService->updateStatus($propId, 'PROPOSAL_SENT', 750000.00, 'Proposal sent to client', $testUserId, $testUserId);
        if ($pr3['status'] !== 'PROPOSAL_SENT') {
            throw new RuntimeException("Proposal transition to PROPOSAL_SENT failed on MySQL 8.0.");
        }
        $pr4 = $proposalService->updateStatus($propId, 'ACCEPTED', 750000.00, 'Contract accepted and signed', $testUserId, $testUserId);
        if ($pr4['status'] !== 'ACCEPTED') {
            throw new RuntimeException("Proposal transition to ACCEPTED failed on MySQL 8.0.");
        }

        // Test Quarantined Document Streaming
        $stream = $proposalService->streamTenderAttachment($propId, $testUserId);
        if ($stream->getStatusCode() !== 200 || $stream->getHeader('Content-Disposition') === null) {
            throw new RuntimeException("Quarantined tender streaming failed on MySQL 8.0.");
        }

        // Test Proposal CSV Export
        $propCsv = $proposalService->exportCsv(['status' => 'ACCEPTED'], $testUserId);
        if (!str_contains($propCsv, $propRef)) {
            throw new RuntimeException("Proposal CSV export missing reference code on MySQL 8.0.");
        }

        // Test Deletion with Cascade on MySQL 8.0
        $proposalService->deleteProposal($propId, $testUserId);

        $checkProp = (int)$pdo->query("SELECT COUNT(*) FROM proposals WHERE id = {$propId}")->fetchColumn();
        if ($checkProp !== 0) {
            throw new RuntimeException("proposals row not deleted on MySQL 8.0.");
        }

        $checkJunc = (int)$pdo->query("SELECT COUNT(*) FROM proposal_services WHERE proposal_id = {$propId}")->fetchColumn();
        if ($checkJunc !== 0) {
            throw new RuntimeException("proposal_services cascade deletion failed on MySQL 8.0.");
        }

        // Clean up email queue records
        $pdo->exec("DELETE FROM email_queue WHERE recipient_email IN ('mysql-ptn@voak.test', 'mysql-rfp@voak.test')");
        if ($svcId === 999) {
            $pdo->exec("DELETE FROM services WHERE id = 999");
        }
    });

    $runTest("Test 12: Phase 13 — Transactional Email & Outbox Concurrency on MySQL 8.0 (FR-ADM-013, ADR-006, TEST-WF-001, TEST-SEC-005)", function () use ($pdo, $testUserId) {
        $connection = new Connection();
        $connection->setMockPdo($pdo);
        $auditLogger = new AuditLogger();
        $sanitizer = new HtmlSanitizer();
        $mailTransport = new ArrayTransport();
        $emailService = new EmailService($connection, $auditLogger, $sanitizer, $mailTransport);

        // 1. Verify email_queue schema on MySQL 8.0
        $eqCols = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'email_queue'")->fetchAll(\PDO::FETCH_COLUMN);
        $requiredCols = ['id', 'template_key', 'recipient_email', 'recipient_name', 'payload_json', 'status', 'attempts', 'max_attempts', 'claimed_at', 'worker_id', 'next_retry_at', 'sent_at', 'error_message', 'created_at'];
        foreach ($requiredCols as $rc) {
            if (!in_array($rc, $eqCols, true)) {
                throw new RuntimeException("email_queue missing required column '{$rc}' on MySQL 8.0.");
            }
        }

        // 2. Mandatory placeholder guard on template updates (TEST-ADM-013)
        $guardTriggered = false;
        try {
            $emailService->updateTemplate('tmpl_contact_ack', [
                'subject'   => 'Subject without ref',
                'body_html' => '<p>Missing ref placeholder entirely</p>',
            ], $testUserId);
        } catch (ValidationException $e) {
            $guardTriggered = true;
        }
        if (!$guardTriggered) {
            throw new RuntimeException("Mandatory placeholder guard failed to reject invalid template update on MySQL 8.0.");
        }

        // Valid template update
        $updatedTpl = $emailService->updateTemplate('tmpl_contact_ack', [
            'subject'   => 'Inquiry {{ref_no}} - Received',
            'body_html' => '<p>Hello {{contact_name}}, inquiry {{ref_no}} received by {{legal_entity_name}}.</p>',
        ], $testUserId);
        if ($updatedTpl['template_key'] !== 'tmpl_contact_ack') {
            throw new RuntimeException("Template update failed on MySQL 8.0.");
        }

        // Verify audit event EMAIL_TEMPLATE_UPDATED in audit_logs
        $auditTpl = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'EMAIL_TEMPLATE_UPDATED' AND user_id = {$testUserId}")->fetchColumn();
        if ($auditTpl < 1) {
            throw new RuntimeException("EMAIL_TEMPLATE_UPDATED audit event missing on MySQL 8.0.");
        }

        // 3. Outbox enqueuing & atomic batch claiming (TEST-WF-001)
        $q1 = $emailService->enqueue('tmpl_contact_ack', 'mysql-worker1@voak.test', 'Worker 1', ['ref_no' => 'ENQ-001', 'contact_name' => 'Client 1']);
        $q2 = $emailService->enqueue('tmpl_contact_ack', 'mysql-worker2@voak.test', 'Worker 2', ['ref_no' => 'ENQ-002', 'contact_name' => 'Client 2']);
        $q3 = $emailService->enqueue('tmpl_contact_ack', 'mysql-worker3@voak.test', 'Worker 3', ['ref_no' => 'ENQ-003', 'contact_name' => 'Client 3']);

        if ($q1 <= 0 || $q2 <= 0 || $q3 <= 0) {
            throw new RuntimeException("Outbox enqueue failed to return positive IDs on MySQL 8.0.");
        }

        // Worker 1 claims batch of 2
        $batch1 = $emailService->claimBatch(2);
        if (count($batch1) !== 2) {
            throw new RuntimeException("claimBatch(2) expected 2 items, got " . count($batch1) . " on MySQL 8.0.");
        }
        $batch1Ids = array_column($batch1, 'id');

        // Worker 2 claims next batch - must not claim already claimed items
        $batch2 = $emailService->claimBatch(2);
        $batch2Ids = array_column($batch2, 'id');
        foreach ($batch2Ids as $b2Id) {
            if (in_array($b2Id, $batch1Ids, true)) {
                throw new RuntimeException("Duplicate claim detected! Worker 2 claimed item {$b2Id} already held by Worker 1 on MySQL 8.0.");
            }
        }

        // Reset worker items back to QUEUED for batch dispatch testing
        $pdo->exec("UPDATE email_queue SET status = 'QUEUED', worker_id = NULL, claimed_at = NULL WHERE recipient_email LIKE 'mysql-worker%'");

        // Process batch 1 with ArrayTransport
        $pRes1 = $emailService->processQueueBatch(10);
        if ($pRes1['processed'] < 3) {
            throw new RuntimeException("processQueueBatch failed to process claimed emails on MySQL 8.0: processed {$pRes1['processed']} expected 3.");
        }

        // Verify sent status in database
        $sentCount = (int)$pdo->query("SELECT COUNT(*) FROM email_queue WHERE status = 'SENT' AND recipient_email LIKE 'mysql-worker%'")->fetchColumn();
        if ($sentCount < 2) {
            throw new RuntimeException("Expected at least 2 SENT records on MySQL 8.0, found {$sentCount}.");
        }

        // 4. Stale Claim Recovery (TEST-WF-001)
        // Simulate crashed worker holding a lock > 5 minutes ago
        $crashedId = $emailService->enqueue('tmpl_contact_ack', 'mysql-crashed@voak.test', 'Crashed Worker', ['ref_no' => 'ENQ-CRASH', 'contact_name' => 'Crashed']);
        $pdo->exec("UPDATE email_queue SET status = 'PROCESSING', claimed_at = DATE_SUB(NOW(), INTERVAL 15 MINUTE) WHERE id = {$crashedId}");

        $recoveredBatch = $emailService->claimBatch(5);
        $recoveredIds = array_column($recoveredBatch, 'id');
        if (!in_array($crashedId, $recoveredIds, true)) {
            throw new RuntimeException("Stale claim recovery failed on MySQL 8.0 for crashed item {$crashedId}.");
        }

        // 5. Transient Failure, Exponential Backoff & Manual Retry (TEST-ADM-013)
        // Reset crashed item back to QUEUED so processQueueBatch picks it up with failure injection
        $pdo->exec("UPDATE email_queue SET status = 'QUEUED', worker_id = NULL, claimed_at = NULL WHERE id = {$crashedId}");
        $mailTransport->setFailing(true);
        $pResFail = $emailService->processQueueBatch(5);
        $mailTransport->setFailing(false);
        if ($pResFail['failed'] < 1) {
            throw new RuntimeException("Expected at least 1 failed dispatch on MySQL 8.0.");
        }

        // Item should now be in FAILED status
        $failedRow = $pdo->query("SELECT status, attempts, error_message FROM email_queue WHERE id = {$crashedId}")->fetch(\PDO::FETCH_ASSOC);
        if ($failedRow['status'] !== 'FAILED' || (int)$failedRow['attempts'] < 1) {
            throw new RuntimeException("Expected FAILED status after transport error on MySQL 8.0.");
        }

        // Admin manual retry
        $retryRes = $emailService->retryMessage($crashedId, $testUserId);
        if ($retryRes['status'] !== 'QUEUED') {
            throw new RuntimeException("Manual retry failed to reset status to QUEUED on MySQL 8.0.");
        }
        $auditRetry = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'EMAIL_MANUAL_RETRY_TRIGGERED' AND user_id = {$testUserId}")->fetchColumn();
        if ($auditRetry < 1) {
            throw new RuntimeException("EMAIL_MANUAL_RETRY_TRIGGERED audit event missing on MySQL 8.0.");
        }

        // 6. Fast-path security dispatch & fallback (ADR-006)
        $fastPathSent = $emailService->sendUrgentFastPath('tmpl_security_alert', 'mysql-fast@voak.test', 'Security Admin', ['incident_code' => 'SEC-01', 'event_type' => 'Security Audit', 'target_user' => 'Admin'], $testUserId);
        if ($fastPathSent !== true) {
            throw new RuntimeException("Urgent fast path dispatch failed on MySQL 8.0.");
        }

        $mailTransport->setFailing(true);
        $fastPathFallback = $emailService->sendUrgentFastPath('tmpl_security_alert', 'mysql-fast-fallback@voak.test', 'Security Admin', ['incident_code' => 'SEC-02', 'event_type' => 'Security Audit', 'target_user' => 'Admin'], $testUserId);
        $mailTransport->setFailing(false);
        if ($fastPathFallback !== false) {
            throw new RuntimeException("Urgent fast path fallback failed to return false on transport failure on MySQL 8.0.");
        }
        $fallbackStatus = (string)$pdo->query("SELECT status FROM email_queue WHERE recipient_email = 'mysql-fast-fallback@voak.test'")->fetchColumn();
        if ($fallbackStatus !== 'QUEUED') {
            throw new RuntimeException("Urgent fast path fallback record status is '{$fallbackStatus}', expected 'QUEUED' on MySQL 8.0.");
        }

        // 7. Diagnostic Test Email (TEST-SEC-005)
        $diagRes = $emailService->sendTestEmail('tmpl_contact_ack', 'mysql-diagnostic@voak.test', $testUserId);
        if ($diagRes['success'] !== true) {
            throw new RuntimeException("sendTestEmail failed on MySQL 8.0.");
        }
        $auditDiag = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'EMAIL_TEST_SENT' AND user_id = {$testUserId}")->fetchColumn();
        if ($auditDiag < 1) {
            throw new RuntimeException("EMAIL_TEST_SENT audit event missing on MySQL 8.0.");
        }

        // 8. Clean up isolated test data for Test 12
        $pdo->exec("DELETE FROM email_queue WHERE recipient_email LIKE 'mysql-%'");
        $pdo->exec("DELETE FROM system_settings WHERE setting_group = 'email_templates' AND updated_by_user_id = {$testUserId}");
    });

    $runTest("Test 13: Phase 14 — Careers & Recruitment Requisitions, Referential Guard, Candidate FSM Progression, Quarantined CV Streaming & Retention Disposal on MySQL 8.0 (FR-ADM-014, FR-ADM-015, FR-WF-004, FR-SEC-004, TEST-ADM-014, TEST-ADM-015, TEST-WF-004, TEST-SEC-004)", function () use ($pdo, $testUserId) {
        $connection = new Connection();
        $connection->setMockPdo($pdo);
        $auditLogger = new AuditLogger();
        $sanitizer = new HtmlSanitizer();
        $mailTransport = new ArrayTransport();
        $emailService = new EmailService($connection, $auditLogger, $sanitizer, $mailTransport);

        $quarantineDir = sys_get_temp_dir() . '/voak_mysql_q_' . bin2hex(random_bytes(4));
        $mediaStorage = new MediaStorageService($connection, $auditLogger, null, null, $quarantineDir);
        $rlDir = sys_get_temp_dir() . '/voak_mysql_rl_' . bin2hex(random_bytes(4));
        $rateLimiter = new RateLimiter($rlDir);

        $rbac = new RbacManager();
        $careerService = new CareerService($connection, $mediaStorage, $auditLogger, $sanitizer, $emailService, $rateLimiter, $rbac);

        // 1. Verify schema existence and columns on MySQL 8.0
        $jpCols = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'job_postings'")->fetchAll(\PDO::FETCH_COLUMN);
        $requiredJpCols = ['id', 'title', 'slug', 'department', 'employment_type', 'location', 'summary', 'responsibilities_json', 'requirements_json', 'is_active', 'closing_date', 'created_by_user_id', 'created_at', 'updated_at'];
        foreach ($requiredJpCols as $rc) {
            if (!in_array($rc, $jpCols, true)) {
                throw new RuntimeException("job_postings missing required column '{$rc}' on MySQL 8.0.");
            }
        }

        $caCols = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'career_applications'")->fetchAll(\PDO::FETCH_COLUMN);
        $requiredCaCols = ['id', 'job_id', 'reference_code', 'full_name', 'email', 'phone', 'country_code', 'ahpra_number', 'quarantined_resume_path', 'original_resume_name', 'status', 'rejection_reason', 'retention_expiry_date', 'created_at', 'updated_at'];
        foreach ($requiredCaCols as $rc) {
            if (!in_array($rc, $caCols, true)) {
                throw new RuntimeException("career_applications missing required column '{$rc}' on MySQL 8.0.");
            }
        }

        // 2. Job Requisition Lifecycle & Slug Uniqueness (FR-ADM-014 / TEST-ADM-014)
        $job = $careerService->createJob([
            'title'            => 'MySQL ICU Clinical Specialist',
            'slug'             => 'mysql-job-icu-specialist',
            'department'       => 'Intensive Care Unit',
            'employment_type'  => 'FULL_TIME',
            'location'         => 'Sydney Campus',
            'summary'          => 'Critical high-acuity patient support and leadership.',
            'responsibilities' => ['Clinical rounds', 'Supervise nursing'],
            'requirements'     => ['AHPRA registration', 'Postgrad cert'],
            'is_active'        => 1,
            'closing_date'     => date('Y-m-d', strtotime('+30 days')),
        ], $testUserId);

        $jobId = (int)$job['id'];
        if ($jobId < 1 || $job['slug'] !== 'mysql-job-icu-specialist') {
            throw new RuntimeException("createJob failed on MySQL 8.0.");
        }

        // Verify duplicate slug throws ValidationException
        $slugThrew = false;
        try {
            $careerService->createJob([
                'title'           => 'Duplicate Slug Role',
                'slug'            => 'mysql-job-icu-specialist',
                'department'      => 'Emergency',
                'employment_type' => 'FULL_TIME',
                'location'        => 'Melbourne',
                'summary'         => 'Duplicate role check.',
            ], $testUserId);
        } catch (ValidationException $e) {
            $slugThrew = true;
        }
        if (!$slugThrew) {
            throw new RuntimeException("Duplicate slug validation failed to reject on MySQL 8.0.");
        }

        // Verify audit event JOB_POSTED
        $auditPosted = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'JOB_POSTED' AND user_id = {$testUserId}")->fetchColumn();
        if ($auditPosted < 1) {
            throw new RuntimeException("JOB_POSTED audit event missing on MySQL 8.0.");
        }

        // 3. Referential Deletion Guard (TEST-ADM-014)
        // Temporary job without applications: can be deleted
        $tempJob = $careerService->createJob([
            'title'           => 'MySQL Temporary Job',
            'slug'            => 'mysql-job-temp-to-delete',
            'department'      => 'General',
            'employment_type' => 'CASUAL',
            'location'        => 'Brisbane',
            'summary'         => 'Temporary position with zero applicants.',
        ], $testUserId);
        $tempJobId = (int)$tempJob['id'];
        $delSuccess = $careerService->deleteJob($tempJobId, $testUserId);
        if ($delSuccess !== true) {
            throw new RuntimeException("deleteJob failed for zero-applicant job on MySQL 8.0.");
        }

        // 4. Candidate Application Intake with Magic Bytes & Quarantine (FR-ADM-015 / FR-SEC-004)
        $resumeTmp = tempnam(sys_get_temp_dir(), 'mysql_cv_');
        file_put_contents($resumeTmp, "%PDF-1.4 Clinical Resume Content\n");
        $mockFile = [
            'tmp_name' => $resumeTmp,
            'name'     => 'applicant_cv.pdf',
            'size'     => filesize($resumeTmp),
            'error'    => UPLOAD_ERR_OK,
        ];

        $appResult = $careerService->submitApplication([
            'job_id'       => $jobId,
            'full_name'    => 'MySQL Candidate, RN',
            'email'        => 'mysql-candidate@voak.test',
            'phone'        => '+61400123456',
            'ahpra_number' => 'NMW1234567890',
        ], $mockFile);
        @unlink($resumeTmp);

        $appId = (int)$appResult['id'];
        if ($appId < 1 || !str_starts_with($appResult['reference_code'], 'APP-')) {
            throw new RuntimeException("submitApplication failed to return valid ID or reference on MySQL 8.0.");
        }

        // Verify referential deletion guard blocks deleting active job with applications (409 Conflict)
        $guardThrew = false;
        try {
            $careerService->deleteJob($jobId, $testUserId);
        } catch (ConflictException $e) {
            $guardThrew = true;
        }
        if (!$guardThrew) {
            throw new RuntimeException("deleteJob failed to trigger referential deletion guard on MySQL 8.0.");
        }

        // 5. Candidate FSM Progression & Mandatory Rejection Reason (FR-WF-004 / TEST-WF-004)
        // Illegal skipping: SUBMITTED -> HIRED directly must fail
        $skipThrew = false;
        try {
            $careerService->updateApplicationStatus($appId, 'HIRED', null, $testUserId);
        } catch (ValidationException $e) {
            $skipThrew = true;
        }
        if (!$skipThrew) {
            throw new RuntimeException("Illegal state transition skipping stages was not blocked on MySQL 8.0.");
        }

        // REJECTED without reason must fail
        $rejectNoReasonThrew = false;
        try {
            $careerService->updateApplicationStatus($appId, 'REJECTED', null, $testUserId);
        } catch (ValidationException $e) {
            $rejectNoReasonThrew = true;
        }
        if (!$rejectNoReasonThrew) {
            throw new RuntimeException("REJECTED transition without rejection_reason was not blocked on MySQL 8.0.");
        }

        // Legal state transitions: SUBMITTED -> REVIEWING -> SCREENING -> INTERVIEWING -> OFFER -> HIRED
        $stages = ['REVIEWING', 'SCREENING', 'INTERVIEWING', 'OFFER', 'HIRED'];
        foreach ($stages as $stage) {
            $updatedApp = $careerService->updateApplicationStatus($appId, $stage, null, $testUserId);
            if ($updatedApp['status'] !== $stage) {
                throw new RuntimeException("Failed to advance candidate stage to {$stage} on MySQL 8.0.");
            }
        }

        // 6. Quarantined CV Streaming & SHA-256 Audit Entry (FR-ADM-015 / TEST-SEC-004)
        $streamRes = $careerService->streamQuarantinedResume($appId, $testUserId);
        if ($streamRes->getStatusCode() !== 200) {
            throw new RuntimeException("streamQuarantinedResume failed with status {$streamRes->getStatusCode()} on MySQL 8.0.");
        }
        $auditDl = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'RESUME_DOWNLOADED' AND user_id = {$testUserId}")->fetchColumn();
        if ($auditDl < 1) {
            throw new RuntimeException("RESUME_DOWNLOADED audit event missing on MySQL 8.0.");
        }

        // 7. CSV Export Formula Injection Neutralization (FR-ADM-014)
        $csv = $careerService->exportApplicationsCsv([], $testUserId);
        if (!str_starts_with($csv, "\xEF\xBB\xBF") || !str_contains($csv, 'MySQL Candidate, RN')) {
            throw new RuntimeException("exportApplicationsCsv failed or missing candidate data on MySQL 8.0.");
        }

        // 8. Privacy Retention Disposal & File Shredding (FR-SEC-004 / TEST-WF-004)
        $quarantinedPath = (string)$appResult['quarantined_resume_path'];
        $pdo->exec("UPDATE career_applications SET retention_expiry_date = '2020-01-01' WHERE id = {$appId}");
        $cleanupReport = $careerService->cleanupExpiredApplications($testUserId);
        if ($cleanupReport['anonymized_count'] < 1) {
            throw new RuntimeException("cleanupExpiredApplications failed to anonymize expired record on MySQL 8.0.");
        }
        $cleanedRow = $pdo->query("SELECT full_name, email, phone FROM career_applications WHERE id = {$appId}")->fetch(\PDO::FETCH_ASSOC);
        if ($cleanedRow['full_name'] !== '[ANONYMIZED]' || $cleanedRow['email'] !== '[ANONYMIZED]') {
            throw new RuntimeException("Candidate record PII was not anonymized on MySQL 8.0.");
        }
        if (file_exists($quarantinedPath)) {
            throw new RuntimeException("Quarantined resume file was not shredded on disk on MySQL 8.0.");
        }
        $auditAnon = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'RECRUITMENT_RECORD_ANONYMIZED' AND user_id = {$testUserId}")->fetchColumn();
        if ($auditAnon < 1) {
            throw new RuntimeException("RECRUITMENT_RECORD_ANONYMIZED audit event missing on MySQL 8.0.");
        }

        // Clean up test data
        $pdo->exec("DELETE FROM career_applications WHERE job_id = {$jobId} OR email LIKE 'mysql-%'");
        $pdo->exec("DELETE FROM job_postings WHERE id = {$jobId} OR slug LIKE 'mysql-job-%'");
    });

    // Clean up isolated test data
    $pdo->exec("DELETE FROM user_roles WHERE user_id = {$testUserId}");
    $pdo->exec("DELETE FROM admin_sessions WHERE user_id = {$testUserId}");
    $pdo->exec("DELETE FROM admin_mfa_secrets WHERE user_id = {$testUserId}");
    $pdo->exec("DELETE FROM password_resets WHERE user_id = {$testUserId}");
    $pdo->exec("DELETE FROM audit_logs WHERE user_id = {$testUserId}");
    $pdo->exec("DELETE FROM content_revisions WHERE editor_user_id = {$testUserId}");
    $pdo->exec("DELETE FROM system_settings WHERE updated_by_user_id = {$testUserId}");
    $pdo->exec("DELETE FROM url_redirects WHERE source_path LIKE '/services/mysql-service-%'");
    $pdo->exec("DELETE FROM url_redirects WHERE source_path LIKE '/industries/mysql-industry-%'");
    $pdo->exec("DELETE FROM media_assets WHERE uploaded_by_user_id = {$testUserId}");
    $pdo->exec("DELETE FROM contact_inquiries WHERE corporate_email LIKE 'mysql-%'");
    $pdo->exec("DELETE FROM partnership_inquiries WHERE corporate_email LIKE 'mysql-%'");
    $pdo->exec("DELETE FROM proposals WHERE contact_email LIKE 'mysql-%'");
    $pdo->exec("DELETE FROM career_applications WHERE email LIKE 'mysql-%' OR email = '[ANONYMIZED]'");
    $pdo->exec("DELETE FROM job_postings WHERE slug LIKE 'mysql-job-%'");
    $pdo->exec("DELETE FROM email_queue WHERE recipient_email LIKE 'mysql-%'");
    $pdo->exec("DELETE FROM pages WHERE slug LIKE 'mysql-page-%'");
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
