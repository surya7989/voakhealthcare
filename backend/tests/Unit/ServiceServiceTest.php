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
use Voak\Health\Services\ServiceService;
use Voak\Health\Tests\TestFramework as T;

class ServiceServiceTest
{
    private static function createDatabase(): \PDO
    {
        $pdo = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
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
        T::suite('Phase 8 — ServiceService & 301 SEO Redirect Engine Unit Tests', function () {

            T::test('validateSlug accepts canonical lowercase alphanumeric with single hyphens', function () {
                $connection = new Connection();
                $audit = new AuditLogger();
                $sanitizer = new HtmlSanitizer();
                $service = new ServiceService($connection, $audit, $sanitizer);

                T::assertEquals('clinical-governance-solutions', $service->validateSlug('clinical-governance-solutions'));
                T::assertEquals('svc-01-aged-care', $service->validateSlug('svc-01-aged-care'));
                T::assertEquals('nursing-workforce-2026', $service->validateSlug('nursing-workforce-2026'));
                T::assertEquals('telehealth-consulting', $service->validateSlug('telehealth-consulting'));
            });

            T::test('validateSlug strictly rejects invalid format, reserved words, and path traversal', function () {
                $connection = new Connection();
                $audit = new AuditLogger();
                $sanitizer = new HtmlSanitizer();
                $service = new ServiceService($connection, $audit, $sanitizer);

                $invalidSlugs = [
                    '',
                    '   ',
                    'Clinical-Governance',
                    'clinical/governance',
                    'clinical\\governance',
                    '../traversal',
                    'clinical..governance',
                    'clinical?param=1',
                    'clinical#fragment',
                    'clinical@governance',
                    '-clinical',
                    'clinical-',
                    'clinical--governance',
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

            T::test('ensureDefaultServices seeds 6 canonical healthcare pillars on empty database idempotently', function () {
                $pdo = self::createDatabase();
                $connection = new Connection();
                $connection->setMockPdo($pdo);
                $audit = new AuditLogger();
                $sanitizer = new HtmlSanitizer();
                $service = new ServiceService($connection, $audit, $sanitizer);

                $seededFirst = $service->ensureDefaultServices(1);
                T::assertEquals(6, $seededFirst, 'Expected 6 services to be seeded on empty DB.');

                // Verify rows in database
                $rows = $pdo->query("SELECT id, slug, title, is_published, display_order FROM services ORDER BY display_order ASC")->fetchAll();
                T::assertEquals(6, count($rows));
                T::assertEquals('clinical-workforce-solutions', $rows[0]['slug']);
                T::assertEquals('healthcare-consulting-advisory', $rows[5]['slug']);

                // Verify JSON payloads were stored and are decodable
                $service1 = $service->getServiceById((int)$rows[0]['id']);
                T::assertTrue(is_array($service1['sla_metrics']));
                T::assertTrue(is_array($service1['deliverables']));
                T::assertTrue(count($service1['deliverables']) >= 3);

                // Run ensureDefaultServices a second time: must be idempotent and return 0
                $seededSecond = $service->ensureDefaultServices(1);
                T::assertEquals(0, $seededSecond, 'Second invocation should seed 0 services.');
            });

            T::test('getPublishedServices returns published services ordered by display_order with ETag', function () {
                $pdo = self::createDatabase();
                $connection = new Connection();
                $connection->setMockPdo($pdo);
                $audit = new AuditLogger();
                $sanitizer = new HtmlSanitizer();
                $service = new ServiceService($connection, $audit, $sanitizer);
                $service->ensureDefaultServices(1);

                $result = $service->getPublishedServices();
                T::assertEquals(6, count($result['services']));
                T::assertTrue(!empty($result['etag']));
                T::assertContains('"', $result['etag']);

                // Deterministic ETag check: calling again without changes returns identical ETag
                $result2 = $service->getPublishedServices();
                T::assertEquals($result['etag'], $result2['etag']);
            });

            T::test('getPublishedServiceBySlug returns published service and null for draft or missing', function () {
                $pdo = self::createDatabase();
                $connection = new Connection();
                $connection->setMockPdo($pdo);
                $audit = new AuditLogger();
                $sanitizer = new HtmlSanitizer();
                $service = new ServiceService($connection, $audit, $sanitizer);

                // Create a draft service
                $created = $service->createService([
                    'title'        => 'Specialist Draft Service',
                    'slug'         => 'specialist-draft-service',
                    'tagline'      => 'A draft service for testing',
                    'description'  => 'Detailed description of draft service.',
                    'is_published' => false,
                ], 1);

                $foundPublic = $service->getPublishedServiceBySlug('specialist-draft-service');
                T::assertEquals(null, $foundPublic, 'Draft service should return null for public retrieval.');

                $nonExistent = $service->getPublishedServiceBySlug('non-existent-service');
                T::assertEquals(null, $nonExistent, 'Non-existent service should return null.');

                // Publish the service
                $service->updateServiceStatus((int)$created['id'], true, 1);
                $foundPublished = $service->getPublishedServiceBySlug('specialist-draft-service');
                T::assertTrue(is_array($foundPublished));
                T::assertEquals('Specialist Draft Service', $foundPublished['title']);
            });

            T::test('createService rejects duplicate slugs and validates required fields', function () {
                $pdo = self::createDatabase();
                $connection = new Connection();
                $connection->setMockPdo($pdo);
                $audit = new AuditLogger();
                $sanitizer = new HtmlSanitizer();
                $service = new ServiceService($connection, $audit, $sanitizer);

                $service->createService([
                    'title'       => 'Palliative Care Advisory',
                    'slug'        => 'palliative-care-advisory',
                    'tagline'     => 'Specialist palliative clinical leadership',
                    'description' => 'Comprehensive clinical guidance.',
                ], 1);

                // Missing required title
                $threwValidation = false;
                try {
                    $service->createService([
                        'title' => '',
                        'slug'  => 'another-slug',
                    ], 1);
                } catch (ValidationException $e) {
                    $threwValidation = true;
                }
                T::assertTrue($threwValidation, 'Expected ValidationException for empty title.');

                // Duplicate slug
                $threwDuplicate = false;
                try {
                    $service->createService([
                        'title'       => 'Duplicate Slug Service',
                        'slug'        => 'palliative-care-advisory',
                        'tagline'     => 'Another tagline',
                        'description' => 'Another description',
                    ], 1);
                } catch (BadRequestException $e) {
                    $threwDuplicate = true;
                    T::assertContains('already exists', $e->getMessage());
                }
                T::assertTrue($threwDuplicate, 'Expected BadRequestException for duplicate slug.');
            });

            T::test('updateService records 301 redirect in url_redirects when slug is changed', function () {
                $pdo = self::createDatabase();
                $connection = new Connection();
                $connection->setMockPdo($pdo);
                $audit = new AuditLogger();
                $sanitizer = new HtmlSanitizer();
                $service = new ServiceService($connection, $audit, $sanitizer);

                $svc = $service->createService([
                    'title'       => 'Initial Service Name',
                    'slug'        => 'initial-service-name',
                    'tagline'     => 'Initial tagline',
                    'description' => 'Initial description.',
                    'is_published' => true,
                ], 1);

                // Update slug
                $updated = $service->updateService((int)$svc['id'], [
                    'slug'  => 'renamed-service-name',
                    'title' => 'Renamed Service Name',
                ], 1);

                T::assertEquals('renamed-service-name', $updated['slug']);

                // Verify redirect entry in url_redirects
                $redirect = $service->findRedirect('/services/initial-service-name');
                T::assertTrue(is_array($redirect), 'Redirect record must exist for old slug.');
                T::assertEquals('/services/renamed-service-name', $redirect['target_path']);
                T::assertEquals(301, (int)$redirect['http_status_code']);

                // Check audit log contains both SERVICE_UPDATED and SERVICE_SLUG_CHANGED
                $logs = $pdo->query("SELECT action FROM audit_logs ORDER BY id ASC")->fetchAll();
                $actions = array_column($logs, 'action');
                T::assertTrue(in_array('SERVICE_CREATED', $actions, true));
                T::assertTrue(in_array('SERVICE_UPDATED', $actions, true));
                T::assertTrue(in_array('SERVICE_SLUG_CHANGED', $actions, true));
            });

            T::test('reorderServices updates display order atomically and logs audit event', function () {
                $pdo = self::createDatabase();
                $connection = new Connection();
                $connection->setMockPdo($pdo);
                $audit = new AuditLogger();
                $sanitizer = new HtmlSanitizer();
                $service = new ServiceService($connection, $audit, $sanitizer);
                $service->ensureDefaultServices(1);

                $listResult = $service->listServices();
                $ids = array_column($listResult['services'], 'id');
                // Reverse the ordering
                $reversedIds = array_reverse($ids);

                $reordered = $service->reorderServices($reversedIds, 1);
                T::assertEquals($reversedIds[0], $reordered[0]['id']);
                T::assertEquals($reversedIds[5], $reordered[5]['id']);

                // Verify display_order column in database
                $firstRow = $pdo->query("SELECT id, display_order FROM services WHERE id = {$reversedIds[0]}")->fetch();
                T::assertEquals(1, (int)$firstRow['display_order']);

                $lastRow = $pdo->query("SELECT id, display_order FROM services WHERE id = {$reversedIds[5]}")->fetch();
                T::assertEquals(6, (int)$lastRow['display_order']);

                // Verify audit log
                $reorderLog = $pdo->query("SELECT action FROM audit_logs WHERE action = 'SERVICES_REORDERED'")->fetch();
                T::assertTrue(!empty($reorderLog));
            });

            T::test('deleteService removes service and logs audit event', function () {
                $pdo = self::createDatabase();
                $connection = new Connection();
                $connection->setMockPdo($pdo);
                $audit = new AuditLogger();
                $sanitizer = new HtmlSanitizer();
                $service = new ServiceService($connection, $audit, $sanitizer);

                $svc = $service->createService([
                    'title'       => 'Service to Delete',
                    'slug'        => 'service-to-delete',
                    'tagline'     => 'Temporary tagline',
                    'description' => 'Temporary description.',
                ], 1);

                $serviceId = (int)$svc['id'];
                $deleted = $service->deleteService($serviceId, 1);
                T::assertTrue($deleted);

                // Confirm gone from database
                $check = $pdo->query("SELECT COUNT(*) as c FROM services WHERE id = {$serviceId}")->fetch();
                T::assertEquals(0, (int)$check['c']);

                // Confirm NotFoundException on second delete
                $threwNotFound = false;
                try {
                    $service->deleteService($serviceId, 1);
                } catch (NotFoundException $e) {
                    $threwNotFound = true;
                }
                T::assertTrue($threwNotFound, 'Deleting non-existent service must throw NotFoundException.');

                // Verify audit log
                $delLog = $pdo->query("SELECT action FROM audit_logs WHERE action = 'SERVICE_DELETED'")->fetch();
                T::assertTrue(!empty($delLog));
            });

            T::test('createService rolls back database mutation if audit logging fails', function () {
                $pdo = self::createDatabase();
                $connection = new Connection();
                $connection->setMockPdo($pdo);
                $sanitizer = new HtmlSanitizer();

                // Mock AuditLogger that throws SecurityException on log()
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
                        throw new SecurityException('Simulated audit sink failure.');
                    }
                };

                $service = new ServiceService($connection, $failingAudit, $sanitizer);

                $threw = false;
                try {
                    $service->createService([
                        'title'       => 'Audit Failure Test',
                        'slug'        => 'audit-failure-test',
                        'tagline'     => 'Audit failure test tagline',
                        'description' => 'Audit failure test description',
                    ], 1);
                } catch (SecurityException $e) {
                    $threw = true;
                    T::assertContains('Simulated audit sink failure', $e->getMessage());
                }
                T::assertTrue($threw, 'Should throw when audit logging fails.');

                // Confirm service was rolled back and does not exist in DB
                $row = $pdo->query("SELECT COUNT(*) as c FROM services WHERE slug = 'audit-failure-test'")->fetch();
                T::assertEquals(0, (int)$row['c'], 'Service creation must be rolled back on audit failure.');
            });
        });
    }
}
