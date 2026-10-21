<?php

declare(strict_types=1);

namespace Voak\Health\Tests\Unit;

use Voak\Health\Auth\SessionManager;
use Voak\Health\Database\Migration\MigrationManager;
use Voak\Health\Tests\TestFramework as T;

class SessionManagerTest
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
        $pdo->exec("INSERT INTO users (id, name, email, password_hash, is_active, created_at, updated_at) VALUES (1, 'Test Admin', 'admin@voak.test', 'hash', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");

        return $pdo;
    }

    public static function run(): void
    {
        T::suite('Phase 6 — SessionManager Security & Lifetime Tests', function () {
            T::test('creates session with 64-char token and csrf token', function () {
                $pdo = self::createDatabase();
                $sm = new SessionManager();

                $session = $sm->createSession($pdo, 1, '127.0.0.1', 'TestBrowser/1.0');

                T::assertEquals(64, strlen($session['session_id']));
                T::assertEquals(64, strlen($session['csrf_token']));

                $validated = $sm->validateSession($pdo, $session['session_id']);
                T::assertTrue($validated !== null);
                T::assertEquals(1, (int)$validated['user_id']);
                T::assertEquals('admin@voak.test', $validated['user_email']);
            });

            T::test('enforces 15-minute idle timeout on inactive sessions', function () {
                $pdo = self::createDatabase();
                $sm = new SessionManager();

                $session = $sm->createSession($pdo, 1, '127.0.0.1', 'TestBrowser/1.0');
                $sessionId = $session['session_id'];

                // Backdate last_activity_at by 16 minutes (960 seconds)
                $oldTime = gmdate('Y-m-d H:i:s', time() - 960);
                $pdo->exec("UPDATE admin_sessions SET last_activity_at = '{$oldTime}' WHERE id = '{$sessionId}'");

                $validated = $sm->validateSession($pdo, $sessionId);
                T::assertEquals(null, $validated, 'Idle session > 15m must be invalidated');
            });

            T::test('enforces 8-hour absolute maximum lifetime', function () {
                $pdo = self::createDatabase();
                $sm = new SessionManager();

                $session = $sm->createSession($pdo, 1, '127.0.0.1', 'TestBrowser/1.0');
                $sessionId = $session['session_id'];

                // Backdate created_at and expires_at to simulate expired session
                $pastExpires = gmdate('Y-m-d H:i:s', time() - 10);
                $pdo->exec("UPDATE admin_sessions SET expires_at = '{$pastExpires}' WHERE id = '{$sessionId}'");

                $validated = $sm->validateSession($pdo, $sessionId);
                T::assertEquals(null, $validated, 'Session > 8h absolute lifetime must be invalidated');
            });

            T::test('prunes oldest session when concurrent limit of 3 is exceeded', function () {
                $pdo = self::createDatabase();
                $sm = new SessionManager();

                $s1 = $sm->createSession($pdo, 1, '127.0.0.1', 'Device 1');
                sleep(1);
                $s2 = $sm->createSession($pdo, 1, '127.0.0.1', 'Device 2');
                sleep(1);
                $s3 = $sm->createSession($pdo, 1, '127.0.0.1', 'Device 3');

                // 3 active sessions
                T::assertTrue($sm->validateSession($pdo, $s1['session_id']) !== null);
                T::assertTrue($sm->validateSession($pdo, $s2['session_id']) !== null);
                T::assertTrue($sm->validateSession($pdo, $s3['session_id']) !== null);

                // Create 4th session - oldest (s1) must be pruned
                $s4 = $sm->createSession($pdo, 1, '127.0.0.1', 'Device 4');
                T::assertEquals(null, $sm->validateSession($pdo, $s1['session_id']), 'Oldest session must be pruned');
                T::assertTrue($sm->validateSession($pdo, $s2['session_id']) !== null);
                T::assertTrue($sm->validateSession($pdo, $s3['session_id']) !== null);
                T::assertTrue($sm->validateSession($pdo, $s4['session_id']) !== null);
            });

            T::test('destroys individual session and all user sessions', function () {
                $pdo = self::createDatabase();
                $sm = new SessionManager();

                $s1 = $sm->createSession($pdo, 1, '127.0.0.1', 'Device 1');
                $s2 = $sm->createSession($pdo, 1, '127.0.0.1', 'Device 2');

                // Destroy single
                $sm->destroySession($pdo, $s1['session_id']);
                T::assertEquals(null, $sm->validateSession($pdo, $s1['session_id']));
                T::assertTrue($sm->validateSession($pdo, $s2['session_id']) !== null);

                // Destroy all for user
                $sm->destroyUserSessions($pdo, 1);
                T::assertEquals(null, $sm->validateSession($pdo, $s2['session_id']));
            });

            T::test('generates secure HttpOnly SameSite cookie header', function () {
                $sm = new SessionManager();
                $header = $sm->buildSessionCookie('test_session_id_64_chars_long_1234567890abcdef1234567890abcdef123456', true);

                T::assertContains('voak_admin_session=', $header);
                T::assertContains('Path=/api/v1/admin', $header);
                T::assertContains('HttpOnly', $header);
                T::assertContains('SameSite=Lax', $header);
                T::assertContains('Secure', $header);

                $clear = $sm->buildExpiredCookie(true);
                T::assertContains('Max-Age=0', $clear);
                T::assertContains('Path=/api/v1/admin', $clear);
            });
        });
    }
}
