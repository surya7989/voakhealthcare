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
use Voak\Health\Services\EmailService;
use Voak\Health\Services\Mail\ArrayTransport;
use Voak\Health\Tests\TestFramework as T;

/**
 * Integration Test Suite for Email Templates & Outbox Telemetry API (Phase 13: ADM-012, FR-ADM-013, ADR-006).
 */
class EmailTemplateAndQueueApiTest
{
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
        T::suite('Email Templates & Outbox Telemetry API Integration Tests (Phase 13)', function () {

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

            // Seed users
            $pdo->exec("INSERT INTO users (id, name, email, password_hash, is_active, created_at, updated_at) 
                        VALUES (1, 'Super Admin', 'superadmin@voak.test', '{$adminHash}', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
            $pdo->exec("INSERT INTO users (id, name, email, password_hash, is_active, created_at, updated_at) 
                        VALUES (2, 'Content Editor', 'editor@voak.test', '{$editorHash}', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");

            $rbac = new RbacManager();
            $rbac->seedRbac($pdo);
            $rbac->assignRole($pdo, 1, RbacManager::ROLE_SUPER_ADMIN);
            $rbac->assignRole($pdo, 2, RbacManager::ROLE_CONTENT_EDITOR);

            $config = new Config([
                'APP_ENV'             => 'testing',
                'APP_DEBUG'           => 'true',
                'AUTH_SIGNING_KEY'    => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
                'CSRF_SIGNING_KEY'    => 'base64:BBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBB=',
                'MFA_ENCRYPTION_KEY'  => 'base64:CCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCC=',
                'CORS_ALLOWED_ORIGINS'=> 'http://localhost:3000',
                'STORAGE_PATH'        => sys_get_temp_dir() . '/voak_email_it_' . bin2hex(random_bytes(4)),
                'MAIL_TRANSPORT'      => 'array',
            ]);

            $app = new App($config);
            /** @var Connection $conn */
            $conn = $app->getContainer()->get(Connection::class);
            $conn->setMockPdo($pdo);

            /** @var EmailService $emailService */
            $emailService = $app->getContainer()->get(EmailService::class);
            $transport = new ArrayTransport();
            $emailService->setTransport($transport);

            // Log in Super Admin & Content Editor
            $adminAuth = self::loginUser($app, 'superadmin@voak.test', 'VoakAdmin#2026!Secure');
            $editorAuth = self::loginUser($app, 'editor@voak.test', 'VoakEditor#2026!Secure');

            // 1. Unauthenticated access rejected
            T::test('Unauthenticated request to email templates fails closed with 401', function () use ($app) {
                $req = new Request('GET', '/api/v1/admin/email-templates');
                $res = $app->handle($req);
                T::assertEquals(401, $res->getStatusCode(), 'Must return HTTP 401 Unauthenticated.');
            });

            // 2. Unauthorized role rejected
            T::test('Content Editor cannot view email templates (403 Forbidden)', function () use ($app, $editorAuth) {
                $req = new Request(
                    'GET',
                    '/api/v1/admin/email-templates',
                    [],
                    [
                        'Cookie' => "voak_admin_session={$editorAuth['session_cookie']}",
                    ]
                );
                $res = $app->handle($req);
                T::assertEquals(403, $res->getStatusCode(), 'Must return HTTP 403 Forbidden.');
            });

            // 3. Super Admin lists all 8 templates
            T::test('Super Admin lists all 8 canonical email templates (200 OK)', function () use ($app, $adminAuth) {
                $req = new Request(
                    'GET',
                    '/api/v1/admin/email-templates',
                    [],
                    [
                        'Cookie' => "voak_admin_session={$adminAuth['session_cookie']}",
                    ]
                );
                $res = $app->handle($req);
                T::assertEquals(200, $res->getStatusCode());

                $data = json_decode($res->getBody(), true);
                T::assertTrue($data['success']);
                T::assertEquals(8, count($data['data']), 'Must return 8 canonical templates.');
            });

            // 4. Super Admin inspects single template
            T::test('Super Admin retrieves single template by ID or slug (200 OK)', function () use ($app, $adminAuth) {
                $req = new Request(
                    'GET',
                    '/api/v1/admin/email-templates/contact-inquiry-ack',
                    [],
                    [
                        'Cookie' => "voak_admin_session={$adminAuth['session_cookie']}",
                    ]
                );
                $res = $app->handle($req);
                T::assertEquals(200, $res->getStatusCode());

                $data = json_decode($res->getBody(), true);
                T::assertTrue($data['success']);
                T::assertEquals('tmpl_contact_ack', $data['data']['template_key']);
            });

            // 5. CSRF protection on template update
            T::test('Updating email template without CSRF token fails with 403', function () use ($app, $adminAuth) {
                $req = new Request(
                    'PUT',
                    '/api/v1/admin/email-templates/1',
                    [],
                    [
                        'Content-Type' => 'application/json',
                        'Cookie'       => "voak_admin_session={$adminAuth['session_cookie']}",
                    ],
                    json_encode([
                        'subject'   => 'New Subject',
                        'body_html' => '<p>Body with {{ref_no}} and {{legal_entity_name}}</p>',
                    ])
                );
                $res = $app->handle($req);
                T::assertEquals(403, $res->getStatusCode(), 'Must return HTTP 403 for missing CSRF.');
            });

            // 6. Mandatory placeholder validation failure (TEST-ADM-013)
            T::test('Updating template missing mandatory placeholder returns 422 (TEST-ADM-013)', function () use ($app, $adminAuth) {
                $req = new Request(
                    'PUT',
                    '/api/v1/admin/email-templates/1',
                    [],
                    [
                        'Content-Type' => 'application/json',
                        'X-CSRF-Token' => $adminAuth['csrf_token'],
                        'Cookie'       => "voak_admin_session={$adminAuth['session_cookie']}",
                    ],
                    json_encode([
                        'subject'   => 'Subject Without Ref',
                        'body_html' => '<p>Dear {{contact_name}}, message for {{legal_entity_name}}.</p>',
                    ])
                );
                $res = $app->handle($req);
                T::assertEquals(422, $res->getStatusCode(), 'Must return HTTP 422 Validation Error.');

                $data = json_decode($res->getBody(), true);
                T::assertFalse($data['success']);
                T::assertTrue(str_contains($res->getBody(), '{{ref_no}}'), 'Must mention missing {{ref_no}}.');
            });

            // 7. Successful template update
            T::test('Updating template with valid placeholders succeeds (200 OK)', function () use ($app, $adminAuth) {
                $req = new Request(
                    'PUT',
                    '/api/v1/admin/email-templates/1',
                    [],
                    [
                        'Content-Type' => 'application/json',
                        'X-CSRF-Token' => $adminAuth['csrf_token'],
                        'Cookie'       => "voak_admin_session={$adminAuth['session_cookie']}",
                    ],
                    json_encode([
                        'subject'   => 'Inquiry Received [{{ref_no}}] - {{legal_entity_name}}',
                        'body_html' => '<p>Hello {{contact_name}}, reference {{ref_no}} has been logged for {{legal_entity_name}}.</p>',
                    ])
                );
                $res = $app->handle($req);
                T::assertEquals(200, $res->getStatusCode());

                $data = json_decode($res->getBody(), true);
                T::assertTrue($data['success']);
                T::assertEquals('Inquiry Received [{{ref_no}}] - {{legal_entity_name}}', $data['data']['subject']);
            });

            // 8. Test send endpoint
            T::test('Diagnostic test email send endpoint dispatches via transport (200 OK)', function () use ($app, $adminAuth, $transport) {
                $req = new Request(
                    'POST',
                    '/api/v1/admin/email-templates/1/test-send',
                    [],
                    [
                        'Content-Type' => 'application/json',
                        'X-CSRF-Token' => $adminAuth['csrf_token'],
                        'Cookie'       => "voak_admin_session={$adminAuth['session_cookie']}",
                    ],
                    json_encode([
                        'email' => 'admin-tester@voak.test',
                    ])
                );
                $res = $app->handle($req);
                T::assertEquals(200, $res->getStatusCode());

                $data = json_decode($res->getBody(), true);
                T::assertTrue($data['success']);
                T::assertEquals(1, $transport->count(), 'Transport must receive 1 message.');
            });

            // 9. Outbox queue listing and filtering
            T::test('Admin outbox queue listing returns paginated telemetry (200 OK)', function () use ($app, $adminAuth, $emailService) {
                $emailService->enqueue('tmpl_contact_ack', 'q1@test.com', 'Client 1', ['ref_no' => 'Q-1']);
                $emailService->enqueue('tmpl_partner_ack', 'q2@test.com', 'Client 2', ['ref_no' => 'Q-2']);

                $req = new Request(
                    'GET',
                    '/api/v1/admin/email-queue',
                    ['status' => 'QUEUED'],
                    [
                        'Cookie' => "voak_admin_session={$adminAuth['session_cookie']}",
                    ]
                );
                $res = $app->handle($req);
                T::assertEquals(200, $res->getStatusCode());

                $data = json_decode($res->getBody(), true);
                T::assertTrue($data['success']);
                T::assertTrue(count($data['data']) >= 2);
                T::assertTrue(isset($data['meta']['pagination']));
            });

            // 10. Manual retry endpoint (TEST-ADM-013)
            T::test('Manual retry endpoint resets failed outbox message to QUEUED (TEST-ADM-013)', function () use ($app, $adminAuth, $emailService, $pdo) {
                $id = $emailService->enqueue('tmpl_contact_ack', 'failed@test.com', 'Failed Client', ['ref_no' => 'FAIL-01']);
                $pdo->exec("UPDATE email_queue SET status = 'FAILED', attempts = 3, error_message = 'Connection dropped' WHERE id = {$id}");

                $req = new Request(
                    'POST',
                    "/api/v1/admin/email-queue/{$id}/retry",
                    [],
                    [
                        'Content-Type' => 'application/json',
                        'X-CSRF-Token' => $adminAuth['csrf_token'],
                        'Cookie'       => "voak_admin_session={$adminAuth['session_cookie']}",
                    ]
                );
                $res = $app->handle($req);
                T::assertEquals(200, $res->getStatusCode());

                $data = json_decode($res->getBody(), true);
                T::assertTrue($data['success']);
                T::assertEquals('QUEUED', $data['data']['status']);
                T::assertEquals(0, $data['data']['attempts']);
            });

            // 11. Bulk retry failed messages endpoint
            T::test('Bulk retry endpoint resets all failed messages (200 OK)', function () use ($app, $adminAuth, $emailService, $pdo) {
                $id = $emailService->enqueue('tmpl_contact_ack', 'f2@test.com', 'Client F2', ['ref_no' => 'FAIL-02']);
                $pdo->exec("UPDATE email_queue SET status = 'FAILED', attempts = 3 WHERE id = {$id}");

                $req = new Request(
                    'POST',
                    '/api/v1/admin/email-queue/retry-all-failed',
                    [],
                    [
                        'Content-Type' => 'application/json',
                        'X-CSRF-Token' => $adminAuth['csrf_token'],
                        'Cookie'       => "voak_admin_session={$adminAuth['session_cookie']}",
                    ]
                );
                $res = $app->handle($req);
                T::assertEquals(200, $res->getStatusCode());

                $data = json_decode($res->getBody(), true);
                T::assertTrue($data['success']);
                T::assertTrue($data['data']['reset_count'] >= 1);
            });

            // 12. Batch dispatch endpoint
            T::test('Batch dispatch endpoint claims and processes queue batch (200 OK)', function () use ($app, $adminAuth, $emailService, $transport) {
                $transport->clear();
                $emailService->enqueue('tmpl_contact_ack', 'batch@test.com', 'Batch User', ['ref_no' => 'BATCH-01']);

                $req = new Request(
                    'POST',
                    '/api/v1/admin/email-queue/dispatch',
                    ['limit' => 10],
                    [
                        'Content-Type' => 'application/json',
                        'X-CSRF-Token' => $adminAuth['csrf_token'],
                        'Cookie'       => "voak_admin_session={$adminAuth['session_cookie']}",
                    ]
                );
                $res = $app->handle($req);
                T::assertEquals(200, $res->getStatusCode());

                $data = json_decode($res->getBody(), true);
                T::assertTrue($data['success']);
                T::assertTrue($data['data']['processed'] >= 1);
                T::assertTrue($data['data']['sent'] >= 1);
            });
        });
    }
}
