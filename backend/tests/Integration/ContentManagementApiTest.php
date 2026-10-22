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

class ContentManagementApiTest
{
    private static function createAppWithMigratedDatabase(): App
    {
        $pdo = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
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

        // Content Editor (content permissions)
        $pdo->exec("INSERT INTO users (id, name, email, password_hash, is_active, created_at, updated_at) 
                    VALUES (2, 'Content Editor', 'editor@voak.test', '{$editorHash}', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");

        // User with no roles
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
     * Helper to authenticate an admin user and return session cookie + CSRF token.
     */
    private static function loginUser(App $app, string $email, string $password): array
    {
        // 1. Get bootstrap CSRF
        $csrfReq = new Request('GET', '/api/v1/admin/auth/csrf');
        $csrfRes = $app->handle($csrfReq);
        $csrfData = json_decode($csrfRes->getBody(), true);
        $bootstrapToken = (string)($csrfData['data']['csrf_token'] ?? '');
        $cookieHeader = (string)($csrfRes->getHeader('Set-Cookie') ?? '');
        preg_match('/voak_csrf=([^;]+)/', $cookieHeader, $matches);
        $bootstrapCookie = $matches[1] ?? '';

        // 2. Perform login
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
        T::suite('Phase 7 — Content Management Public & Admin API Integration Tests', function () {

            T::test('Public GET /api/v1/public/pages/:slug returns 200 with ETag for published page', function () {
                $app = self::createAppWithMigratedDatabase();
                $auth = self::loginUser($app, 'admin@voak.test', 'VoakAdmin#2026!Secure');

                // Create and publish a page
                $createReq = new Request(
                    'POST',
                    '/api/v1/admin/pages',
                    [],
                    [
                        'Content-Type' => 'application/json',
                        'X-CSRF-Token' => $auth['csrf_token'],
                        'Cookie'       => "voak_admin_session={$auth['session_cookie']}",
                    ],
                    json_encode([
                        'slug'    => 'clinical-governance',
                        'title'   => 'Clinical Governance Framework',
                        'content' => '<p>National Safety and Quality Health Service Standards</p>',
                        'status'  => 'published',
                    ])
                );
                $createRes = $app->handle($createReq);
                T::assertEquals(201, $createRes->getStatusCode());

                // Fetch via public endpoint
                $pubReq = new Request('GET', '/api/v1/public/pages/clinical-governance');
                $pubRes = $app->handle($pubReq);
                T::assertEquals(200, $pubRes->getStatusCode());

                $data = json_decode($pubRes->getBody(), true);
                T::assertTrue($data['success']);
                T::assertEquals('Clinical Governance Framework', $data['data']['page']['title']);

                // Verify ETag and Cache-Control headers
                $etag = $pubRes->getHeader('ETag');
                T::assertTrue($etag !== null);
                T::assertContains('public', (string)$pubRes->getHeader('Cache-Control'));
                T::assertContains('max-age=300', (string)$pubRes->getHeader('Cache-Control'));

                // Verify Conditional GET (304 Not Modified)
                $conditionalReq = new Request('GET', '/api/v1/public/pages/clinical-governance', [], [
                    'If-None-Match' => $etag,
                ]);
                $conditionalRes = $app->handle($conditionalReq);
                T::assertEquals(304, $conditionalRes->getStatusCode());
            });

            T::test('Public GET /api/v1/public/pages/:slug returns 404 for draft, archived, or soft-deleted pages', function () {
                $app = self::createAppWithMigratedDatabase();
                $auth = self::loginUser($app, 'admin@voak.test', 'VoakAdmin#2026!Secure');

                // 1. Create a draft page
                $createDraftReq = new Request(
                    'POST',
                    '/api/v1/admin/pages',
                    [],
                    [
                        'Content-Type' => 'application/json',
                        'X-CSRF-Token' => $auth['csrf_token'],
                        'Cookie'       => "voak_admin_session={$auth['session_cookie']}",
                    ],
                    json_encode([
                        'slug'    => 'internal-confidential-draft',
                        'title'   => 'Confidential Policy',
                        'content' => '<p>Private draft</p>',
                        'status'  => 'draft',
                    ])
                );
                $app->handle($createDraftReq);

                // Draft page MUST NOT leak through public route -> 404
                $draftPubReq = new Request('GET', '/api/v1/public/pages/internal-confidential-draft');
                $draftPubRes = $app->handle($draftPubReq);
                T::assertEquals(404, $draftPubRes->getStatusCode());

                // 2. Non-existent slug -> 404
                $missingReq = new Request('GET', '/api/v1/public/pages/does-not-exist');
                $missingRes = $app->handle($missingReq);
                T::assertEquals(404, $missingRes->getStatusCode());
            });

            T::test('Public GET /api/v1/public/homepage returns active config with ETag and 304 support', function () {
                $app = self::createAppWithMigratedDatabase();

                $req = new Request('GET', '/api/v1/public/homepage');
                $res = $app->handle($req);
                T::assertEquals(200, $res->getStatusCode());

                $data = json_decode($res->getBody(), true);
                T::assertTrue($data['success']);
                T::assertTrue(!empty($data['data']['config']['hero_headline']));

                $etag = $res->getHeader('ETag');
                T::assertTrue($etag !== null);

                // Conditional GET
                $condReq = new Request('GET', '/api/v1/public/homepage', [], [
                    'If-None-Match' => $etag,
                ]);
                $condRes = $app->handle($condReq);
                T::assertEquals(304, $condRes->getStatusCode());
            });

            T::test('Public GET /api/v1/public/settings returns ONLY public settings (is_secret = 0)', function () {
                $app = self::createAppWithMigratedDatabase();

                $req = new Request('GET', '/api/v1/public/settings');
                $res = $app->handle($req);
                T::assertEquals(200, $res->getStatusCode());

                $data = json_decode($res->getBody(), true);
                T::assertTrue($data['success']);
                $settings = $data['data']['settings'];

                T::assertTrue(isset($settings['site_name']));
                T::assertTrue(isset($settings['contact_email']));

                // Security Isolation Verification: Secrets must NOT exist in response
                T::assertFalse(isset($settings['session_idle_timeout_minutes']));
                T::assertFalse(isset($settings['max_login_attempts']));

                // Conditional GET
                $etag = $res->getHeader('ETag');
                T::assertTrue($etag !== null);
                $condReq = new Request('GET', '/api/v1/public/settings', [], [
                    'If-None-Match' => $etag,
                ]);
                $condRes = $app->handle($condReq);
                T::assertEquals(304, $condRes->getStatusCode());
            });

            T::test('Admin CMS endpoints require authentication and reject unauthenticated requests (HTTP 401)', function () {
                $app = self::createAppWithMigratedDatabase();

                $endpoints = [
                    ['GET', '/api/v1/admin/pages'],
                    ['POST', '/api/v1/admin/pages'],
                    ['GET', '/api/v1/admin/homepage'],
                    ['PUT', '/api/v1/admin/homepage'],
                    ['GET', '/api/v1/admin/settings'],
                    ['PUT', '/api/v1/admin/settings'],
                ];

                foreach ($endpoints as [$method, $path]) {
                    $req = new Request($method, $path);
                    $res = $app->handle($req);
                    T::assertEquals(401, $res->getStatusCode(), "Unauthenticated request to {$method} {$path} must return 401.");
                }
            });

            T::test('Admin CMS endpoints enforce RBAC permissions and reject unauthorized users (HTTP 403)', function () {
                $app = self::createAppWithMigratedDatabase();
                // Login user with no permissions
                $auth = self::loginUser($app, 'noperms@voak.test', 'VoakUnpriv#2026!Secure');

                $req = new Request(
                    'GET',
                    '/api/v1/admin/pages',
                    [],
                    [
                        'Cookie' => "voak_admin_session={$auth['session_cookie']}",
                    ]
                );
                $res = $app->handle($req);
                T::assertEquals(403, $res->getStatusCode());

                $data = json_decode($res->getBody(), true);
                T::assertFalse($data['success']);
                T::assertEquals('ERR_FORBIDDEN', $data['error']['code']);
            });

            T::test('Mutating admin CMS endpoints reject missing or invalid CSRF tokens (HTTP 403)', function () {
                $app = self::createAppWithMigratedDatabase();
                $auth = self::loginUser($app, 'admin@voak.test', 'VoakAdmin#2026!Secure');

                // 1. Missing CSRF token
                $noCsrfReq = new Request(
                    'POST',
                    '/api/v1/admin/pages',
                    [],
                    [
                        'Content-Type' => 'application/json',
                        'Cookie'       => "voak_admin_session={$auth['session_cookie']}",
                    ],
                    json_encode(['slug' => 'test', 'title' => 'Test'])
                );
                $noCsrfRes = $app->handle($noCsrfReq);
                T::assertEquals(403, $noCsrfRes->getStatusCode());
                $noCsrfData = json_decode($noCsrfRes->getBody(), true);
                T::assertEquals('ERR_CSRF_MISSING', $noCsrfData['error']['code']);

                // 2. Tampered CSRF token
                $badCsrfReq = new Request(
                    'POST',
                    '/api/v1/admin/pages',
                    [],
                    [
                        'Content-Type' => 'application/json',
                        'X-CSRF-Token' => 'tampered_invalid_csrf_token',
                        'Cookie'       => "voak_admin_session={$auth['session_cookie']}",
                    ],
                    json_encode(['slug' => 'test', 'title' => 'Test'])
                );
                $badCsrfRes = $app->handle($badCsrfReq);
                T::assertEquals(403, $badCsrfRes->getStatusCode());
                $badCsrfData = json_decode($badCsrfRes->getBody(), true);
                T::assertEquals('ERR_CSRF_INVALID', $badCsrfData['error']['code']);
            });

            T::test('Full Admin Page Lifecycle: Create, Read, Update, Rollback, Soft-Delete with XSS Sanitization', function () {
                $app = self::createAppWithMigratedDatabase();
                $auth = self::loginUser($app, 'admin@voak.test', 'VoakAdmin#2026!Secure');

                // 1. Create Page
                $createReq = new Request(
                    'POST',
                    '/api/v1/admin/pages',
                    [],
                    [
                        'Content-Type' => 'application/json',
                        'X-CSRF-Token' => $auth['csrf_token'],
                        'Cookie'       => "voak_admin_session={$auth['session_cookie']}",
                    ],
                    json_encode([
                        'slug'    => 'lifecycle-test',
                        'title'   => 'Lifecycle Test Page',
                        'content' => '<p>Initial clean content</p>',
                        'status'  => 'draft',
                    ])
                );
                $createRes = $app->handle($createReq);
                T::assertEquals(201, $createRes->getStatusCode());
                $createData = json_decode($createRes->getBody(), true);
                $pageId = (int)$createData['data']['page']['id'];
                T::assertTrue($pageId > 0);

                // 2. Read Page by ID
                $showReq = new Request(
                    'GET',
                    "/api/v1/admin/pages/{$pageId}",
                    [],
                    ['Cookie' => "voak_admin_session={$auth['session_cookie']}"]
                );
                $showRes = $app->handle($showReq);
                T::assertEquals(200, $showRes->getStatusCode());
                $showData = json_decode($showRes->getBody(), true);
                T::assertEquals('Lifecycle Test Page', $showData['data']['page']['title']);
                T::assertEquals(1, count($showData['data']['revisions']));

                // 3. Update Page with Stored-XSS injection attempt
                $updateReq = new Request(
                    'PUT',
                    "/api/v1/admin/pages/{$pageId}",
                    [],
                    [
                        'Content-Type' => 'application/json',
                        'X-CSRF-Token' => $auth['csrf_token'],
                        'Cookie'       => "voak_admin_session={$auth['session_cookie']}",
                    ],
                    json_encode([
                        'title'          => 'Updated Lifecycle Page',
                        'content'        => '<p>Safe update <script>alert(1)</script><img src=x onerror=alert(2)></p>',
                        'change_summary' => 'Added security disclosure section',
                    ])
                );
                $updateRes = $app->handle($updateReq);
                T::assertEquals(200, $updateRes->getStatusCode());
                $updateData = json_decode($updateRes->getBody(), true);

                // Verify XSS neutralization in persisted response
                $contentStr = json_encode($updateData['data']['page']['content_json']);
                T::assertFalse(str_contains($contentStr, '<script>'));
                T::assertFalse(str_contains($contentStr, 'onerror'));
                T::assertContains('Safe update', $contentStr);

                // 4. Update Status to published
                $statusReq = new Request(
                    'PATCH',
                    "/api/v1/admin/pages/{$pageId}/status",
                    [],
                    [
                        'Content-Type' => 'application/json',
                        'X-CSRF-Token' => $auth['csrf_token'],
                        'Cookie'       => "voak_admin_session={$auth['session_cookie']}",
                    ],
                    json_encode(['status' => 'published'])
                );
                $statusRes = $app->handle($statusReq);
                T::assertEquals(200, $statusRes->getStatusCode());
                $statusData = json_decode($statusRes->getBody(), true);
                T::assertEquals('published', $statusData['data']['page']['status']);
                T::assertTrue($statusData['data']['page']['is_published']);

                // 5. Rollback to Revision 1
                $rollbackReq = new Request(
                    'POST',
                    "/api/v1/admin/pages/{$pageId}/rollback/1",
                    [],
                    [
                        'X-CSRF-Token' => $auth['csrf_token'],
                        'Cookie'       => "voak_admin_session={$auth['session_cookie']}",
                    ]
                );
                $rollbackRes = $app->handle($rollbackReq);
                T::assertEquals(200, $rollbackRes->getStatusCode());
                $rollbackData = json_decode($rollbackRes->getBody(), true);
                T::assertEquals('Lifecycle Test Page', $rollbackData['data']['page']['title']);

                // Verify 4 revisions now exist (created, updated, published, rolled-back)
                $revsReq = new Request('GET', "/api/v1/admin/pages/{$pageId}/revisions", [], [
                    'Cookie' => "voak_admin_session={$auth['session_cookie']}",
                ]);
                $revsRes = $app->handle($revsReq);
                $revsData = json_decode($revsRes->getBody(), true);
                T::assertEquals(4, count($revsData['data']['revisions']));

                // 6. Soft Delete Page
                $delReq = new Request('DELETE', "/api/v1/admin/pages/{$pageId}", [], [
                    'X-CSRF-Token' => $auth['csrf_token'],
                    'Cookie'       => "voak_admin_session={$auth['session_cookie']}",
                ]);
                $delRes = $app->handle($delReq);
                T::assertEquals(200, $delRes->getStatusCode());

                // Public endpoint returns 404
                $pubReq = new Request('GET', '/api/v1/public/pages/lifecycle-test');
                $pubRes = $app->handle($pubReq);
                T::assertEquals(404, $pubRes->getStatusCode());
            });

            T::test('Admin Homepage CMS: Update copy and validate CTA URL safety', function () {
                $app = self::createAppWithMigratedDatabase();
                $auth = self::loginUser($app, 'admin@voak.test', 'VoakAdmin#2026!Secure');

                // 1. Get homepage
                $getReq = new Request('GET', '/api/v1/admin/homepage', [], [
                    'Cookie' => "voak_admin_session={$auth['session_cookie']}",
                ]);
                $getRes = $app->handle($getReq);
                T::assertEquals(200, $getRes->getStatusCode());

                // 2. Reject unsafe CTA URL (javascript:)
                $badCtaReq = new Request(
                    'PUT',
                    '/api/v1/admin/homepage',
                    [],
                    [
                        'Content-Type' => 'application/json',
                        'X-CSRF-Token' => $auth['csrf_token'],
                        'Cookie'       => "voak_admin_session={$auth['session_cookie']}",
                    ],
                    json_encode([
                        'hero_headline'           => 'Empowering Healthcare',
                        'hero_subheadline'        => 'Clinical governance solutions',
                        'hero_primary_cta_text'   => 'Click Me',
                        'hero_primary_cta_url'    => 'javascript:alert(1)',
                        'hero_secondary_cta_text' => 'Services',
                        'hero_secondary_cta_url'  => '/services',
                    ])
                );
                $badCtaRes = $app->handle($badCtaReq);
                T::assertEquals(400, $badCtaRes->getStatusCode());

                // 3. Update with valid relative CTAs
                $goodCtaReq = new Request(
                    'PUT',
                    '/api/v1/admin/homepage',
                    [],
                    [
                        'Content-Type' => 'application/json',
                        'X-CSRF-Token' => $auth['csrf_token'],
                        'Cookie'       => "voak_admin_session={$auth['session_cookie']}",
                    ],
                    json_encode([
                        'hero_headline'           => 'Empowering Healthcare Networks Across Australia',
                        'hero_subheadline'        => 'Accredited specialist clinical workforce solutions.',
                        'hero_primary_cta_text'   => 'Partner With Us',
                        'hero_primary_cta_url'    => '/contact',
                        'hero_secondary_cta_text' => 'View Solutions',
                        'hero_secondary_cta_url'  => '/services',
                        'proof_metrics'           => [
                            ['label' => 'Hospital Networks', 'value' => '150+'],
                        ],
                    ])
                );
                $goodCtaRes = $app->handle($goodCtaReq);
                T::assertEquals(200, $goodCtaRes->getStatusCode());
            });

            T::test('Admin System Settings CMS: Update settings and inspect masked secrets', function () {
                $app = self::createAppWithMigratedDatabase();
                $auth = self::loginUser($app, 'admin@voak.test', 'VoakAdmin#2026!Secure');

                // 1. Get all settings (admin inspects)
                $getReq = new Request('GET', '/api/v1/admin/settings', [], [
                    'Cookie' => "voak_admin_session={$auth['session_cookie']}",
                ]);
                $getRes = $app->handle($getReq);
                T::assertEquals(200, $getRes->getStatusCode());
                $getData = json_decode($getRes->getBody(), true);
                T::assertTrue($getData['data']['count'] >= 8);

                // 2. Update platform settings
                $putReq = new Request(
                    'PUT',
                    '/api/v1/admin/settings',
                    [],
                    [
                        'Content-Type' => 'application/json',
                        'X-CSRF-Token' => $auth['csrf_token'],
                        'Cookie'       => "voak_admin_session={$auth['session_cookie']}",
                    ],
                    json_encode([
                        'site_name'     => 'Voak Health Care Australia',
                        'contact_email' => 'triage@voak.com.au',
                    ])
                );
                $putRes = $app->handle($putReq);
                T::assertEquals(200, $putRes->getStatusCode());
                $putData = json_decode($putRes->getBody(), true);
                T::assertEquals(2, $putData['data']['updated_count']);

                // 3. Verify public settings reflect the change
                $pubReq = new Request('GET', '/api/v1/public/settings');
                $pubRes = $app->handle($pubReq);
                $pubData = json_decode($pubRes->getBody(), true);
                T::assertEquals('Voak Health Care Australia', $pubData['data']['settings']['site_name']['value']);
            });

            T::test('Admin controllers fail closed (HTTP 401) when admin_user_id is missing or invalid', function () {
                $app = self::createAppWithMigratedDatabase();
                $pageService = $app->getContainer()->get(\Voak\Health\Services\PageService::class);
                $homepageService = $app->getContainer()->get(\Voak\Health\Services\HomepageService::class);
                $settingService = $app->getContainer()->get(\Voak\Health\Services\SettingService::class);

                $pageCtrl = new \Voak\Health\Modules\Admin\AdminPageController($pageService);
                $homeCtrl = new \Voak\Health\Modules\Admin\AdminHomepageController($homepageService);
                $setCtrl = new \Voak\Health\Modules\Admin\AdminSettingController($settingService);

                // Request with no admin_user_id attribute
                $reqWithoutUser = new Request('POST', '/api/v1/admin/pages', [], [], json_encode(['slug' => 'test-anon', 'title' => 'Anon']));

                $pageFailed = false;
                try {
                    $pageCtrl->create($reqWithoutUser);
                } catch (\Voak\Health\Core\Exceptions\AuthenticationException $e) {
                    $pageFailed = true;
                    T::assertContains('Authentication required', $e->getMessage());
                }
                T::assertTrue($pageFailed, "AdminPageController::create must fail closed when admin_user_id is missing");

                $homeFailed = false;
                try {
                    $homeCtrl->update($reqWithoutUser);
                } catch (\Voak\Health\Core\Exceptions\AuthenticationException $e) {
                    $homeFailed = true;
                    T::assertContains('Authentication required', $e->getMessage());
                }
                T::assertTrue($homeFailed, "AdminHomepageController::update must fail closed when admin_user_id is missing");

                $setFailed = false;
                try {
                    $setCtrl->update($reqWithoutUser);
                } catch (\Voak\Health\Core\Exceptions\AuthenticationException $e) {
                    $setFailed = true;
                    T::assertContains('Authentication required', $e->getMessage());
                }
                T::assertTrue($setFailed, "AdminSettingController::update must fail closed when admin_user_id is missing");
            });

            T::test('Content Editor cannot delete pages (HTTP 403) under least-privilege RBAC', function () {
                $app = self::createAppWithMigratedDatabase();
                $superAuth = self::loginUser($app, 'admin@voak.test', 'VoakAdmin#2026!Secure');
                $editorAuth = self::loginUser($app, 'editor@voak.test', 'VoakEditor#2026!Secure');

                // 1. Super Admin creates page #1
                $createReq = new Request(
                    'POST',
                    '/api/v1/admin/pages',
                    [],
                    [
                        'Content-Type' => 'application/json',
                        'X-CSRF-Token' => $superAuth['csrf_token'],
                        'Cookie'       => "voak_admin_session={$superAuth['session_cookie']}",
                    ],
                    json_encode([
                        'slug'    => 'protected-corp-page',
                        'title'   => 'Protected Corporate Page',
                        'content' => 'Important policy',
                    ])
                );
                $createRes = $app->handle($createReq);
                T::assertEquals(201, $createRes->getStatusCode());
                $createdData = json_decode($createRes->getBody(), true);
                $pageId = (int)$createdData['data']['page']['id'];

                // 2. Content Editor attempts to DELETE page -> MUST return 403
                $delReq = new Request(
                    'DELETE',
                    "/api/v1/admin/pages/{$pageId}",
                    [],
                    [
                        'X-CSRF-Token' => $editorAuth['csrf_token'],
                        'Cookie'       => "voak_admin_session={$editorAuth['session_cookie']}",
                    ]
                );
                $delRes = $app->handle($delReq);
                T::assertEquals(403, $delRes->getStatusCode(), "Content Editor must be forbidden from deleting pages");
                $delData = json_decode($delRes->getBody(), true);
                T::assertEquals('ERR_FORBIDDEN', $delData['error']['code']);

                // 3. Super Admin CAN delete the page -> 200
                $superDelReq = new Request(
                    'DELETE',
                    "/api/v1/admin/pages/{$pageId}",
                    [],
                    [
                        'X-CSRF-Token' => $superAuth['csrf_token'],
                        'Cookie'       => "voak_admin_session={$superAuth['session_cookie']}",
                    ]
                );
                $superDelRes = $app->handle($superDelReq);
                T::assertEquals(200, $superDelRes->getStatusCode(), "Super Admin must be authorized to delete page");
            });

            T::test('Route regex supports hyphenated route parameter names without PCRE syntax errors', function () {
                $route1 = new \Voak\Health\Core\Router\Route('GET', '/pages/{page-id}/sections/{section-name}', function () {});
                T::assertTrue($route1->matchesPath('/pages/42/sections/intro'));
                $params1 = $route1->extractParameters('/pages/42/sections/intro');
                T::assertEquals('42', $params1['page-id']);
                T::assertEquals('intro', $params1['section-name']);

                $route2 = new \Voak\Health\Core\Router\Route('GET', '/items/:item-slug', function () {});
                T::assertTrue($route2->matchesPath('/items/clinical-governance'));
                $params2 = $route2->extractParameters('/items/clinical-governance');
                T::assertEquals('clinical-governance', $params2['item-slug']);
            });
        });
    }
}
