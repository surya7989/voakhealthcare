<?php

declare(strict_types=1);

namespace Voak\Health\Tests\Unit;

use Voak\Health\Auth\RbacManager;
use Voak\Health\Core\Exceptions\NotFoundException;
use Voak\Health\Core\Exceptions\ValidationException;
use Voak\Health\Database\Connection;
use Voak\Health\Database\Migration\MigrationManager;
use Voak\Health\Security\AuditLogger;
use Voak\Health\Security\HtmlSanitizer;
use Voak\Health\Services\FaqService;
use Voak\Health\Tests\TestFramework as T;

/**
 * Unit Test Suite for FaqService (Module 16 / FR-PUB-018 / TEST-PUB-018).
 */
class FaqServiceTest
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
        T::suite('Phase 15 — FaqService Unit Tests', function () {

            T::test('createFaq validates required fields and sanitizes answer HTML', function () {
                $pdo = self::createDatabase();
                $connection = new Connection();
                $connection->setMockPdo($pdo);
                $audit = new AuditLogger();
                $sanitizer = new HtmlSanitizer();
                $service = new FaqService($connection, $audit, $sanitizer);

                // Validation failure on empty question
                $threw = false;
                try {
                    $service->createFaq([
                        'question'    => '',
                        'category'    => 'Procurement',
                        'answer_html' => 'Answer',
                    ], 2);
                } catch (ValidationException $e) {
                    $threw = true;
                    T::assertTrue(isset($e->getErrors()['question']));
                }
                T::assertTrue($threw, 'Should fail when question is empty');

                // Valid creation with XSS payload in answer
                $faq = $service->createFaq([
                    'category'    => 'Procurement',
                    'question'    => 'What are VOAK service level agreements (SLAs)?',
                    'answer_html' => '<p>Our SLAs guarantee 98% shift fill rate within 2 hours.</p><script>alert("xss")</script>',
                    'is_published' => 1,
                ], 2);

                T::assertEquals('Procurement', $faq['category']);
                T::assertEquals('What are VOAK service level agreements (SLAs)?', $faq['question']);
                T::assertFalse(str_contains($faq['answer_html'], '<script>'));
                T::assertTrue(str_contains($faq['answer_html'], 'Our SLAs guarantee 98% shift fill rate'));
                T::assertEquals(1, $faq['display_order']);
                T::assertEquals(1, $faq['is_published']);

                // Verify audit log
                $logs = $pdo->query("SELECT * FROM audit_logs WHERE entity_type = 'faqs' AND entity_id = {$faq['id']}")->fetchAll();
                T::assertEquals(1, count($logs));
                T::assertEquals('FAQ_CREATED', $logs[0]['action']);
            });

            T::test('updateFaq and deleteFaq modify records and emit audit logs', function () {
                $pdo = self::createDatabase();
                $connection = new Connection();
                $connection->setMockPdo($pdo);
                $audit = new AuditLogger();
                $sanitizer = new HtmlSanitizer();
                $service = new FaqService($connection, $audit, $sanitizer);

                $faq = $service->createFaq([
                    'category'    => 'Compliance',
                    'question'    => 'Are all clinicians credentialed under AHPRA?',
                    'answer_html' => '<p>Yes, 100% of clinicians undergo primary source verification.</p>',
                ], 1);

                $updated = $service->updateFaq($faq['id'], [
                    'question' => 'Are all clinicians verified through AHPRA primary source registries?',
                ], 2);

                T::assertEquals('Are all clinicians verified through AHPRA primary source registries?', $updated['question']);

                // Delete
                $service->deleteFaq($faq['id'], 1);
                $deletedCheck = (int)$pdo->query("SELECT COUNT(*) FROM faqs WHERE id = {$faq['id']}")->fetchColumn();
                T::assertEquals(0, $deletedCheck);

                // Audit events
                $logs = $pdo->query("SELECT action FROM audit_logs WHERE entity_type = 'faqs' AND entity_id = {$faq['id']}")->fetchAll(\PDO::FETCH_COLUMN);
                T::assertTrue(in_array('FAQ_CREATED', $logs, true));
                T::assertTrue(in_array('FAQ_UPDATED', $logs, true));
                T::assertTrue(in_array('FAQ_DELETED', $logs, true));
            });

            T::test('reorderFaqs sequentially updates display orders', function () {
                $pdo = self::createDatabase();
                $connection = new Connection();
                $connection->setMockPdo($pdo);
                $audit = new AuditLogger();
                $sanitizer = new HtmlSanitizer();
                $service = new FaqService($connection, $audit, $sanitizer);

                $f1 = $service->createFaq(['category' => 'Operations', 'question' => 'Q1', 'answer_html' => 'A1'], 1);
                $f2 = $service->createFaq(['category' => 'Operations', 'question' => 'Q2', 'answer_html' => 'A2'], 1);
                $f3 = $service->createFaq(['category' => 'Operations', 'question' => 'Q3', 'answer_html' => 'A3'], 1);

                // Reorder to 3, 1, 2
                $service->reorderFaqs([$f3['id'], $f1['id'], $f2['id']], 1);

                T::assertEquals(1, $service->getFaqById($f3['id'])['display_order']);
                T::assertEquals(2, $service->getFaqById($f1['id'])['display_order']);
                T::assertEquals(3, $service->getFaqById($f2['id'])['display_order']);
            });

            T::test('getPublishedFaqs returns categorized accordions and filters by search (TEST-PUB-018)', function () {
                $pdo = self::createDatabase();
                $connection = new Connection();
                $connection->setMockPdo($pdo);
                $audit = new AuditLogger();
                $sanitizer = new HtmlSanitizer();
                $service = new FaqService($connection, $audit, $sanitizer);

                // Seed Procurement, Operations, and Compliance FAQs
                $service->createFaq(['category' => 'Procurement', 'question' => 'How does pricing work?', 'answer_html' => '<p>Transparent tiered pricing.</p>', 'is_published' => 1], 1);
                $service->createFaq(['category' => 'Procurement', 'question' => 'What SLA guarantees are provided?', 'answer_html' => '<p>Standard SLA terms.</p>', 'is_published' => 1], 1);
                $service->createFaq(['category' => 'Operations', 'question' => 'How are shifts scheduled?', 'answer_html' => '<p>Automated roster integration.</p>', 'is_published' => 1], 1);
                $service->createFaq(['category' => 'Compliance', 'question' => 'What is the accreditation standard?', 'answer_html' => '<p>NSQHS Standards 1-8.</p>', 'is_published' => 1], 1);
                // Draft FAQ
                $service->createFaq(['category' => 'Compliance', 'question' => 'Draft unapproved question?', 'answer_html' => '<p>Draft answer.</p>', 'is_published' => 0], 1);

                $public = $service->getPublishedFaqs();
                T::assertEquals(4, $public['total']);
                T::assertTrue(isset($public['categories']['Procurement']));
                T::assertTrue(isset($public['categories']['Operations']));
                T::assertTrue(isset($public['categories']['Compliance']));
                T::assertEquals(2, count($public['categories']['Procurement']));
                T::assertEquals(1, count($public['categories']['Operations']));
                T::assertEquals(1, count($public['categories']['Compliance']));
                T::assertNotEmpty($public['etag']);

                // Filter by search "SLA" (Canonical TEST-PUB-018 requirement)
                $filtered = $service->getPublishedFaqs(null, 'SLA');
                T::assertEquals(1, $filtered['total']);
                T::assertEquals('What SLA guarantees are provided?', $filtered['faqs'][0]['question']);
            });
        });
    }
}
