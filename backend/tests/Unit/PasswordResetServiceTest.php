<?php

declare(strict_types=1);

namespace Voak\Health\Tests\Unit;

use Voak\Health\Auth\PasswordResetService;
use Voak\Health\Auth\SessionManager;
use Voak\Health\Database\Migration\MigrationManager;
use Voak\Health\Security\AuditLogger;
use Voak\Health\Security\PasswordHasher;
use Voak\Health\Tests\TestFramework as T;

class PasswordResetServiceTest
{
    private static function createDatabase(): \PDO
    {
        $pdo = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ]);
        $migrationsDir = dirname(__DIR__, 2) . '/database/migrations';
        $manager = new MigrationManager($migrationsDir, 'migrations');
        $manager->migrateUp($pdo);

        // Insert test user
        $hasher = new PasswordHasher();
        $hash = $hasher->hash('OldSecurePassword#123!');
        $pdo->exec("INSERT INTO users (id, name, email, password_hash, is_active, created_at, updated_at) VALUES (1, 'Reset User', 'reset_user@voak.test', '{$hash}', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");

        return $pdo;
    }

    public static function run(): void
    {
        T::suite('Phase 6 — PasswordResetService Single-Use & Expiry Tests', function () {
            T::test('generates secure 64-char single-use token', function () {
                $pdo = self::createDatabase();
                $hasher = new PasswordHasher();
                $audit = new AuditLogger();
                $sm = new SessionManager();
                $service = new PasswordResetService($hasher, $sm, $audit);

                $token = $service->requestReset($pdo, 'reset_user@voak.test', '127.0.0.1', 'TestBrowser');
                T::assertTrue($token !== null);
                T::assertEquals(64, strlen($token));

                $stmt = $pdo->prepare('SELECT * FROM password_resets WHERE user_id = 1 AND used_at IS NULL');
                $stmt->execute();
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                T::assertTrue($row !== false);
                T::assertEquals(hash('sha256', $token), $row['token_hash']);
            });

            T::test('returns null for non-existent email without leaking existence', function () {
                $pdo = self::createDatabase();
                $hasher = new PasswordHasher();
                $audit = new AuditLogger();
                $sm = new SessionManager();
                $service = new PasswordResetService($hasher, $sm, $audit);

                $token = $service->requestReset($pdo, 'nonexistent@voak.test', '127.0.0.1', 'TestBrowser');
                T::assertEquals(null, $token);
            });

            T::test('resets password, invalidates active sessions, and logs audit record', function () {
                $pdo = self::createDatabase();
                $hasher = new PasswordHasher();
                $audit = new AuditLogger();
                $sm = new SessionManager();
                $service = new PasswordResetService($hasher, $sm, $audit);

                // Create an active session for the user
                $session = $sm->createSession($pdo, 1, '127.0.0.1', 'TestBrowser');
                T::assertTrue($sm->validateSession($pdo, $session['session_id']) !== null);

                // Generate reset token
                $token = $service->requestReset($pdo, 'reset_user@voak.test', '127.0.0.1', 'TestBrowser');

                // Perform reset
                $success = $service->confirmReset($pdo, $token, 'BrandNewP@ssword#2026!', '127.0.0.1', 'TestBrowser');
                T::assertTrue($success);

                // Verify previous session was revoked
                T::assertEquals(null, $sm->validateSession($pdo, $session['session_id']));

                // Verify new password works in database
                $stmt = $pdo->query('SELECT password_hash FROM users WHERE id = 1');
                $newHash = $stmt->fetchColumn();
                T::assertTrue($hasher->verify('BrandNewP@ssword#2026!', $newHash));

                // Verify audit log entry
                $stmtAudit = $pdo->query("SELECT action FROM audit_logs WHERE action = 'AUTH_PASSWORD_RESET_COMPLETE'");
                T::assertEquals('AUTH_PASSWORD_RESET_COMPLETE', $stmtAudit->fetchColumn());
            });

            T::test('rejects token replay: token cannot be used twice', function () {
                $pdo = self::createDatabase();
                $hasher = new PasswordHasher();
                $audit = new AuditLogger();
                $sm = new SessionManager();
                $service = new PasswordResetService($hasher, $sm, $audit);

                $token = $service->requestReset($pdo, 'reset_user@voak.test', '127.0.0.1', 'TestBrowser');
                $first = $service->confirmReset($pdo, $token, 'FirstChange#2026!Pass', '127.0.0.1', 'TestBrowser');
                T::assertTrue($first);

                // Second attempt with same token must return false
                $second = $service->confirmReset($pdo, $token, 'SecondAttempt#2026!Pass', '127.0.0.1', 'TestBrowser');
                T::assertFalse($second, 'Token must not be reusable');
            });

            T::test('rejects expired reset token (> 1 hour)', function () {
                $pdo = self::createDatabase();
                $hasher = new PasswordHasher();
                $audit = new AuditLogger();
                $sm = new SessionManager();
                $service = new PasswordResetService($hasher, $sm, $audit);

                $token = $service->requestReset($pdo, 'reset_user@voak.test', '127.0.0.1', 'TestBrowser');
                $tokenHash = hash('sha256', $token);

                // Expire token in database (set expires_at in the past)
                $past = gmdate('Y-m-d H:i:s', time() - 60);
                $pdo->exec("UPDATE password_resets SET expires_at = '{$past}' WHERE token_hash = '{$tokenHash}'");

                $result = $service->confirmReset($pdo, $token, 'ExpiredReset#2026!Pass', '127.0.0.1', 'TestBrowser');
                T::assertFalse($result, 'Expired token must fail validation');
            });
        });
    }
}
