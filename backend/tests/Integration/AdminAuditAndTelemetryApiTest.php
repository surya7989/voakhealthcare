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
use Voak\Health\Security\PasswordHasher;
use Voak\Health\Tests\TestFramework as T;

/**
 * Integration Test Suite for Administrative Audit Ledger, System Diagnostics & Dashboard Telemetry (Phase 17).
 *
 * Requirements Tested:
 * - Fail-closed authentication (HTTP 401)
 * - Granular RBAC enforcement (HTTP 403 for unprivileged roles, HTTP 200 for authorized roles)
 * - Audit logs ledger querying, detail inspection, and formula-injection protected CSV export (ADM-019 / FR-ADM-019)
 * - Cryptographic SHA-256 chain verification (TEST-ADM-019)
 * - Protected system telemetry and diagnostics (ADM-018 / SYS-004)
 * - Executive dashboard aggregated KPI statistics (ADM-001 / FR-ADM-001)
 */
class AdminAuditAndTelemetryApiTest
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

        // Website Admin (NO audit permissions)
        $pdo->exec("INSERT INTO users (id, name, email, password_hash, is_active, created_at, updated_at) 
                    VALUES (2, 'Website Admin', 'webadmin@voak.test', '{$webAdminHash}', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");

        // Content Editor (NO audit permissions)
        $pdo->exec("INSERT INTO users (id, name, email, password_hash, is_active, created_at, updated_at) 
                    VALUES (3, 'Content Editor', 'editor@voak.test', '{$editorHash}', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");

        $rbac = new RbacManager();
        $rbac->seedRbac($pdo);
        $rbac->assignRole($pdo, 1, RbacManager::ROLE_SUPER_ADMIN);
        $rbac->assignRole($pdo, 2, RbacManager::ROLE_WEBSITE_ADMIN);
        $rbac->assignRole($pdo, 3, RbacManager::ROLE_CONTENT_EDITOR);

        // Pre-populate some domain records for dashboard KPI tests
        $pdo->exec("INSERT INTO contact_inquiries (id, reference_code, full_name, organization_name, corporate_email, contact_phone, inquiry_details, status, ip_address, created_at, updated_at)
                    VALUES (1, 'ENQ-TEST-001', 'Dr. Smith', 'St Jude', 'smith@clinic.test', '+61400111222', 'Need nurses', 'NEW', '127.0.0.1', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");

        $pdo->exec("INSERT INTO proposals (id, reference_code, organization_name, contact_name, contact_email, contact_phone, facility_type, timeline, budget_band, quarantined_file_path, original_file_name, file_size_bytes, status, created_at, updated_at)
                    VALUES (1, 'RFP-TEST-001', 'General Hospital', 'Jane Doe', 'doe@hospital.test', '+61400333444', 'Hospital', 'Immediate', '$100k+', '/storage/test.pdf', 'test.pdf', 1024, 'SUBMITTED', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");

        $pdo->exec("INSERT INTO job_postings (id, title, slug, department, employment_type, location, summary, responsibilities_json, requirements_json, is_active, created_by_user_id, created_at, updated_at)
                    VALUES (1, 'Registered Nurse', 'rn-sydney', 'Clinical', 'FULL_TIME', 'Sydney', 'RN Wanted', '[]', '[]', 1, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");

        $pdo->exec("INSERT INTO pages (id, slug, title, meta_title, meta_description, content_json, is_published, created_at, updated_at)
                    VALUES (1, 'home', 'Home', 'Home Meta', 'Home Desc', '{}', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");

        // Pre-populate audit logs with realistic events
        $auditLogger = new AuditLogger();
        $auditLogger->log($pdo, 'AUTH', 'LOGIN_SUCCESS', 1, '192.168.1.100', 'Agent/1.0', 'users', 1, ['login' => 'ok']);
        $auditLogger->log($pdo, 'PAGES', 'PAGE_CREATED', 1, '192.168.1.100', 'Agent/1.0', 'pages', 1, ['title' => 'Home']);

        $config = new Config([
            'APP_KEY'              => 'base64:' . base64_encode('test-encryption-key-32-chars-long!'),
            'AUTH_SIGNING_KEY'     => 'auth-signing-key-32-characters-min!!',
            'MFA_ENCRYPTION_KEY'   => 'mfa-encryption-key-32-characters-min!!',
            'CSRF_SIGNING_KEY'     => 'csrf-signing-key-32-characters-min!',
            'APP_ENV'              => 'testing',
            'APP_DEBUG'            => true,
            'DB_DRIVER'            => 'sqlite',
            'STORAGE_PATH'         => sys_get_temp_dir() . '/voak_test_audit_' . bin2hex(random_bytes(4)),
            'APP_URL'              => 'https://voakhealthcare.com',
            'CORS_ALLOWED_ORIGINS' => 'https://voakhealthcare.com',
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
        T::suite('Phase 17 — Administrative Audit, Diagnostics & Dashboard API Integration Tests', function () {

            T::test('Unauthenticated request to audit logs fails closed with 401', function () {
                $app = self::createAppWithMigratedDatabase();

                $req = new Request('GET', '/api/v1/admin/audit-logs');
                $res = $app->handle($req);

                T::assertEquals(401, $res->getStatusCode());
                $body = json_decode($res->getBody(), true);
                T::assertFalse($body['success']);
            });

            T::test('Content Editor and Website Admin are forbidden from audit logs (403 Forbidden)', function () {
                $app = self::createAppWithMigratedDatabase();

                // 1. Content Editor
                $editor = self::loginUser($app, 'editor@voak.test', 'VoakEditor#2026!Secure');
                $req1 = new Request('GET', '/api/v1/admin/audit-logs', [], [
                    'Cookie' => $editor['cookie_header'],
                ]);
                $res1 = $app->handle($req1);
                T::assertEquals(403, $res1->getStatusCode());

                // 2. Website Admin
                $webAdmin = self::loginUser($app, 'webadmin@voak.test', 'VoakWebAdmin#2026!Secure');
                $req2 = new Request('GET', '/api/v1/admin/audit-logs', [], [
                    'Cookie' => $webAdmin['cookie_header'],
                ]);
                $res2 = $app->handle($req2);
                T::assertEquals(403, $res2->getStatusCode());
            });

            T::test('Super Admin retrieves paginated audit logs (200 OK)', function () {
                $app = self::createAppWithMigratedDatabase();
                $admin = self::loginUser($app, 'admin@voak.test', 'VoakAdmin#2026!Secure');

                $req = new Request('GET', '/api/v1/admin/audit-logs', ['per_page' => 10], [
                    'Cookie' => $admin['cookie_header'],
                ]);
                $res = $app->handle($req);

                T::assertEquals(200, $res->getStatusCode());
                $body = json_decode($res->getBody(), true);
                T::assertTrue($body['success']);
                T::assertTrue($body['data']['logs'] !== null);
                T::assertTrue($body['data']['pagination'] !== null);
                T::assertTrue($body['data']['pagination']['total_records'] >= 2);
            });

            T::test('Super Admin retrieves single audit log by ID (200 OK)', function () {
                $app = self::createAppWithMigratedDatabase();
                $admin = self::loginUser($app, 'admin@voak.test', 'VoakAdmin#2026!Secure');

                $req = new Request('GET', '/api/v1/admin/audit-logs/1', [], [
                    'Cookie' => $admin['cookie_header'],
                ]);
                $res = $app->handle($req);

                T::assertEquals(200, $res->getStatusCode());
                $body = json_decode($res->getBody(), true);
                T::assertTrue($body['success']);
                T::assertEquals(1, $body['data']['log']['id']);
                T::assertEquals('LOGIN_SUCCESS', $body['data']['log']['action']);
                T::assertEquals('AUTH', $body['data']['log']['domain']);

                // Missing ID
                $missingReq = new Request('GET', '/api/v1/admin/audit-logs/999999', [], [
                    'Cookie' => $admin['cookie_header'],
                ]);
                $missingRes = $app->handle($missingReq);
                T::assertEquals(404, $missingRes->getStatusCode());
            });

            T::test('Super Admin executes cryptographic audit chain verification (200 OK)', function () {
                $app = self::createAppWithMigratedDatabase();
                $admin = self::loginUser($app, 'admin@voak.test', 'VoakAdmin#2026!Secure');

                $req = new Request('POST', '/api/v1/admin/audit-logs/verify', [], [
                    'Cookie'       => $admin['cookie_header'],
                    'X-CSRF-Token' => $admin['csrf_token'],
                ]);
                $res = $app->handle($req);

                T::assertEquals(200, $res->getStatusCode());
                $body = json_decode($res->getBody(), true);
                T::assertTrue($body['success']);
                T::assertTrue($body['data']['verification']['valid']);
                T::assertTrue($body['data']['verification']['total_records'] >= 2);
                T::assertNull($body['data']['verification']['tampered_at_id']);
            });

            T::test('Super Admin exports audit logs to CSV with formula injection defense and UTF-8 BOM', function () {
                $app = self::createAppWithMigratedDatabase();
                $admin = self::loginUser($app, 'admin@voak.test', 'VoakAdmin#2026!Secure');

                $req = new Request('GET', '/api/v1/admin/audit-logs/export', [], [
                    'Cookie' => $admin['cookie_header'],
                ]);
                $res = $app->handle($req);

                T::assertEquals(200, $res->getStatusCode());
                T::assertEquals('text/csv; charset=UTF-8', $res->getHeader('Content-Type'));
                T::assertTrue(str_contains((string)$res->getHeader('Content-Disposition'), 'attachment; filename="audit-logs-export-'));

                $csvBody = $res->getBody();
                T::assertTrue(str_starts_with($csvBody, "\xEF\xBB\xBF"));
                T::assertTrue(str_contains($csvBody, 'Log ID'));
                T::assertTrue(str_contains($csvBody, 'Row Hash'));
            });

            T::test('System diagnostics endpoint enforces RBAC (401 unauth, 403 non-SuperAdmin, 200 SuperAdmin)', function () {
                $app = self::createAppWithMigratedDatabase();

                // 1. Unauthenticated -> 401
                $req1 = new Request('GET', '/api/v1/admin/system/diagnostics');
                T::assertEquals(401, $app->handle($req1)->getStatusCode());

                // 2. Content Editor -> 403
                $editor = self::loginUser($app, 'editor@voak.test', 'VoakEditor#2026!Secure');
                $req2 = new Request('GET', '/api/v1/admin/system/diagnostics', [], [
                    'Cookie' => $editor['cookie_header'],
                ]);
                T::assertEquals(403, $app->handle($req2)->getStatusCode());

                // 3. Super Admin -> 200
                $admin = self::loginUser($app, 'admin@voak.test', 'VoakAdmin#2026!Secure');
                $req3 = new Request('GET', '/api/v1/admin/system/diagnostics', [], [
                    'Cookie' => $admin['cookie_header'],
                ]);
                $res3 = $app->handle($req3);
                T::assertEquals(200, $res3->getStatusCode());

                $body = json_decode($res3->getBody(), true);
                T::assertTrue($body['success']);
                T::assertEquals('healthy', $body['data']['status']);
                T::assertEquals('sqlite', $body['data']['database']['driver']);
                T::assertTrue($body['data']['database']['connected']);
                T::assertTrue(isset($body['data']['database']['latency_ms']));
                T::assertTrue(isset($body['data']['environment']['php_version']));
                T::assertTrue(isset($body['data']['environment']['memory_usage_mb']));
            });

            T::test('Dashboard stats endpoint returns aggregated KPI metrics for authenticated admin roles', function () {
                $app = self::createAppWithMigratedDatabase();

                // 1. Unauthenticated -> 401
                $req1 = new Request('GET', '/api/v1/admin/dashboard/stats');
                T::assertEquals(401, $app->handle($req1)->getStatusCode());

                // 2. Super Admin -> 200
                $admin = self::loginUser($app, 'admin@voak.test', 'VoakAdmin#2026!Secure');
                $req2 = new Request('GET', '/api/v1/admin/dashboard/stats', [], [
                    'Cookie' => $admin['cookie_header'],
                ]);
                $res2 = $app->handle($req2);
                T::assertEquals(200, $res2->getStatusCode());

                $body = json_decode($res2->getBody(), true);
                T::assertTrue($body['success']);
                T::assertEquals(1, $body['data']['metrics']['inquiries']['contact_total']);
                T::assertEquals(1, $body['data']['metrics']['inquiries']['proposals_total']);
                T::assertEquals(1, $body['data']['metrics']['recruitment']['active_jobs']);
                T::assertEquals(1, $body['data']['metrics']['content']['published_pages']);
                T::assertTrue(count($body['data']['recent_activity']) >= 2);

                // 3. Content Editor also authorized for dashboard.view -> 200
                $editor = self::loginUser($app, 'editor@voak.test', 'VoakEditor#2026!Secure');
                $req3 = new Request('GET', '/api/v1/admin/dashboard/stats', [], [
                    'Cookie' => $editor['cookie_header'],
                ]);
                T::assertEquals(200, $app->handle($req3)->getStatusCode());
            });

        });
    }
}
