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

class ServicesManagementApiTest
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

    /**
     * Authenticates an administrative user and returns session cookie + session CSRF token.
     */
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
        T::suite('Phase 8 — Services Catalog Management API Integration Tests', function () {

            T::test('Public GET /api/v1/public/services returns 200 with 6 canonical pillars and ETag', function () {
                $app = self::createAppWithMigratedDatabase();

                $req = new Request('GET', '/api/v1/public/services');
                $res = $app->handle($req);

                T::assertEquals(200, $res->getStatusCode());
                $data = json_decode($res->getBody(), true);
                T::assertTrue($data['success']);
                T::assertEquals(6, count($data['data']['services']));
                T::assertEquals('clinical-workforce-solutions', $data['data']['services'][0]['slug']);

                // Verify ETag header
                $etag = $res->getHeader('ETag');
                T::assertTrue(!empty($etag));

                // Verify conditional GET returns 304 Not Modified
                $condReq = new Request('GET', '/api/v1/public/services', [], ['If-None-Match' => $etag]);
                $condRes = $app->handle($condReq);
                T::assertEquals(304, $condRes->getStatusCode());
                T::assertEquals('', $condRes->getBody());
            });

            T::test('Public route alias GET /api/services serves services catalog with parity', function () {
                $app = self::createAppWithMigratedDatabase();

                $aliasReq = new Request('GET', '/api/services');
                $aliasRes = $app->handle($aliasReq);

                T::assertEquals(200, $aliasRes->getStatusCode());
                $data = json_decode($aliasRes->getBody(), true);
                T::assertTrue($data['success']);
                T::assertEquals(6, count($data['data']['services']));
            });

            T::test('Public GET /api/v1/public/services/:slug returns service details and ETag', function () {
                $app = self::createAppWithMigratedDatabase();

                $req = new Request('GET', '/api/v1/public/services/clinical-workforce-solutions');
                $res = $app->handle($req);

                T::assertEquals(200, $res->getStatusCode());
                $data = json_decode($res->getBody(), true);
                T::assertTrue($data['success']);
                T::assertEquals('Clinical Workforce Solutions', $data['data']['service']['title']);

                $etag = $res->getHeader('ETag');
                T::assertTrue(!empty($etag));

                // 304 conditional get
                $condReq = new Request('GET', '/api/v1/public/services/clinical-workforce-solutions', [], ['If-None-Match' => $etag]);
                $condRes = $app->handle($condReq);
                T::assertEquals(304, $condRes->getStatusCode());
            });

            T::test('Public GET /api/v1/public/services/:slug returns 301 redirect after service is renamed', function () {
                $app = self::createAppWithMigratedDatabase();
                $auth = self::loginUser($app, 'admin@voak.test', 'VoakAdmin#2026!Secure');

                // Super Admin renames a service slug
                // First find the service ID
                $listReq = new Request(
                    'GET',
                    '/api/v1/admin/services',
                    [],
                    [
                        'Cookie' => "voak_admin_session={$auth['session_cookie']}",
                    ]
                );
                $listRes = $app->handle($listReq);
                $listData = json_decode($listRes->getBody(), true);
                $serviceId = (int)$listData['data']['services'][0]['id'];
                $oldSlug = (string)$listData['data']['services'][0]['slug'];
                $newSlug = 'clinical-workforce-2026-solutions';

                // Rename via admin PUT
                $updateReq = new Request(
                    'PUT',
                    "/api/v1/admin/services/{$serviceId}",
                    [],
                    [
                        'Content-Type' => 'application/json',
                        'X-CSRF-Token' => $auth['csrf_token'],
                        'Cookie'       => "voak_admin_session={$auth['session_cookie']}",
                    ],
                    json_encode([
                        'slug'  => $newSlug,
                        'title' => 'Updated Clinical Workforce 2026',
                    ])
                );
                $updateRes = $app->handle($updateReq);
                T::assertEquals(200, $updateRes->getStatusCode());

                // Public request to old slug must receive HTTP 301 Redirect to /services/{newSlug}
                $oldReq = new Request('GET', "/api/v1/public/services/{$oldSlug}");
                $oldRes = $app->handle($oldReq);

                T::assertEquals(301, $oldRes->getStatusCode());
                T::assertEquals("/services/{$newSlug}", $oldRes->getHeader('Location'));

                // Public request to new slug must return 200
                $newReq = new Request('GET', "/api/v1/public/services/{$newSlug}");
                $newRes = $app->handle($newReq);
                T::assertEquals(200, $newRes->getStatusCode());
                $newData = json_decode($newRes->getBody(), true);
                T::assertEquals('Updated Clinical Workforce 2026', $newData['data']['service']['title']);
            });

            T::test('Public GET /api/v1/public/services/:slug returns 404 for non-existent or draft service', function () {
                $app = self::createAppWithMigratedDatabase();
                $auth = self::loginUser($app, 'admin@voak.test', 'VoakAdmin#2026!Secure');

                // 1. Non-existent slug
                $missingReq = new Request('GET', '/api/v1/public/services/non-existent-healthcare-service');
                $missingRes = $app->handle($missingReq);
                T::assertEquals(404, $missingRes->getStatusCode());

                // 2. Create draft service
                $createReq = new Request(
                    'POST',
                    '/api/v1/admin/services',
                    [],
                    [
                        'Content-Type' => 'application/json',
                        'X-CSRF-Token' => $auth['csrf_token'],
                        'Cookie'       => "voak_admin_session={$auth['session_cookie']}",
                    ],
                    json_encode([
                        'title'        => 'Internal Draft Research',
                        'slug'         => 'internal-draft-research',
                        'tagline'      => 'Internal research only',
                        'description'  => 'Confidential workforce research',
                        'is_published' => 0,
                    ])
                );
                $createRes = $app->handle($createReq);
                T::assertEquals(201, $createRes->getStatusCode());

                // Public endpoint must return 404 for unpublished draft
                $draftReq = new Request('GET', '/api/v1/public/services/internal-draft-research');
                $draftRes = $app->handle($draftReq);
                T::assertEquals(404, $draftRes->getStatusCode());
            });

            T::test('Admin endpoints fail closed with 401 when session is missing', function () {
                $app = self::createAppWithMigratedDatabase();

                $endpoints = [
                    ['GET', '/api/v1/admin/services'],
                    ['POST', '/api/v1/admin/services'],
                    ['GET', '/api/v1/admin/services/1'],
                    ['PUT', '/api/v1/admin/services/1'],
                    ['PATCH', '/api/v1/admin/services/1/status'],
                    ['PATCH', '/api/v1/admin/services/reorder'],
                    ['DELETE', '/api/v1/admin/services/1'],
                ];

                foreach ($endpoints as [$method, $uri]) {
                    $req = new Request($method, $uri, [], ['Content-Type' => 'application/json'], '{}');
                    $res = $app->handle($req);
                    T::assertEquals(401, $res->getStatusCode(), "Endpoint {$method} {$uri} must reject unauthenticated requests with 401.");
                }
            });

            T::test('Admin mutation endpoints reject missing CSRF token with 403 Forbidden', function () {
                $app = self::createAppWithMigratedDatabase();
                $auth = self::loginUser($app, 'admin@voak.test', 'VoakAdmin#2026!Secure');

                $mutations = [
                    ['POST', '/api/v1/admin/services', json_encode(['title' => 'No CSRF', 'slug' => 'no-csrf'])],
                    ['PUT', '/api/v1/admin/services/1', json_encode(['title' => 'No CSRF Update'])],
                    ['PATCH', '/api/v1/admin/services/1/status', json_encode(['is_published' => 0])],
                    ['PATCH', '/api/v1/admin/services/reorder', json_encode(['order' => [1, 2]])],
                    ['DELETE', '/api/v1/admin/services/1', ''],
                ];

                foreach ($mutations as [$method, $uri, $body]) {
                    $req = new Request(
                        $method,
                        $uri,
                        [],
                        [
                            'Content-Type' => 'application/json',
                            'Cookie'       => "voak_admin_session={$auth['session_cookie']}",
                            // X-CSRF-Token intentionally omitted
                        ],
                        $body
                    );
                    $res = $app->handle($req);
                    T::assertEquals(403, $res->getStatusCode(), "Mutation {$method} {$uri} must reject missing CSRF token with 403.");
                }
            });

            T::test('Least-privilege RBAC: Content Editor can draft & edit but CANNOT publish or delete services', function () {
                $app = self::createAppWithMigratedDatabase();
                $editorAuth = self::loginUser($app, 'editor@voak.test', 'VoakEditor#2026!Secure');

                // 1. Content Editor CAN list services
                $listReq = new Request(
                    'GET',
                    '/api/v1/admin/services',
                    [],
                    ['Cookie' => "voak_admin_session={$editorAuth['session_cookie']}"]
                );
                $listRes = $app->handle($listReq);
                T::assertEquals(200, $listRes->getStatusCode(), 'Content Editor must have read/list access.');

                // 2. Content Editor CAN create a new service
                $createReq = new Request(
                    'POST',
                    '/api/v1/admin/services',
                    [],
                    [
                        'Content-Type' => 'application/json',
                        'X-CSRF-Token' => $editorAuth['csrf_token'],
                        'Cookie'       => "voak_admin_session={$editorAuth['session_cookie']}",
                    ],
                    json_encode([
                        'title'        => 'Editor Authored Service',
                        'slug'         => 'editor-authored-service',
                        'tagline'      => 'Authored by Content Editor',
                        'description'  => 'Clinical service overview drafted by editor.',
                        'is_published' => 0,
                    ])
                );
                $createRes = $app->handle($createReq);
                T::assertEquals(201, $createRes->getStatusCode(), 'Content Editor must be able to create service drafts.');
                $createData = json_decode($createRes->getBody(), true);
                $createdId = (int)$createData['data']['service']['id'];

                // 3. Content Editor CAN update service content
                $updateReq = new Request(
                    'PUT',
                    "/api/v1/admin/services/{$createdId}",
                    [],
                    [
                        'Content-Type' => 'application/json',
                        'X-CSRF-Token' => $editorAuth['csrf_token'],
                        'Cookie'       => "voak_admin_session={$editorAuth['session_cookie']}",
                    ],
                    json_encode([
                        'title' => 'Editor Authored Service (Revised)',
                    ])
                );
                $updateRes = $app->handle($updateReq);
                T::assertEquals(200, $updateRes->getStatusCode(), 'Content Editor must be able to edit service content.');

                // 4. Content Editor CANNOT publish service (requires service:publish -> 403 Forbidden)
                $publishReq = new Request(
                    'PATCH',
                    "/api/v1/admin/services/{$createdId}/status",
                    [],
                    [
                        'Content-Type' => 'application/json',
                        'X-CSRF-Token' => $editorAuth['csrf_token'],
                        'Cookie'       => "voak_admin_session={$editorAuth['session_cookie']}",
                    ],
                    json_encode(['is_published' => 1])
                );
                $publishRes = $app->handle($publishReq);
                T::assertEquals(403, $publishRes->getStatusCode(), 'Content Editor must be denied publishing permission (HTTP 403).');

                // 5. Content Editor CANNOT delete service (requires service:delete -> 403 Forbidden)
                $deleteReq = new Request(
                    'DELETE',
                    "/api/v1/admin/services/{$createdId}",
                    [],
                    [
                        'X-CSRF-Token' => $editorAuth['csrf_token'],
                        'Cookie'       => "voak_admin_session={$editorAuth['session_cookie']}",
                    ]
                );
                $deleteRes = $app->handle($deleteReq);
                T::assertEquals(403, $deleteRes->getStatusCode(), 'Content Editor must be denied service deletion (HTTP 403).');
            });

            T::test('Least-privilege RBAC: Unprivileged user has zero access to services administration', function () {
                $app = self::createAppWithMigratedDatabase();
                $unprivAuth = self::loginUser($app, 'noperms@voak.test', 'VoakUnpriv#2026!Secure');

                $req = new Request(
                    'GET',
                    '/api/v1/admin/services',
                    [],
                    ['Cookie' => "voak_admin_session={$unprivAuth['session_cookie']}"]
                );
                $res = $app->handle($req);
                T::assertEquals(403, $res->getStatusCode(), 'User with no roles must receive 403 Forbidden on admin services.');
            });

            T::test('Full Admin CRUD lifecycle with cryptographic audit ledger verification', function () {
                $app = self::createAppWithMigratedDatabase();
                $auth = self::loginUser($app, 'admin@voak.test', 'VoakAdmin#2026!Secure');

                // 1. Create Service
                $createReq = new Request(
                    'POST',
                    '/api/v1/admin/services',
                    [],
                    [
                        'Content-Type' => 'application/json',
                        'X-CSRF-Token' => $auth['csrf_token'],
                        'Cookie'       => "voak_admin_session={$auth['session_cookie']}",
                    ],
                    json_encode([
                        'title'         => 'Specialist Aged Care Roster Advisory',
                        'slug'          => 'specialist-aged-care-roster-advisory',
                        'tagline'       => 'Optimized clinical rosters compliant with 2026 Australian Aged Care Mandates.',
                        'description'   => '<p>Comprehensive clinical rostering for residential aged care facilities.</p>',
                        'deliverables'  => [
                            'Direct-care minute compliance audit',
                            '24/7 Registered Nurse coverage verification',
                            'Automated agency shift filling',
                        ],
                        'sla_metrics'   => [
                            'mandate_compliance' => '100% Guaranteed',
                            'fill_turnaround'    => '< 30 Minutes',
                        ],
                        'is_published'  => 0,
                        'display_order' => 7,
                    ])
                );
                $createRes = $app->handle($createReq);
                T::assertEquals(201, $createRes->getStatusCode());
                $created = json_decode($createRes->getBody(), true)['data']['service'];
                $svcId = (int)$created['id'];

                // 2. Inspect Service
                $getReq = new Request(
                    'GET',
                    "/api/v1/admin/services/{$svcId}",
                    [],
                    ['Cookie' => "voak_admin_session={$auth['session_cookie']}"]
                );
                $getRes = $app->handle($getReq);
                T::assertEquals(200, $getRes->getStatusCode());
                $fetched = json_decode($getRes->getBody(), true)['data']['service'];
                T::assertEquals('Specialist Aged Care Roster Advisory', $fetched['title']);
                T::assertEquals(0, (int)$fetched['is_published']);

                // 3. Update Service Content
                $updateReq = new Request(
                    'PUT',
                    "/api/v1/admin/services/{$svcId}",
                    [],
                    [
                        'Content-Type' => 'application/json',
                        'X-CSRF-Token' => $auth['csrf_token'],
                        'Cookie'       => "voak_admin_session={$auth['session_cookie']}",
                    ],
                    json_encode([
                        'title'   => 'Specialist Aged Care Roster Advisory (Updated)',
                        'tagline' => 'Updated tagline with enhanced SLA metrics.',
                    ])
                );
                $updateRes = $app->handle($updateReq);
                T::assertEquals(200, $updateRes->getStatusCode());
                $updated = json_decode($updateRes->getBody(), true)['data']['service'];
                T::assertEquals('Specialist Aged Care Roster Advisory (Updated)', $updated['title']);

                // 4. Publish Service
                $pubReq = new Request(
                    'PATCH',
                    "/api/v1/admin/services/{$svcId}/status",
                    [],
                    [
                        'Content-Type' => 'application/json',
                        'X-CSRF-Token' => $auth['csrf_token'],
                        'Cookie'       => "voak_admin_session={$auth['session_cookie']}",
                    ],
                    json_encode(['is_published' => 1])
                );
                $pubRes = $app->handle($pubReq);
                T::assertEquals(200, $pubRes->getStatusCode());
                $published = json_decode($pubRes->getBody(), true)['data']['service'];
                T::assertEquals(1, (int)$published['is_published']);

                // 5. Reorder Services
                $reorderReq = new Request(
                    'PATCH',
                    '/api/v1/admin/services/reorder',
                    [],
                    [
                        'Content-Type' => 'application/json',
                        'X-CSRF-Token' => $auth['csrf_token'],
                        'Cookie'       => "voak_admin_session={$auth['session_cookie']}",
                    ],
                    json_encode([
                        'order' => [
                            ['id' => $svcId, 'display_order' => 1],
                        ],
                    ])
                );
                $reorderRes = $app->handle($reorderReq);
                T::assertEquals(200, $reorderRes->getStatusCode());

                // 6. Delete Service
                $delReq = new Request(
                    'DELETE',
                    "/api/v1/admin/services/{$svcId}",
                    [],
                    [
                        'X-CSRF-Token' => $auth['csrf_token'],
                        'Cookie'       => "voak_admin_session={$auth['session_cookie']}",
                    ]
                );
                $delRes = $app->handle($delReq);
                T::assertEquals(200, $delRes->getStatusCode());

                // Verify service is gone
                $verifyReq = new Request(
                    'GET',
                    "/api/v1/admin/services/{$svcId}",
                    [],
                    ['Cookie' => "voak_admin_session={$auth['session_cookie']}"]
                );
                $verifyRes = $app->handle($verifyReq);
                T::assertEquals(404, $verifyRes->getStatusCode());

                // 7. Verify cryptographic audit chain integrity
                /** @var Connection $conn */
                $conn = $app->getContainer()->get(Connection::class);
                $pdo = $conn->getPdo();
                $auditRows = $pdo->query("SELECT action, prev_row_hash, row_hash FROM audit_logs WHERE domain = 'services' ORDER BY id ASC")->fetchAll();
                T::assertTrue(count($auditRows) >= 4, 'Audit logs must capture service lifecycle actions.');

                foreach ($auditRows as $auditRow) {
                    T::assertTrue(!empty($auditRow['row_hash']), 'Each audit log row must have SHA-256 row_hash.');
                    T::assertTrue(!empty($auditRow['prev_row_hash']), 'Each audit log row must maintain prev_row_hash chain.');
                }
            });
        });
    }
}
