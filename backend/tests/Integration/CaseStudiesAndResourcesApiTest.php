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
 * Integration Test Suite for Case Studies & Resources API (Phase 15).
 * Tests HTTP end-to-end request lifecycle:
 * - Admin Auth & Fail-Closed gate (401)
 * - CSRF verification on mutating admin endpoints
 * - RBAC authorization checks (403)
 * - Module 14 Articles CMS CRUD, reading time auto-calc, and CSV export
 * - Module 15 Case Studies CRUD, consent release verification guard (422), and CSV export
 * - Module 16 FAQs CRUD and sequential reordering
 * - Public content delivery endpoints with ETag / 304 conditional responses
 */
class CaseStudiesAndResourcesApiTest
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

        // Content Editor
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
            'cookie_header'  => "voak_admin_session={$sessionToken}",
        ];
    }

    public static function run(): void
    {
        T::suite('Phase 15 — Case Studies & Resources API Integration Tests', function () {

            T::test('unauthenticated admin requests fail closed with HTTP 401', function () {
                $app = self::createAppWithMigratedDatabase();

                $endpoints = [
                    ['GET', '/api/v1/admin/articles'],
                    ['POST', '/api/v1/admin/articles'],
                    ['GET', '/api/v1/admin/case-studies'],
                    ['POST', '/api/v1/admin/case-studies'],
                    ['GET', '/api/v1/admin/faqs'],
                    ['POST', '/api/v1/admin/faqs'],
                ];

                foreach ($endpoints as [$method, $uri]) {
                    $req = new Request($method, $uri);
                    $res = $app->handle($req);
                    T::assertEquals(401, $res->getStatusCode(), "Unauthenticated {$method} {$uri} must return 401.");
                }
            });

            T::test('mutating admin requests strictly require valid CSRF protection', function () {
                $app = self::createAppWithMigratedDatabase();
                $auth = self::loginUser($app, 'editor@voak.test', 'VoakEditor#2026!Secure');

                // POST /api/v1/admin/articles without CSRF header
                $req1 = new Request(
                    'POST',
                    '/api/v1/admin/articles',
                    [],
                    [
                        'Content-Type' => 'application/json',
                        'Cookie'       => $auth['cookie_header'], // missing CSRF token & cookie
                    ],
                    json_encode(['title' => 'Article'])
                );
                $res1 = $app->handle($req1);
                T::assertEquals(403, $res1->getStatusCode(), 'Mutating request without CSRF must return 403.');

                // POST /api/v1/admin/case-studies without CSRF
                $req2 = new Request(
                    'POST',
                    '/api/v1/admin/case-studies',
                    [],
                    ['Content-Type' => 'application/json', 'Cookie' => $auth['cookie_header']],
                    json_encode(['title' => 'Case Study'])
                );
                $res2 = $app->handle($req2);
                T::assertEquals(403, $res2->getStatusCode(), 'Case study POST without CSRF must return 403.');
            });

            T::test('unprivileged user requests are rejected with HTTP 403', function () {
                $app = self::createAppWithMigratedDatabase();
                $auth = self::loginUser($app, 'noperms@voak.test', 'VoakUnpriv#2026!Secure');

                $req = new Request(
                    'GET',
                    '/api/v1/admin/articles',
                    [],
                    ['Cookie' => $auth['cookie_header']]
                );
                $res = $app->handle($req);
                T::assertEquals(403, $res->getStatusCode(), 'Unprivileged user must receive 403.');
            });

            T::test('admin articles CRUD, reading time auto-calc, and CSV export (TEST-ADM-017)', function () {
                $app = self::createAppWithMigratedDatabase();
                $auth = self::loginUser($app, 'editor@voak.test', 'VoakEditor#2026!Secure');

                // 1000 words body for TEST-ADM-017
                $words1000 = implode(' ', array_fill(0, 1000, 'governance'));

                // 1. Create Article
                $createReq = new Request(
                    'POST',
                    '/api/v1/admin/articles',
                    [],
                    [
                        'Content-Type' => 'application/json',
                        'X-CSRF-Token' => $auth['csrf_token'],
                        'Cookie'       => $auth['cookie_header'],
                    ],
                    json_encode([
                        'title'        => 'Navigating Healthcare Staffing Regulations',
                        'summary'      => 'A deep dive into clinical compliance standards.',
                        'content_html' => "<p>{$words1000}</p>",
                        'category'     => 'Workforce Strategy',
                        'tags'         => ['Governance', 'Workforce'],
                        'is_published' => 1,
                    ])
                );

                $createRes = $app->handle($createReq);
                T::assertEquals(201, $createRes->getStatusCode());
                $created = json_decode($createRes->getBody(), true)['data'];
                $articleId = $created['id'];

                T::assertEquals('navigating-healthcare-staffing-regulations', $created['slug']);
                T::assertEquals(5, $created['reading_time_minutes']); // 1000 words / 200 = 5 min (TEST-ADM-017)
                T::assertEquals(1, $created['is_published']);

                // 2. Read Article
                $getReq = new Request(
                    'GET',
                    "/api/v1/admin/articles/{$articleId}",
                    [],
                    ['Cookie' => $auth['cookie_header']]
                );
                $getRes = $app->handle($getReq);
                T::assertEquals(200, $getRes->getStatusCode());

                // 3. Update Article
                $updateReq = new Request(
                    'PUT',
                    "/api/v1/admin/articles/{$articleId}",
                    [],
                    [
                        'Content-Type' => 'application/json',
                        'X-CSRF-Token' => $auth['csrf_token'],
                        'Cookie'       => $auth['cookie_header'],
                    ],
                    json_encode([
                        'category' => 'Clinical Governance',
                    ])
                );
                $updateRes = $app->handle($updateReq);
                T::assertEquals(200, $updateRes->getStatusCode());
                $updated = json_decode($updateRes->getBody(), true)['data'];
                T::assertEquals('Clinical Governance', $updated['category']);

                // 4. Export CSV
                $exportReq = new Request(
                    'GET',
                    '/api/v1/admin/articles/export',
                    [],
                    ['Cookie' => $auth['cookie_header']]
                );
                $exportRes = $app->handle($exportReq);
                T::assertEquals(200, $exportRes->getStatusCode());
                T::assertTrue(str_contains($exportRes->getBody(), 'navigating-healthcare-staffing-regulations'));

                // 5. Delete Article
                $delReq = new Request(
                    'DELETE',
                    "/api/v1/admin/articles/{$articleId}",
                    [],
                    [
                        'X-CSRF-Token' => $auth['csrf_token'],
                        'Cookie'       => $auth['cookie_header'],
                    ]
                );
                $delRes = $app->handle($delReq);
                T::assertEquals(200, $delRes->getStatusCode());
            });

            T::test('case studies admin enforces consent release guard before publication (TEST-ADM-016)', function () {
                $app = self::createAppWithMigratedDatabase();
                $auth = self::loginUser($app, 'editor@voak.test', 'VoakEditor#2026!Secure');

                // 1. Attempt publication with consent_verified = 0 -> MUST FAIL 422
                $pubReq1 = new Request(
                    'POST',
                    '/api/v1/admin/case-studies',
                    [],
                    [
                        'Content-Type' => 'application/json',
                        'X-CSRF-Token' => $auth['csrf_token'],
                        'Cookie'       => $auth['cookie_header'],
                    ],
                    json_encode([
                        'title'                => 'Major Regional Hospital Surge',
                        'client_sector'        => 'Hospitals',
                        'challenge_summary'    => 'Critical nurse deficit',
                        'intervention_summary' => 'Rapid placement of 30 RNs',
                        'consent_verified'     => 0,
                        'is_published'         => 1, // UNVERIFIED PUBLISH
                    ])
                );

                $pubRes1 = $app->handle($pubReq1);
                T::assertEquals(422, $pubRes1->getStatusCode(), 'Publishing unverified case study must return HTTP 422.');
                $err1 = json_decode($pubRes1->getBody(), true);
                T::assertTrue(isset($err1['error']['validation_errors']['consent_verified']) || isset($err1['error']['details']['consent_verified']) || str_contains($err1['error']['message'] ?? '', 'consent'));

                // 2. Create as draft -> 201
                $draftReq = new Request(
                    'POST',
                    '/api/v1/admin/case-studies',
                    [],
                    [
                        'Content-Type' => 'application/json',
                        'X-CSRF-Token' => $auth['csrf_token'],
                        'Cookie'       => $auth['cookie_header'],
                    ],
                    json_encode([
                        'title'                => 'Major Regional Hospital Surge',
                        'client_sector'        => 'Hospitals',
                        'challenge_summary'    => 'Critical nurse deficit',
                        'intervention_summary' => 'Rapid placement of 30 RNs',
                        'consent_verified'     => 0,
                        'is_published'         => 0,
                    ])
                );
                $draftRes = $app->handle($draftReq);
                T::assertEquals(201, $draftRes->getStatusCode());
                $caseId = json_decode($draftRes->getBody(), true)['data']['id'];

                // 3. Update with consent_verified = 1 and is_published = 1 -> SUCCESS 200
                $pubReq2 = new Request(
                    'PUT',
                    "/api/v1/admin/case-studies/{$caseId}",
                    [],
                    [
                        'Content-Type' => 'application/json',
                        'X-CSRF-Token' => $auth['csrf_token'],
                        'Cookie'       => $auth['cookie_header'],
                    ],
                    json_encode([
                        'consent_verified' => 1,
                        'is_published'     => 1,
                    ])
                );
                $pubRes2 = $app->handle($pubReq2);
                T::assertEquals(200, $pubRes2->getStatusCode());
                $publishedCase = json_decode($pubRes2->getBody(), true)['data'];
                T::assertEquals(1, $publishedCase['consent_verified']);
                T::assertEquals(1, $publishedCase['is_published']);

                // 4. Export CSV
                $exportReq = new Request(
                    'GET',
                    '/api/v1/admin/case-studies/export',
                    [],
                    ['Cookie' => $auth['cookie_header']]
                );
                $exportRes = $app->handle($exportReq);
                T::assertEquals(200, $exportRes->getStatusCode());
                T::assertTrue(str_contains($exportRes->getBody(), 'Major Regional Hospital Surge'));
            });

            T::test('faqs admin CRUD and sequential reordering', function () {
                $app = self::createAppWithMigratedDatabase();
                $auth = self::loginUser($app, 'editor@voak.test', 'VoakEditor#2026!Secure');

                // 1. Create FAQ
                $createReq = new Request(
                    'POST',
                    '/api/v1/admin/faqs',
                    [],
                    [
                        'Content-Type' => 'application/json',
                        'X-CSRF-Token' => $auth['csrf_token'],
                        'Cookie'       => $auth['cookie_header'],
                    ],
                    json_encode([
                        'category'    => 'Procurement',
                        'question'    => 'What is the standard contract term?',
                        'answer_html' => '<p>Standard enterprise terms are 12 to 36 months.</p>',
                    ])
                );
                $createRes = $app->handle($createReq);
                T::assertEquals(201, $createRes->getStatusCode());
                $faqId = json_decode($createRes->getBody(), true)['data']['id'];

                // 2. Reorder FAQs
                $reorderReq = new Request(
                    'POST',
                    '/api/v1/admin/faqs/reorder',
                    [],
                    [
                        'Content-Type' => 'application/json',
                        'X-CSRF-Token' => $auth['csrf_token'],
                        'Cookie'       => $auth['cookie_header'],
                    ],
                    json_encode(['ordered_ids' => [$faqId]])
                );
                $reorderRes = $app->handle($reorderReq);
                T::assertEquals(200, $reorderRes->getStatusCode());

                // 3. Delete FAQ
                $delReq = new Request(
                    'DELETE',
                    "/api/v1/admin/faqs/{$faqId}",
                    [],
                    [
                        'X-CSRF-Token' => $auth['csrf_token'],
                        'Cookie'       => $auth['cookie_header'],
                    ]
                );
                $delRes = $app->handle($delReq);
                T::assertEquals(200, $delRes->getStatusCode());
            });

            T::test('public delivery endpoints support ETag and conditional HTTP 304 (TEST-PUB-013, TEST-PUB-016, TEST-PUB-018)', function () {
                $app = self::createAppWithMigratedDatabase();
                $auth = self::loginUser($app, 'editor@voak.test', 'VoakEditor#2026!Secure');

                // Seed 1 published article
                $app->handle(new Request(
                    'POST',
                    '/api/v1/admin/articles',
                    [],
                    ['Content-Type' => 'application/json', 'X-CSRF-Token' => $auth['csrf_token'], 'Cookie' => $auth['cookie_header']],
                    json_encode([
                        'title'        => 'Public Insights Article',
                        'summary'      => 'Summary',
                        'content_html' => '<p>Public content</p>',
                        'category'     => 'General',
                        'is_published' => 1,
                    ])
                ));

                // Seed 1 verified published case study
                $app->handle(new Request(
                    'POST',
                    '/api/v1/admin/case-studies',
                    [],
                    ['Content-Type' => 'application/json', 'X-CSRF-Token' => $auth['csrf_token'], 'Cookie' => $auth['cookie_header']],
                    json_encode([
                        'title'                => 'Public Verified Proof',
                        'client_sector'        => 'Hospitals',
                        'challenge_summary'    => 'Challenge',
                        'intervention_summary' => 'Intervention',
                        'consent_verified'     => 1,
                        'is_published'         => 1,
                    ])
                ));

                // Seed 1 published FAQ
                $app->handle(new Request(
                    'POST',
                    '/api/v1/admin/faqs',
                    [],
                    ['Content-Type' => 'application/json', 'X-CSRF-Token' => $auth['csrf_token'], 'Cookie' => $auth['cookie_header']],
                    json_encode([
                        'category'    => 'General',
                        'question'    => 'Public Question?',
                        'answer_html' => '<p>Public Answer</p>',
                        'is_published' => 1,
                    ])
                ));

                // 1. Articles Feed & 304
                $artRes1 = $app->handle(new Request('GET', '/api/v1/public/articles'));
                T::assertEquals(200, $artRes1->getStatusCode());
                $artEtag = $artRes1->getHeader('ETag');
                T::assertNotEmpty($artEtag);

                $artRes2 = $app->handle(new Request('GET', '/api/v1/public/articles', [], ['If-None-Match' => $artEtag]));
                T::assertEquals(304, $artRes2->getStatusCode());

                // 2. Case Studies Feed & 304
                $csRes1 = $app->handle(new Request('GET', '/api/v1/public/case-studies'));
                T::assertEquals(200, $csRes1->getStatusCode());
                $csEtag = $csRes1->getHeader('ETag');
                T::assertNotEmpty($csEtag);

                $csRes2 = $app->handle(new Request('GET', '/api/v1/public/case-studies', [], ['If-None-Match' => $csEtag]));
                T::assertEquals(304, $csRes2->getStatusCode());

                // 3. FAQs Feed & 304
                $faqRes1 = $app->handle(new Request('GET', '/api/v1/public/faqs'));
                T::assertEquals(200, $faqRes1->getStatusCode());
                $faqEtag = $faqRes1->getHeader('ETag');
                T::assertNotEmpty($faqEtag);

                $faqRes2 = $app->handle(new Request('GET', '/api/v1/public/faqs', [], ['If-None-Match' => $faqEtag]));
                T::assertEquals(304, $faqRes2->getStatusCode());
            });
        });
    }
}
