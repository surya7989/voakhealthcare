<?php

declare(strict_types=1);

namespace Voak\Health\Tests\Unit;

use Voak\Health\Auth\RbacManager;
use Voak\Health\Core\Exceptions\NotFoundException;
use Voak\Health\Core\Exceptions\ValidationException;
use Voak\Health\Database\Connection;
use Voak\Health\Database\Migration\MigrationManager;
use Voak\Health\Security\AuditLogger;
use Voak\Health\Services\SeoService;
use Voak\Health\Tests\TestFramework as T;

/**
 * Unit Test Suite for SeoService (Module 17 / ADM-017 / ADM-018 / FR-ADM-018 / FR-WF-006 / FR-SEO-001..004).
 */
class SeoServiceTest
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
                    VALUES (2, 'Website Admin', 'webadmin@voak.test', 'hash', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");

        $rbac = new RbacManager();
        $rbac->seedRbac($pdo);
        $rbac->assignRole($pdo, 1, RbacManager::ROLE_SUPER_ADMIN);
        $rbac->assignRole($pdo, 2, RbacManager::ROLE_WEBSITE_ADMIN);

        return $pdo;
    }

    public static function run(): void
    {
        T::suite('Phase 16 — SeoService Unit Tests', function () {

            T::test('normalizePath cleans and standardizes paths correctly', function () {
                T::assertEquals('/', SeoService::normalizePath(''));
                T::assertEquals('/', SeoService::normalizePath('/'));
                T::assertEquals('/', SeoService::normalizePath('///'));
                T::assertEquals('/services/clinical', SeoService::normalizePath('services/clinical'));
                T::assertEquals('/services/clinical', SeoService::normalizePath('/services/clinical/'));
                T::assertEquals('/services/clinical', SeoService::normalizePath('/SERVICES/CLINICAL/'));
                T::assertEquals('/services/clinical', SeoService::normalizePath('//services//clinical//'));
                T::assertEquals('/services/clinical', SeoService::normalizePath('\\services\\clinical\\'));
            });

            T::test('registerRedirect and resolveRedirect manage 301 rules atomically (FR-WF-006 / TEST-WF-006)', function () {
                $pdo = self::createDatabase();
                $conn = new Connection();
                $conn->setMockPdo($pdo);
                $audit = new AuditLogger();
                $rbac = new RbacManager();

                $service = new SeoService($conn, $audit, $rbac);

                // Register redirect
                $res = $service->registerRedirect('/services/old-staffing', '/services/new-staffing', 301, 1);
                T::assertEquals('/services/old-staffing', $res['source_path']);
                T::assertEquals('/services/new-staffing', $res['target_path']);
                T::assertEquals(301, $res['http_status_code']);

                // Resolve redirect
                $resolved = $service->resolveRedirect('/services/old-staffing');
                T::assertTrue($resolved !== null);
                T::assertEquals('/services/new-staffing', $resolved['target_path']);
                T::assertEquals(301, $resolved['http_status_code']);

                // Verify hit count incremented
                $resolvedAgain = $service->resolveRedirect('/services/old-staffing/');
                T::assertTrue($resolvedAgain !== null);
                $data = $service->getRedirect($res['id']);
                T::assertEquals(2, $data['hit_count']);
                T::assertTrue($data['last_accessed_at'] !== null);

                // Non-existent route returns null
                T::assertNull($service->resolveRedirect('/services/non-existent-redirect'));
            });

            T::test('registerRedirect rejects self-redirects and reserved routes (FR-WF-006 / TEST-WF-006)', function () {
                $pdo = self::createDatabase();
                $conn = new Connection();
                $conn->setMockPdo($pdo);
                $service = new SeoService($conn, new AuditLogger());

                // Self-redirect
                $selfRedirectError = false;
                try {
                    $service->registerRedirect('/about', '/about', 301, 1);
                } catch (ValidationException $e) {
                    $selfRedirectError = true;
                    T::assertTrue(isset($e->getErrors()['target_path']));
                }
                T::assertTrue($selfRedirectError, 'Expected ValidationException for self-redirect');

                // Reserved routes as source
                $reservedSourceError = false;
                try {
                    $service->registerRedirect('/admin', '/somewhere', 301, 1);
                } catch (ValidationException $e) {
                    $reservedSourceError = true;
                    T::assertTrue(isset($e->getErrors()['source_path']));
                }
                T::assertTrue($reservedSourceError, 'Expected ValidationException for reserved /admin source');

                // Reserved /api route as source
                $apiSourceError = false;
                try {
                    $service->registerRedirect('/api/v1/health', '/somewhere', 301, 1);
                } catch (ValidationException $e) {
                    $apiSourceError = true;
                    T::assertTrue(isset($e->getErrors()['source_path']));
                }
                T::assertTrue($apiSourceError, 'Expected ValidationException for /api source');

                // Internal API as target
                $apiTargetError = false;
                try {
                    $service->registerRedirect('/some-page', '/api/v1/private', 301, 1);
                } catch (ValidationException $e) {
                    $apiTargetError = true;
                    T::assertTrue(isset($e->getErrors()['target_path']));
                }
                T::assertTrue($apiTargetError, 'Expected ValidationException for internal API target');
            });

            T::test('registerRedirect detects and rejects direct circular redirect loops A -> B -> A (TEST-WF-006)', function () {
                $pdo = self::createDatabase();
                $conn = new Connection();
                $conn->setMockPdo($pdo);
                $service = new SeoService($conn, new AuditLogger());

                // Step 1: Register A -> B
                $service->registerRedirect('/services/slug-alpha', '/services/slug-beta', 301, 1);

                // Step 2: Attempt registering B -> A (creates direct circular loop)
                $loopDetected = false;
                try {
                    $service->registerRedirect('/services/slug-beta', '/services/slug-alpha', 301, 1);
                } catch (ValidationException $e) {
                    $loopDetected = true;
                    T::assertTrue(isset($e->getErrors()['target_path']));
                }
                T::assertTrue($loopDetected, 'Expected ValidationException detecting circular redirect loop');
            });

            T::test('registerRedirect detects deep multi-hop circular loops A -> B -> C -> A (TEST-WF-006)', function () {
                $pdo = self::createDatabase();
                $conn = new Connection();
                $conn->setMockPdo($pdo);
                $service = new SeoService($conn, new AuditLogger());

                // Register A -> B
                $service->registerRedirect('/slug-a', '/slug-b', 301, 1);
                // Register B -> C (A is automatically flattened to C)
                $service->registerRedirect('/slug-b', '/slug-c', 301, 1);

                // Attempt C -> A (would complete circular loop)
                $loopDetected = false;
                try {
                    $service->registerRedirect('/slug-c', '/slug-a', 301, 1);
                } catch (ValidationException $e) {
                    $loopDetected = true;
                }
                T::assertTrue($loopDetected, 'Expected ValidationException detecting multi-hop circular loop');
            });

            T::test('registerRedirect flattens redirect chains to 1 hop (FR-WF-006 / TEST-WF-006)', function () {
                $pdo = self::createDatabase();
                $conn = new Connection();
                $conn->setMockPdo($pdo);
                $service = new SeoService($conn, new AuditLogger());

                // Step 1: Register slug-alpha -> slug-beta
                $service->registerRedirect('/services/slug-alpha', '/services/slug-beta', 301, 1);
                T::assertEquals('/services/slug-beta', $service->resolveRedirect('/services/slug-alpha')['target_path']);

                // Step 2: Register slug-beta -> slug-gamma
                $service->registerRedirect('/services/slug-beta', '/services/slug-gamma', 301, 1);

                // Step 3: Assert slug-alpha resolves DIRECTLY to slug-gamma (1 hop, flattened!)
                $alphaResolved = $service->resolveRedirect('/services/slug-alpha');
                T::assertEquals('/services/slug-gamma', $alphaResolved['target_path'], 'Expected chain to flatten: alpha must point directly to gamma');

                // Step 4: Register slug-gamma -> slug-delta
                $service->registerRedirect('/services/slug-gamma', '/services/slug-delta', 301, 1);

                // Step 5: Assert both slug-alpha and slug-beta now point directly to slug-delta
                T::assertEquals('/services/slug-delta', $service->resolveRedirect('/services/slug-alpha')['target_path']);
                T::assertEquals('/services/slug-delta', $service->resolveRedirect('/services/slug-beta')['target_path']);
            });

            T::test('generateSitemapXml builds valid W3C XML and excludes drafts (FR-SEO-003 / TEST-SEO-003)', function () {
                $pdo = self::createDatabase();
                $conn = new Connection();
                $conn->setMockPdo($pdo);
                $service = new SeoService($conn, new AuditLogger());

                // Insert published page
                $pdo->exec("INSERT INTO pages (slug, title, meta_title, meta_description, content_json, is_published, published_at, updated_at)
                            VALUES ('clinical-excellence', 'Clinical Excellence', 'Title', 'Desc', '{}', 1, '2026-10-15 10:00:00', '2026-10-20 12:00:00')");

                // Insert published service
                $pdo->exec("INSERT INTO services (slug, title, tagline, description, sla_metrics_json, deliverables_json, is_published, created_at, updated_at)
                            VALUES ('workforce-solutions', 'Workforce Solutions', 'Tagline', 'Description', '[]', '[]', 1, '2026-10-10 10:00:00', '2026-10-22 14:00:00')");

                // Insert draft service (MUST BE EXCLUDED)
                $pdo->exec("INSERT INTO services (slug, title, tagline, description, sla_metrics_json, deliverables_json, is_published, created_at, updated_at)
                            VALUES ('draft-service', 'Draft Service', 'Draft Tagline', 'Draft Description', '[]', '[]', 0, '2026-10-10 10:00:00', '2026-10-22 14:00:00')");

                // Insert published article
                $pdo->exec("INSERT INTO articles (slug, title, summary, content_html, author_id, category, tags_json, is_published, published_at, updated_at)
                            VALUES ('nursing-standards', 'Nursing Standards', 'Summary', 'Content', 1, 'Clinical', '[]', 1, '2026-10-18 09:00:00', '2026-10-21 11:00:00')");

                // Insert draft article (MUST BE EXCLUDED)
                $pdo->exec("INSERT INTO articles (slug, title, summary, content_html, author_id, category, tags_json, is_published, updated_at)
                            VALUES ('draft-article', 'Draft Article', 'Summary', 'Content', 1, 'Clinical', '[]', 0, '2026-10-22 11:00:00')");

                // Insert verified case study
                $pdo->exec("INSERT INTO case_studies (slug, title, client_sector, challenge_summary, intervention_summary, outcome_metrics_json, consent_verified, is_published, published_at, updated_at)
                            VALUES ('hospital-partnership', 'Hospital Partnership', 'Public Health', 'Challenge', 'Intervention', '[]', 1, 1, '2026-10-19 08:00:00', '2026-10-20 09:00:00')");

                // Insert unverified case study (consent_verified = 0, MUST BE EXCLUDED)
                $pdo->exec("INSERT INTO case_studies (slug, title, client_sector, challenge_summary, intervention_summary, outcome_metrics_json, consent_verified, is_published, published_at, updated_at)
                            VALUES ('unverified-case', 'Unverified Case', 'Private Health', 'Challenge', 'Intervention', '[]', 0, 1, '2026-10-19 08:00:00', '2026-10-20 09:00:00')");

                $xml = $service->generateSitemapXml();

                // 1. Valid XML declaration and schema
                T::assertTrue(str_starts_with($xml, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>"));
                T::assertTrue(str_contains($xml, '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'));
                T::assertTrue(str_ends_with(trim($xml), '</urlset>'));

                // 2. Core static routes present
                T::assertTrue(str_contains($xml, '<loc>https://voakhealthcare.com/</loc>'));
                T::assertTrue(str_contains($xml, '<loc>https://voakhealthcare.com/about</loc>'));
                T::assertTrue(str_contains($xml, '<loc>https://voakhealthcare.com/services</loc>'));
                T::assertTrue(str_contains($xml, '<loc>https://voakhealthcare.com/careers</loc>'));
                T::assertTrue(str_contains($xml, '<loc>https://voakhealthcare.com/faqs</loc>'));

                // 3. Published entities present
                T::assertTrue(str_contains($xml, '<loc>https://voakhealthcare.com/clinical-excellence</loc>'));
                T::assertTrue(str_contains($xml, '<loc>https://voakhealthcare.com/services/workforce-solutions</loc>'));
                T::assertTrue(str_contains($xml, '<loc>https://voakhealthcare.com/insights/nursing-standards</loc>'));
                T::assertTrue(str_contains($xml, '<loc>https://voakhealthcare.com/case-studies/hospital-partnership</loc>'));

                // 4. Drafts and unverified entities STRICTLY EXCLUDED
                T::assertFalse(str_contains($xml, 'draft-service'), 'Draft service must not appear in sitemap');
                T::assertFalse(str_contains($xml, 'draft-article'), 'Draft article must not appear in sitemap');
                T::assertFalse(str_contains($xml, 'unverified-case'), 'Unverified case study must not appear in sitemap');

                // 5. ISO 8601 lastmod dates verified (YYYY-MM-DD)
                T::assertTrue((bool)preg_match('/<lastmod>\d{4}-\d{2}-\d{2}<\/lastmod>/', $xml));
                T::assertTrue(str_contains($xml, '<lastmod>2026-10-22</lastmod>')); // service updated_at
                T::assertTrue(str_contains($xml, '<lastmod>2026-10-21</lastmod>')); // article updated_at
            });

            T::test('getStructuredData generates Schema.org JSON-LD across entities (FR-SEO-004 / TEST-SEO-004)', function () {
                $pdo = self::createDatabase();
                $conn = new Connection();
                $conn->setMockPdo($pdo);
                $service = new SeoService($conn, new AuditLogger());

                // 1. Homepage Organization schema
                $org = $service->getStructuredData('/');
                T::assertEquals('https://schema.org', $org['@context']);
                T::assertEquals('MedicalOrganization', $org['@type']);
                T::assertEquals('VOAK Health Care', $org['name']);
                T::assertTrue(isset($org['contactPoint']));

                // 2. Service schema
                $svc = $service->getStructuredData('/services/clinical-staffing', [
                    'title'       => 'Clinical Staffing Solutions',
                    'description' => 'Healthcare workforce augmentation across regional hospitals.',
                ]);
                T::assertEquals('MedicalBusiness', $svc['@type']);
                T::assertEquals('Clinical Staffing Solutions', $svc['name']);
                T::assertEquals('VOAK Health Care', $svc['provider']['name']);

                // 3. Article schema
                $art = $service->getStructuredData('/insights/workforce-optimization', [
                    'title'        => 'Hospital Workforce Optimization',
                    'summary'      => 'Strategic workforce management in clinical environments.',
                    'author_name'  => 'Dr. Jane Smith',
                    'published_at' => '2026-10-20 10:00:00',
                ]);
                T::assertEquals('Article', $art['@type']);
                T::assertEquals('Hospital Workforce Optimization', $art['headline']);
                T::assertEquals('Dr. Jane Smith', $art['author']['name']);
                T::assertEquals('2026-10-20', $art['datePublished']);

                // 4. FAQPage schema
                $faq = $service->getStructuredData('/faqs', [
                    'faqs' => [
                        ['question' => 'How does VOAK vet nursing staff?', 'answer' => 'All clinicians undergo rigorous AHPRA verification.'],
                        ['question' => 'What is the deployment timeframe?', 'answer' => 'Emergency rosters are filled within 2 hours.'],
                    ],
                ]);
                T::assertEquals('FAQPage', $faq['@type']);
                T::assertEquals(2, count($faq['mainEntity']));
                T::assertEquals('How does VOAK vet nursing staff?', $faq['mainEntity'][0]['name']);
                T::assertEquals('All clinicians undergo rigorous AHPRA verification.', $faq['mainEntity'][0]['acceptedAnswer']['text']);

                // 5. JobPosting schema
                $job = $service->getStructuredData('/careers/registered-nurse-icu', [
                    'title'       => 'Registered Nurse - ICU',
                    'description' => 'Full-time intensive care nursing role.',
                    'created_at'  => '2026-10-22 08:00:00',
                ]);
                T::assertEquals('JobPosting', $job['@type']);
                T::assertEquals('Registered Nurse - ICU', $job['title']);
                T::assertEquals('VOAK Health Care', $job['hiringOrganization']['name']);
            });

            T::test('renderSsrFallback and render404Page generate semantic escaped HTML (FR-SEO-001, FR-SEO-002)', function () {
                $pdo = self::createDatabase();
                $conn = new Connection();
                $conn->setMockPdo($pdo);
                $service = new SeoService($conn, new AuditLogger());

                // Semantic fallback pre-rendering
                $html = $service->renderSsrFallback('/services/specialist-care', [
                    'title'   => 'Specialist Care <script>alert(1)</script>',
                    'summary' => 'Safe & compliant staffing.',
                    'body'    => '<p>Clinical delivery details.</p>',
                ]);

                T::assertTrue(str_contains($html, '<article id="ssr-fallback"'));
                T::assertTrue(str_contains($html, '<script type="application/ld+json">'));
                T::assertTrue(str_contains($html, 'Specialist Care &lt;script&gt;alert(1)&lt;/script&gt;'), 'XSS in title must be escaped');
                T::assertFalse(str_contains($html, '<script>alert(1)</script>'));

                // Authentic 404 HTML
                $html404 = $service->render404Page('/services/invalid-test-slug');
                T::assertTrue(str_contains($html404, '<meta name="robots" content="noindex, nofollow">'));
                T::assertTrue(str_contains($html404, 'Page Not Found (404)'));
                T::assertTrue(str_contains($html404, '/services/invalid-test-slug'));
            });

            T::test('Route SEO metadata list and update with length indicators (FR-ADM-018 / TEST-ADM-018)', function () {
                $pdo = self::createDatabase();
                $conn = new Connection();
                $conn->setMockPdo($pdo);
                $service = new SeoService($conn, new AuditLogger());

                // Insert page
                $pdo->exec("INSERT INTO pages (id, slug, title, meta_title, meta_description, content_json, is_published)
                            VALUES (10, 'about', 'About VOAK', 'VOAK Health Care - Australian Healthcare Operations Specialists', 'Enterprise B2B clinical workforce solutions and health operational management across NSW, Victoria, and Queensland hospitals.', '{}', 1)");

                $list = $service->getRouteSeoList();
                T::assertTrue(count($list) > 0);

                $aboutItem = null;
                foreach ($list as $item) {
                    if ($item['route'] === '/about') {
                        $aboutItem = $item;
                        break;
                    }
                }
                T::assertTrue($aboutItem !== null);
                T::assertEquals('optimal', $aboutItem['desc_status']);

                // Update route SEO metadata
                $updated = $service->updateRouteSeo(10, [
                    'meta_title'       => 'About VOAK Health Care - Excellence in Healthcare Operations',
                    'meta_description' => 'Discover how VOAK Health Care delivers reliable clinical workforce solutions and hospital support across Australia.',
                ], 1);

                T::assertEquals('About VOAK Health Care - Excellence in Healthcare Operations', $updated['meta_title']);
                T::assertEquals('optimal', $updated['title_status']);

                // Verify persistence in database
                $checkStmt = $pdo->query("SELECT meta_title FROM pages WHERE id = 10");
                T::assertEquals('About VOAK Health Care - Excellence in Healthcare Operations', $checkStmt->fetchColumn());
            });

            T::test('deleteRedirect removes redirect and invalidates cache', function () {
                $pdo = self::createDatabase();
                $conn = new Connection();
                $conn->setMockPdo($pdo);
                $service = new SeoService($conn, new AuditLogger());

                $created = $service->registerRedirect('/temp-source', '/temp-target', 301, 1);
                T::assertTrue($service->resolveRedirect('/temp-source') !== null);

                $service->deleteRedirect($created['id'], 1);
                T::assertTrue($service->resolveRedirect('/temp-source') === null);
            });
        });
    }
}
