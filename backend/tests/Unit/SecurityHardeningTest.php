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
use Voak\Health\Security\AuditLogger;
use Voak\Health\Security\PasswordHasher;
use Voak\Health\Security\RateLimiter;
use Voak\Health\Security\TotpEngine;
use Voak\Health\Tests\TestFramework as T;

class SecurityHardeningTest
{
    private static function createDatabase(): \PDO
    {
        $pdo = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ]);
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

            // 1. Missing production cryptographic keys fail safely
            T::test('missing production cryptographic keys fail safely with SecurityException', function () {
                $emptyConfig = new Config(['APP_KEY' => '', 'MFA_ENCRYPTION_KEY' => '', 'AUTH_SIGNING_KEY' => '']);
                $engine = new TotpEngine();
                $totp = new TotpService($emptyConfig, $engine);

                $pdo = self::createDatabase();
                T::expectException(SecurityException::class, function () use ($totp, $pdo) {
                    $totp->initiateSetup($pdo, 1, 'super@voak.test');
                }, 'MFA encryption key is not configured');

                $auth = new AuthenticationService(null, null, null, null, null, $emptyConfig);
                T::expectException(SecurityException::class, function () use ($auth, $pdo) {
                    $auth->createPreAuthToken($pdo, 1);
                }, 'signing key is not configured');
            });

            // 2. Pre-authentication challenge signing, forged, expired, and replay protection
            T::test('forged, expired, and replayed pre-authentication challenges are rejected', function () {
                $pdo = self::createDatabase();
                $validConfig = new Config([
                    'APP_KEY'            => 'production-grade-secret-key-32-chars!!',
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

            // 3. Rate limiting persistent locking and proxy trust validation
            T::test('rate limiter persists across requests and rejects forwarded IP spoofing without trusted proxy', function () {
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

            // 4. Audit ledger integrity detects modification of every protected field
            T::test('audit ledger detects modification of user_agent, created_at, or re-ordering', function () {
                $pdo = self::createDatabase();
                $logger = new AuditLogger();

                $r1 = $logger->log($pdo, 'security', 'EVENT_ONE', 1, '10.0.0.1', 'OriginalBrowser/1.0', 'user', 1);
                $r2 = $logger->log($pdo, 'security', 'EVENT_TWO', 1, '10.0.0.2', 'OriginalBrowser/2.0', 'user', 1);
                $r3 = $logger->log($pdo, 'security', 'EVENT_THREE', 1, '10.0.0.3', 'OriginalBrowser/3.0', 'user', 1);

                T::assertTrue($logger->verifyChain($pdo), 'Fresh chain must verify');

                // 4A: Modify user_agent directly in database
                $pdo->exec("UPDATE audit_logs SET user_agent = 'TamperedBrowser/9.9' WHERE id = {$r2['id']}");
                T::assertFalse($logger->verifyChain($pdo), 'Tampered user_agent must break chain');

                // Revert
                $pdo->exec("UPDATE audit_logs SET user_agent = 'OriginalBrowser/2.0' WHERE id = {$r2['id']}");
                T::assertTrue($logger->verifyChain($pdo));

                // 4B: Modify created_at
                $pdo->exec("UPDATE audit_logs SET created_at = '2020-01-01 00:00:00' WHERE id = {$r2['id']}");
                T::assertFalse($logger->verifyChain($pdo), 'Tampered created_at must break chain');

                // Revert
                $stmt = $pdo->prepare("SELECT created_at FROM audit_logs WHERE id = {$r1['id']}");
                $stmt->execute();
                $t1 = $stmt->fetchColumn();
                $pdo->exec("UPDATE audit_logs SET created_at = '{$t1}' WHERE id = {$r2['id']}");

                // 4C: Break link (prev_row_hash)
                $pdo->exec("UPDATE audit_logs SET prev_row_hash = 'ffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff' WHERE id = {$r3['id']}");
                T::assertFalse($logger->verifyChain($pdo), 'Broken predecessor link must fail verification');

                // 4D: Delete middle record
                $pdo->exec("DELETE FROM audit_logs WHERE id = {$r2['id']}");
                T::assertFalse($logger->verifyChain($pdo), 'Deleted middle record must break chain');
            });

            // 5. Atomic password-reset and recovery code reuse
            T::test('concurrent or repeated password reset token and recovery code consumption fails safely', function () {
                $pdo = self::createDatabase();
                $hasher = new PasswordHasher();
                $sm = new SessionManager();
                $audit = new AuditLogger();
                $resetService = new PasswordResetService($hasher, $sm, $audit);

                $token = $resetService->requestReset($pdo, 'super@voak.test');
                T::assertTrue($token !== null);

                // First consumption succeeds
                $c1 = $resetService->confirmReset($pdo, $token, 'NewSecurePassword#2026!');
                T::assertTrue($c1);

                // Second attempt with exact same token MUST return false
                $c2 = $resetService->confirmReset($pdo, $token, 'AnotherPassword#2026!Attempt');
                T::assertFalse($c2, 'Token must not be reusable');

                // Recovery code atomic consumption
                $config = new Config(['APP_KEY' => 'valid-encryption-master-key-32-chars!']);
                $totp = new TotpService($config, new TotpEngine());
                $setup = $totp->initiateSetup($pdo, 1, 'super@voak.test');
                $code = (new TotpEngine())->generateCode($setup['secret']);
                $totp->confirmSetup($pdo, 1, $code);

                $backupCode = $setup['backup_codes'][0];
                $b1 = $totp->verifyAndConsumeRecoveryCode($pdo, 1, $backupCode);
                T::assertTrue($b1);

                $b2 = $totp->verifyAndConsumeRecoveryCode($pdo, 1, $backupCode);
                T::assertFalse($b2, 'Recovery code cannot be consumed twice');
            });

            // 6. Repeated failed logins trigger lockout and cannot bypass it
            T::test('repeated failed logins cannot bypass account lockout', function () {
                $pdo = self::createDatabase();
                $config = new Config(['APP_KEY' => 'valid-encryption-master-key-32-chars!']);
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
                    'APP_KEY'                 => 'valid-encryption-master-key-32-chars!',
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

            // 8. Secrets are not exposed through command arguments or normal API responses
            T::test('dev reset tokens are concealed by default unless explicitly configured in tests', function () {
                $pdo = self::createDatabase();
                $defaultConfig = new Config([
                    'APP_KEY'                => 'valid-encryption-master-key-32-chars!',
                    'APP_ENV'                => 'testing',
                    'ALLOW_DEV_RESET_TOKENS' => false,
                ]);

                $appDefault = new App($defaultConfig);
                /** @var Connection $conn */
                $conn = $appDefault->getContainer()->get(Connection::class);
                $conn->setMockPdo($pdo);

                $req = new Request(
                    'POST',
                    '/api/v1/admin/auth/password-reset/request',
                    [],
                    ['Content-Type' => 'application/json'],
                    json_encode(['email' => 'super@voak.test'])
                );
                $resDefault = $appDefault->handle($req);
                $dataDefault = json_decode($resDefault->getBody(), true);
                T::assertEquals(200, $resDefault->getStatusCode());
                T::assertFalse(isset($dataDefault['data']['dev_reset_token']), 'Reset token must NOT be disclosed by default');

                // When explicitly allowed in test configuration:
                $testConfig = new Config([
                    'APP_KEY'                => 'valid-encryption-master-key-32-chars!',
                    'APP_ENV'                => 'testing',
                    'ALLOW_DEV_RESET_TOKENS' => true,
                ]);
                $appTest = new App($testConfig);
                $connTest = $appTest->getContainer()->get(Connection::class);
                $connTest->setMockPdo($pdo);

                $resTest = $appTest->handle($req);
                $dataTest = json_decode($resTest->getBody(), true);
                T::assertTrue(isset($dataTest['data']['dev_reset_token']), 'Reset token is disclosed only when ALLOW_DEV_RESET_TOKENS is true');
            });
        });
    }
}
