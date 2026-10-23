<?php

declare(strict_types=1);

namespace Voak\Health\Tests\Integration;

use Voak\Health\Auth\RbacManager;
use Voak\Health\Core\App;
use Voak\Health\Core\Config;
use Voak\Health\Core\Http\Request;
use Voak\Health\Database\Connection;
use Voak\Health\Database\Migration\MigrationManager;
use Voak\Health\Modules\Admin\AdminApplicationController;
use Voak\Health\Modules\Admin\AdminJobController;
use Voak\Health\Modules\PublicContent\PublicContentController;
use Voak\Health\Security\AuditLogger;
use Voak\Health\Security\HtmlSanitizer;
use Voak\Health\Security\PasswordHasher;
use Voak\Health\Security\RateLimiter;
use Voak\Health\Services\CareerService;
use Voak\Health\Services\ContactService;
use Voak\Health\Services\HomepageService;
use Voak\Health\Services\IndustryService;
use Voak\Health\Services\MediaStorageService;
use Voak\Health\Services\PageService;
use Voak\Health\Services\PartnershipService;
use Voak\Health\Services\ProposalService;
use Voak\Health\Services\ServiceService;
use Voak\Health\Services\SettingService;
use Voak\Health\Tests\TestFramework as T;

/**
 * Integration Test Suite for Careers & Recruitment API (Phase 14: ADM-013, ADM-014, FR-ADM-014, FR-ADM-015, FR-WF-004).
 */
