<?php

declare(strict_types=1);

namespace Voak\Health\Tests\Integration;

use Voak\Health\Auth\RbacManager;
use Voak\Health\Core\App;
use Voak\Health\Core\Config;
use Voak\Health\Core\Http\Request;
use Voak\Health\Database\Connection;
use Voak\Health\Database\Migration\MigrationManager;
use Voak\Health\Security\AuditLogger;
use Voak\Health\Security\HtmlSanitizer;
use Voak\Health\Security\PasswordHasher;
use Voak\Health\Security\RateLimiter;
use Voak\Health\Services\ContactService;
use Voak\Health\Tests\TestFramework as T;

/**
 * Integration Test Suite for Contact Enquiries CRM & Lifecycle State Machine API (Phase 11).
 */
class ContactInquiryApiTest
{
    private static function recursiveDelete(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                self::recursiveDelete($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
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
        T::suite('Contact Inquiry API Integration Tests', function () {

            $rateLimiterDir = sys_get_temp_dir() . '/voak_api_rl_' . bin2hex(random_bytes(4));

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
            $bdHash = $hasher->hash('VoakBd#2026!Secure');
            $editorHash = $hasher->hash('VoakEditor#2026!Secure');
            $hrHash = $hasher->hash('VoakHr#2026!Secure');

            // Seed users
            $pdo->exec("INSERT INTO users (id, name, email, password_hash, is_active, created_at, updated_at) 
                        VALUES (1, 'Super Admin', 'superadmin@voak.test', '{$adminHash}', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
            $pdo->exec("INSERT INTO users (id, name, email, password_hash, is_active, created_at, updated_at) 
                        VALUES (2, 'BD Manager', 'bdmanager@voak.test', '{$bdHash}', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
            $pdo->exec("INSERT INTO users (id, name, email, password_hash, is_active, created_at, updated_at) 
                        VALUES (3, 'Content Editor', 'editor@voak.test', '{$editorHash}', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
            $pdo->exec("INSERT INTO users (id, name, email, password_hash, is_active, created_at, updated_at) 
                        VALUES (4, 'HR Manager', 'hrmanager@voak.test', '{$hrHash}', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");

            $rbac = new RbacManager();
            $rbac->seedRbac($pdo);
            $rbac->assignRole($pdo, 1, RbacManager::ROLE_SUPER_ADMIN);
            $rbac->assignRole($pdo, 2, RbacManager::ROLE_BD_MANAGER);
            $rbac->assignRole($pdo, 3, RbacManager::ROLE_CONTENT_EDITOR);
            $rbac->assignRole($pdo, 4, RbacManager::ROLE_HR_MANAGER);

            $config = new Config([
                'APP_KEY'            => 'base64:' . base64_encode('test-encryption-key-32-chars-long!'),
                'AUTH_SIGNING_KEY'   => 'auth-signing-key-32-characters-min!!',
                'MFA_ENCRYPTION_KEY' => 'mfa-encryption-key-32-characters-min!!',
                'CSRF_SIGNING_KEY'   => 'csrf-signing-key-32-characters-min!',
                'APP_ENV'            => 'testing',
                'APP_DEBUG'          => true,
            ]);

            $app = new App($config);
            $container = $app->getContainer();
            /** @var Connection $conn */
            $conn = $container->get(Connection::class);
            $conn->setMockPdo($pdo);

            $contactService = new ContactService($conn, new AuditLogger(), new HtmlSanitizer(), new RateLimiter($rateLimiterDir), $rbac);
            $container->set(ContactService::class, $contactService);
            $container->set(\Voak\Health\Modules\Admin\AdminContactController::class, new \Voak\Health\Modules\Admin\AdminContactController($contactService));

            \Voak\Health\Middleware\RateLimitMiddleware::reset();

            // Log in users
            $superAdminAuth = self::loginUser($app, 'superadmin@voak.test', 'VoakAdmin#2026!Secure');
            $bdManagerAuth = self::loginUser($app, 'bdmanager@voak.test', 'VoakBd#2026!Secure');
            $editorAuth = self::loginUser($app, 'editor@voak.test', 'VoakEditor#2026!Secure');
            $hrManagerAuth = self::loginUser($app, 'hrmanager@voak.test', 'VoakHr#2026!Secure');

            T::test('Public endpoint POST /api/v1/public/contact creates enquiry successfully', function () use ($app) {
                $req = new Request(
                    'POST',
                    '/api/v1/public/contact',
                    [],
                    ['Content-Type' => 'application/json'],
                    json_encode([
                        'full_name'         => 'Dr. Gregory House',
                        'organization_name' => 'Princeton Plainsboro Hospital',
                        'corporate_email'   => 'ghouse@ppth.org',
                        'contact_phone'     => '+1 609 555 0100',
                        'inquiry_details'   => 'Diagnostic medicine department enterprise healthcare consulting engagement.',
                    ])
                );

                $res = $app->handle($req);
                T::assertEquals(201, $res->getStatusCode(), 'Public submission must return HTTP 201');

                $data = json_decode($res->getBody(), true);
                T::assertEquals(true, $data['success'], 'Status must be success');
                T::assertTrue(str_starts_with($data['data']['reference_code'], 'CNT-'), 'Reference code prefix CNT-');
            });

            T::test('Legacy canonical alias POST /api/enquiries/contact creates enquiry', function () use ($app) {
                $req = new Request(
                    'POST',
                    '/api/enquiries/contact',
                    [],
                    ['Content-Type' => 'application/json'],
                    json_encode([
                        'full_name'         => 'Dr. James Wilson',
                        'organization_name' => 'Oncology Center',
                        'corporate_email'   => 'jwilson@oncology.org',
                        'contact_phone'     => '+1 609 555 0101',
                        'inquiry_details'   => 'Department oncology healthcare staffing partnership discussion.',
                    ])
                );

                $res = $app->handle($req);
                T::assertEquals(201, $res->getStatusCode(), 'Alias route must return HTTP 201');
                $data = json_decode($res->getBody(), true);
                T::assertEquals(true, $data['success']);
            });

            T::test('Public submission fails with 422 on validation error', function () use ($app) {
                $req = new Request(
                    'POST',
                    '/api/v1/public/contact',
                    [],
                    ['Content-Type' => 'application/json'],
                    json_encode([
                        'full_name'         => 'A',
                        'organization_name' => 'B',
                        'corporate_email'   => 'invalid-email',
                        'contact_phone'     => '123',
                        'inquiry_details'   => 'short',
                    ])
                );

                $res = $app->handle($req);
                T::assertEquals(422, $res->getStatusCode(), 'Must return HTTP 422 on validation failure');
                $data = json_decode($res->getBody(), true);
                T::assertEquals(false, $data['success']);
            });

            T::test('Unauthenticated admin access strictly fails closed with HTTP 401', function () use ($app) {
                $req = new Request('GET', '/api/v1/admin/inquiries/contact');
                $res = $app->handle($req);
                T::assertEquals(401, $res->getStatusCode(), 'Unauthenticated request must return HTTP 401');

                $data = json_decode($res->getBody(), true);
                T::assertEquals('ERR_UNAUTHENTICATED', $data['error']['code'], 'Must return ERR_UNAUTHENTICATED');
            });

            T::test('Mutating admin request without CSRF token fails with HTTP 403', function () use ($app, $superAdminAuth) {
                $req = new Request(
                    'PATCH',
                    '/api/v1/admin/inquiries/contact/1/status',
                    [],
                    [
                        'Content-Type' => 'application/json',
                        'Cookie'       => "voak_admin_session={$superAdminAuth['session_cookie']}",
                        // Missing X-CSRF-Token
                    ],
                    json_encode(['status' => 'IN_REVIEW'])
                );

                $res = $app->handle($req);
                T::assertEquals(403, $res->getStatusCode(), 'Missing CSRF must return HTTP 403');
            });

            T::test('RBAC: Content Editor and HR Manager are forbidden from Contact CRM endpoints', function () use ($app, $editorAuth, $hrManagerAuth) {
                // Content Editor
                $req1 = new Request(
                    'GET',
                    '/api/v1/admin/inquiries/contact',
                    [],
                    ['Cookie' => "voak_admin_session={$editorAuth['session_cookie']}"]
                );
                $res1 = $app->handle($req1);
                T::assertEquals(403, $res1->getStatusCode(), 'Content Editor must get HTTP 403');

                // HR Manager
                $req2 = new Request(
                    'GET',
                    '/api/v1/admin/inquiries/contact',
                    [],
                    ['Cookie' => "voak_admin_session={$hrManagerAuth['session_cookie']}"]
                );
                $res2 = $app->handle($req2);
                T::assertEquals(403, $res2->getStatusCode(), 'HR Manager must get HTTP 403');
            });

            T::test('RBAC: BD Manager can list, show, update status, add note, export, but CANNOT delete', function () use ($app, $bdManagerAuth) {
                // 1. List
                $listReq = new Request(
                    'GET',
                    '/api/v1/admin/inquiries/contact',
                    [],
                    ['Cookie' => "voak_admin_session={$bdManagerAuth['session_cookie']}"]
                );
                $listRes = $app->handle($listReq);
                T::assertEquals(200, $listRes->getStatusCode(), 'BD Manager can list inquiries');

                // 2. Show
                $showReq = new Request(
                    'GET',
                    '/api/v1/admin/inquiries/contact/1',
                    [],
                    ['Cookie' => "voak_admin_session={$bdManagerAuth['session_cookie']}"]
                );
                $showRes = $app->handle($showReq);
                T::assertEquals(200, $showRes->getStatusCode(), 'BD Manager can view inquiry detail');

                // 3. Update status (NEW -> TRIAGED)
                $statusReq = new Request(
                    'PATCH',
                    '/api/v1/admin/inquiries/contact/1/status',
                    [],
                    [
                        'Content-Type' => 'application/json',
                        'Cookie'       => "voak_admin_session={$bdManagerAuth['session_cookie']}",
                        'X-CSRF-Token' => $bdManagerAuth['csrf_token'],
                    ],
                    json_encode(['status' => 'TRIAGED', 'note' => 'Triage complete by BD Manager'])
                );
                $statusRes = $app->handle($statusReq);
                T::assertEquals(200, $statusRes->getStatusCode(), 'BD Manager can update status');

                // 4. Add Note
                $noteReq = new Request(
                    'POST',
                    '/api/v1/admin/inquiries/contact/1/notes',
                    [],
                    [
                        'Content-Type' => 'application/json',
                        'Cookie'       => "voak_admin_session={$bdManagerAuth['session_cookie']}",
                        'X-CSRF-Token' => $bdManagerAuth['csrf_token'],
                    ],
                    json_encode(['note' => 'Scheduled meeting with clinical lead for Friday.'])
                );
                $noteRes = $app->handle($noteReq);
                T::assertEquals(200, $noteRes->getStatusCode(), 'BD Manager can add notes');

                // 5. Export CSV
                $exportReq = new Request(
                    'GET',
                    '/api/v1/admin/inquiries/contact/export',
                    [],
                    ['Cookie' => "voak_admin_session={$bdManagerAuth['session_cookie']}"]
                );
                $exportRes = $app->handle($exportReq);
                T::assertEquals(200, $exportRes->getStatusCode(), 'BD Manager can export CSV');
                T::assertTrue(str_contains((string)$exportRes->getHeader('Content-Type'), 'text/csv'), 'Content-Type must be text/csv');

                // 6. Delete (MUST FAIL with HTTP 403)
                $delReq = new Request(
                    'DELETE',
                    '/api/v1/admin/inquiries/contact/1',
                    [],
                    [
                        'Cookie'       => "voak_admin_session={$bdManagerAuth['session_cookie']}",
                        'X-CSRF-Token' => $bdManagerAuth['csrf_token'],
                    ]
                );
                $delRes = $app->handle($delReq);
                T::assertEquals(403, $delRes->getStatusCode(), 'BD Manager must be forbidden from deleting inquiries');
            });

            T::test('Invalid DAG state transition returns HTTP 422 ERR_INVALID_TRANSITION', function () use ($app, $superAdminAuth) {
                // Inquiry 2 is in status 'NEW'. Attempting to jump directly to 'RESOLVED'
                $req = new Request(
                    'PATCH',
                    '/api/v1/admin/inquiries/contact/2/status',
                    [],
                    [
                        'Content-Type' => 'application/json',
                        'Cookie'       => "voak_admin_session={$superAdminAuth['session_cookie']}",
                        'X-CSRF-Token' => $superAdminAuth['csrf_token'],
                    ],
                    json_encode(['status' => 'RESOLVED'])
                );

                $res = $app->handle($req);
                T::assertEquals(422, $res->getStatusCode(), 'Illegal transition must return HTTP 422');
                $data = json_decode($res->getBody(), true);
                T::assertEquals('ERR_INVALID_TRANSITION', $data['error']['code'], 'Error code must be ERR_INVALID_TRANSITION');
            });

            T::test('Super Admin can assign team member and delete inquiry', function () use ($app, $superAdminAuth) {
                // Assign to BD Manager (user_id = 2)
                $assignReq = new Request(
                    'POST',
                    '/api/v1/admin/inquiries/contact/2/assign',
                    [],
                    [
                        'Content-Type' => 'application/json',
                        'Cookie'       => "voak_admin_session={$superAdminAuth['session_cookie']}",
                        'X-CSRF-Token' => $superAdminAuth['csrf_token'],
                    ],
                    json_encode(['assigned_user_id' => 2])
                );
                $assignRes = $app->handle($assignReq);
                T::assertEquals(200, $assignRes->getStatusCode(), 'Assign must return HTTP 200');

                // Delete inquiry 2
                $delReq = new Request(
                    'DELETE',
                    '/api/v1/admin/inquiries/contact/2',
                    [],
                    [
                        'Cookie'       => "voak_admin_session={$superAdminAuth['session_cookie']}",
                        'X-CSRF-Token' => $superAdminAuth['csrf_token'],
                    ]
                );
                $delRes = $app->handle($delReq);
                T::assertEquals(200, $delRes->getStatusCode(), 'Super Admin delete must return HTTP 200');
                $delData = json_decode($delRes->getBody(), true);
                T::assertTrue($delData['data']['deleted'] === true, 'Deleted flag must be true');

                // Verify 404 on show
                $showReq = new Request(
                    'GET',
                    '/api/v1/admin/inquiries/contact/2',
                    [],
                    ['Cookie' => "voak_admin_session={$superAdminAuth['session_cookie']}"]
                );
                $showRes = $app->handle($showReq);
                T::assertEquals(404, $showRes->getStatusCode(), 'Deleted inquiry must return HTTP 404');
            });

            self::recursiveDelete($rateLimiterDir);
        });
    }
}
