<?php

declare(strict_types=1);

namespace Voak\Health\Tests\Unit;

use Voak\Health\Auth\RbacManager;
use Voak\Health\Core\Exceptions\ConflictException;
use Voak\Health\Core\Exceptions\NotFoundException;
use Voak\Health\Core\Exceptions\ValidationException;
use Voak\Health\Database\Connection;
use Voak\Health\Database\Migration\MigrationManager;
use Voak\Health\Security\AuditLogger;
use Voak\Health\Security\HtmlSanitizer;
use Voak\Health\Services\ArticleService;
use Voak\Health\Tests\TestFramework as T;

/**
 * Unit Test Suite for ArticleService (Module 14 / ADM-016 / FR-ADM-017 / TEST-ADM-017 / TEST-PUB-016 / TEST-PUB-017).
 */
class ArticleServiceTest
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
        $pdo->exec("INSERT INTO users (id, name, email, password_hash, is_active, created_at, updated_at) 
                    VALUES (2, 'Content Editor', 'editor@voak.test', 'hash', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");

        $rbac = new RbacManager();
        $rbac->seedRbac($pdo);
        $rbac->assignRole($pdo, 1, RbacManager::ROLE_SUPER_ADMIN);
        $rbac->assignRole($pdo, 2, RbacManager::ROLE_CONTENT_EDITOR);

        return $pdo;
    }

    public static function run(): void
    {
        T::suite('Phase 15 — ArticleService Unit Tests', function () {

            T::test('calculateReadingTime calculates reading time at 200 wpm with minimum 1 minute (TEST-ADM-017)', function () {
                // Empty content
                T::assertEquals(1, ArticleService::calculateReadingTime(''));
                T::assertEquals(1, ArticleService::calculateReadingTime('   '));

                // Short content (50 words) -> 1 min
                $short = implode(' ', array_fill(0, 50, 'word'));
                T::assertEquals(1, ArticleService::calculateReadingTime($short));

                // 200 words -> 1 min
                $words200 = implode(' ', array_fill(0, 200, 'word'));
                T::assertEquals(1, ArticleService::calculateReadingTime($words200));

                // 201 words -> 2 min
                $words201 = implode(' ', array_fill(0, 201, 'word'));
                T::assertEquals(2, ArticleService::calculateReadingTime($words201));

                // 1000 words -> 5 min (Canonical TEST-ADM-017 requirement)
                $words1000 = implode(' ', array_fill(0, 1000, 'clinical'));
                T::assertEquals(5, ArticleService::calculateReadingTime($words1000));

                // HTML content with tags stripped
                $html1000 = '<p>' . implode('</p><p>', array_fill(0, 1000, 'governance')) . '</p>';
                T::assertEquals(5, ArticleService::calculateReadingTime($html1000));
            });

            T::test('normalizeSlug and generateSlugFromTitle generate valid kebab-case and handle collisions', function () {
                $pdo = self::createDatabase();
                $connection = new Connection();
                $connection->setMockPdo($pdo);
                $audit = new AuditLogger();
                $sanitizer = new HtmlSanitizer();
                $service = new ArticleService($connection, $audit, $sanitizer);

                // Auto-generate from title
                $slug1 = $service->generateSlugFromTitle('Navigating Healthcare Staffing Regulations 2026!');
                T::assertEquals('navigating-healthcare-staffing-regulations-2026', $slug1);

                // Create article with slug1
                $service->createArticle([
                    'title'        => 'Navigating Healthcare Staffing Regulations 2026',
                    'slug'         => $slug1,
                    'summary'      => 'A comprehensive review of healthcare staffing standards.',
                    'content_html' => '<p>Staffing regulations in Australian healthcare.</p>',
                    'category'     => 'Workforce Strategy',
                ], 1);

                // Second creation with same title resolves collision by appending suffix
                $slug2 = $service->generateSlugFromTitle('Navigating Healthcare Staffing Regulations 2026');
                T::assertEquals('navigating-healthcare-staffing-regulations-2026-2', $slug2);
            });

            T::test('createArticle validates required fields and stores tags as JSON', function () {
                $pdo = self::createDatabase();
                $connection = new Connection();
                $connection->setMockPdo($pdo);
                $audit = new AuditLogger();
                $sanitizer = new HtmlSanitizer();
                $service = new ArticleService($connection, $audit, $sanitizer);

                // Validation failure on empty title
                $threw = false;
                try {
                    $service->createArticle([
                        'title'        => '',
                        'summary'      => 'Summary',
                        'content_html' => 'Content',
                    ], 1);
                } catch (ValidationException $e) {
                    $threw = true;
                    T::assertTrue(isset($e->getErrors()['title']));
                }
                T::assertTrue($threw, 'Should fail when title is empty');

                // Valid creation with tags array
                $article = $service->createArticle([
                    'title'        => 'Clinical Governance in Regional Health',
                    'summary'      => 'Summary of regional clinical governance',
                    'content_html' => '<p>Regional clinical governance details.</p><script>alert("xss")</script>',
                    'category'     => 'Clinical Governance',
                    'tags'         => ['Governance', 'Workforce', 'Compliance'],
                    'is_published' => 1,
                ], 2);

                T::assertEquals('clinical-governance-in-regional-health', $article['slug']);
                T::assertEquals(1, $article['is_published']);
                T::assertNotEmpty($article['published_at']);
                T::assertEquals(['Governance', 'Workforce', 'Compliance'], $article['tags']);
                // XSS stripped
                T::assertFalse(str_contains($article['content_html'], '<script>'));
                T::assertTrue(str_contains($article['content_html'], '<p>Regional clinical governance details.</p>'));
            });

            T::test('updateArticle recalculates reading time and emits audit logs', function () {
                $pdo = self::createDatabase();
                $connection = new Connection();
                $connection->setMockPdo($pdo);
                $audit = new AuditLogger();
                $sanitizer = new HtmlSanitizer();
                $service = new ArticleService($connection, $audit, $sanitizer);

                $created = $service->createArticle([
                    'title'        => 'AI in Healthcare Operations',
                    'summary'      => 'Summary of AI in operations',
                    'content_html' => 'Short content here.',
                    'category'     => 'Technology',
                    'is_published' => 0,
                ], 1);

                T::assertEquals(1, $created['reading_time_minutes']);
                T::assertEquals(0, $created['is_published']);

                // Update with 600 words and publish
                $body600 = implode(' ', array_fill(0, 600, 'automation'));
                $updated = $service->updateArticle($created['id'], [
                    'content_html' => '<p>' . $body600 . '</p>',
                    'is_published' => 1,
                ], 2);

                T::assertEquals(3, $updated['reading_time_minutes']);
                T::assertEquals(1, $updated['is_published']);
                T::assertNotEmpty($updated['published_at']);

                // Verify audit logs
                $logs = $pdo->query("SELECT * FROM audit_logs WHERE entity_type = 'articles' AND entity_id = {$created['id']}")->fetchAll();
                T::assertTrue(count($logs) >= 2);
                $actions = array_column($logs, 'action');
                T::assertTrue(in_array('ARTICLE_CREATED', $actions, true));
                T::assertTrue(in_array('ARTICLE_PUBLISHED', $actions, true));
            });

            T::test('public article retrieval strictly hides drafts and returns related articles (TEST-PUB-016 / TEST-PUB-017)', function () {
                $pdo = self::createDatabase();
                $connection = new Connection();
                $connection->setMockPdo($pdo);
                $audit = new AuditLogger();
                $sanitizer = new HtmlSanitizer();
                $service = new ArticleService($connection, $audit, $sanitizer);

                // Create 1 draft and 4 published articles in Technology category
                $service->createArticle([
                    'title'        => 'Draft Article Hidden',
                    'slug'         => 'draft-article-hidden',
                    'summary'      => 'Draft summary',
                    'content_html' => 'Draft body',
                    'category'     => 'Technology',
                    'is_published' => 0,
                ], 1);

                for ($i = 1; $i <= 4; $i++) {
                    $service->createArticle([
                        'title'        => "Published Tech Article {$i}",
                        'slug'         => "published-tech-article-{$i}",
                        'summary'      => "Summary for tech article {$i}",
                        'content_html' => "<p>Body content for article {$i}</p>",
                        'category'     => 'Technology',
                        'is_published' => 1,
                    ], 1);
                }

                // Public feed should contain 4, not 5
                $publicFeed = $service->getPublishedArticles(['category' => 'Technology']);
                T::assertEquals(4, $publicFeed['meta']['total']);
                T::assertNotEmpty($publicFeed['etag']);

                // Public slug query for draft must throw NotFoundException
                $threw404 = false;
                try {
                    $service->getPublishedBySlug('draft-article-hidden');
                } catch (NotFoundException $e) {
                    $threw404 = true;
                }
                T::assertTrue($threw404, 'Draft article should return 404 for public');

                // Public slug query for published article returns 3 related articles
                $detail = $service->getPublishedBySlug('published-tech-article-1');
                T::assertEquals('published-tech-article-1', $detail['article']['slug']);
                T::assertEquals(3, count($detail['article']['related_articles']));
            });

            T::test('exportCsv applies formula injection defense', function () {
                $pdo = self::createDatabase();
                $connection = new Connection();
                $connection->setMockPdo($pdo);
                $audit = new AuditLogger();
                $sanitizer = new HtmlSanitizer();
                $service = new ArticleService($connection, $audit, $sanitizer);

                $service->createArticle([
                    'title'        => '=cmd|"/C calc"!A0',
                    'slug'         => 'formula-injection-title',
                    'summary'      => 'Formula test',
                    'content_html' => 'Content',
                    'category'     => 'Security',
                ], 1);

                $csv = $service->exportCsv();
                T::assertTrue(str_contains($csv, "'=cmd|"), "Dangerous formula trigger should be prefixed with single quote.");
            });
        });
    }
}
