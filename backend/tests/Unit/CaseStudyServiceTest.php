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
use Voak\Health\Services\CaseStudyService;
use Voak\Health\Tests\TestFramework as T;

/**
 * Unit Test Suite for CaseStudyService (Module 15 / ADM-015 / FR-ADM-016 / TEST-ADM-016 / TEST-PUB-013).
 */
class CaseStudyServiceTest
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
        T::suite('Phase 15 — CaseStudyService Unit Tests', function () {

            T::test('createCaseStudy strictly blocks publication without verified consent release (TEST-ADM-016 / FR-ADM-016)', function () {
                $pdo = self::createDatabase();
                $connection = new Connection();
                $connection->setMockPdo($pdo);
                $audit = new AuditLogger();
                $sanitizer = new HtmlSanitizer();
                $service = new CaseStudyService($connection, $audit, $sanitizer);

                // Attempt to publish with consent_verified = 0
                $threw = false;
                try {
                    $service->createCaseStudy([
                        'title'                => 'Major Tertiary Hospital ICU Surge Deployment',
                        'client_sector'        => 'Hospitals',
                        'challenge_summary'    => 'Acute nurse shortages during winter peak demand.',
                        'intervention_summary' => 'Rapid deployment of 40 specialized critical care clinicians within 72 hours.',
                        'outcome_metrics'      => [
                            ['metric' => '99.4%', 'label' => 'Shift Coverage'],
                            ['metric' => '0', 'label' => 'Critical Incidents'],
                        ],
                        'consent_verified'     => 0,
                        'is_published'         => 1, // PUBLISH ATTEMPT WITHOUT CONSENT
                    ], 2);
                } catch (ValidationException $e) {
                    $threw = true;
                    T::assertTrue(isset($e->getErrors()['consent_verified']));
                    T::assertTrue(str_contains($e->getErrors()['consent_verified'], 'verified client legal consent release is required'));
                }

                T::assertTrue($threw, 'Publication MUST be blocked when consent_verified is 0.');

                // Verify zero records were created
                $count = (int)$pdo->query("SELECT COUNT(*) FROM case_studies")->fetchColumn();
                T::assertEquals(0, $count);
            });

            T::test('createCaseStudy saves draft when consent_verified is 0 and is_published is 0', function () {
                $pdo = self::createDatabase();
                $connection = new Connection();
                $connection->setMockPdo($pdo);
                $audit = new AuditLogger();
                $sanitizer = new HtmlSanitizer();
                $service = new CaseStudyService($connection, $audit, $sanitizer);

                $case = $service->createCaseStudy([
                    'title'                => 'Regional Aged Care Staffing Stabilization',
                    'client_sector'        => 'Aged Care',
                    'challenge_summary'    => 'High turnover and roster churn across 3 regional care facilities.',
                    'intervention_summary' => 'Placement of dedicated residential clinical coordinators and compliance training.',
                    'outcome_metrics'      => [
                        ['metric' => '42%', 'label' => 'Reduction in Agency Reliance'],
                    ],
                    'consent_verified'     => 0,
                    'is_published'         => 0,
                ], 2);

                T::assertEquals('regional-aged-care-staffing-stabilization', $case['slug']);
                T::assertEquals(0, $case['consent_verified']);
                T::assertEquals(0, $case['is_published']);
                T::assertNull($case['published_at']);
                T::assertEquals(1, count($case['outcome_metrics']));
            });

            T::test('updateCaseStudy enforces consent release gate on publication transition (TEST-ADM-016)', function () {
                $pdo = self::createDatabase();
                $connection = new Connection();
                $connection->setMockPdo($pdo);
                $audit = new AuditLogger();
                $sanitizer = new HtmlSanitizer();
                $service = new CaseStudyService($connection, $audit, $sanitizer);

                // Create draft
                $case = $service->createCaseStudy([
                    'title'                => 'Sub-Acute Rehabilitation Roster Transformation',
                    'client_sector'        => 'Sub-Acute',
                    'challenge_summary'    => 'Rostering inefficiencies leading to high clinician fatigue.',
                    'intervention_summary' => 'Digitized workforce scheduling with VOAK workforce management platform.',
                    'consent_verified'     => 0,
                    'is_published'         => 0,
                ], 2);

                // Attempt update to is_published = 1 without consent
                $threw = false;
                try {
                    $service->updateCaseStudy($case['id'], [
                        'is_published' => 1,
                    ], 2);
                } catch (ValidationException $e) {
                    $threw = true;
                    T::assertTrue(isset($e->getErrors()['consent_verified']));
                }
                T::assertTrue($threw, 'Updating to is_published=1 without consent must fail.');

                // Now provide consent_verified = 1 and publish
                $published = $service->updateCaseStudy($case['id'], [
                    'consent_verified' => 1,
                    'is_published'     => 1,
                ], 2);

                T::assertEquals(1, $published['consent_verified']);
                T::assertEquals(1, $published['is_published']);
                T::assertNotEmpty($published['published_at']);

                // Verify audit log
                $logs = $pdo->query("SELECT * FROM audit_logs WHERE entity_type = 'case_studies' AND entity_id = {$case['id']}")->fetchAll();
                $actions = array_column($logs, 'action');
                T::assertTrue(in_array('CASE_STUDY_PUBLISHED', $actions, true));
            });

            T::test('public case studies directory strictly hides unverified and unpublished records (TEST-PUB-013)', function () {
                $pdo = self::createDatabase();
                $connection = new Connection();
                $connection->setMockPdo($pdo);
                $audit = new AuditLogger();
                $sanitizer = new HtmlSanitizer();
                $service = new CaseStudyService($connection, $audit, $sanitizer);

                // Case 1: Verified and published
                $c1 = $service->createCaseStudy([
                    'title'                => 'Verified Hospital Transformation',
                    'client_sector'        => 'Hospitals',
                    'challenge_summary'    => 'Challenge 1',
                    'intervention_summary' => 'Intervention 1',
                    'consent_verified'     => 1,
                    'is_published'         => 1,
                ], 1);

                // Case 2: Draft (consent_verified = 0, is_published = 0)
                $service->createCaseStudy([
                    'title'                => 'Draft Aged Care Proof',
                    'client_sector'        => 'Aged Care',
                    'challenge_summary'    => 'Challenge 2',
                    'intervention_summary' => 'Intervention 2',
                    'consent_verified'     => 0,
                    'is_published'         => 0,
                ], 1);

                // Case 3: Verified but unpublished draft (consent_verified = 1, is_published = 0)
                $c3 = $service->createCaseStudy([
                    'title'                => 'Verified Unpublished Case',
                    'client_sector'        => 'Hospitals',
                    'challenge_summary'    => 'Challenge 3',
                    'intervention_summary' => 'Intervention 3',
                    'consent_verified'     => 1,
                    'is_published'         => 0,
                ], 1);

                // Public feed should only return Case 1
                $public = $service->getVerifiedPublishedCaseStudies();
                T::assertEquals(1, $public['meta']['total']);
                T::assertEquals($c1['slug'], $public['case_studies'][0]['slug']);

                // Fetching Case 1 by slug succeeds
                $detail = $service->getVerifiedPublishedBySlug($c1['slug']);
                T::assertEquals($c1['slug'], $detail['case_study']['slug']);

                // Fetching Case 3 (unpublished) throws 404
                $threw404 = false;
                try {
                    $service->getVerifiedPublishedBySlug($c3['slug']);
                } catch (NotFoundException $e) {
                    $threw404 = true;
                }
                T::assertTrue($threw404, 'Unpublished case study must throw 404');

                // Unpublish Case 1 and verify public feed collapses to 0 immediately (TEST-ADM-016)
                $service->updateCaseStudy($c1['id'], ['is_published' => 0], 1);
                $publicAfter = $service->getVerifiedPublishedCaseStudies();
                T::assertEquals(0, $publicAfter['meta']['total']);
            });

            T::test('exportCsv applies CSV formula injection defense', function () {
                $pdo = self::createDatabase();
                $connection = new Connection();
                $connection->setMockPdo($pdo);
                $audit = new AuditLogger();
                $sanitizer = new HtmlSanitizer();
                $service = new CaseStudyService($connection, $audit, $sanitizer);

                $service->createCaseStudy([
                    'title'                => '@SUM(1+1)*cmd',
                    'slug'                 => 'formula-injection-case',
                    'client_sector'        => 'Hospitals',
                    'challenge_summary'    => 'Challenge',
                    'intervention_summary' => 'Intervention',
                    'consent_verified'     => 0,
                    'is_published'         => 0,
                ], 1);

                $csv = $service->exportCsv();
                T::assertTrue(str_contains($csv, "'@SUM"), "CSV formula trigger '@' should be prefixed with single quote.");
            });
        });
    }
}
