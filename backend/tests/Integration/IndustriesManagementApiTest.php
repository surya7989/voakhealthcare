<?php

declare(strict_types=1);

namespace Voak\Health\Tests\Integration;

use Voak\Health\Auth\RbacManager;
use Voak\Health\Core\App;
use Voak\Health\Core\Config;
use Voak\Health\Core\Http\Request;
use Voak\Health\Database\Connection;
use Voak\Health\Database\Migration\MigrationManager;
use Voak\Health\Security\PasswordHasher;
use Voak\Health\Tests\TestFramework as T;

class IndustriesManagementApiTest
{
    private static function createAppWithMigratedDatabase(): App
    {
        $pdo = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        $pdo->exec("PRAGMA foreign_keys = ON;");

        $migrationsDir = dirname(__DIR__, 2) . '/database/migrations';
        $manager = new MigrationManager($migrationsDir, 'migrations');
        $manager->migrateUp($pdo);

        $hasher = new PasswordHasher();
        $adminHash = $hasher->hash('VoakAdmin#2026!Secure');
        $editorHash = $hasher->hash('VoakEditor#2026!Secure');
        $unprivilegedHash = $hasher->hash('VoakUnpriv#2026!Secure');

        // Super Admin (all permissions)
        $pdo->exec("INSERT INTO users (id, name, email, password_hash, is_active, created_at, updated_at) 
                    VALUES (1, 'Super Admin', 'admin@voak.test', '{$adminHash}', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");

        // Content Editor (draft & edit permissions, cannot publish or delete)
        $pdo->exec("INSERT INTO users (id, name, email, password_hash, is_active, created_at, updated_at) 
                    VALUES (2, 'Content Editor', 'editor@voak.test', '{$editorHash}', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");

        // Unprivileged User (no roles)
        $pdo->exec("INSERT INTO users (id, name, email, password_hash, is_active, created_at, updated_at) 
                    VALUES (3, 'Unprivileged User', 'noperms@voak.test', '{$unprivilegedHash}', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");

        $rbac = new RbacManager();
        $rbac->seedRbac($pdo);
        $rbac->assignRole($pdo, 1, RbacManager::ROLE_SUPER_ADMIN);
        $rbac->assignRole($pdo, 2, RbacManager::ROLE_CONTENT_EDITOR);

        $config = new Config([
            'APP_KEY'            => 'base64:' . base64_encode('test-encryption-key-32-chars-long!'),
            'AUTH_SIGNING_KEY'   => 'auth-signing-key-32-characters-min!!',
            'MFA_ENCRYPTION_KEY' => 'mfa-encryption-key-32-characters-min!!',
            'CSRF_SIGNING_KEY'   => 'csrf-signing-key-32-characters-min!',
            'APP_ENV'            => 'testing',
            'APP_DEBUG'          => true,
        ]);

        $app = new App($config);
        /** @var Connection $conn */
        $conn = $app->getContainer()->get(Connection::class);
        $conn->setMockPdo($pdo);

        \Voak\Health\Middleware\RateLimitMiddleware::reset();

        return $app;
    }

    private static function loginUser(App $app, string $email, string $password): array
    {
        $csrfReq = new Request('GET', '/api/v1/admin/auth/csrf');
        $csrfRes = $app->handle($csrfReq);
        $csrfData = json_decode($csrfRes->getBody(), true);
        $bootstrapToken = (string)($csrfData['data']['csrf_token'] ?? '');
        $cookieHeader = (string)($csrfRes->getHeader('Set-Cookie') ?? '');
        preg_match('/voak_csrf=([^;]+)/', $cookieHeader, $matches);
        $bootstrapCookie = $matches[1] ?? '';

        $loginReq = new Request(
            'POST',
            '/api/v1/admin/auth/login',
            [],
            [
                'Content-Type' => 'application/json',
                'X-CSRF-Token' => $bootstrapToken,
                'Cookie'       => "voak_csrf={$bootstrapCookie}",
            ],
            json_encode(['email' => $email, 'password' => $password])
        );

        $loginRes = $app->handle($loginReq);
        $loginData = json_decode($loginRes->getBody(), true);
        $sessionCookieHeader = (string)($loginRes->getHeader('Set-Cookie') ?? '');
        preg_match('/voak_admin_session=([^;]+)/', $sessionCookieHeader, $sessionMatches);
        $sessionToken = $sessionMatches[1] ?? '';

        $sessionCsrf = (string)($loginData['data']['session']['csrf_token'] ?? '');

        return [
            'session_cookie' => $sessionToken,
            'csrf_token'     => $sessionCsrf,
        ];
    }

    public static function run(): void
    {
        T::suite('Phase 9 — Healthcare Industries & Sectors Management API Tests', function () {

            T::test('Public delivery endpoint returns published industries with ETag and Cache-Control', function () {
                $app = self::createAppWithMigratedDatabase();

                $req = new Request('GET', '/api/v1/public/industries');
                $res = $app->handle($req);

                T::assertEquals(200, $res->getStatusCode());
                $body = json_decode($res->getBody(), true);
                T::assertTrue($body['success']);
                T::assertEquals(6, count($body['data']['industries']));
                T::assertTrue($res->getHeader('ETag') !== null);
                T::assertContains('public', (string)$res->getHeader('Cache-Control'));

                // Verify alias endpoint
                $aliasReq = new Request('GET', '/api/industries');
                $aliasRes = $app->handle($aliasReq);
                T::assertEquals(200, $aliasRes->getStatusCode());
            });

            T::test('Public delivery supports conditional GET returning 304 Not Modified', function () {
                $app = self::createAppWithMigratedDatabase();

                $initialReq = new Request('GET', '/api/v1/public/industries');
                $initialRes = $app->handle($initialReq);
                $etag = (string)$initialRes->getHeader('ETag');

                $condReq = new Request('GET', '/api/v1/public/industries', [], [
                    'If-None-Match' => $etag,
                ]);
                $condRes = $app->handle($condReq);

                T::assertEquals(304, $condRes->getStatusCode());
                T::assertEquals($etag, $condRes->getHeader('ETag'));
                T::assertEquals('', $condRes->getBody());
            });

            T::test('Public single industry delivery returns mapped services and handles 404 for missing', function () {
                $app = self::createAppWithMigratedDatabase();

                $req = new Request('GET', '/api/v1/public/industries/hospitals-health-services');
                $res = $app->handle($req);
                T::assertEquals(200, $res->getStatusCode());
                $body = json_decode($res->getBody(), true);
                T::assertEquals('Hospitals & Health Services', $body['data']['industry']['title']);
                T::assertTrue(is_array($body['data']['industry']['services']));

                // Alias route
                $aliasReq = new Request('GET', '/api/industries/hospitals-health-services');
                $aliasRes = $app->handle($aliasReq);
                T::assertEquals(200, $aliasRes->getStatusCode());

                // Missing industry emits true 404
                $missingReq = new Request('GET', '/api/v1/public/industries/aerospace-medicine');
                $missingRes = $app->handle($missingReq);
                T::assertEquals(404, $missingRes->getStatusCode());
            });

            T::test('Fail-closed authentication: missing or invalid session returns HTTP 401', function () {
                $app = self::createAppWithMigratedDatabase();

                // Missing session
                $req1 = new Request('GET', '/api/v1/admin/industries');
                $res1 = $app->handle($req1);
                T::assertEquals(401, $res1->getStatusCode());
                $body1 = json_decode($res1->getBody(), true);
                T::assertEquals('ERR_UNAUTHENTICATED', $body1['error']['code']);

                // Invalid session cookie
                $req2 = new Request('GET', '/api/v1/admin/industries', [], [
                    'Cookie' => 'voak_admin_session=invalid.bogus.token',
                ]);
                $res2 = $app->handle($req2);
                T::assertEquals(401, $res2->getStatusCode());
            });

            T::test('CSRF protection: mutating requests without CSRF token return HTTP 403', function () {
                $app = self::createAppWithMigratedDatabase();
                $auth = self::loginUser($app, 'admin@voak.test', 'VoakAdmin#2026!Secure');

                // POST without CSRF token
                $req = new Request(
                    'POST',
                    '/api/v1/admin/industries',
                    [],
                    [
                        'Content-Type' => 'application/json',
                        'Cookie'       => "voak_admin_session={$auth['session_cookie']}",
                    ],
                    json_encode(['title' => 'CSRF Attack Sector'])
                );
                $res = $app->handle($req);
                T::assertEquals(403, $res->getStatusCode());
                $body = json_decode($res->getBody(), true);
                $msg = $body['error']['message'] ?? $body['error']['code'] ?? '';
                T::assertTrue(str_contains($msg, 'CSRF') || str_contains($msg, 'token'));
            });

            T::test('Least-privilege RBAC: Content Editor can create/edit but cannot publish or delete', function () {
                $app = self::createAppWithMigratedDatabase();
                $editor = self::loginUser($app, 'editor@voak.test', 'VoakEditor#2026!Secure');

                // 1. Content Editor CAN list industries (GET)
                $listReq = new Request('GET', '/api/v1/admin/industries', [], [
                    'Cookie' => "voak_admin_session={$editor['session_cookie']}",
                ]);
                $listRes = $app->handle($listReq);
                T::assertEquals(200, $listRes->getStatusCode());

                // 2. Content Editor CAN create an industry (POST)
                $createReq = new Request(
                    'POST',
                    '/api/v1/admin/industries',
                    [],
                    [
                        'Content-Type' => 'application/json',
                        'X-CSRF-Token' => $editor['csrf_token'],
                        'Cookie'       => "voak_admin_session={$editor['session_cookie']}",
                    ],
                    json_encode([
                        'title'        => 'Community Health Centers',
                        'slug'         => 'community-health-centers',
                        'summary'      => 'Local non-profit primary healthcare services.',
                        'is_published' => 0, // Content Editor submits draft
                    ])
                );
                $createRes = $app->handle($createReq);
                T::assertEquals(201, $createRes->getStatusCode());
                $createBody = json_decode($createRes->getBody(), true);
                $newId = (int)$createBody['data']['industry']['id'];

                // 3. Content Editor CAN update draft (PUT)
                $updateReq = new Request(
                    'PUT',
                    "/api/v1/admin/industries/{$newId}",
                    [],
                    [
                        'Content-Type' => 'application/json',
                        'X-CSRF-Token' => $editor['csrf_token'],
                        'Cookie'       => "voak_admin_session={$editor['session_cookie']}",
                    ],
                    json_encode([
                        'title'   => 'Community Health Services Network',
                        'summary' => 'Updated community primary healthcare network.',
                    ])
                );
                $updateRes = $app->handle($updateReq);
                T::assertEquals(200, $updateRes->getStatusCode());

                // 4. Content Editor CANNOT publish (PATCH /status) -> 403 Forbidden
                $publishReq = new Request(
                    'PATCH',
                    "/api/v1/admin/industries/{$newId}/status",
                    [],
                    [
                        'Content-Type' => 'application/json',
                        'X-CSRF-Token' => $editor['csrf_token'],
                        'Cookie'       => "voak_admin_session={$editor['session_cookie']}",
                    ],
                    json_encode(['is_published' => 1])
                );
                $publishRes = $app->handle($publishReq);
                T::assertEquals(403, $publishRes->getStatusCode());
                $publishBody = json_decode($publishRes->getBody(), true);
                T::assertEquals('ERR_FORBIDDEN', $publishBody['error']['code']);

                // 5. Content Editor CANNOT delete (DELETE) -> 403 Forbidden
                $deleteReq = new Request(
                    'DELETE',
                    "/api/v1/admin/industries/{$newId}",
                    [],
                    [
                        'X-CSRF-Token' => $editor['csrf_token'],
                        'Cookie'       => "voak_admin_session={$editor['session_cookie']}",
                    ]
                );
                $deleteRes = $app->handle($deleteReq);
                T::assertEquals(403, $deleteRes->getStatusCode());
                $deleteBody = json_decode($deleteRes->getBody(), true);
                T::assertEquals('ERR_FORBIDDEN', $deleteBody['error']['code']);
            });

            T::test('Unprivileged user is completely forbidden from industries admin routes', function () {
                $app = self::createAppWithMigratedDatabase();
                $unpriv = self::loginUser($app, 'noperms@voak.test', 'VoakUnpriv#2026!Secure');

                $req = new Request('GET', '/api/v1/admin/industries', [], [
                    'Cookie' => "voak_admin_session={$unpriv['session_cookie']}",
                ]);
                $res = $app->handle($req);
                T::assertEquals(403, $res->getStatusCode());
                $body = json_decode($res->getBody(), true);
                T::assertEquals('ERR_FORBIDDEN', $body['error']['code']);
            });

            T::test('Full Admin CRUD lifecycle and cryptographic audit ledger verification', function () {
                $app = self::createAppWithMigratedDatabase();
                $admin = self::loginUser($app, 'admin@voak.test', 'VoakAdmin#2026!Secure');

                // 1. Create Industry (POST)
                $createReq = new Request(
                    'POST',
                    '/api/v1/admin/industries',
                    [],
                    [
                        'Content-Type' => 'application/json',
                        'X-CSRF-Token' => $admin['csrf_token'],
                        'Cookie'       => "voak_admin_session={$admin['session_cookie']}",
                    ],
                    json_encode([
                        'title'                   => 'Emergency & Retrieval Medicine',
                        'slug'                    => 'emergency-retrieval-medicine',
                        'summary'                 => 'Aeromedical and critical emergency transport services.',
                        'regulatory_burden_specs' => ['standards' => ['ACEM Standards']],
                        'pain_points'             => ['Critical flight nurse shortage'],
                        'is_published'            => 0,
                        'display_order'           => 10,
                        'service_ids'             => [1],
                    ])
                );
                $createRes = $app->handle($createReq);
                T::assertEquals(201, $createRes->getStatusCode());
                $createData = json_decode($createRes->getBody(), true);
                $id = (int)$createData['data']['industry']['id'];

                // 2. Fetch Single (GET /:id)
                $getReq = new Request('GET', "/api/v1/admin/industries/{$id}", [], [
                    'Cookie' => "voak_admin_session={$admin['session_cookie']}",
                ]);
                $getRes = $app->handle($getReq);
                T::assertEquals(200, $getRes->getStatusCode());
                $getData = json_decode($getRes->getBody(), true);
                T::assertEquals('Emergency & Retrieval Medicine', $getData['data']['industry']['title']);

                // 3. Update Industry and Change Slug (PUT /:id) -> generates 301 redirect
                $updateReq = new Request(
                    'PUT',
                    "/api/v1/admin/industries/{$id}",
                    [],
                    [
                        'Content-Type' => 'application/json',
                        'X-CSRF-Token' => $admin['csrf_token'],
                        'Cookie'       => "voak_admin_session={$admin['session_cookie']}",
                    ],
                    json_encode([
                        'title' => 'Critical Emergency & Retrieval Medicine',
                        'slug'  => 'critical-emergency-retrieval',
                    ])
                );
                $updateRes = $app->handle($updateReq);
                T::assertEquals(200, $updateRes->getStatusCode());

                // 4. Publish Industry (PATCH /:id/status)
                $statusReq = new Request(
                    'PATCH',
                    "/api/v1/admin/industries/{$id}/status",
                    [],
                    [
                        'Content-Type' => 'application/json',
                        'X-CSRF-Token' => $admin['csrf_token'],
                        'Cookie'       => "voak_admin_session={$admin['session_cookie']}",
                    ],
                    json_encode(['is_published' => 1])
                );
                $statusRes = $app->handle($statusReq);
                T::assertEquals(200, $statusRes->getStatusCode());
                $statusData = json_decode($statusRes->getBody(), true);
                T::assertEquals(1, $statusData['data']['industry']['is_published']);

                // 5. Test 301 URL redirect via Public API
                $oldPublicReq = new Request('GET', '/api/v1/public/industries/emergency-retrieval-medicine');
                $oldPublicRes = $app->handle($oldPublicReq);
                T::assertEquals(301, $oldPublicRes->getStatusCode());
                T::assertEquals('/industries/critical-emergency-retrieval', $oldPublicRes->getHeader('Location'));

                // New slug accessible via Public API
                $newPublicReq = new Request('GET', '/api/v1/public/industries/critical-emergency-retrieval');
                $newPublicRes = $app->handle($newPublicReq);
                T::assertEquals(200, $newPublicRes->getStatusCode());

                // 6. Reorder (PATCH /reorder)
                $reorderReq = new Request(
                    'PATCH',
                    '/api/v1/admin/industries/reorder',
                    [],
                    [
                        'Content-Type' => 'application/json',
                        'X-CSRF-Token' => $admin['csrf_token'],
                        'Cookie'       => "voak_admin_session={$admin['session_cookie']}",
                    ],
                    json_encode(['order' => [$id]])
                );
                $reorderRes = $app->handle($reorderReq);
                T::assertEquals(200, $reorderRes->getStatusCode());

                // 7. Delete (DELETE /:id)
                $delReq = new Request(
                    'DELETE',
                    "/api/v1/admin/industries/{$id}",
                    [],
                    [
                        'X-CSRF-Token' => $admin['csrf_token'],
                        'Cookie'       => "voak_admin_session={$admin['session_cookie']}",
                    ]
                );
                $delRes = $app->handle($delReq);
                T::assertEquals(200, $delRes->getStatusCode());

                // 8. Verify Cryptographic Audit Chain Integrity
                /** @var Connection $conn */
                $conn = $app->getContainer()->get(Connection::class);
                $pdo = $conn->getPdo();
                $auditRows = $pdo->query("SELECT * FROM audit_logs WHERE domain = 'industries' ORDER BY id ASC")->fetchAll(\PDO::FETCH_ASSOC);
                T::assertTrue(count($auditRows) > 0);

                $prevHash = null;
                foreach ($auditRows as $row) {
                    T::assertTrue(!empty($row['row_hash']));
                    if ($prevHash !== null) {
                        T::assertEquals($prevHash, $row['prev_row_hash']);
                    }
                    $prevHash = $row['row_hash'];
                }
            });

        });
    }
}