class CareerAndRecruitmentApiTest
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

    private static function createMockResumeFile(): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'test_int_resume_');
        file_put_contents($tmp, "%PDF-1.4 PDF-content-candidate\n");
        return [
            'tmp_name' => $tmp,
            'name'     => 'applicant_cv.pdf',
            'size'     => filesize($tmp),
            'error'    => UPLOAD_ERR_OK,
        ];
    }

    public static function run(): void
    {
        T::suite('Careers & Recruitment API Integration Tests (Phase 14)', function () {

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
            $hrHash = $hasher->hash('VoakHR#2026!Secure');
            $editorHash = $hasher->hash('VoakEditor#2026!Secure');

            // Seed users
            $pdo->exec("INSERT INTO users (id, name, email, password_hash, is_active, created_at, updated_at) 
                        VALUES (1, 'Super Admin', 'admin@voak.test', '{$adminHash}', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
            $pdo->exec("INSERT INTO users (id, name, email, password_hash, is_active, created_at, updated_at) 
                        VALUES (2, 'HR Manager', 'hr@voak.test', '{$hrHash}', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
            $pdo->exec("INSERT INTO users (id, name, email, password_hash, is_active, created_at, updated_at) 
                        VALUES (3, 'Content Editor', 'editor@voak.test', '{$editorHash}', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");

            $rbac = new RbacManager();
            $rbac->seedRbac($pdo);
            $rbac->assignRole($pdo, 1, RbacManager::ROLE_SUPER_ADMIN);
            $rbac->assignRole($pdo, 2, RbacManager::ROLE_HR_MANAGER);
            $rbac->assignRole($pdo, 3, RbacManager::ROLE_CONTENT_EDITOR);

            $quarantineDir = sys_get_temp_dir() . '/voak_api_q_car_' . bin2hex(random_bytes(4));
            $rateLimiterDir = sys_get_temp_dir() . '/voak_api_rl_car_' . bin2hex(random_bytes(4));

            $config = new Config([
                'APP_KEY'              => 'base64:' . base64_encode('test-encryption-key-32-chars-long!'),
                'AUTH_SIGNING_KEY'     => 'auth-signing-key-32-characters-min!!',
                'MFA_ENCRYPTION_KEY'   => 'mfa-encryption-key-32-characters-min!!',
                'CSRF_SIGNING_KEY'     => 'csrf-signing-key-32-characters-min!',
                'APP_ENV'              => 'testing',
                'APP_DEBUG'            => true,
                'MEDIA_QUARANTINE_DIR' => $quarantineDir,
            ]);

            $app = new App($config);
            $container = $app->getContainer();
            /** @var Connection $connection */
            $connection = $container->get(Connection::class);
            $connection->setMockPdo($pdo);

            $auditLogger = new AuditLogger();
            $sanitizer = new HtmlSanitizer();
            $rateLimiter = new RateLimiter($rateLimiterDir);
            $mediaService = new MediaStorageService($connection, $auditLogger, null, null, $quarantineDir);
            $careerService = new CareerService($connection, $mediaService, $auditLogger, $sanitizer, null, $rateLimiter, $rbac);

            $container->set(MediaStorageService::class, $mediaService);
            $container->set(CareerService::class, $careerService);
            $container->set(AdminJobController::class, new AdminJobController($careerService));
            $container->set(AdminApplicationController::class, new AdminApplicationController($careerService));

            $pageService = $container->get(PageService::class);
            $homepageService = $container->get(HomepageService::class);
            $settingService = $container->get(SettingService::class);
            $serviceService = $container->get(ServiceService::class);
            $industryService = $container->get(IndustryService::class);
            $contactService = $container->get(ContactService::class);
            $partnershipService = $container->get(PartnershipService::class);
            $proposalService = $container->get(ProposalService::class);

            $container->set(PublicContentController::class, new PublicContentController(
                $pageService,
                $homepageService,
                $settingService,
                $serviceService,
                $industryService,
                $mediaService,
                $contactService,
                $partnershipService,
                $proposalService,
                $careerService
            ));

            \Voak\Health\Middleware\RateLimitMiddleware::reset();

            // Login sessions
            $adminAuth = self::loginUser($app, 'admin@voak.test', 'VoakAdmin#2026!Secure');
            $hrAuth = self::loginUser($app, 'hr@voak.test', 'VoakHR#2026!Secure');
            $editorAuth = self::loginUser($app, 'editor@voak.test', 'VoakEditor#2026!Secure');

            // 1. Unauthenticated request to admin jobs fails closed with 401
            T::test("Unauthenticated request to /api/v1/admin/jobs fails closed with 401", function () use ($app) {
                $req = new Request('GET', '/api/v1/admin/jobs');
                $res = $app->handle($req);

                T::assertEquals(401, $res->getStatusCode());
                $body = json_decode($res->getBody(), true);
                T::assertFalse($body['success']);
                T::assertEquals('ERR_UNAUTHENTICATED', $body['error']['code']);
            });

            // 2. Content Editor forbidden from candidate applications
            T::test("Content Editor is strictly forbidden from candidate applications (403 Forbidden)", function () use ($app, $editorAuth) {
                $req = new Request(
                    'GET',
                    '/api/v1/admin/applications',
                    [],
                    ['Cookie' => "voak_admin_session={$editorAuth['session_cookie']}"]
                );
                $res = $app->handle($req);

                T::assertEquals(403, $res->getStatusCode());
                $body = json_decode($res->getBody(), true);
                T::assertFalse($body['success']);
                T::assertEquals('ERR_FORBIDDEN', $body['error']['code']);
            });

            // 3. Creating job vacancy without CSRF token returns 403
            T::test("POST /api/v1/admin/jobs without CSRF token returns 403 ERR_CSRF_MISSING", function () use ($app, $hrAuth) {
                $req = new Request(
                    'POST',
                    '/api/v1/admin/jobs',
                    [],
                    [
                        'Content-Type' => 'application/json',
                        'Cookie'       => "voak_admin_session={$hrAuth['session_cookie']}",
                    ],
                    json_encode([
                        'title'           => 'Clinical Nurse',
                        'department'      => 'Aged Care',
                        'employment_type' => 'FULL_TIME',
                        'location'        => 'Brisbane',
                        'summary'         => 'Clinical nurse opening.',
                    ])
                );
                $res = $app->handle($req);

                T::assertEquals(403, $res->getStatusCode());
                $body = json_decode($res->getBody(), true);
                T::assertEquals('ERR_CSRF_MISSING', $body['error']['code']);
            });

            // 4. HR Manager creates job vacancy successfully (201 Created)
            $createdJobId = 0;
            $createdJobSlug = '';
            T::test("HR Manager creates job vacancy with valid CSRF token (201 Created)", function () use ($app, $hrAuth, &$createdJobId, &$createdJobSlug) {
                $req = new Request(
                    'POST',
                    '/api/v1/admin/jobs',
                    [],
                    [
                        'Content-Type' => 'application/json',
                        'X-CSRF-Token' => $hrAuth['csrf_token'],
                        'Cookie'       => "voak_admin_session={$hrAuth['session_cookie']}",
                    ],
                    json_encode([
                        'title'            => 'Palliative Care Clinical Lead',
                        'slug'             => 'palliative-care-lead',
                        'department'       => 'Palliative Care',
                        'employment_type'  => 'FULL_TIME',
                        'location'         => 'Melbourne Hospital',
                        'summary'          => 'Senior clinical leadership for palliative healthcare operations.',
                        'responsibilities' => ['Clinical consultations', 'Staff education'],
                        'requirements'     => ['AHPRA nursing registration', '5+ years palliative experience'],
                        'is_active'        => 1,
                    ])
                );
                $res = $app->handle($req);

                T::assertEquals(201, $res->getStatusCode());
                $body = json_decode($res->getBody(), true);
                T::assertTrue($body['success']);
                T::assertEquals('Palliative Care Clinical Lead', $body['data']['title']);
                T::assertEquals('palliative-care-lead', $body['data']['slug']);
                $createdJobId = (int)$body['data']['id'];
                $createdJobSlug = (string)$body['data']['slug'];
            });

            // 5. Public jobs listing delivers published active jobs
            T::test("Public GET /api/v1/public/jobs returns 200 with ETag and active jobs", function () use ($app) {
                $req = new Request('GET', '/api/v1/public/jobs');
                $res = $app->handle($req);

                T::assertEquals(200, $res->getStatusCode());
                T::assertNotEmpty($res->getHeader('ETag'));
                $body = json_decode($res->getBody(), true);
                T::assertTrue($body['success']);
                T::assertGreaterThan(0, count($body['data']));
            });

            // 6. Public single job detail delivers active job
            T::test("Public GET /api/v1/public/jobs/:slug returns 200 with job details", function () use ($app, &$createdJobSlug) {
                $req = new Request('GET', "/api/v1/public/jobs/{$createdJobSlug}");
                $res = $app->handle($req);

                T::assertEquals(200, $res->getStatusCode());
                $body = json_decode($res->getBody(), true);
                T::assertTrue($body['success']);
                T::assertEquals('Palliative Care Clinical Lead', $body['data']['title']);
            });

            // 7. Public job application submission
            $appRefCode = '';
            $submittedAppId = 0;
            T::test("Public POST /api/v1/public/careers/apply submits application (201 Created)", function () use ($app, &$createdJobId, &$appRefCode, &$submittedAppId) {
                $mockFile = self::createMockResumeFile();
                $_FILES['resume_file'] = $mockFile;

                $req = new Request(
                    'POST',
                    '/api/v1/public/careers/apply',
                    [],
                    ['Content-Type' => 'application/json'],
                    json_encode([
                        'job_id'       => $createdJobId,
                        'full_name'    => 'Michael Green, RN',
                        'email'        => 'm.green@candidate.test',
                        'phone'        => '+61433221100',
                        'ahpra_number' => 'NMW9988776655',
                    ])
                );
                $res = $app->handle($req);
                @unlink($mockFile['tmp_name']);
                unset($_FILES['resume_file']);

                T::assertEquals(201, $res->getStatusCode());
                $body = json_decode($res->getBody(), true);
                T::assertTrue($body['success']);
                T::assertStringStartsWith('APP-', $body['data']['reference_code']);
                $appRefCode = (string)$body['data']['reference_code'];
                $submittedAppId = (int)$body['data']['id'];
            });

            // 8. HR Manager reviews candidate applications
            T::test("HR Manager retrieves candidate application roster on /api/v1/admin/applications (200 OK)", function () use ($app, $hrAuth) {
                $req = new Request(
                    'GET',
                    '/api/v1/admin/applications',
                    [],
                    ['Cookie' => "voak_admin_session={$hrAuth['session_cookie']}"]
                );
                $res = $app->handle($req);

                T::assertEquals(200, $res->getStatusCode());
                $body = json_decode($res->getBody(), true);
                T::assertTrue($body['success']);
                T::assertGreaterThan(0, count($body['data']['items']));
            });

            // 9. Advance candidate stage via PATCH /api/v1/admin/applications/:id/status
            T::test("HR Manager advances candidate stage to REVIEWING (200 OK)", function () use ($app, $hrAuth, &$submittedAppId) {
                $req = new Request(
                    'PATCH',
                    "/api/v1/admin/applications/{$submittedAppId}/status",
                    [],
                    [
                        'Content-Type' => 'application/json',
                        'X-CSRF-Token' => $hrAuth['csrf_token'],
                        'Cookie'       => "voak_admin_session={$hrAuth['session_cookie']}",
                    ],
                    json_encode([
                        'status'   => 'REVIEWING',
                        'feedback' => 'Initial resume screening passed.',
                    ])
                );
                $res = $app->handle($req);

                T::assertEquals(200, $res->getStatusCode());
                $body = json_decode($res->getBody(), true);
                T::assertTrue($body['success']);
                T::assertEquals('REVIEWING', $body['data']['status']);
            });

            // 10. Authenticated resume streaming
            T::test("HR Manager streams candidate resume via /api/v1/admin/applications/:id/download (200 OK)", function () use ($app, $hrAuth, &$submittedAppId) {
                $req = new Request(
                    'GET',
                    "/api/v1/admin/applications/{$submittedAppId}/download",
                    [],
                    ['Cookie' => "voak_admin_session={$hrAuth['session_cookie']}"]
                );
                $res = $app->handle($req);

                T::assertEquals(200, $res->getStatusCode());
                T::assertEquals('application/pdf', $res->getHeader('Content-Type'));
                T::assertEquals('nosniff', $res->getHeader('X-Content-Type-Options'));
                T::assertStringContainsString('attachment', (string)$res->getHeader('Content-Disposition'));
            });

            // 11. Referential deletion guard blocks deleting active job
            T::test("DELETE /api/v1/admin/jobs/:id is blocked with 409 Conflict when applications exist", function () use ($app, $hrAuth, &$createdJobId) {
                $req = new Request(
                    'DELETE',
                    "/api/v1/admin/jobs/{$createdJobId}",
                    [],
                    [
                        'X-CSRF-Token' => $hrAuth['csrf_token'],
                        'Cookie'       => "voak_admin_session={$hrAuth['session_cookie']}",
                    ]
                );
                $res = $app->handle($req);

                T::assertEquals(409, $res->getStatusCode());
                $body = json_decode($res->getBody(), true);
                T::assertEquals('ERR_CONFLICT', $body['error']['code']);
            });

            // 12. Export candidate applications as CSV
            T::test("HR Manager exports candidate applications as CSV via /api/v1/admin/applications/export (200 OK)", function () use ($app, $hrAuth) {
                $req = new Request(
                    'GET',
                    '/api/v1/admin/applications/export',
                    [],
                    ['Cookie' => "voak_admin_session={$hrAuth['session_cookie']}"]
                );
                $res = $app->handle($req);

                T::assertEquals(200, $res->getStatusCode());
                T::assertStringContainsString('text/csv', (string)$res->getHeader('Content-Type'));
                T::assertStringContainsString('Michael Green, RN', $res->getBody());
            });
        });
    }
}
