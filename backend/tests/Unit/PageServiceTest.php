<?php

declare(strict_types=1);

namespace Voak\Health\Tests\Unit;

use Voak\Health\Core\Exceptions\BadRequestException;
use Voak\Health\Core\Exceptions\NotFoundException;
use Voak\Health\Core\Exceptions\ValidationException;
use Voak\Health\Database\Connection;
use Voak\Health\Database\Migration\MigrationManager;
use Voak\Health\Security\AuditLogger;
use Voak\Health\Security\HtmlSanitizer;
use Voak\Health\Services\PageService;
use Voak\Health\Tests\TestFramework as T;

class PageServiceTest
{
    private static function createDatabase(): \PDO
    {
        $pdo = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ]);
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
        T::suite('Phase 7 — PageService & Content Revision Engine Unit Tests', function () {
            T::test('validateSlug accepts canonical lowercase alphanumeric with single hyphens', function () {
                $connection = new Connection();
                $audit = new AuditLogger();
                $sanitizer = new HtmlSanitizer();
                $service = new PageService($connection, $audit, $sanitizer);

                T::assertEquals('about-us', $service->validateSlug('about-us'));
                T::assertEquals('clinical-workforce-solutions', $service->validateSlug('clinical-workforce-solutions'));
                T::assertEquals('quality-compliance-2026', $service->validateSlug('quality-compliance-2026'));
                T::assertEquals('privacy-policy', $service->validateSlug('privacy-policy'));
            });

            T::test('validateSlug strictly rejects path traversal, uppercase, slashes, and illegal symbols', function () {
                $connection = new Connection();
                $audit = new AuditLogger();
                $sanitizer = new HtmlSanitizer();
                $service = new PageService($connection, $audit, $sanitizer);

                $invalidSlugs = [
                    '',
                    '   ',
                    'About-Us',
                    'about/us',
                    'about\\us',
                    '../traversal',
                    'about..us',
                    'about?param=1',
                    'about#fragment',
                    'about@us',
                    '-about',
                    'about-',
                    'about--us',
                    str_repeat('a', 101),
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

            T::test('createPage creates page and initial immutable content revision atomically', function () {
                $pdo = self::createDatabase();
                $connection = new Connection();
                $connection->setMockPdo($pdo);
                $audit = new AuditLogger();
                $sanitizer = new HtmlSanitizer();
                $service = new PageService($connection, $audit, $sanitizer);

                $page = $service->createPage([
                    'slug'             => 'about-us',
                    'title'            => 'About Voak Health Care',
                    'meta_title'       => 'About Us | Voak Health Care',
                    'meta_description' => 'Australia premier healthcare workforce partner.',
                    'content'          => '<p>Leading clinical governance provider.</p>',
                    'status'           => 'draft',
                ], 1);

                T::assertTrue($page['id'] > 0);
                T::assertEquals('about-us', $page['slug']);
                T::assertEquals('About Voak Health Care', $page['title']);
                T::assertEquals('draft', $page['status']);
                T::assertFalse($page['is_published']);

                // Verify initial revision exists
                $revisions = $service->listRevisions($page['id']);
                T::assertEquals(1, count($revisions));
                T::assertEquals(1, $revisions[0]['revision_number']);
                T::assertEquals(1, $revisions[0]['author_id']);
                T::assertEquals('Initial page creation', $revisions[0]['change_summary']);
            });

            T::test('createPage rejects duplicate active slug', function () {
                $pdo = self::createDatabase();
                $connection = new Connection();
                $connection->setMockPdo($pdo);
                $service = new PageService($connection, new AuditLogger(), new HtmlSanitizer());

                $service->createPage([
                    'slug'    => 'duplicate-test',
                    'title'   => 'First Instance',
                    'content' => 'First content',
                ], 1);

                T::expectException(BadRequestException::class, function () use ($service) {
                    $service->createPage([
                        'slug'    => 'duplicate-test',
                        'title'   => 'Second Instance',
                        'content' => 'Second content',
                    ], 1);
                }, "already exists");
            });

            T::test('updatePage sanitizes HTML, updates record, and appends immutable revision #2', function () {
                $pdo = self::createDatabase();
                $connection = new Connection();
                $connection->setMockPdo($pdo);
                $service = new PageService($connection, new AuditLogger(), new HtmlSanitizer());

                $created = $service->createPage([
                    'slug'    => 'executive-summary',
                    'title'   => 'Executive Summary',
                    'content' => '<p>Version 1 content</p>',
                ], 1);

                $updated = $service->updatePage($created['id'], [
                    'title'          => 'Executive Summary (Updated)',
                    'content'        => '<p>Version 2 with XSS attempt <script>alert("hacked")</script></p>',
                    'change_summary' => 'Added clinical governance section',
                ], 1);

                T::assertEquals('Executive Summary (Updated)', $updated['title']);
                T::assertFalse(str_contains(json_encode($updated['content_json']), '<script>'));
                T::assertContains('Version 2 with XSS attempt', json_encode($updated['content_json']));

                // Revisions must now contain #1 and #2
                $revisions = $service->listRevisions($created['id']);
                T::assertEquals(2, count($revisions));
                T::assertEquals(1, $revisions[0]['revision_number']);
                T::assertEquals(2, $revisions[1]['revision_number']);
                T::assertEquals('Added clinical governance section', $revisions[1]['change_summary']);
            });

            T::test('rollbackRevision creates revision #3 restoring content while preserving historical immutability', function () {
                $pdo = self::createDatabase();
                $connection = new Connection();
                $connection->setMockPdo($pdo);
                $service = new PageService($connection, new AuditLogger(), new HtmlSanitizer());

                $page = $service->createPage([
                    'slug'    => 'rollback-test',
                    'title'   => 'Original Good Title',
                    'content' => '<p>Good clean content</p>',
                ], 1);

                // Revision 2 with corrupted/unwanted changes
                $service->updatePage($page['id'], [
                    'title'          => 'Unwanted Accidental Title',
                    'content'        => '<p>Unwanted content</p>',
                    'change_summary' => 'Unwanted edit',
                ], 1);

                // Rollback to Revision 1
                $restored = $service->rollbackRevision($page['id'], 1, 1);

                T::assertEquals('Original Good Title', $restored['title']);

                // Revision history must now have 3 revisions
                $revisions = $service->listRevisions($page['id']);
                T::assertEquals(3, count($revisions));
                T::assertEquals(1, $revisions[0]['revision_number']);
                T::assertEquals(2, $revisions[1]['revision_number']);
                T::assertEquals(3, $revisions[2]['revision_number']);
                T::assertContains('Rollback to revision #1', $revisions[2]['change_summary']);
            });

            T::test('updatePageStatus transitions publishing state and sets publication timestamp', function () {
                $pdo = self::createDatabase();
                $connection = new Connection();
                $connection->setMockPdo($pdo);
                $service = new PageService($connection, new AuditLogger(), new HtmlSanitizer());

                $page = $service->createPage([
                    'slug'    => 'workflow-page',
                    'title'   => 'Workflow Page',
                    'content' => '<p>Workflow testing</p>',
                    'status'  => 'draft',
                ], 1);

                T::assertEquals('draft', $page['status']);
                T::assertFalse($page['is_published']);
                T::assertTrue($page['published_at'] === null);

                // Transition to published
                $published = $service->updatePageStatus($page['id'], 'published', 1);
                T::assertEquals('published', $published['status']);
                T::assertTrue($published['is_published']);
                T::assertTrue($published['published_at'] !== null);

                // Transition to archived
                $archived = $service->updatePageStatus($page['id'], 'archived', 1);
                T::assertEquals('archived', $archived['status']);
                T::assertFalse($archived['is_published']);
            });

            T::test('softDeletePage sets deleted_at and unpublishes page safely', function () {
                $pdo = self::createDatabase();
                $connection = new Connection();
                $connection->setMockPdo($pdo);
                $service = new PageService($connection, new AuditLogger(), new HtmlSanitizer());

                $page = $service->createPage([
                    'slug'    => 'delete-target',
                    'title'   => 'To Be Deleted',
                    'content' => 'Content',
                    'status'  => 'published',
                ], 1);

                $service->softDeletePage($page['id'], 1);

                // Public search must return null
                T::assertTrue($service->getPublishedPageBySlug('delete-target') === null);

                // Admin retrieval with includeDeleted=true should show soft-deleted status
                $adminPage = $service->getPageById($page['id'], true);
                T::assertTrue($adminPage !== null);
                T::assertTrue(!empty($adminPage['deleted_at']));
                T::assertFalse($adminPage['is_published']);
            });

            T::test('getPublishedPageBySlug conceals drafts, archived, and deleted pages from public', function () {
                $pdo = self::createDatabase();
                $connection = new Connection();
                $connection->setMockPdo($pdo);
                $service = new PageService($connection, new AuditLogger(), new HtmlSanitizer());

                // 1. Draft page
                $service->createPage([
                    'slug'    => 'draft-secret',
                    'title'   => 'Draft Page',
                    'content' => 'Top secret internal policy',
                    'status'  => 'draft',
                ], 1);
                T::assertTrue($service->getPublishedPageBySlug('draft-secret') === null);

                // 2. Published page
                $pub = $service->createPage([
                    'slug'    => 'public-service',
                    'title'   => 'Public Page',
                    'content' => 'Public information',
                    'status'  => 'published',
                ], 1);
                $fetched = $service->getPublishedPageBySlug('public-service');
                T::assertTrue($fetched !== null);
                T::assertEquals('Public Page', $fetched['title']);

                // 3. Archive the published page
                $service->updatePageStatus($pub['id'], 'archived', 1);
                T::assertTrue($service->getPublishedPageBySlug('public-service') === null);
            });

            T::test('calculateEtag generates deterministic hash and updates on content mutation', function () {
                $connection = new Connection();
                $service = new PageService($connection, new AuditLogger(), new HtmlSanitizer());

                $page1 = [
                    'id' => 10,
                    'slug' => 'about-us',
                    'title' => 'About Us',
                    'updated_at' => '2026-10-22 10:00:00',
                    'content_json' => ['body' => 'Original text'],
                ];

                $etag1 = $service->calculateEtag($page1);
                $etag2 = $service->calculateEtag($page1);
                T::assertEquals($etag1, $etag2);

                $page2 = $page1;
                $page2['updated_at'] = '2026-10-22 10:05:00';
                $page2['content_json'] = ['body' => 'Mutated text'];

                $etag3 = $service->calculateEtag($page2);
                T::assertFalse($etag1 === $etag3);
            });
        });
    }
}
