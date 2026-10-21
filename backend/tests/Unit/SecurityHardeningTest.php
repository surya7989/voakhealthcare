<?php

declare(strict_types=1);

namespace Voak\Health\Tests\Unit;

use Voak\Health\Auth\AuthenticationService;
use Voak\Health\Auth\PasswordResetService;
use Voak\Health\Auth\RbacManager;
use Voak\Health\Auth\SessionManager;
use Voak\Health\Auth\TotpService;
use Voak\Health\Core\App;
use Voak\Health\Core\Config;
use Voak\Health\Core\Exceptions\AuthenticationException;
use Voak\Health\Core\Exceptions\SecurityException;
use Voak\Health\Core\Http\Request;
use Voak\Health\Database\Connection;
use Voak\Health\Database\Migration\MigrationManager;
use Voak\Health\Middleware\CsrfProtectionMiddleware;
use Voak\Health\Security\AuditLogger;
use Voak\Health\Security\PasswordHasher;
use Voak\Health\Security\RateLimiter;
use Voak\Health\Security\TotpEngine;
use Voak\Health\Tests\TestFramework as T;

class SecurityHardeningTest
{
    private static function createDatabase(?string $filepath = null): \PDO
    {
        $dsn = $filepath ? "sqlite:{$filepath}" : 'sqlite::memory:';
        $pdo = new \PDO($dsn, null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_TIMEOUT => 10,
        ]);
        if ($filepath) {
            $pdo->exec("PRAGMA journal_mode = WAL;");
            $pdo->exec("PRAGMA busy_timeout = 10000;");
        }
        $migrationsDir = dirname(__DIR__, 2) . '/database/migrations';
        $manager = new MigrationManager($migrationsDir, 'migrations');
        $manager->migrateUp($pdo);

        $hasher = new PasswordHasher();
        $hash = $hasher->hash('VoakAdmin#2026!Secure');

        // Admin 1: Super Admin
        $pdo->exec("INSERT INTO users (id, name, email, password_hash, is_active, failed_login_attempts, created_at, updated_at) VALUES (1, 'Super Admin', 'super@voak.test', '{$hash}', 1, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");

        // Admin 2: Content Editor (lacks user:list permission)
        $pdo->exec("INSERT INTO users (id, name, email, password_hash, is_active, failed_login_attempts, created_at, updated_at) VALUES (2, 'Editor User', 'editor@voak.test', '{$hash}', 1, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");

        $rbac = new RbacManager();
        $rbac->seedRbac($pdo);
        $rbac->assignRole($pdo, 1, RbacManager::ROLE_SUPER_ADMIN);
        $rbac->assignRole($pdo, 2, RbacManager::ROLE_CONTENT_EDITOR);

