<?php

declare(strict_types=1);

namespace Voak\Health\Tests\Integration;

use Voak\Health\Auth\RbacManager;
use Voak\Health\Core\App;
use Voak\Health\Core\Config;
use Voak\Health\Core\Http\Request;
use Voak\Health\Database\Migration\MigrationManager;
use Voak\Health\Security\PasswordHasher;
use Voak\Health\Tests\TestFramework as T;

class AdminAuthApiTest
{
    private static function createAppWithMigratedDatabase(): App
    {
        $pdo = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ]);
        $migrationsDir = dirname(__DIR__, 2) . '/database/migrations';
        $manager = new MigrationManager($migrationsDir, 'migrations');
        $manager->migrateUp($pdo);

        $hasher = new PasswordHasher();
        $hash = $hasher->hash('VoakAdmin#2026!Secure');

        $pdo->exec("INSERT INTO users (id, name, email, password_hash, is_active, created_at, updated_at) VALUES (1, 'Voak Admin', 'admin@voak.test', '{$hash}', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");

        $rbac = new RbacManager();
        $rbac->seedRbac($pdo);
        $rbac->assignRole($pdo, 1, RbacManager::ROLE_SUPER_ADMIN);

        $config = new Config([
            'APP_KEY' => 'base64:' . base64_encode('test-encryption-key-32-chars-long!'),
            'APP_ENV' => 'testing',
            'APP_DEBUG' => true,
        ]);

        $app = new App($config);
        /** @var \Voak\Health\Database\Connection $conn */
        $conn = $app->getContainer()->get(\Voak\Health\Database\Connection::class);
        $conn->setMockPdo($pdo);

        return $app;
    }

    public static function run(): void
    {
        T::suite('Phase 6 — Admin Authentication & RBAC API Integration Tests', function () {
            T::test('POST /api/v1/admin/auth/login with wrong credentials returns HTTP 401', function () {
                $app = self::createAppWithMigratedDatabase();

                $req = new Request(
                    'POST',
                    '/api/v1/admin/auth/login',
                    [],
                    ['Content-Type' => 'application/json'],
                    json_encode(['email' => 'admin@voak.test', 'password' => 'WrongPassword!'])
                );

                $res = $app->handle($req);
                T::assertEquals(401, $res->getStatusCode());

                $data = json_decode($res->getBody(), true);
                T::assertFalse($data['success']);
                T::assertEquals('UNAUTHORIZED', $data['error']['code']);
            });

            T::test('POST /api/v1/admin/auth/login with valid credentials returns 200 and Set-Cookie', function () {
                $app = self::createAppWithMigratedDatabase();

                $req = new Request(
                    'POST',
                    '/api/v1/admin/auth/login',
                    [],
                    ['Content-Type' => 'application/json'],
                    json_encode(['email' => 'admin@voak.test', 'password' => 'VoakAdmin#2026!Secure'])
                );

                $res = $app->handle($req);
                T::assertEquals(200, $res->getStatusCode());

                $data = json_decode($res->getBody(), true);
                T::assertTrue($data['success']);
                T::assertEquals('admin@voak.test', $data['data']['user']['email']);
                T::assertTrue(in_array('super_admin', $data['data']['user']['roles'], true));
                T::assertTrue(isset($data['data']['session']['csrf_token']));

                // Check Set-Cookie
                $cookie = $res->getHeader('Set-Cookie');
                T::assertTrue($cookie !== null);
                T::assertContains('voak_admin_session=', $cookie);
                T::assertContains('HttpOnly', $cookie);
                T::assertContains('SameSite=Lax', $cookie);
            });

            T::test('GET /api/v1/admin/auth/me returns 401 when unauthenticated', function () {
                $app = self::createAppWithMigratedDatabase();

                $req = new Request('GET', '/api/v1/admin/auth/me');
                $res = $app->handle($req);

                T::assertEquals(401, $res->getStatusCode());
            });

            T::test('GET /api/v1/admin/auth/me returns user profile when session cookie is provided', function () {
                $app = self::createAppWithMigratedDatabase();

                // 1. Login
                $loginReq = new Request(
                    'POST',
                    '/api/v1/admin/auth/login',
                    [],
                    ['Content-Type' => 'application/json'],
                    json_encode(['email' => 'admin@voak.test', 'password' => 'VoakAdmin#2026!Secure'])
                );
                $loginRes = $app->handle($loginReq);
                $cookieHeader = $loginRes->getHeader('Set-Cookie');
                // Extract session token
                preg_match('/voak_admin_session=([a-f0-9]+);/', $cookieHeader, $matches);
                $sessionToken = $matches[1];

                // 2. Call /me with cookie
                $meReq = new Request(
                    'GET',
                    '/api/v1/admin/auth/me',
                    [],
                    ['Cookie' => "voak_admin_session={$sessionToken}"]
                );
                $meRes = $app->handle($meReq);
                T::assertEquals(200, $meRes->getStatusCode());

                $meData = json_decode($meRes->getBody(), true);
                T::assertTrue($meData['success']);
                T::assertEquals('admin@voak.test', $meData['data']['user']['email']);
                T::assertTrue(in_array('super_admin', $meData['data']['roles'], true));
            });

            T::test('POST /api/v1/admin/auth/logout revokes session and clears cookie', function () {
                $app = self::createAppWithMigratedDatabase();

                // 1. Login
                $loginReq = new Request(
                    'POST',
                    '/api/v1/admin/auth/login',
                    [],
                    ['Content-Type' => 'application/json'],
                    json_encode(['email' => 'admin@voak.test', 'password' => 'VoakAdmin#2026!Secure'])
                );
                $loginRes = $app->handle($loginReq);
                $data = json_decode($loginRes->getBody(), true);
                $csrfToken = $data['data']['session']['csrf_token'];
                $cookieHeader = $loginRes->getHeader('Set-Cookie');
                preg_match('/voak_admin_session=([a-f0-9]+);/', $cookieHeader, $matches);
                $sessionToken = $matches[1];

                // 2. Logout with CSRF token and session cookie
                $logoutReq = new Request(
                    'POST',
                    '/api/v1/admin/auth/logout',
                    [],
                    [
                        'Cookie' => "voak_admin_session={$sessionToken}",
                        'X-CSRF-Token' => $csrfToken,
                    ]
                );
                $logoutRes = $app->handle($logoutReq);
                T::assertEquals(200, $logoutRes->getStatusCode());

                // Cookie cleared
                $logoutCookie = $logoutRes->getHeader('Set-Cookie');
                T::assertContains('Max-Age=0', $logoutCookie);

                // 3. /me now fails
                $meReqAfter = new Request(
                    'GET',
                    '/api/v1/admin/auth/me',
                    [],
                    ['Cookie' => "voak_admin_session={$sessionToken}"]
                );
                $meResAfter = $app->handle($meReqAfter);
                T::assertEquals(401, $meResAfter->getStatusCode());
            });

            T::test('POST mutating admin request without CSRF token returns 403 CSRF_INVALID', function () {
                $app = self::createAppWithMigratedDatabase();

                // Login
                $loginReq = new Request(
                    'POST',
                    '/api/v1/admin/auth/login',
                    [],
                    ['Content-Type' => 'application/json'],
                    json_encode(['email' => 'admin@voak.test', 'password' => 'VoakAdmin#2026!Secure'])
                );
                $loginRes = $app->handle($loginReq);
                $cookieHeader = $loginRes->getHeader('Set-Cookie');
                preg_match('/voak_admin_session=([a-f0-9]+);/', $cookieHeader, $matches);
                $sessionToken = $matches[1];

                // Attempt POST logout without CSRF token
                $noCsrfReq = new Request(
                    'POST',
                    '/api/v1/admin/auth/logout',
                    [],
                    ['Cookie' => "voak_admin_session={$sessionToken}"]
                );
                $noCsrfRes = $app->handle($noCsrfReq);
                T::assertEquals(403, $noCsrfRes->getStatusCode());

                $resData = json_decode($noCsrfRes->getBody(), true);
                T::assertContains('CSRF', $resData['error']['code']);
            });

            T::test('rate limits repeated login attempts (HTTP 429)', function () {
                $app = self::createAppWithMigratedDatabase();

                $loginPayload = json_encode(['email' => 'admin@voak.test', 'password' => 'BadPass!']);
                $hit429 = false;

                // Send 15 login requests from same client IP (limit is 10/min)
                for ($i = 0; $i < 15; $i++) {
                    $req = new Request(
                        'POST',
                        '/api/v1/admin/auth/login',
                        [],
                        [
                            'Content-Type' => 'application/json',
                            'X-Forwarded-For' => '192.168.1.100',
                        ],
                        $loginPayload
                    );
                    $res = $app->handle($req);
                    if ($res->getStatusCode() === 429) {
                        $hit429 = true;
                        T::assertTrue($res->getHeader('Retry-After') !== null);
                        break;
                    }
                }

                T::assertTrue($hit429, 'Expected rate limiter to trigger HTTP 429');
            });
        });
    }
}
