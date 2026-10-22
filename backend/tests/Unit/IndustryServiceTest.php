<?php

declare(strict_types=1);

namespace Voak\Health\Tests\Unit;

use Voak\Health\Core\Exceptions\BadRequestException;
use Voak\Health\Core\Exceptions\NotFoundException;
use Voak\Health\Core\Exceptions\SecurityException;
use Voak\Health\Core\Exceptions\ValidationException;
use Voak\Health\Database\Connection;
use Voak\Health\Database\Migration\MigrationManager;
use Voak\Health\Security\AuditLogger;
use Voak\Health\Security\HtmlSanitizer;
use Voak\Health\Services\IndustryService;
use Voak\Health\Services\ServiceService;
use Voak\Health\Tests\TestFramework as T;

class IndustryServiceTest
{
    private static function createDatabase(): \PDO
    {
        $pdo = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        $pdo->exec("PRAGMA foreign_keys = ON;");

        $migrationsDir = dirname(__DIR__, 2) . '/database/migrations';
        $manager = new MigrationManager($migrationsDir, 'migrations');
        $manager->migrateUp($pdo);

        // Seed users
        $pdo->exec("INSERT INTO users (id, name, email, password_hash, is_active, created_at, updated_at) 
                    VALUES (1, 'Super Admin', 'admin@voak.test', 'hash', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");

        return $pdo;
    }

    public static function run(): void
    {
        T::suite('Phase 9 — IndustryService & Relational Mappings Unit Tests', function () {

            T::test('validateSlug accepts canonical lowercase alphanumeric with single hyphens', function () {
                $connection = new Connection();
                $audit = new AuditLogger();
                $sanitizer = new HtmlSanitizer();
                $service = new IndustryService($connection, $audit, $sanitizer);

                T::assertEquals('hospitals-health-services', $service->validateSlug('hospitals-health-services'));
                T::assertEquals('residential-aged-care', $service->validateSlug('residential-aged-care'));
                T::assertEquals('primary-care-general-practice', $service->validateSlug('primary-care-general-practice'));
                T::assertEquals('allied-health-networks', $service->validateSlug('allied-health-networks'));
                T::assertEquals('diagnostic-pathology-centers', $service->validateSlug('diagnostic-pathology-centers'));
                T::assertEquals('corporate-occupational-health', $service->validateSlug('corporate-occupational-health'));
                T::assertEquals('ind-07-specialist-clinics', $service->validateSlug('ind-07-specialist-clinics'));
            });

            T::test('validateSlug strictly rejects invalid format, reserved words, and path traversal', function () {
                $connection = new Connection();
                $audit = new AuditLogger();
                $sanitizer = new HtmlSanitizer();
                $service = new IndustryService($connection, $audit, $sanitizer);

                $invalidSlugs = [
                    '',
                    '   ',
                    'Hospitals-Health-Services',
                    'hospitals/health',
                    'hospitals\\health',
                    '../traversal',
                    'hospitals..health',
                    'hospitals?param=1',
                    'hospitals#fragment',
                    'hospitals@health',
                    '-hospitals',
                    'hospitals-',
                    'hospitals--health',
                    str_repeat('a', 121),
                    // Reserved words
                    'admin',
                    'api',
                    'services',
                    'industries',
                    'login',
                    'logout',
                    'dashboard',
                    'settings',
                    'public',
                    'pages',
                    'careers',
                    'enquiries',
                    'proposals',
                ];

                foreach ($invalidSlugs as $slug) {
                    $threw = false;
                    try {
                        $service->validateSlug($slug);
                    } catch (BadRequestException $e) {
                        $threw = true;
                    }
                    T::assertTrue($threw, "Slug [{$slug}] should have been rejected by validateSlug.");
                }
            });

            T::test('ensureDefaultIndustries seeds 6 canonical healthcare verticals on empty database idempotently', function () {
                $pdo = self::createDatabase();
                $conn = new Connection();
                $conn->setMockPdo($pdo);
                $audit = new AuditLogger();
                $sanitizer = new HtmlSanitizer();
                $service = new IndustryService($conn, $audit, $sanitizer);

                // Initial seed
                $seeded = $service->ensureDefaultIndustries();
                T::assertEquals(6, $seeded);

                $count = (int)$pdo->query("SELECT COUNT(*) FROM industries")->fetchColumn();
                T::assertEquals(6, $count);

                // Check default slugs
                $slugs = $pdo->query("SELECT slug FROM industries ORDER BY display_order ASC")->fetchAll(\PDO::FETCH_COLUMN);
                T::assertEquals([
                    'hospitals-health-services',
                    'residential-aged-care',
                    'primary-care-general-practice',
                    'allied-health-networks',
                    'diagnostic-pathology-centers',
                    'corporate-occupational-health',
                ], $slugs);

                // Second call must be idempotent and return 0
                $recheck = $service->ensureDefaultIndustries();
                T::assertEquals(0, $recheck);
                $countAfter = (int)$pdo->query("SELECT COUNT(*) FROM industries")->fetchColumn();
                T::assertEquals(6, $countAfter);
            });

            T::test('getPublishedIndustries delivers published industries sorted by display_order ASC with deterministic ETag', function () {
                $pdo = self::createDatabase();
                $conn = new Connection();
                $conn->setMockPdo($pdo);
                $audit = new AuditLogger();
                $sanitizer = new HtmlSanitizer();
                $service = new IndustryService($conn, $audit, $sanitizer);

                $result = $service->getPublishedIndustries();
                T::assertArrayHasKey('industries', $result);
                T::assertArrayHasKey('etag', $result);
                T::assertEquals(6, count($result['industries']));

                // Deterministic ETag check
                $expectedEtag = $service->calculateEtag($result['industries']);
                T::assertEquals($expectedEtag, $result['etag']);
                T::assertTrue(str_starts_with($result['etag'], '"'));
                T::assertTrue(str_ends_with($result['etag'], '"'));

                // Verify fields are parsed
                $first = $result['industries'][0];
                T::assertEquals('Hospitals & Health Services', $first['title']);
                T::assertEquals('hospitals-health-services', $first['slug']);
                T::assertTrue(is_array($first['regulatory_burden_specs']));
                T::assertTrue(is_array($first['pain_points']));
                T::assertEquals(1, $first['display_order']);
            });

            T::test('getPublishedIndustryBySlug returns industry detail with mapped services and ETag', function () {
                $pdo = self::createDatabase();
                $conn = new Connection();
                $conn->setMockPdo($pdo);
                $audit = new AuditLogger();
                $sanitizer = new HtmlSanitizer();
                $industryService = new IndustryService($conn, $audit, $sanitizer);
                $serviceService = new ServiceService($conn, $audit, $sanitizer);

                // Seed default services so mappings find matching services
                $serviceService->ensureDefaultServices();
                $industryService->ensureDefaultIndustries();

                $industry = $industryService->getPublishedIndustryBySlug('residential-aged-care');
                T::assertTrue($industry !== null);
                T::assertEquals('Residential Aged Care Facilities', $industry['title']);
                T::assertEquals('residential-aged-care', $industry['slug']);
                T::assertTrue(is_array($industry['services']));
                T::assertTrue(count($industry['services']) > 0);
                T::assertArrayHasKey('etag', $industry);

                // Non-existent slug returns null
                $missing = $industryService->getPublishedIndustryBySlug('space-tourism-medicine');
                T::assertTrue($missing === null);
            });

            T::test('listIndustries provides administrative pagination, published filter, and text search', function () {
                $pdo = self::createDatabase();
                $conn = new Connection();
                $conn->setMockPdo($pdo);
                $audit = new AuditLogger();
                $sanitizer = new HtmlSanitizer();
                $service = new IndustryService($conn, $audit, $sanitizer);

                // Seed defaults
                $service->ensureDefaultIndustries();

                // Unpublish one industry
                $pdo->exec("UPDATE industries SET is_published = 0 WHERE slug = 'corporate-occupational-health'");

                // List all
                $all = $service->listIndustries([], 1, 10);
                T::assertEquals(6, $all['pagination']['total']);
                T::assertEquals(6, count($all['industries']));

                // Filter published = 1
                $publishedOnly = $service->listIndustries(['is_published' => 1], 1, 10);
                T::assertEquals(5, $publishedOnly['pagination']['total']);
                T::assertEquals(5, count($publishedOnly['industries']));

                // Filter published = 0
                $unpublishedOnly = $service->listIndustries(['is_published' => 0], 1, 10);
                T::assertEquals(1, $unpublishedOnly['pagination']['total']);
                T::assertEquals(1, count($unpublishedOnly['industries']));
                T::assertEquals('Corporate & Occupational Health', $unpublishedOnly['industries'][0]['title']);

                // Search by title keyword
                $searchResult = $service->listIndustries(['search' => 'Pathology'], 1, 10);
                T::assertEquals(1, $searchResult['pagination']['total']);
                T::assertEquals('Diagnostic & Pathology Centers', $searchResult['industries'][0]['title']);
            });

            T::test('createIndustry validates, sanitizes, writes audit record, and synchronizes service mappings', function () {
                $pdo = self::createDatabase();
                $conn = new Connection();
                $conn->setMockPdo($pdo);
                $audit = new AuditLogger();
                $sanitizer = new HtmlSanitizer();
                $service = new IndustryService($conn, $audit, $sanitizer);

                $payload = [
                    'title'                   => 'Specialist Medical Centers <script>alert(1)</script>',
                    'slug'                    => 'specialist-medical-centers',
                    'summary'                 => 'Integrated specialist consulting suites <b>accredited</b> to RACGP standards.',
                    'regulatory_burden_specs' => [
                        'governing_body' => 'Medical Board of Australia',
                        'standards'      => ['RACGP Standards for General Practices 5th Edition'],
                    ],
                    'pain_points'             => [
                        'Coordinating visiting specialist rostering across regional satellite clinics',
                    ],
                    'is_published'            => 1,
                    'display_order'           => 7,
                    'service_ids'             => [1, 2],
                ];

                $created = $service->createIndustry($payload, 1);
                T::assertTrue((int)$created['id'] > 0);
                T::assertEquals('Specialist Medical Centers', $created['title']);
                T::assertEquals('specialist-medical-centers', $created['slug']);
                T::assertFalse(str_contains($created['title'], '<script>'));
                T::assertEquals(7, $created['display_order']);
                T::assertEquals(1, $created['is_published']);

                // Check audit log
                $auditRow = $pdo->query("SELECT * FROM audit_logs WHERE action = 'INDUSTRY_CREATED' ORDER BY id DESC LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
                T::assertTrue($auditRow !== false && !empty($auditRow));
                T::assertEquals('industries', $auditRow['domain']);
                T::assertEquals((string)$created['id'], (string)$auditRow['entity_id']);
                T::assertEquals('1', (string)$auditRow['user_id']);
            });

            T::test('createIndustry rejects duplicate slug with BadRequestException', function () {
                $pdo = self::createDatabase();
                $conn = new Connection();
                $conn->setMockPdo($pdo);
                $audit = new AuditLogger();
                $sanitizer = new HtmlSanitizer();
                $service = new IndustryService($conn, $audit, $sanitizer);

                $service->ensureDefaultIndustries();

                $threw = false;
                try {
                    $service->createIndustry([
                        'title' => 'Duplicate Hospitals Sector',
                        'slug'  => 'hospitals-health-services',
                    ], 1);
                } catch (BadRequestException $e) {
                    $threw = true;
                    T::assertContains("already exists", $e->getMessage());
                }
                T::assertTrue($threw, "createIndustry should reject duplicate slug.");
            });

            T::test('updateIndustry updates fields, records 301 URL redirect on slug change, and audits changes', function () {
                $pdo = self::createDatabase();
                $conn = new Connection();
                $conn->setMockPdo($pdo);
                $audit = new AuditLogger();
                $sanitizer = new HtmlSanitizer();
                $service = new IndustryService($conn, $audit, $sanitizer);

                $service->ensureDefaultIndustries();

                $orig = $service->getPublishedIndustryBySlug('allied-health-networks');
                T::assertTrue($orig !== null);
                $indId = (int)$orig['id'];

                $updatePayload = [
                    'title'                   => 'Allied Health & Rehabilitation Networks',
                    'slug'                    => 'allied-health-rehab-networks',
                    'summary'                 => 'Comprehensive multidisciplinary physiotherapy, OT, and speech pathology networks.',
                    'regulatory_burden_specs' => [
                        'governing_body' => 'AHPRA & National Boards',
                        'standards'      => ['NDIS Practice Standards and Quality Indicators'],
                    ],
                    'pain_points'             => [
                        'Clinical supervision ratios in rural and remote allied health practices',
                    ],
                    'is_published'            => 1,
                    'display_order'           => 4,
                ];

                $updated = $service->updateIndustry($indId, $updatePayload, 1);
                T::assertEquals('Allied Health & Rehabilitation Networks', $updated['title']);
                T::assertEquals('allied-health-rehab-networks', $updated['slug']);

                // Verify 301 redirect entry created
                $redirect = $service->findRedirect('/industries/allied-health-networks');
                T::assertTrue($redirect !== null);
                T::assertEquals('/industries/allied-health-rehab-networks', $redirect['target_path']);
                T::assertEquals(301, (int)$redirect['http_status_code']);
                T::assertEquals(1, (int)$redirect['is_active']);

                // Check audit log contains INDUSTRY_SLUG_CHANGED
                $slugAudit = $pdo->query("SELECT * FROM audit_logs WHERE action = 'INDUSTRY_SLUG_CHANGED' ORDER BY id DESC LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
                T::assertTrue($slugAudit !== false && !empty($slugAudit));
                T::assertEquals('1', (string)$slugAudit['user_id']);
            });

            T::test('updateIndustryStatus toggles publication status and logs INDUSTRY_STATUS_CHANGED', function () {
                $pdo = self::createDatabase();
                $conn = new Connection();
                $conn->setMockPdo($pdo);
                $audit = new AuditLogger();
                $sanitizer = new HtmlSanitizer();
                $service = new IndustryService($conn, $audit, $sanitizer);

                $service->ensureDefaultIndustries();
                $orig = $service->getPublishedIndustryBySlug('primary-care-general-practice');
                $indId = (int)$orig['id'];

                // Unpublish
                $unpub = $service->updateIndustryStatus($indId, 0, 1);
                T::assertEquals(0, $unpub['is_published']);

                // Check status audit
                $statusAudit = $pdo->query("SELECT * FROM audit_logs WHERE action = 'INDUSTRY_STATUS_CHANGED' ORDER BY id DESC LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
                T::assertTrue($statusAudit !== false && !empty($statusAudit));
                $meta = json_decode((string)$statusAudit['diff_json'], true);
                T::assertEquals(1, $meta['old_status']);
                T::assertEquals(0, $meta['new_status']);

                // Re-publish
                $repub = $service->updateIndustryStatus($indId, 1, 1);
                T::assertEquals(1, $repub['is_published']);
            });

            T::test('reorderIndustries updates display sequence and logs INDUSTRIES_REORDERED', function () {
                $pdo = self::createDatabase();
                $conn = new Connection();
                $conn->setMockPdo($pdo);
                $audit = new AuditLogger();
                $sanitizer = new HtmlSanitizer();
                $service = new IndustryService($conn, $audit, $sanitizer);

                $service->ensureDefaultIndustries();

                // Reverse the ordering
                $reordered = $service->reorderIndustries([6, 5, 4, 3, 2, 1], 1);
                T::assertEquals(6, count($reordered));
                T::assertEquals(6, $reordered[0]['id']);
                T::assertEquals(1, $reordered[0]['display_order']);

                $reorderAudit = $pdo->query("SELECT * FROM audit_logs WHERE action = 'INDUSTRIES_REORDERED' ORDER BY id DESC LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
                T::assertTrue($reorderAudit !== false && !empty($reorderAudit));
            });

            T::test('deleteIndustry removes industry and relational mappings, logs INDUSTRY_DELETED', function () {
                $pdo = self::createDatabase();
                $conn = new Connection();
                $conn->setMockPdo($pdo);
                $audit = new AuditLogger();
                $sanitizer = new HtmlSanitizer();
                $service = new IndustryService($conn, $audit, $sanitizer);

                $service->ensureDefaultIndustries();

                $toDelete = $service->getPublishedIndustryBySlug('diagnostic-pathology-centers');
                $indId = (int)$toDelete['id'];

                $deleted = $service->deleteIndustry($indId, 1);
                T::assertTrue($deleted);

                $check = $pdo->query("SELECT COUNT(*) FROM industries WHERE id = {$indId}")->fetchColumn();
                T::assertEquals(0, (int)$check);

                $mappingsCheck = $pdo->query("SELECT COUNT(*) FROM service_industry_mappings WHERE industry_id = {$indId}")->fetchColumn();
                T::assertEquals(0, (int)$mappingsCheck);

                $delAudit = $pdo->query("SELECT * FROM audit_logs WHERE action = 'INDUSTRY_DELETED' ORDER BY id DESC LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
                T::assertTrue($delAudit !== false && !empty($delAudit));

                // Deleting non-existent throws NotFoundException
                $threw = false;
                try {
                    $service->deleteIndustry(9999, 1);
                } catch (NotFoundException) {
                    $threw = true;
                }
                T::assertTrue($threw);
            });

            T::test('fail-closed compensating rollback restores industry if audit log creation fails', function () {
                $pdo = self::createDatabase();
                $conn = new Connection();
                $conn->setMockPdo($pdo);

                // Mock AuditLogger that throws
                $failingAudit = new class extends AuditLogger {
                    public function log(
                        \PDO $pdo,
                        string $domain,
                        string $action,
                        ?int $userId = null,
                        string $ipAddress = '127.0.0.1',
                        string $userAgent = 'CLI/Internal',
                        ?string $entityType = null,
                        ?int $entityId = null,
                        ?array $diff = null
                    ): array {
                        throw new \RuntimeException("Audit ledger storage partition corrupted.");
                    }
                };

                $sanitizer = new HtmlSanitizer();
                $service = new IndustryService($conn, $failingAudit, $sanitizer);
                $service->ensureDefaultIndustries();

                $item = $service->getPublishedIndustryBySlug('residential-aged-care');
                $indId = (int)$item['id'];

                // Attempt deletion with failing audit logger
                $threw = false;
                try {
                    $service->deleteIndustry($indId, 1);
                } catch (SecurityException $e) {
                    $threw = true;
                    T::assertEquals('ERR_AUDIT_LOG_FAILED', $e->getErrorCode());
                }
                T::assertTrue($threw, "deleteIndustry must throw SecurityException when audit log fails.");

                // Verify compensating restore: the record must still exist
                $restored = $pdo->query("SELECT * FROM industries WHERE id = {$indId}")->fetch(\PDO::FETCH_ASSOC);
                T::assertTrue($restored !== false && !empty($restored));
                T::assertEquals('Residential Aged Care Facilities', $restored['title']);
            });

        });
    }
}
