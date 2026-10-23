<?php

declare(strict_types=1);

namespace Voak\Health\Tests\Integration;

use Voak\Health\Auth\RbacManager;
use Voak\Health\Core\App;
use Voak\Health\Core\Config;
use Voak\Health\Core\Http\Request;
use Voak\Health\Database\Connection;
use Voak\Health\Database\Migration\MigrationManager;
use Voak\Health\Modules\Admin\AdminPartnershipController;
use Voak\Health\Modules\Admin\AdminProposalController;
use Voak\Health\Modules\PublicContent\PublicContentController;
use Voak\Health\Security\AuditLogger;
use Voak\Health\Security\HtmlSanitizer;
use Voak\Health\Security\PasswordHasher;
use Voak\Health\Security\RateLimiter;
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
 * Integration Test Suite for Proposals & Partnerships CRM API (Phase 12: FR-ADM-011, FR-ADM-012, FR-WF-002, FR-WF-003).
 */
class PartnershipAndProposalApiTest
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
        T::suite('Partnership & Proposal CRM API Integration Tests', function () {

            $quarantineDir = sys_get_temp_dir() . '/voak_api_q_' . bin2hex(random_bytes(4));
            $rateLimiterDir = sys_get_temp_dir() . '/voak_api_ptn_rl_' . bin2hex(random_bytes(4));

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

            // Seed users
            $pdo->exec("INSERT INTO users (id, name, email, password_hash, is_active, created_at, updated_at) 
                        VALUES (1, 'Super Admin', 'superadmin@voak.test', '{$adminHash}', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
            $pdo->exec("INSERT INTO users (id, name, email, password_hash, is_active, created_at, updated_at) 
                        VALUES (2, 'BD Manager', 'bdmanager@voak.test', '{$bdHash}', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
            $pdo->exec("INSERT INTO users (id, name, email, password_hash, is_active, created_at, updated_at) 
                        VALUES (3, 'Content Editor', 'editor@voak.test', '{$editorHash}', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");

            // Seed dummy service
            $pdo->exec("INSERT INTO services (id, title, slug, tagline, description, sla_metrics_json, deliverables_json, is_published, display_order, created_at, updated_at)
                        VALUES (1, 'Workforce Solutions', 'workforce-solutions', 'Tagline', 'Description', '[]', '[]', 1, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");

            $rbac = new RbacManager();
            $rbac->seedRbac($pdo);
            $rbac->assignRole($pdo, 1, RbacManager::ROLE_SUPER_ADMIN);
            $rbac->assignRole($pdo, 2, RbacManager::ROLE_BD_MANAGER);
            $rbac->assignRole($pdo, 3, RbacManager::ROLE_CONTENT_EDITOR);

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

            $auditLogger = new AuditLogger();
            $sanitizer = new HtmlSanitizer();
            $rateLimiter = new RateLimiter($rateLimiterDir);

            $mediaService = new MediaStorageService($conn, $auditLogger, null, null, $quarantineDir);
            $partnershipService = new PartnershipService($conn, $auditLogger, $sanitizer, $rateLimiter, $rbac);
            $proposalService = new ProposalService($conn, $mediaService, $auditLogger, $sanitizer, $rateLimiter, $rbac);

            $container->set(MediaStorageService::class, $mediaService);
            $container->set(PartnershipService::class, $partnershipService);
            $container->set(ProposalService::class, $proposalService);

            $container->set(AdminPartnershipController::class, new AdminPartnershipController($partnershipService));
            $container->set(AdminProposalController::class, new AdminProposalController($proposalService));

            $pageService = new PageService($conn, $auditLogger, $sanitizer);
            $homepageService = new HomepageService($conn, $auditLogger, $sanitizer);
            $settingService = new SettingService($conn, $auditLogger);
            $serviceService = new ServiceService($conn, $auditLogger, $sanitizer);
            $industryService = new IndustryService($conn, $auditLogger, $sanitizer);

            $container->set(PublicContentController::class, new PublicContentController(
                $pageService,
                $homepageService,
                $settingService,
                $serviceService,
                $industryService,
                $mediaService,
                null,
                $partnershipService,
                $proposalService
            ));

            $superAdminAuth = self::loginUser($app, 'superadmin@voak.test', 'VoakAdmin#2026!Secure');
            $bdAuth         = self::loginUser($app, 'bdmanager@voak.test', 'VoakBd#2026!Secure');
            $editorAuth     = self::loginUser($app, 'editor@voak.test', 'VoakEditor#2026!Secure');

            T::test('Public partnership submission endpoints ingest alliance proposals', function () use ($app) {
                // Canonical endpoint
                $req1 = new Request(
                    'POST',
                    '/api/v1/public/partnerships/inquire',
                    [],
                    ['Content-Type' => 'application/json'],
                    json_encode([
                        'organization_name' => 'Royal Melbourne Healthcare',
                        'contact_name'      => 'Dr. Colin Firth',
                        'corporate_email'   => 'cfirth@royalmelbourne.org',
                        'contact_phone'     => '+61 3 9342 7000',
                        'alliance_type'     => 'Tertiary Teaching Hospital',
                        'proposed_scope'    => 'Clinical placement and workforce collaboration for specialized acute care units.',
                    ])
                );

                $res1 = $app->handle($req1);
                T::assertEquals(201, $res1->getStatusCode());
                $data1 = json_decode($res1->getBody(), true);
                T::assertTrue($data1['success']);
                T::assertTrue(str_starts_with($data1['data']['reference_code'], 'PTN-'));

                // Alias endpoint
                $req2 = new Request(
                    'POST',
                    '/api/enquiries/partner',
                    [],
                    ['Content-Type' => 'application/json'],
                    json_encode([
                        'organization_name' => 'Sydney Local Health District',
                        'contact_name'      => 'Dr. Emma Watson',
                        'corporate_email'   => 'ewatson@slhd.nsw.gov.au',
                        'contact_phone'     => '+61 2 9515 6111',
                        'alliance_type'     => 'Regional Health Authority',
                        'proposed_scope'    => 'Regional health joint venture for rural clinical coverage and telehealth triage.',
                    ])
                );

                $res2 = $app->handle($req2);
                T::assertEquals(201, $res2->getStatusCode());
                $data2 = json_decode($res2->getBody(), true);
                T::assertTrue($data2['success']);
                T::assertTrue(str_starts_with($data2['data']['reference_code'], 'PTN-'));
            });

            T::test('Public proposal RFP submission endpoints ingest 4-step wizard entries', function () use ($app) {
                // Canonical endpoint
                $req1 = new Request(
                    'POST',
                    '/api/v1/public/proposals/submit',
                    [],
                    ['Content-Type' => 'application/json'],
                    json_encode([
                        'organization_name' => 'Alfred Health Consortium',
                        'contact_name'      => 'Brenda Vance',
                        'contact_email'     => 'bvance@alfredhealth.org',
                        'contact_phone'     => '+61 3 9076 2000',
                        'facility_type'     => 'Academic Medical Center',
                        'timeline'          => 'Within 60 Days',
                        'budget_band'       => '$500,000 - $1,000,000',
                        'service_ids'       => [1],
                    ])
                );

                $res1 = $app->handle($req1);
                T::assertEquals(201, $res1->getStatusCode());
                $data1 = json_decode($res1->getBody(), true);
                T::assertTrue($data1['success']);
                T::assertTrue(str_starts_with($data1['data']['reference_code'], 'RFP-'));

                // Alias endpoint
                $req2 = new Request(
                    'POST',
                    '/api/enquiries/proposal',
                    [],
                    ['Content-Type' => 'application/json'],
                    json_encode([
                        'organization_name' => 'Monash Health System',
                        'contact_name'      => 'David Attenborough',
                        'contact_email'     => 'dattenborough@monashhealth.org',
                        'contact_phone'     => '+61 3 9594 6666',
                        'facility_type'     => 'Specialist Health Facility',
                        'timeline'          => 'Immediate',
                        'budget_band'       => '$1,000,000+',
                        'service_ids'       => [1],
                    ])
                );

                $res2 = $app->handle($req2);
                T::assertEquals(201, $res2->getStatusCode());
                $data2 = json_decode($res2->getBody(), true);
                T::assertTrue($data2['success']);
                T::assertTrue(str_starts_with($data2['data']['reference_code'], 'RFP-'));
            });

            T::test('Unauthenticated access to admin endpoints fails closed with HTTP 401', function () use ($app) {
                $req1 = new Request('GET', '/api/v1/admin/partnerships');
                $res1 = $app->handle($req1);
                T::assertEquals(401, $res1->getStatusCode());

                $req2 = new Request('GET', '/api/v1/admin/proposals');
                $res2 = $app->handle($req2);
                T::assertEquals(401, $res2->getStatusCode());
            });

            T::test('BD Manager is authorized for CRM while Content Editor is forbidden', function () use ($app, $bdAuth, $editorAuth) {
                // BD Manager on partnerships
                $reqBdPtn = new Request('GET', '/api/v1/admin/partnerships', [], [
                    'Cookie' => "voak_admin_session={$bdAuth['session_cookie']}",
                ]);
                $resBdPtn = $app->handle($reqBdPtn);
                T::assertEquals(200, $resBdPtn->getStatusCode());

                // BD Manager on proposals
                $reqBdProp = new Request('GET', '/api/v1/admin/proposals', [], [
                    'Cookie' => "voak_admin_session={$bdAuth['session_cookie']}",
                ]);
                $resBdProp = $app->handle($reqBdProp);
                T::assertEquals(200, $resBdProp->getStatusCode());

                // Content Editor on partnerships -> 403
                $reqEdPtn = new Request('GET', '/api/v1/admin/partnerships', [], [
                    'Cookie' => "voak_admin_session={$editorAuth['session_cookie']}",
                ]);
                $resEdPtn = $app->handle($reqEdPtn);
                T::assertEquals(403, $resEdPtn->getStatusCode());

                // Content Editor on proposals -> 403
                $reqEdProp = new Request('GET', '/api/v1/admin/proposals', [], [
                    'Cookie' => "voak_admin_session={$editorAuth['session_cookie']}",
                ]);
                $resEdProp = $app->handle($reqEdProp);
                T::assertEquals(403, $resEdProp->getStatusCode());
            });

            T::test('Mutating partnership or proposal status requires valid CSRF token', function () use ($app, $bdAuth) {
                // Without CSRF token
                $reqNoCsrf = new Request(
                    'PATCH',
                    '/api/v1/admin/partnerships/1/status',
                    [],
                    [
                        'Cookie'       => "voak_admin_session={$bdAuth['session_cookie']}",
                        'Content-Type' => 'application/json',
                    ],
                    json_encode(['status' => 'EVALUATION'])
                );
                $resNoCsrf = $app->handle($reqNoCsrf);
                T::assertEquals(403, $resNoCsrf->getStatusCode());
                $dataNoCsrf = json_decode($resNoCsrf->getBody(), true);
                T::assertEquals('ERR_CSRF_MISSING', $dataNoCsrf['error']['code']);

                // With valid CSRF token
                $reqValidCsrf = new Request(
                    'PATCH',
                    '/api/v1/admin/partnerships/1/status',
                    [],
                    [
                        'Cookie'       => "voak_admin_session={$bdAuth['session_cookie']}",
                        'X-CSRF-Token' => $bdAuth['csrf_token'],
                        'Content-Type' => 'application/json',
                    ],
                    json_encode(['status' => 'EVALUATION', 'notes' => 'Transitioned by BD lead'])
                );
                $resValidCsrf = $app->handle($reqValidCsrf);
                T::assertEquals(200, $resValidCsrf->getStatusCode(), 'Status mutation error: ' . $resValidCsrf->getBody());
                $dataValidCsrf = json_decode($resValidCsrf->getBody(), true);
                T::assertTrue($dataValidCsrf['success']);
                T::assertEquals('EVALUATION', $dataValidCsrf['data']['status']);
            });

            T::test('BD Manager can export CSV while delete requires Super Admin', function () use ($app, $bdAuth, $superAdminAuth) {
                // BD Manager exports partnerships CSV
                $expPtn = new Request('GET', '/api/v1/admin/partnerships/export', [], [
                    'Cookie' => "voak_admin_session={$bdAuth['session_cookie']}",
                ]);
                $resExpPtn = $app->handle($expPtn);
                T::assertEquals(200, $resExpPtn->getStatusCode());
                T::assertTrue(str_contains((string)$resExpPtn->getHeader('Content-Type'), 'text/csv'));

                // BD Manager attempts delete -> 403 Forbidden
                $delPtnBd = new Request(
                    'DELETE',
                    '/api/v1/admin/partnerships/2',
                    [],
                    [
                        'Cookie'       => "voak_admin_session={$bdAuth['session_cookie']}",
                        'X-CSRF-Token' => $bdAuth['csrf_token'],
                    ]
                );
                $resDelPtnBd = $app->handle($delPtnBd);
                T::assertEquals(403, $resDelPtnBd->getStatusCode());

                // Super Admin executes delete -> 200 OK
                $delPtnAdmin = new Request(
                    'DELETE',
                    '/api/v1/admin/partnerships/2',
                    [],
                    [
                        'Cookie'       => "voak_admin_session={$superAdminAuth['session_cookie']}",
                        'X-CSRF-Token' => $superAdminAuth['csrf_token'],
                    ]
                );
                $resDelPtnAdmin = $app->handle($delPtnAdmin);
                T::assertEquals(200, $resDelPtnAdmin->getStatusCode());
                $dataDel = json_decode($resDelPtnAdmin->getBody(), true);
                T::assertTrue($dataDel['success']);
                T::assertTrue($dataDel['data']['deleted']);
            });

            self::recursiveDelete($quarantineDir);
            self::recursiveDelete($rateLimiterDir);
        });
    }
}
