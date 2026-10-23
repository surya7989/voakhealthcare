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

/**
 * Integration Test Suite for SEO Management API & Dynamic Sitemap (Phase 16).
 *
 * Tests HTTP end-to-end request lifecycle:
 * - Admin Auth & Fail-Closed gate (401)
 * - CSRF verification on mutating admin endpoints
 * - RBAC authorization checks (403 for Content Editor, 200 for Website Admin / Super Admin)
 * - Route SEO metadata management & character guidance (FR-ADM-018 / TEST-ADM-018)
 * - Dynamic XML sitemap rebuild & public /sitemap.xml delivery with ETag caching (FR-SEO-003 / TEST-SEO-003)
 * - Safe 301 URL redirect management and public redirect emission (FR-WF-006 / TEST-WF-006)
 */
class SeoManagementApiTest
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
        $webAdminHash = $hasher->hash('VoakWebAdmin#2026!Secure');
        $editorHash = $hasher->hash('VoakEditor#2026!Secure');

        // Super Admin (all permissions)
        $pdo->exec("INSERT INTO users (id, name, email, password_hash, is_active, created_at, updated_at) 
                    VALUES (1, 'Super Admin', 'admin@voak.test', '{$adminHash}', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");

        // Website Admin (SEO permissions)
        $pdo->exec("INSERT INTO users (id, name, email, password_hash, is_active, created_at, updated_at) 
                    VALUES (2, 'Website Admin', 'webadmin@voak.test', '{$webAdminHash}', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");

        // Content Editor (NO SEO permissions)
        $pdo->exec("INSERT INTO users (id, name, email, password_hash, is_active, created_at, updated_at) 
                    VALUES (3, 'Content Editor', 'editor@voak.test', '{$editorHash}', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");

        $rbac = new RbacManager();
        $rbac->seedRbac($pdo);
        $rbac->assignRole($pdo, 1, RbacManager::ROLE_SUPER_ADMIN);
        $rbac->assignRole($pdo, 2, RbacManager::ROLE_WEBSITE_ADMIN);
        $rbac->assignRole($pdo, 3, RbacManager::ROLE_CONTENT_EDITOR);

        // Seed initial CMS pages
        $pdo->exec("INSERT INTO pages (id, slug, title, meta_title, meta_description, content_json, is_published, published_at, updated_at)
                    VALUES (1, 'about', 'About VOAK', 'About VOAK Health Care - Healthcare Operational Excellence', 'Discover how VOAK Health Care delivers reliable clinical workforce solutions and hospital support across Australia.', '{}', 1, '2026-10-15 10:00:00', '2026-10-20 12:00:00')");

        $pdo->exec("INSERT INTO pages (id, slug, title, meta_title, meta_description, content_json, is_published, published_at, updated_at)
                    VALUES (2, 'contact', 'Contact Us', 'Contact VOAK Health Care - 24/7 Clinical Support', 'Get in touch with VOAK Health Care for emergency staffing and hospital partnerships.', '{}', 1, '2026-10-15 10:00:00', '2026-10-20 12:00:00')");

        $config = new Config([
            'APP_KEY'             => 'base64:' . base64_encode('test-encryption-key-32-chars-long!'),
            'AUTH_SIGNING_KEY'    => 'auth-signing-key-32-characters-min!!',
            'MFA_ENCRYPTION_KEY'  => 'mfa-encryption-key-32-characters-min!!',
            'CSRF_SIGNING_KEY'    => 'csrf-signing-key-32-characters-min!',
            'APP_ENV'             => 'testing',
            'APP_DEBUG'           => true,
            'DB_DRIVER'           => 'sqlite',
            'STORAGE_PATH'        => sys_get_temp_dir() . '/voak_test_seo_' . bin2hex(random_bytes(4)),
            'APP_URL'             => 'https://voakhealthcare.com',
            'CORS_ALLOWED_ORIGINS'=> 'https://voakhealthcare.com',
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
            'cookie_header'  => "voak_admin_session={$sessionToken}",
        ];
    }

    public static function run(): void
    {
        T::suite('Phase 16 — SEO Management & Dynamic Sitemap API Integration Tests', function () {

            T::test('Unauthenticated requests are strictly rejected with HTTP 401 fail-closed', function () {
                $app = self::createAppWithMigratedDatabase();

                $endpoints = [
                    ['GET', '/api/v1/admin/seo/routes'],
                    ['PUT', '/api/v1/admin/seo/routes/1'],
                    ['POST', '/api/v1/admin/seo/rebuild-sitemap'],
                    ['GET', '/api/v1/admin/seo/redirects'],
                    ['POST', '/api/v1/admin/seo/redirects'],
                    ['DELETE', '/api/v1/admin/seo/redirects/1'],
                ];

                foreach ($endpoints as [$method, $uri]) {
                    $req = new Request($method, $uri, [], ['Content-Type' => 'application/json'], '{}');
                    $res = $app->handle($req);
                    T::assertEquals(401, $res->getStatusCode(), "Endpoint {$method} {$uri} must require authentication (401)");
                    $data = json_decode($res->getBody(), true);
                    T::assertFalse($data['success']);
                    T::assertEquals('ERR_UNAUTHENTICATED', $data['error']['code']);
                }
            });

            T::test('Mutating endpoints reject requests with missing or invalid CSRF token (HTTP 403)', function () {
                $app = self::createAppWithMigratedDatabase();
                $auth = self::loginUser($app, 'admin@voak.test', 'VoakAdmin#2026!Secure');

                // 1. Missing CSRF on PUT routes
                $req1 = new Request(
                    'PUT',
                    '/api/v1/admin/seo/routes/1',
                    [],
                    [
                        'Content-Type' => 'application/json',
                        'Cookie'       => $auth['cookie_header'],
                    ],
                    json_encode(['meta_title' => 'Valid Title', 'meta_description' => 'Valid description here.'])
                );
                $res1 = $app->handle($req1);
                T::assertEquals(403, $res1->getStatusCode(), 'Expected 403 for missing CSRF on route update');

                // 2. Invalid CSRF on POST rebuild-sitemap
                $req2 = new Request(
                    'POST',
                    '/api/v1/admin/seo/rebuild-sitemap',
                    [],
                    [
                        'Content-Type' => 'application/json',
                        'Cookie'       => $auth['cookie_header'],
                        'X-CSRF-Token' => 'invalid-tampered-token',
                    ],
                    '{}'
                );
                $res2 = $app->handle($req2);
                T::assertEquals(403, $res2->getStatusCode(), 'Expected 403 for invalid CSRF token');

                // 3. Missing CSRF on POST redirects
                $req3 = new Request(
                    'POST',
                    '/api/v1/admin/seo/redirects',
                    [],
                    [
                        'Content-Type' => 'application/json',
                        'Cookie'       => $auth['cookie_header'],
                    ],
                    json_encode(['source_path' => '/old-path', 'target_path' => '/new-path'])
                );
                $res3 = $app->handle($req3);
                T::assertEquals(403, $res3->getStatusCode(), 'Expected 403 for missing CSRF on create redirect');
            });

            T::test('RBAC enforces least-privilege: Content Editor is denied (HTTP 403), Website Admin is allowed (HTTP 200)', function () {
                $app = self::createAppWithMigratedDatabase();

                $editorAuth = self::loginUser($app, 'editor@voak.test', 'VoakEditor#2026!Secure');
                $webAdminAuth = self::loginUser($app, 'webadmin@voak.test', 'VoakWebAdmin#2026!Secure');

                // Content Editor attempting to view SEO routes -> 403 Forbidden
                $editorReq = new Request(
                    'GET',
                    '/api/v1/admin/seo/routes',
                    [],
                    ['Cookie' => $editorAuth['cookie_header']]
                );
                $editorRes = $app->handle($editorReq);
                T::assertEquals(403, $editorRes->getStatusCode(), 'Content Editor must be denied on SEO routes');
                $editorData = json_decode($editorRes->getBody(), true);
                T::assertEquals('ERR_FORBIDDEN', $editorData['error']['code']);

                // Website Admin viewing SEO routes -> 200 OK
                $webAdminReq = new Request(
                    'GET',
                    '/api/v1/admin/seo/routes',
                    [],
                    ['Cookie' => $webAdminAuth['cookie_header']]
                );
                $webAdminRes = $app->handle($webAdminReq);
                T::assertEquals(200, $webAdminRes->getStatusCode(), 'Website Admin must be granted access to SEO routes');
                $webAdminData = json_decode($webAdminRes->getBody(), true);
                T::assertTrue($webAdminData['success']);
                T::assertTrue(count($webAdminData['data']['routes']) > 0);
            });

            T::test('Route SEO metadata update workflow (ADM-017 / FR-ADM-018 / TEST-ADM-018)', function () {
                $app = self::createAppWithMigratedDatabase();
                $auth = self::loginUser($app, 'webadmin@voak.test', 'VoakWebAdmin#2026!Secure');

                $updatePayload = [
                    'meta_title'       => 'About VOAK Health Care - Hospital Operational Excellence',
                    'meta_description' => 'Explore VOAK Health Care enterprise clinical solutions, hospital staffing, and health facility administration throughout Australia.',
                ];

                $updateReq = new Request(
                    'PUT',
                    '/api/v1/admin/seo/routes/1',
                    [],
                    [
                        'Content-Type' => 'application/json',
                        'Cookie'       => $auth['cookie_header'],
                        'X-CSRF-Token' => $auth['csrf_token'],
                    ],
                    json_encode($updatePayload)
                );

                $updateRes = $app->handle($updateReq);
                T::assertEquals(200, $updateRes->getStatusCode());
                $resData = json_decode($updateRes->getBody(), true);
                T::assertTrue($resData['success']);
                T::assertEquals('About VOAK Health Care - Hospital Operational Excellence', $resData['data']['route']['meta_title']);
                T::assertEquals('optimal', $resData['data']['route']['title_status']);
                T::assertEquals('optimal', $resData['data']['route']['desc_status']);
            });

            T::test('Rebuild sitemap workflow triggers cache invalidation (FR-ADM-018 / FR-SEO-003)', function () {
                $app = self::createAppWithMigratedDatabase();
                $auth = self::loginUser($app, 'webadmin@voak.test', 'VoakWebAdmin#2026!Secure');

                $rebuildReq = new Request(
                    'POST',
                    '/api/v1/admin/seo/rebuild-sitemap',
                    [],
                    [
                        'Content-Type' => 'application/json',
                        'Cookie'       => $auth['cookie_header'],
                        'X-CSRF-Token' => $auth['csrf_token'],
                    ],
                    '{}'
                );

                $rebuildRes = $app->handle($rebuildReq);
                T::assertEquals(200, $rebuildRes->getStatusCode());
                $resData = json_decode($rebuildRes->getBody(), true);
                T::assertTrue($resData['success']);
                T::assertEquals('/sitemap.xml', $resData['data']['sitemap_url']);
                T::assertTrue($resData['data']['xml_length'] > 0);
            });

            T::test('301 Redirect CRUD and loop prevention workflow (FR-WF-006 / TEST-WF-006)', function () {
                $app = self::createAppWithMigratedDatabase();
                $auth = self::loginUser($app, 'webadmin@voak.test', 'VoakWebAdmin#2026!Secure');

                // 1. Create redirect rule
                $createReq = new Request(
                    'POST',
                    '/api/v1/admin/seo/redirects',
                    [],
                    [
                        'Content-Type' => 'application/json',
                        'Cookie'       => $auth['cookie_header'],
                        'X-CSRF-Token' => $auth['csrf_token'],
                    ],
                    json_encode([
                        'source_path'      => '/services/clinical-care',
                        'target_path'      => '/services/clinical-staffing',
                        'http_status_code' => 301,
                    ])
                );
                $createRes = $app->handle($createReq);
                T::assertEquals(201, $createRes->getStatusCode());
                $createData = json_decode($createRes->getBody(), true);
                T::assertTrue($createData['success']);
                $redirectId = $createData['data']['redirect']['id'];
                T::assertTrue($redirectId > 0);

                // 2. List redirects
                $listReq = new Request(
                    'GET',
                    '/api/v1/admin/seo/redirects',
                    [],
                    ['Cookie' => $auth['cookie_header']]
                );
                $listRes = $app->handle($listReq);
                T::assertEquals(200, $listRes->getStatusCode());
                $listData = json_decode($listRes->getBody(), true);
                T::assertTrue($listData['success']);
                T::assertTrue(count($listData['data']['redirects']) >= 1);

                // 3. Test public redirect interception: GET /services/clinical-care -> HTTP 301 Location: /services/clinical-staffing
                $publicReq = new Request('GET', '/services/clinical-care');
                $publicRes = $app->handle($publicReq);
                T::assertEquals(301, $publicRes->getStatusCode(), 'Expected public request to match active redirect and return HTTP 301');
                T::assertEquals('/services/clinical-staffing', $publicRes->getHeader('Location'));
                T::assertEquals('Voak-SEO-Engine', $publicRes->getHeader('X-Redirect-By'));

                // 4. Delete redirect
                $deleteReq = new Request(
                    'DELETE',
                    "/api/v1/admin/seo/redirects/{$redirectId}",
                    [],
                    [
                        'Cookie'       => $auth['cookie_header'],
                        'X-CSRF-Token' => $auth['csrf_token'],
                    ]
                );
                $deleteRes = $app->handle($deleteReq);
                T::assertEquals(200, $deleteRes->getStatusCode());

                // 5. Verify public request no longer redirects
                $publicReqAfter = new Request('GET', '/services/clinical-care');
                $publicResAfter = $app->handle($publicReqAfter);
                T::assertFalse($publicResAfter->getStatusCode() === 301, 'Deleted redirect must no longer emit 301');
            });

            T::test('Public /sitemap.xml returns valid XML, caching headers and 304 on ETag match (FR-SEO-003 / TEST-SEO-003)', function () {
                $app = self::createAppWithMigratedDatabase();

                // Initial GET /sitemap.xml
                $req = new Request('GET', '/sitemap.xml');
                $res = $app->handle($req);

                T::assertEquals(200, $res->getStatusCode());
                T::assertEquals('application/xml; charset=utf-8', $res->getHeader('Content-Type'));
                T::assertTrue($res->hasHeader('ETag'));
                T::assertEquals('public, max-age=3600', $res->getHeader('Cache-Control'));

                $xml = $res->getBody();
                T::assertTrue(str_contains($xml, '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'));
                T::assertTrue(str_contains($xml, '<loc>https://voakhealthcare.com/</loc>'));
                T::assertTrue(str_contains($xml, '<loc>https://voakhealthcare.com/about</loc>'));

                $etag = $res->getHeader('ETag');

                // Conditional GET with matching If-None-Match -> 304 Not Modified
                $condReq = new Request('GET', '/sitemap.xml', [], ['If-None-Match' => $etag]);
                $condRes = $app->handle($condReq);

                T::assertEquals(304, $condRes->getStatusCode(), 'Expected HTTP 304 Not Modified when ETag matches');
                T::assertEquals('', $condRes->getBody(), '304 response body must be empty');
            });

            T::test('Public /robots.txt serves correct directives and sitemap reference', function () {
                $app = self::createAppWithMigratedDatabase();

                $req = new Request('GET', '/robots.txt');
                $res = $app->handle($req);

                T::assertEquals(200, $res->getStatusCode());
                T::assertEquals('text/plain; charset=utf-8', $res->getHeader('Content-Type'));

                $content = $res->getBody();
                T::assertTrue(str_contains($content, 'User-agent: *'));
                T::assertTrue(str_contains($content, 'Disallow: /admin/'));
                T::assertTrue(str_contains($content, 'Disallow: /api/'));
                T::assertTrue(str_contains($content, 'Sitemap: https://voakhealthcare.com/sitemap.xml'));
            });
        });
    }
}
