<?php

declare(strict_types=1);

namespace Voak\Health\Tests\Unit;

use Voak\Health\Core\Exceptions\BadRequestException;
use Voak\Health\Core\Exceptions\ValidationException;
use Voak\Health\Database\Connection;
use Voak\Health\Database\Migration\MigrationManager;
use Voak\Health\Security\AuditLogger;
use Voak\Health\Security\HtmlSanitizer;
use Voak\Health\Services\HomepageService;
use Voak\Health\Tests\TestFramework as T;

class HomepageServiceTest
{
    private static function createDatabase(): \PDO
    {
        $pdo = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ]);
        $migrationsDir = dirname(__DIR__, 2) . '/database/migrations';
        $manager = new MigrationManager($migrationsDir, 'migrations');
        $manager->migrateUp($pdo);

        $pdo->exec("INSERT INTO users (id, name, email, password_hash, is_active, created_at, updated_at) 
                    VALUES (1, 'Admin User', 'admin@voak.test', 'hash', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");

        return $pdo;
    }

    public static function run(): void
    {
        T::suite('Phase 7 — HomepageService & CTA Security Tests', function () {
            T::test('validateCtaUrl accepts valid internal paths and HTTPS absolute URLs', function () {
                $service = new HomepageService(new Connection(), new AuditLogger(), new HtmlSanitizer());

                T::assertEquals('/services', $service->validateCtaUrl('/services'));
                T::assertEquals('/contact', $service->validateCtaUrl('/contact'));
                T::assertEquals('/about-us/governance', $service->validateCtaUrl('/about-us/governance'));
                T::assertEquals('https://voak.com.au/portal', $service->validateCtaUrl('https://voak.com.au/portal'));
                T::assertEquals('https://health.gov.au', $service->validateCtaUrl('https://health.gov.au'));
            });

            T::test('validateCtaUrl rejects dangerous URI schemes and path traversal attempts', function () {
                $service = new HomepageService(new Connection(), new AuditLogger(), new HtmlSanitizer());

                $maliciousUrls = [
                    'javascript:alert(1)',
                    'JAVASCRIPT:alert(document.cookie)',
                    'javascript:/*--></title></style></textarea><em><script>alert(1)</script>',
                    'data:text/html,<script>alert(1)</script>',
                    'vbscript:msgbox(1)',
                    'file:///etc/passwd',
                    '/services/../traversal',
                    'ftp://insecure.test',
                    '',
                ];

                foreach ($maliciousUrls as $url) {
                    $threw = false;
                    try {
                        $service->validateCtaUrl($url);
                    } catch (BadRequestException $e) {
                        $threw = true;
                    }
                    T::assertTrue($threw, "URL [{$url}] should have been rejected by validateCtaUrl.");
                }
            });

            T::test('getActiveConfig returns safe default corporate configuration when table is empty', function () {
                $pdo = self::createDatabase();
                $connection = new Connection();
                $connection->setMockPdo($pdo);
                $service = new HomepageService($connection, new AuditLogger(), new HtmlSanitizer());

                $config = $service->getActiveConfig();
                T::assertTrue(!empty($config['hero_headline']));
                T::assertTrue(!empty($config['hero_primary_cta_url']));
                T::assertTrue(!empty($config['proof_metrics']));
                T::assertTrue(!empty($config['bento_order']));
                T::assertTrue(!empty($config['etag']));
            });

            T::test('updateConfig sanitizes hero copy, validates input, and updates configuration', function () {
                $pdo = self::createDatabase();
                $connection = new Connection();
                $connection->setMockPdo($pdo);
                $audit = new AuditLogger();
                $service = new HomepageService($connection, $audit, new HtmlSanitizer());

                $updated = $service->updateConfig([
                    'hero_headline'           => 'Empowering Healthcare Networks <script>alert(1)</script>',
                    'hero_subheadline'        => 'Australian clinical workforce specialists and digital governance.',
                    'hero_primary_cta_text'   => 'Get Started',
                    'hero_primary_cta_url'    => '/contact',
                    'hero_secondary_cta_text' => 'View Services',
                    'hero_secondary_cta_url'  => '/services',
                    'proof_metrics'           => [
                        ['label' => 'Specialists', 'value' => '500+'],
                    ],
                    'bento_order'             => [
                        ['id' => 'card-1', 'title' => 'Card 1'],
                    ],
                ], 1);

                T::assertFalse(str_contains($updated['hero_headline'], '<script>'));
                T::assertContains('Empowering Healthcare Networks', $updated['hero_headline']);
                T::assertEquals('/contact', $updated['hero_primary_cta_url']);
                T::assertEquals('/services', $updated['hero_secondary_cta_url']);
                T::assertEquals('500+', $updated['proof_metrics'][0]['value']);

                // Verify audit log row was appended
                $stmt = $pdo->query("SELECT * FROM audit_logs WHERE action = 'homepage_updated'");
                $auditRow = $stmt ? $stmt->fetch(\PDO::FETCH_ASSOC) : false;
                T::assertTrue($auditRow !== false);
            });

            T::test('updateConfig rejects missing required fields with ValidationException', function () {
                $pdo = self::createDatabase();
                $connection = new Connection();
                $connection->setMockPdo($pdo);
                $service = new HomepageService($connection, new AuditLogger(), new HtmlSanitizer());

                T::expectException(ValidationException::class, function () use ($service) {
                    $service->updateConfig([
                        'hero_headline' => '',
                    ], 1);
                });
            });

            T::test('calculateEtag produces deterministic hash sensitive to hero copy mutations', function () {
                $service = new HomepageService(new Connection(), new AuditLogger(), new HtmlSanitizer());

                $c1 = [
                    'id' => 1,
                    'updated_at' => '2026-10-22 10:00:00',
                    'hero_headline' => 'Initial Copy',
                    'hero_primary_cta_url' => '/contact',
                    'proof_metrics' => [],
                    'bento_order' => [],
                ];

                $etag1 = $service->calculateEtag($c1);
                $etag2 = $service->calculateEtag($c1);
                T::assertEquals($etag1, $etag2);

                $c2 = $c1;
                $c2['hero_headline'] = 'Mutated Copy';
                $etag3 = $service->calculateEtag($c2);
                T::assertFalse($etag1 === $etag3);
            });
        });
    }
}
