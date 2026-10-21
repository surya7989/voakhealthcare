<?php

declare(strict_types=1);

namespace Voak\Health\Tests\Unit;

use Voak\Health\Auth\AuthenticationService;
use Voak\Health\Auth\RbacManager;
use Voak\Health\Auth\SessionManager;
use Voak\Health\Auth\TotpService;
use Voak\Health\Core\Config;
use Voak\Health\Core\Exceptions\AuthenticationException;
use Voak\Health\Database\Migration\MigrationManager;
use Voak\Health\Security\AuditLogger;
use Voak\Health\Security\PasswordHasher;
use Voak\Health\Security\TotpEngine;
use Voak\Health\Tests\TestFramework as T;

class AuthenticationServiceTest
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
        $hash = $hasher->hash('Admin@Voak#2026!');

        $pdo->exec("INSERT INTO users (id, name, email, password_hash, is_active, failed_login_attempts, created_at, updated_at) VALUES (1, 'Active Admin', 'active@voak.test', '{$hash}', 1, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
        $pdo->exec("INSERT INTO users (id, name, email, password_hash, is_active, failed_login_attempts, created_at, updated_at) VALUES (2, 'Inactive Admin', 'inactive@voak.test', '{$hash}', 0, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");

        $rbac = new RbacManager();
        $rbac->seedRbac($pdo);
        $rbac->assignRole($pdo, 1, RbacManager::ROLE_SUPER_ADMIN);

        return $pdo;
    }

    public static function run(): void
    {
        T::suite('Phase 6 — AuthenticationService & Account Lockout Tests', function () {
            $config = new Config([
                'APP_KEY' => 'base64:' . base64_encode('test-encryption-key-32-chars-long!'),
                'MFA_ENCRYPTION_KEY' => 'test-encryption-key-32-chars-long!',
            ]);

            T::test('authenticates active user successfully and creates session', function () use ($config) {
                $pdo = self::createDatabase();
                $hasher = new PasswordHasher();
                $engine = new TotpEngine();
                $audit = new AuditLogger();
                $sm = new SessionManager();
                $totp = new TotpService($config, $engine);
                $rbac = new RbacManager();

                $auth = new AuthenticationService($hasher, $sm, $totp, $rbac, $audit, $config);

                $res = $auth->authenticate($pdo, 'active@voak.test', 'Admin@Voak#2026!', '127.0.0.1', 'TestBrowser');

                T::assertEquals('authenticated', $res['status']);
                T::assertEquals(1, $res['user']['id']);
                T::assertEquals('active@voak.test', $res['user']['email']);
                T::assertTrue(strlen($res['session']['session_id']) === 64);
                T::assertTrue(in_array('super_admin', $res['user']['roles'], true));
            });

            T::test('rejects inactive user with AuthenticationException', function () use ($config) {
                $pdo = self::createDatabase();
                $hasher = new PasswordHasher();
                $engine = new TotpEngine();
                $audit = new AuditLogger();
                $sm = new SessionManager();
                $totp = new TotpService($config, $engine);
                $rbac = new RbacManager();

                $auth = new AuthenticationService($hasher, $sm, $totp, $rbac, $audit, $config);

                T::expectException(AuthenticationException::class, function () use ($auth, $pdo) {
                    $auth->authenticate($pdo, 'inactive@voak.test', 'Admin@Voak#2026!', '127.0.0.1', 'TestBrowser');
                }, 'Account is inactive or disabled.');
            });

            T::test('increments failed login attempts and triggers 15-minute account lockout on 5th failure', function () use ($config) {
                $pdo = self::createDatabase();
                $hasher = new PasswordHasher();
                $engine = new TotpEngine();
                $audit = new AuditLogger();
                $sm = new SessionManager();
                $totp = new TotpService($config, $engine);
                $rbac = new RbacManager();

                $auth = new AuthenticationService($hasher, $sm, $totp, $rbac, $audit, $config);

                // Fail 4 times
                for ($i = 1; $i <= 4; $i++) {
                    try {
                        $auth->authenticate($pdo, 'active@voak.test', 'WrongPassword!', '127.0.0.1', 'TestBrowser');
                    } catch (AuthenticationException $e) {
                        T::assertEquals('Invalid credentials or account locked.', $e->getMessage());
                    }
                }

                // Check failed count in DB
                $stmt = $pdo->query('SELECT failed_login_attempts, lockout_until FROM users WHERE id = 1');
                $userRow = $stmt->fetch(\PDO::FETCH_ASSOC);
                T::assertEquals(4, (int)$userRow['failed_login_attempts']);
                T::assertEquals(null, $userRow['lockout_until']);

                // 5th failure triggers lockout
                try {
                    $auth->authenticate($pdo, 'active@voak.test', 'WrongPassword!', '127.0.0.1', 'TestBrowser');
                } catch (AuthenticationException $e) {
                    T::assertEquals('Invalid credentials or account locked.', $e->getMessage());
                }

                $stmt = $pdo->query('SELECT failed_login_attempts, lockout_until FROM users WHERE id = 1');
                $userRowLocked = $stmt->fetch(\PDO::FETCH_ASSOC);
                T::assertEquals(5, (int)$userRowLocked['failed_login_attempts']);
                T::assertTrue($userRowLocked['lockout_until'] !== null);

                // Subsequent attempt with correct password during lockout MUST still fail
                T::expectException(AuthenticationException::class, function () use ($auth, $pdo) {
                    $auth->authenticate($pdo, 'active@voak.test', 'Admin@Voak#2026!', '127.0.0.1', 'TestBrowser');
                }, 'Invalid credentials or account locked.');
            });

            T::test('challenges for MFA when enabled and authenticates with TOTP code', function () use ($config) {
                $pdo = self::createDatabase();
                $hasher = new PasswordHasher();
                $engine = new TotpEngine();
                $audit = new AuditLogger();
                $sm = new SessionManager();
                $totp = new TotpService($config, $engine);
                $rbac = new RbacManager();

                $auth = new AuthenticationService($hasher, $sm, $totp, $rbac, $audit, $config);

                // Setup MFA for active user
                $setup = $totp->initiateSetup($pdo, 1, 'active@voak.test');
                $code = $engine->generateCode($setup['secret']);
                $totp->confirmSetup($pdo, 1, $code);

                // Primary login now returns status 'requires_mfa'
                $firstStep = $auth->authenticate($pdo, 'active@voak.test', 'Admin@Voak#2026!', '127.0.0.1', 'TestBrowser');
                T::assertEquals('requires_mfa', $firstStep['status']);
                T::assertTrue(isset($firstStep['pre_auth_token']));

                // Second step: MFA code verification
                $secondStep = $auth->verifyMfaAndEstablishSession($pdo, $firstStep['pre_auth_token'], $code, false, '127.0.0.1', 'TestBrowser');
                T::assertEquals('authenticated', $secondStep['status']);
                T::assertEquals(1, $secondStep['user']['id']);
                T::assertEquals(64, strlen($secondStep['session']['session_id']));
            });
        });
    }
}