        return $pdo;
    }

    public static function run(): void
    {
        T::suite('Phase 6 — Comprehensive Security Hardening & Abuse Defense Tests', function () {

            // 1. Missing and invalid production cryptographic keys fail safely (no fallback to APP_KEY)
            T::test('missing and invalid production cryptographic keys fail safely with SecurityException', function () {
                $emptyConfig = new Config(['APP_KEY' => 'valid-app-key-32-chars-present!', 'MFA_ENCRYPTION_KEY' => '', 'AUTH_SIGNING_KEY' => '']);
                $engine = new TotpEngine();
                $totp = new TotpService($emptyConfig, $engine);

                $pdo = self::createDatabase();

                // MFA encryption key missing must fail safely even if APP_KEY is set (no fallback)
                T::expectException(SecurityException::class, function () use ($totp, $pdo) {
                    $totp->initiateSetup($pdo, 1, 'super@voak.test');
                }, 'MFA encryption key is not configured');

                // MFA encryption key too short (< 16 chars)
                $shortMfaConfig = new Config(['MFA_ENCRYPTION_KEY' => 'short']);
                $totpShort = new TotpService($shortMfaConfig, $engine);
                T::expectException(SecurityException::class, function () use ($totpShort, $pdo) {
                    $totpShort->initiateSetup($pdo, 1, 'super@voak.test');
                }, 'MFA encryption key is not configured or fails security requirements');

                // Pre-auth signing key missing must fail safely even if APP_KEY is set (no fallback)
                $auth = new AuthenticationService(null, null, null, null, null, $emptyConfig);
                T::expectException(SecurityException::class, function () use ($auth, $pdo) {
                    $auth->createPreAuthToken($pdo, 1);
                }, 'signing key is not configured');

                // Pre-auth signing key too short (< 16 chars)
                $shortAuthConfig = new Config(['AUTH_SIGNING_KEY' => 'short_key']);
                $authShort = new AuthenticationService(null, null, null, null, null, $shortAuthConfig);
                T::expectException(SecurityException::class, function () use ($authShort, $pdo) {
                    $authShort->createPreAuthToken($pdo, 1);
                }, 'signing key is not configured or insecure');
            });

            // 2. Pre-authentication challenge signing, forged, expired, and replay protection
            T::test('forged, expired, and replayed pre-authentication challenges are rejected', function () {
                $pdo = self::createDatabase();
                $validConfig = new Config([
                    'AUTH_SIGNING_KEY'   => 'preauth-signing-key-32-characters-min!',
                    'MFA_ENCRYPTION_KEY' => 'mfa-encryption-key-32-characters-min!!',
                ]);
                $engine = new TotpEngine();
                $totp = new TotpService($validConfig, $engine);
                $hasher = new PasswordHasher();
                $sm = new SessionManager();
                $rbac = new RbacManager();
                $audit = new AuditLogger();
                $auth = new AuthenticationService($hasher, $sm, $totp, $rbac, $audit, $validConfig);

                // Setup MFA for super admin
                $setup = $totp->initiateSetup($pdo, 1, 'super@voak.test');
                $code = $engine->generateCode($setup['secret']);
                $totp->confirmSetup($pdo, 1, $code);

                // Authenticate Step 1 -> get pre_auth_token
                $firstStep = $auth->authenticate($pdo, 'super@voak.test', 'VoakAdmin#2026!Secure');
                $validToken = $firstStep['pre_auth_token'];

                // 2A: Forged signature must be rejected
                $decoded = base64_decode($validToken);
                $parts = explode(':', $decoded);
                $tamperedPayload = $parts[0] . ':' . $parts[1] . ':' . $parts[2] . ':bad_signature_here';
                $forgedToken = base64_encode($tamperedPayload);

                T::expectException(AuthenticationException::class, function () use ($auth, $pdo, $forgedToken, $code) {
                    $auth->verifyMfaAndEstablishSession($pdo, $forgedToken, $code);
                }, 'Pre-authentication challenge expired, invalid, or already consumed.');

                // 2B: Expired challenge must be rejected
                $expiredPayload = $parts[0] . ':' . $parts[1] . ':' . (time() - 100);
                $expiredSig = hash_hmac('sha256', $parts[0] . ':' . $parts[1] . ':' . (time() - 100), 'preauth-signing-key-32-characters-min!');
                $expiredToken = base64_encode($expiredPayload . ':' . $expiredSig);

                T::expectException(AuthenticationException::class, function () use ($auth, $pdo, $expiredToken, $code) {
                    $auth->verifyMfaAndEstablishSession($pdo, $expiredToken, $code);
                }, 'Pre-authentication challenge expired, invalid, or already consumed.');

                // 2C: First valid consumption succeeds
                $result = $auth->verifyMfaAndEstablishSession($pdo, $validToken, $code);
                T::assertEquals('authenticated', $result['status']);

                // 2D: Replay of same pre-auth token must immediately fail (single-use burned from DB)
                T::expectException(AuthenticationException::class, function () use ($auth, $pdo, $validToken, $code) {
                    $auth->verifyMfaAndEstablishSession($pdo, $validToken, $code);
                }, 'Pre-authentication challenge expired, invalid, or already consumed.');
            });

            // 3. Rate limiter fail-closed storage safety, persistence, and proxy trust validation
            T::test('rate limiter persists across requests, fails closed on errors, and validates proxy trust', function () {
                $tempDir = sys_get_temp_dir() . '/voak_rl_test_' . bin2hex(random_bytes(4));
                $limiter = new RateLimiter($tempDir);

                // Hit 5 times (limit 5)
                for ($i = 0; $i < 5; $i++) {
                    $res = $limiter->hit('test_client_key', 5, 60);
                    T::assertTrue($res['allowed']);
                }

                // 6th hit must be throttled
                $res6 = $limiter->hit('test_client_key', 5, 60);
                T::assertFalse($res6['allowed']);
                T::assertTrue($res6['retry_after'] > 0);

                // Fail-closed test on invalid/unwritable directory
                $inaccessibleLimiter = new RateLimiter('Z:\\non_existent_drive_never_writable\\voak_limits');
                $failClosedRes = $inaccessibleLimiter->hit('any_key', 10, 60);
                T::assertFalse($failClosedRes['allowed'], 'Rate limiter must fail closed when storage is inaccessible');

                // Reset works
                $limiter->reset('test_client_key');
                $resAfterReset = $limiter->hit('test_client_key', 5, 60);
                T::assertTrue($resAfterReset['allowed']);

                $limiter->reset();

                // Test Request proxy trust
                Request::setTrustedProxies([]); // No trusted proxies configured
                $reqUntrusted = new Request('GET', '/api/test', [], ['X-Forwarded-For' => '8.8.8.8'], '', null, '127.0.0.1');
                T::assertEquals('127.0.0.1', $reqUntrusted->getClientIp(), 'Untrusted forwarded IP must be ignored');

                Request::setTrustedProxies(['127.0.0.1']); // 127.0.0.1 is trusted proxy
                $reqTrusted = new Request('GET', '/api/test', [], ['X-Forwarded-For' => '8.8.8.8'], '', null, '127.0.0.1');
                T::assertEquals('8.8.8.8', $reqTrusted->getClientIp(), 'Trusted proxy forwarded IP must be resolved');

                Request::setTrustedProxies([]);
            });

            // 4. Audit ledger integrity normalizes overlength fields and detects tampering of all fields
            T::test('audit ledger normalizes overlength fields and detects modification of every field', function () {
                $pdo = self::createDatabase();
                $logger = new AuditLogger();

                // 4A: Normalization test: provide field values longer than database column capacity
                $longDomain = str_repeat('a', 80); // max 50
                $longAction = str_repeat('b', 150); // max 100
                $longUa = str_repeat('BrowserX/', 40); // > 255 chars

                $r1 = $logger->log($pdo, $longDomain, $longAction, 1, '10.0.0.1', $longUa, 'user', 1, ['key' => 'val']);
                $r2 = $logger->log($pdo, 'security', 'EVENT_TWO', 1, '10.0.0.2', 'OriginalBrowser/2.0', 'user', 1);

                // Hash must match normalized values exactly
                T::assertTrue($logger->verifyChain($pdo), 'Fresh chain with normalized overlength values must verify');

                // 4B: Modify user_agent directly in database
                $pdo->exec("UPDATE audit_logs SET user_agent = 'TamperedBrowser/9.9' WHERE id = {$r2['id']}");
                T::assertFalse($logger->verifyChain($pdo), 'Tampered user_agent must break chain');

                // Revert
                $pdo->exec("UPDATE audit_logs SET user_agent = 'OriginalBrowser/2.0' WHERE id = {$r2['id']}");
                T::assertTrue($logger->verifyChain($pdo));

                // 4C: Modify created_at
                $pdo->exec("UPDATE audit_logs SET created_at = '2020-01-01 00:00:00' WHERE id = {$r2['id']}");
                T::assertFalse($logger->verifyChain($pdo), 'Tampered created_at must break chain');

                // Revert
                $stmt = $pdo->prepare("SELECT created_at FROM audit_logs WHERE id = {$r1['id']}");
                $stmt->execute();
                $t1 = $stmt->fetchColumn();
                $pdo->exec("UPDATE audit_logs SET created_at = '{$t1}' WHERE id = {$r2['id']}");

                // 4D: Modify diff_json
                $pdo->exec("UPDATE audit_logs SET diff_json = '{\"tampered\":true}' WHERE id = {$r1['id']}");
                T::assertFalse($logger->verifyChain($pdo), 'Tampered diff_json must break chain');

                // 4E: Delete record
                $pdo->exec("DELETE FROM audit_logs WHERE id = {$r1['id']}");
                T::assertFalse($logger->verifyChain($pdo), 'Deleted record must break chain');
            });

            // 5. Meaningful Real Multi-Process Concurrency Tests
            T::test('real concurrent requests for password reset and MFA recovery fail safely', function () {
                $workerScript = dirname(__DIR__) . '/concurrency_worker.php';
                if (!file_exists($workerScript)) {
                    throw new \RuntimeException("Worker script not found at: {$workerScript}");
                }

                $tempDb = sys_get_temp_dir() . '/voak_concurrency_' . bin2hex(random_bytes(4)) . '.sqlite';
                $pdo = self::createDatabase($tempDb);

                // 5A: Concurrent Password Reset Consumption
                $hasher = new PasswordHasher();
                $sm = new SessionManager();
                $audit = new AuditLogger();
                $resetService = new PasswordResetService($hasher, $sm, $audit);
                $token = $resetService->requestReset($pdo, 'super@voak.test');
                T::assertTrue($token !== null);
                unset($pdo); // Close handle so workers can access WAL file

                $phpBin = PHP_BINARY;

                // Launch two simultaneous child processes to consume the exact same token
                $cmd1 = "\"{$phpBin}\" \"{$workerScript}\" --action=consume_reset --db=\"{$tempDb}\" --token=\"{$token}\" --password=\"PassOne#2026!\"";
                $cmd2 = "\"{$phpBin}\" \"{$workerScript}\" --action=consume_reset --db=\"{$tempDb}\" --token=\"{$token}\" --password=\"PassTwo#2026!\"";

                $p1 = proc_open($cmd1, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes1);
                $p2 = proc_open($cmd2, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes2);

                if (isset($pipes1[1])) { fclose($pipes1[1]); }
                if (isset($pipes1[2])) { fclose($pipes1[2]); }
                if (isset($pipes2[1])) { fclose($pipes2[1]); }
                if (isset($pipes2[2])) { fclose($pipes2[2]); }

                $code1 = proc_close($p1);
                $code2 = proc_close($p2);

                // Exactly one must succeed (exit 0) and exactly one must fail (exit 1)
                $successes = ($code1 === 0 ? 1 : 0) + ($code2 === 0 ? 1 : 0);
                T::assertEquals(1, $successes, 'Exactly one concurrent reset request must succeed');

                // 5B: Concurrent MFA Recovery Code Consumption
                $pdoCheck = new \PDO("sqlite:{$tempDb}");
                $config = new Config(['MFA_ENCRYPTION_KEY' => 'concurrency-worker-mfa-key-32-chars!']);
                $totp = new TotpService($config, new TotpEngine());
                $setup = $totp->initiateSetup($pdoCheck, 1, 'super@voak.test');
                $code = (new TotpEngine())->generateCode($setup['secret']);
                $totp->confirmSetup($pdoCheck, 1, $code);
                $recoveryCode = $setup['backup_codes'][0];
                unset($pdoCheck);

                $cmdRec1 = "\"{$phpBin}\" \"{$workerScript}\" --action=consume_recovery --db=\"{$tempDb}\" --user_id=1 --code=\"{$recoveryCode}\"";
                $cmdRec2 = "\"{$phpBin}\" \"{$workerScript}\" --action=consume_recovery --db=\"{$tempDb}\" --user_id=1 --code=\"{$recoveryCode}\"";

                $pr1 = proc_open($cmdRec1, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipesR1);
                $pr2 = proc_open($cmdRec2, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipesR2);

                if (isset($pipesR1[1])) { fclose($pipesR1[1]); }
                if (isset($pipesR1[2])) { fclose($pipesR1[2]); }
                if (isset($pipesR2[1])) { fclose($pipesR2[1]); }
                if (isset($pipesR2[2])) { fclose($pipesR2[2]); }

                $codeRec1 = proc_close($pr1);
                $codeRec2 = proc_close($pr2);

                $recSuccesses = ($codeRec1 === 0 ? 1 : 0) + ($codeRec2 === 0 ? 1 : 0);
                T::assertEquals(1, $recSuccesses, 'Exactly one concurrent recovery code consumption must succeed');

                // 5C: Concurrent Audit Writes under Simultaneous Multi-Process Load
                $auditProcs = [];
                $auditPipes = [];
                for ($w = 0; $w < 4; $w++) {
                    $cmdAudit = "\"{$phpBin}\" \"{$workerScript}\" --action=audit_log --db=\"{$tempDb}\" --action_name=\"CONCURRENT_EVENT_{$w}\" --payload=\"load_{$w}\"";
                    $auditProcs[$w] = proc_open($cmdAudit, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $auditPipes[$w]);
                }
                foreach ($auditProcs as $w => $ap) {
                    if (isset($auditPipes[$w][1])) { fclose($auditPipes[$w][1]); }
                    if (isset($auditPipes[$w][2])) { fclose($auditPipes[$w][2]); }
                    $c = proc_close($ap);
                    T::assertEquals(0, $c, 'Concurrent audit log worker must succeed');
                }

                // Verify resulting audit chain is cryptographically unbroken
                $pdoFinal = new \PDO("sqlite:{$tempDb}");
                $auditChecker = new AuditLogger();
                T::assertTrue($auditChecker->verifyChain($pdoFinal), 'Cryptographic chain must remain unbroken after concurrent writes');
                unset($pdoFinal);

                @unlink($tempDb);
                @unlink($tempDb . '-wal');
                @unlink($tempDb . '-shm');
            });

            // 6. Repeated failed logins trigger lockout and cannot bypass it
            T::test('repeated failed logins cannot bypass account lockout', function () {
                $pdo = self::createDatabase();
                $config = new Config([
                    'AUTH_SIGNING_KEY'   => 'auth-signing-key-32-chars-long!',
                    'MFA_ENCRYPTION_KEY' => 'mfa-encryption-key-32-chars-long!',
                ]);
                $auth = new AuthenticationService(null, null, null, null, null, $config);

                for ($i = 0; $i < 5; $i++) {
                    try {
                        $auth->authenticate($pdo, 'super@voak.test', 'WrongPassword123!');
                    } catch (AuthenticationException $e) {
                        // Expected
                    }
                }

                $stmt = $pdo->query("SELECT failed_login_attempts, lockout_until FROM users WHERE email = 'super@voak.test'");
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                T::assertEquals(5, (int)$row['failed_login_attempts']);
                T::assertTrue($row['lockout_until'] !== null);

                // Correct password during lockout MUST still be rejected
                T::expectException(AuthenticationException::class, function () use ($auth, $pdo) {
                    $auth->authenticate($pdo, 'super@voak.test', 'VoakAdmin#2026!Secure');
                }, 'Invalid credentials or account locked.');
            });

            // 7. Protected operational routes require authentication and proper RBAC permission
            T::test('privileged operational routes enforce authentication and granular RBAC permission', function () {
                $pdo = self::createDatabase();
                $config = new Config([
                    'AUTH_SIGNING_KEY'        => 'auth-signing-key-32-chars-long!',
                    'MFA_ENCRYPTION_KEY'      => 'mfa-encryption-key-32-chars-long!',
                    'CSRF_SIGNING_KEY'        => 'csrf-signing-key-32-chars-long!',
                    'APP_ENV'                 => 'testing',
                    'APP_DEBUG'               => true,
                    'ALLOW_DEV_RESET_TOKENS'  => false,
                ]);

                $app = new App($config);
                /** @var Connection $conn */
                $conn = $app->getContainer()->get(Connection::class);
                $conn->setMockPdo($pdo);

                // 7A: Unauthenticated access to /api/v1/admin/users -> 401
                $unauthReq = new Request('GET', '/api/v1/admin/users');
                $unauthRes = $app->handle($unauthReq);
                T::assertEquals(401, $unauthRes->getStatusCode());

                // 7B: Authenticate as Content Editor (User 2, lacks user:list)
                $sm = new SessionManager();
                $editorSession = $sm->createSession($pdo, 2, '127.0.0.1', 'TestBrowser');

                $editorReq = new Request(
                    'GET',
                    '/api/v1/admin/users',
                    [],
                    ['Cookie' => "voak_admin_session={$editorSession['session_id']}"]
                );
                $editorRes = $app->handle($editorReq);
                T::assertEquals(403, $editorRes->getStatusCode(), 'Content Editor must be denied 403 Forbidden on user:list');
                $editorBody = json_decode($editorRes->getBody(), true);
                T::assertEquals('ERR_FORBIDDEN', $editorBody['error']['code']);

                // 7C: Authenticate as Super Admin (User 1, has user:list)
                $adminSession = $sm->createSession($pdo, 1, '127.0.0.1', 'TestBrowser');
                $adminReq = new Request(
                    'GET',
                    '/api/v1/admin/users',
                    [],
                    ['Cookie' => "voak_admin_session={$adminSession['session_id']}"]
                );
                $adminRes = $app->handle($adminReq);
                T::assertEquals(200, $adminRes->getStatusCode(), 'Super Admin must be authorized on user:list');
                $adminBody = json_decode($adminRes->getBody(), true);
                T::assertTrue($adminBody['success']);
                T::assertTrue(count($adminBody['data']['users']) >= 2);
            });

            // 8. Reconciled CSRF Protection on Unauthenticated Authentication Routes
            T::test('unauthenticated auth endpoints strictly enforce CSRF bootstrap tokens', function () {
                $pdo = self::createDatabase();
                $config = new Config([
                    'AUTH_SIGNING_KEY'        => 'auth-signing-key-32-chars-long!',
                    'MFA_ENCRYPTION_KEY'      => 'mfa-encryption-key-32-chars-long!',
                    'CSRF_SIGNING_KEY'        => 'csrf-signing-key-32-chars-long!',
                    'APP_ENV'                 => 'testing',
                    'ALLOW_DEV_RESET_TOKENS'  => true,
                ]);

                $app = new App($config);
                /** @var Connection $conn */
                $conn = $app->getContainer()->get(Connection::class);
                $conn->setMockPdo($pdo);

                // 8A: Password reset request without CSRF token returns 403
                $noCsrfReq = new Request(
                    'POST',
                    '/api/v1/admin/auth/password-reset/request',
                    [],
                    ['Content-Type' => 'application/json'],
                    json_encode(['email' => 'super@voak.test'])
                );
                $noCsrfRes = $app->handle($noCsrfReq);
                T::assertEquals(403, $noCsrfRes->getStatusCode());
                $noCsrfData = json_decode($noCsrfRes->getBody(), true);
                T::assertEquals('ERR_CSRF_MISSING', $noCsrfData['error']['code']);

                // 8B: Obtain bootstrap token from GET /api/v1/admin/auth/csrf
                $csrfReq = new Request('GET', '/api/v1/admin/auth/csrf');
                $csrfRes = $app->handle($csrfReq);
                T::assertEquals(200, $csrfRes->getStatusCode());
                $csrfBody = json_decode($csrfRes->getBody(), true);
                $token = $csrfBody['data']['csrf_token'];
                $cookieHdr = $csrfRes->getHeader('Set-Cookie');
                preg_match('/voak_csrf=([^;]+)/', $cookieHdr, $matches);
                $cookieVal = $matches[1] ?? '';

                // 8C: Password reset request with valid bootstrap CSRF succeeds
                $validCsrfReq = new Request(
                    'POST',
                    '/api/v1/admin/auth/password-reset/request',
                    [],
                    [
                        'Content-Type' => 'application/json',
                        'X-CSRF-Token' => $token,
                        'Cookie'       => "voak_csrf={$cookieVal}",
                    ],
                    json_encode(['email' => 'super@voak.test'])
                );
                $validCsrfRes = $app->handle($validCsrfReq);
                T::assertEquals(200, $validCsrfRes->getStatusCode());
                $validCsrfData = json_decode($validCsrfRes->getBody(), true);
                T::assertTrue($validCsrfData['success']);
                T::assertTrue(isset($validCsrfData['data']['dev_reset_token']));
            });

            // 9. Secrets are not exposed through command arguments or normal API responses
            T::test('dev reset tokens are concealed by default unless explicitly configured in tests', function () {
                $pdo = self::createDatabase();
                $defaultConfig = new Config([
                    'AUTH_SIGNING_KEY'       => 'auth-signing-key-32-chars-long!',
                    'MFA_ENCRYPTION_KEY'     => 'mfa-encryption-key-32-chars-long!',
                    'CSRF_SIGNING_KEY'       => 'csrf-signing-key-32-chars-long!',
                    'APP_ENV'                => 'testing',
                    'ALLOW_DEV_RESET_TOKENS' => false,
                ]);

                $appDefault = new App($defaultConfig);
                /** @var Connection $conn */
                $conn = $appDefault->getContainer()->get(Connection::class);
                $conn->setMockPdo($pdo);

                // Get bootstrap CSRF
                $csrfMiddleware = new CsrfProtectionMiddleware($conn, $defaultConfig);
                $bt = $csrfMiddleware->generateBootstrapToken();

                $req = new Request(
                    'POST',
                    '/api/v1/admin/auth/password-reset/request',
                    [],
                    [
                        'Content-Type' => 'application/json',
                        'X-CSRF-Token' => $bt['token'],
                        'Cookie'       => "voak_csrf={$bt['cookie_value']}",
                    ],
                    json_encode(['email' => 'super@voak.test'])
                );
                $resDefault = $appDefault->handle($req);
                $dataDefault = json_decode($resDefault->getBody(), true);
                T::assertEquals(200, $resDefault->getStatusCode());
                T::assertFalse(isset($dataDefault['data']['dev_reset_token']), 'Reset token must NOT be disclosed by default');

                // When explicitly allowed in test configuration:
                $testConfig = new Config([
                    'AUTH_SIGNING_KEY'       => 'auth-signing-key-32-chars-long!',
                    'MFA_ENCRYPTION_KEY'     => 'mfa-encryption-key-32-chars-long!',
                    'CSRF_SIGNING_KEY'       => 'csrf-signing-key-32-chars-long!',
                    'APP_ENV'                => 'testing',
                    'ALLOW_DEV_RESET_TOKENS' => true,
                ]);
                $appTest = new App($testConfig);
                $connTest = $appTest->getContainer()->get(Connection::class);
                $connTest->setMockPdo($pdo);

                $csrfMwTest = new CsrfProtectionMiddleware($connTest, $testConfig);
                $btTest = $csrfMwTest->generateBootstrapToken();

                $reqTest = new Request(
                    'POST',
                    '/api/v1/admin/auth/password-reset/request',
                    [],
                    [
                        'Content-Type' => 'application/json',
                        'X-CSRF-Token' => $btTest['token'],
                        'Cookie'       => "voak_csrf={$btTest['cookie_value']}",
                    ],
                    json_encode(['email' => 'super@voak.test'])
                );

                $resTest = $appTest->handle($reqTest);
                $dataTest = json_decode($resTest->getBody(), true);
                T::assertTrue(isset($dataTest['data']['dev_reset_token']), 'Reset token is disclosed only when ALLOW_DEV_RESET_TOKENS is true');
            });
        });
    }
}
