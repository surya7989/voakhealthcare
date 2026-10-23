<?php

declare(strict_types=1);

namespace Voak\Health\Tests\Unit;

use Voak\Health\Auth\RbacManager;
use Voak\Health\Core\Exceptions\ForbiddenException;
use Voak\Health\Core\Exceptions\HttpException;
use Voak\Health\Core\Exceptions\NotFoundException;
use Voak\Health\Core\Exceptions\ValidationException;
use Voak\Health\Database\Connection;
use Voak\Health\Database\Migration\MigrationManager;
use Voak\Health\Security\AuditLogger;
use Voak\Health\Security\HtmlSanitizer;
use Voak\Health\Security\RateLimiter;
use Voak\Health\Services\MediaStorageService;
use Voak\Health\Services\ProposalService;
use Voak\Health\Tests\TestFramework as T;

/**
 * Unit Test Suite for ProposalService (Phase 12: FR-ADM-012, FR-WF-003, FR-PUB-021, FR-SEC-003, FR-SEC-004).
 */
class ProposalServiceTest
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
                    VALUES (2, 'BD Lead Specialist', 'bdlead@voak.test', 'hash', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
        $pdo->exec("INSERT INTO users (id, name, email, password_hash, is_active, created_at, updated_at) 
                    VALUES (3, 'Content Editor', 'editor@voak.test', 'hash', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");

        // Seed dummy services in services table
        $pdo->exec("INSERT INTO services (id, title, slug, tagline, description, sla_metrics_json, deliverables_json, is_published, display_order, created_at, updated_at)
                    VALUES (1, 'Clinical Workforce Solutions', 'clinical-workforce', 'Tagline', 'Description', '[]', '[]', 1, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
        $pdo->exec("INSERT INTO services (id, title, slug, tagline, description, sla_metrics_json, deliverables_json, is_published, display_order, created_at, updated_at)
                    VALUES (2, 'Emergency Surge Roster', 'emergency-surge', 'Tagline', 'Description', '[]', '[]', 1, 2, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");

        $rbac = new RbacManager();
        $rbac->seedRbac($pdo);
        $rbac->assignRole($pdo, 1, RbacManager::ROLE_SUPER_ADMIN);
        $rbac->assignRole($pdo, 2, RbacManager::ROLE_BD_MANAGER);
        $rbac->assignRole($pdo, 3, RbacManager::ROLE_CONTENT_EDITOR);

        return $pdo;
    }

    private static function createService(\PDO $pdo, string $quarantineDir, ?string $rateLimiterDir = null): array
    {
        $conn = new Connection();
        $conn->setMockPdo($pdo);
        $audit = new AuditLogger();
        $sanitizer = new HtmlSanitizer();
        $dir = $rateLimiterDir ?? (sys_get_temp_dir() . '/voak_prop_rl_' . bin2hex(random_bytes(4)));
        $rateLimiter = new RateLimiter($dir);
        $rbac = new RbacManager();

        $mediaService = new MediaStorageService(
            $conn,
            $audit,
            null,
            null,
            $quarantineDir
        );

        $proposalService = new ProposalService(
            $conn,
            $mediaService,
            $audit,
            $sanitizer,
            $rateLimiter,
            $rbac
        );

        return [$proposalService, $mediaService];
    }

    private static function createDummyPdf(string $path): void
    {
        file_put_contents($path, "%PDF-1.4\n%âãÏÓ\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");
    }

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

    public static function run(): void
    {
        T::suite('ProposalService Unit Tests', function () {

            T::test('4-Step RFP wizard submission with tender attachment creates proposal, junction rows, and quarantine file', function () {
                $pdo = self::createDatabase();
                $quarantineDir = sys_get_temp_dir() . '/voak_prop_q_' . bin2hex(random_bytes(4));
                $rateLimiterDir = sys_get_temp_dir() . '/voak_prop_rl_' . bin2hex(random_bytes(4));
                [$service] = self::createService($pdo, $quarantineDir, $rateLimiterDir);

                // Create dummy tender PDF
                $tempPdf = sys_get_temp_dir() . '/tender_spec_' . bin2hex(random_bytes(3)) . '.pdf';
                self::createDummyPdf($tempPdf);

                $file = [
                    'tmp_name' => $tempPdf,
                    'name'     => 'St_Jude_Tender_Specification_2026.pdf',
                    'size'     => filesize($tempPdf),
                    'error'    => 0,
                ];

                $payload = [
                    'organization_name' => 'St. Jude Healthcare Network',
                    'contact_name'      => 'Marcus Aurelius',
                    'contact_email'     => 'm.aurelius@stjude.org',
                    'contact_phone'     => '+1 415 555 9876',
                    'facility_type'     => 'Tertiary Teaching Hospital (500+ Beds)',
                    'timeline'          => 'Within 30 Days',
                    'budget_band'       => '$500,000 - $1,000,000',
                    'service_ids'       => [1, 2],
                ];

                $result = $service->submitProposal($payload, $file, '192.0.2.10', 'Public/Wizard');

                T::assertEquals('success', $result['status']);
                T::assertTrue(str_starts_with($result['reference_code'], 'RFP-'), 'Reference code must start with RFP-');
                T::assertTrue(isset($result['id']) && $result['id'] > 0);

                // Verify proposal in DB
                $stmt = $pdo->prepare("SELECT * FROM proposals WHERE id = :id");
                $stmt->execute(['id' => $result['id']]);
                $proposal = $stmt->fetch(\PDO::FETCH_ASSOC);

                T::assertTrue($proposal !== false);
                T::assertEquals('SUBMITTED', $proposal['status']);
                T::assertEquals('St_Jude_Tender_Specification_2026.pdf', $proposal['original_file_name']);
                T::assertTrue(file_exists($proposal['quarantined_file_path']), 'Quarantined file must exist on disk');

                // Verify junction mapping in proposal_services
                $jStmt = $pdo->prepare("SELECT service_id FROM proposal_services WHERE proposal_id = :id ORDER BY service_id ASC");
                $jStmt->execute(['id' => $result['id']]);
                $serviceIds = $jStmt->fetchAll(\PDO::FETCH_COLUMN);

                T::assertEquals([1, 2], array_map('intval', $serviceIds), 'Junction table must map service 1 and 2');

                // Verify email staged in email_queue
                $qStmt = $pdo->prepare("SELECT * FROM email_queue WHERE recipient_email = 'm.aurelius@stjude.org'");
                $qStmt->execute();
                $mail = $qStmt->fetch(\PDO::FETCH_ASSOC);
                T::assertTrue($mail !== false);
                T::assertTrue(str_contains($mail['payload_json'], $result['reference_code']));

                // Verify audit log
                $aStmt = $pdo->prepare("SELECT * FROM audit_logs WHERE action = 'RFP_SUBMITTED' AND entity_id = :id");
                $aStmt->execute(['id' => $result['id']]);
                $audit = $aStmt->fetch(\PDO::FETCH_ASSOC);
                T::assertTrue($audit !== false);

                @unlink($tempPdf);
                self::recursiveDelete($quarantineDir);
                self::recursiveDelete($rateLimiterDir);
            });

            T::test('Honeypot detection silently returns fake reference without database persistence', function () {
                $pdo = self::createDatabase();
                $quarantineDir = sys_get_temp_dir() . '/voak_prop_q_' . bin2hex(random_bytes(4));
                [$service] = self::createService($pdo, $quarantineDir);

                $payload = [
                    'organization_name' => 'Bot Procurement',
                    'contact_name'      => 'Bot Spammer',
                    'contact_email'     => 'spam@bot.org',
                    'contact_phone'     => '+1 800 555 1234',
                    'facility_type'     => 'Clinic',
                    'timeline'          => 'Immediate',
                    'budget_band'       => 'Small',
                    'honeypot'          => 'I am a spam bot',
                ];

                $result = $service->submitProposal($payload);

                T::assertEquals('success', $result['status']);
                T::assertTrue(str_starts_with($result['reference_code'], 'RFP-'));
                T::assertFalse(isset($result['id']));

                $count = (int)$pdo->query("SELECT COUNT(*) FROM proposals WHERE contact_email = 'spam@bot.org'")->fetchColumn();
                T::assertEquals(0, $count);

                self::recursiveDelete($quarantineDir);
            });

            T::test('Rate limiting prevents abuse beyond 5 proposals per hour per IP', function () {
                $pdo = self::createDatabase();
                $quarantineDir = sys_get_temp_dir() . '/voak_prop_q_' . bin2hex(random_bytes(4));
                $rateLimiterDir = sys_get_temp_dir() . '/voak_prop_rl_' . bin2hex(random_bytes(4));
                [$service] = self::createService($pdo, $quarantineDir, $rateLimiterDir);

                $ip = '203.0.113.19';
                $payload = [
                    'organization_name' => 'Rate Limited Facility',
                    'contact_name'      => 'Dr. Rate Limit',
                    'contact_email'     => 'ratelimit@hospital.org',
                    'contact_phone'     => '+1 202 555 9999',
                    'facility_type'     => 'General Hospital',
                    'timeline'          => 'Within 60 Days',
                    'budget_band'       => '$250,000 - $500,000',
                ];

                for ($i = 1; $i <= 5; $i++) {
                    $res = $service->submitProposal($payload, null, $ip);
                    T::assertEquals('success', $res['status']);
                }

                $blocked = false;
                try {
                    $service->submitProposal($payload, null, $ip);
                } catch (HttpException $e) {
                    $blocked = true;
                    T::assertEquals(429, $e->getStatusCode());
                    T::assertEquals('ERR_RATE_LIMITED', $e->getErrorCode());
                }

                T::assertTrue($blocked, '6th proposal must be rejected by rate limiter');

                self::recursiveDelete($quarantineDir);
                self::recursiveDelete($rateLimiterDir);
            });

            T::test('Tender quarantine rejects dangerous extensions and non-PDF/DOCX magic bytes', function () {
                $pdo = self::createDatabase();
                $quarantineDir = sys_get_temp_dir() . '/voak_prop_q_' . bin2hex(random_bytes(4));
                [$service] = self::createService($pdo, $quarantineDir);

                // Executable script disguised as pdf
                $fakeFile = sys_get_temp_dir() . '/malicious.pdf';
                file_put_contents($fakeFile, "<?php echo 'malicious payload'; ?>");

                $file = [
                    'tmp_name' => $fakeFile,
                    'name'     => 'malicious.pdf',
                    'size'     => filesize($fakeFile),
                    'error'    => 0,
                ];

                $payload = [
                    'organization_name' => 'Security Audit Clinic',
                    'contact_name'      => 'Sec Officer',
                    'contact_email'     => 'sec@audit.org',
                    'contact_phone'     => '+1 202 555 7777',
                    'facility_type'     => 'Audit Facility',
                    'timeline'          => 'Now',
                    'budget_band'       => 'Open',
                ];

                $rejected = false;
                try {
                    $service->submitProposal($payload, $file);
                } catch (ValidationException $e) {
                    $rejected = true;
                    T::assertTrue(
                        in_array($e->getErrorCode(), ['INVALID_FILE_SIGNATURE', 'INVALID_EXTENSION'], true),
                        'Error code must be file signature rejection'
                    );
                }

                T::assertTrue($rejected, 'Malicious file must be rejected by quarantine engine');

                @unlink($fakeFile);
                self::recursiveDelete($quarantineDir);
            });

            T::test('FSM enforces proposal lifecycle transitions: SUBMITTED -> UNDER_REVIEW -> SCOPING -> PROPOSAL_SENT -> ACCEPTED', function () {
                $pdo = self::createDatabase();
                $quarantineDir = sys_get_temp_dir() . '/voak_prop_q_' . bin2hex(random_bytes(4));
                [$service] = self::createService($pdo, $quarantineDir);

                $payload = [
                    'organization_name' => 'Mercy General Hospital',
                    'contact_name'      => 'Dr. Robert Chen',
                    'contact_email'     => 'rchen@mercygeneral.org',
                    'contact_phone'     => '+1 415 555 3333',
                    'facility_type'     => 'Metropolitan Trauma Center',
                    'timeline'          => 'Within 30 Days',
                    'budget_band'       => '$1,000,000+',
                    'service_ids'       => [1],
                ];
                $init = $service->submitProposal($payload);
                $id = $init['id'];

                // 1. Advance to UNDER_REVIEW
                $step1 = $service->updateStatus($id, 'UNDER_REVIEW', null, 'Triage commenced', 2, 2);
                T::assertEquals('UNDER_REVIEW', $step1['status']);

                // 2. Advance to SCOPING (alias: DRAFTING_PROPOSAL) and attach commercial value estimate
                $step2 = $service->updateStatus($id, 'DRAFTING_PROPOSAL', 1250000.00, 'Scoping commercial pricing', null, 2);
                T::assertEquals('SCOPING', $step2['status']);
                T::assertEquals(1250000.00, $step2['estimated_commercial_value']);

                // 3. Advance to PROPOSAL_SENT
                $step3 = $service->updateStatus($id, 'PROPOSAL_SENT', null, 'Formal bid dispatched to procurement board', null, 2);
                T::assertEquals('PROPOSAL_SENT', $step3['status']);

                // 4. Advance to ACCEPTED (alias: CONTRACT_EXECUTED)
                $step4 = $service->updateStatus($id, 'CONTRACT_EXECUTED', null, 'Contract executed by hospital board', null, 2);
                T::assertEquals('ACCEPTED', $step4['status']);

                self::recursiveDelete($quarantineDir);
            });

            T::test('FSM rejects invalid transition jumping from SUBMITTED directly to ACCEPTED', function () {
                $pdo = self::createDatabase();
                $quarantineDir = sys_get_temp_dir() . '/voak_prop_q_' . bin2hex(random_bytes(4));
                [$service] = self::createService($pdo, $quarantineDir);

                $payload = [
                    'organization_name' => 'Illegal Leap Hospital',
                    'contact_name'      => 'Dr. Leap',
                    'contact_email'     => 'leap@hospital.org',
                    'contact_phone'     => '+1 415 555 4444',
                    'facility_type'     => 'Clinic',
                    'timeline'          => '30 Days',
                    'budget_band'       => 'Tier 1',
                ];
                $init = $service->submitProposal($payload);
                $id = $init['id'];

                $blocked = false;
                try {
                    $service->updateStatus($id, 'ACCEPTED', null, 'Illegal transition attempt', null, 2);
                } catch (ValidationException $e) {
                    $blocked = true;
                    T::assertEquals('ERR_INVALID_TRANSITION', $e->getErrorCode());
                }

                T::assertTrue($blocked, 'Direct jump from SUBMITTED to ACCEPTED must be blocked');

                self::recursiveDelete($quarantineDir);
            });

            T::test('Assign proposal lead specialist updates assigned_lead_id and rejects invalid user', function () {
                $pdo = self::createDatabase();
                $quarantineDir = sys_get_temp_dir() . '/voak_prop_q_' . bin2hex(random_bytes(4));
                [$service] = self::createService($pdo, $quarantineDir);

                $payload = [
                    'organization_name' => 'Lead Assign Hospital',
                    'contact_name'      => 'Dr. Assign',
                    'contact_email'     => 'assign@hospital.org',
                    'contact_phone'     => '+1 415 555 5555',
                    'facility_type'     => 'Health System',
                    'timeline'          => '60 Days',
                    'budget_band'       => 'Tier 2',
                ];
                $init = $service->submitProposal($payload);
                $id = $init['id'];

                // Assign to BD Lead (id=2)
                $assigned = $service->assignLead($id, 2, 1);
                T::assertEquals(2, $assigned['assigned_lead_id']);
                T::assertEquals('BD Lead Specialist', $assigned['assigned_lead_name']);

                // Nonexistent user id=999 must fail
                $rejected = false;
                try {
                    $service->assignLead($id, 999, 1);
                } catch (ValidationException $e) {
                    $rejected = true;
                }
                T::assertTrue($rejected, 'Assigning to nonexistent user must fail validation');

                self::recursiveDelete($quarantineDir);
            });

            T::test('Authenticated streaming delivers quarantined tender attachment with security headers', function () {
                $pdo = self::createDatabase();
                $quarantineDir = sys_get_temp_dir() . '/voak_prop_q_' . bin2hex(random_bytes(4));
                [$service] = self::createService($pdo, $quarantineDir);

                $tempPdf = sys_get_temp_dir() . '/tender_spec_' . bin2hex(random_bytes(3)) . '.pdf';
                self::createDummyPdf($tempPdf);

                $file = [
                    'tmp_name' => $tempPdf,
                    'name'     => 'Hospital_RFP_Specification.pdf',
                    'size'     => filesize($tempPdf),
                    'error'    => 0,
                ];

                $payload = [
                    'organization_name' => 'Streaming Test Hospital',
                    'contact_name'      => 'Dr. Stream',
                    'contact_email'     => 'stream@hospital.org',
                    'contact_phone'     => '+1 415 555 6666',
                    'facility_type'     => 'Metro Medical',
                    'timeline'          => '90 Days',
                    'budget_band'       => 'Tier 3',
                ];
                $init = $service->submitProposal($payload, $file);
                $id = $init['id'];

                $response = $service->streamTenderAttachment($id, 2, '127.0.0.1');

                T::assertEquals(200, $response->getStatusCode());
                $headers = $response->getHeaders();
                T::assertTrue(isset($headers['Content-Disposition']), 'Content-Disposition header must be set');
                T::assertTrue(str_contains($headers['Content-Disposition'], 'Hospital_RFP_Specification.pdf'));
                T::assertEquals('application/pdf', $headers['Content-Type']);

                @unlink($tempPdf);
                self::recursiveDelete($quarantineDir);
            });

            T::test('RFC 4180 CSV export neutralizes formula injection cells', function () {
                $pdo = self::createDatabase();
                $quarantineDir = sys_get_temp_dir() . '/voak_prop_q_' . bin2hex(random_bytes(4));
                [$service] = self::createService($pdo, $quarantineDir);

                $payload = [
                    'organization_name' => '=HYPERLINK("http://attacker.com")',
                    'contact_name'      => '+987654321',
                    'contact_email'     => 'formula@hospital.org',
                    'contact_phone'     => '+1 415 555 7777',
                    'facility_type'     => '@SUM(B1:B10)',
                    'timeline'          => '-TimelineDangerous',
                    'budget_band'       => 'Tier 1',
                ];
                $service->submitProposal($payload);

                $csv = $service->exportCsv([], 2);

                T::assertTrue(str_contains($csv, "'=HYPERLINK"));
                T::assertTrue(str_contains($csv, "'@SUM(B1:B10)"));
                T::assertTrue(str_contains($csv, "'-TimelineDangerous"));

                self::recursiveDelete($quarantineDir);
            });

            T::test('Super Admin can purge proposal cascading junction rows and unlinking quarantined file', function () {
                $pdo = self::createDatabase();
                $quarantineDir = sys_get_temp_dir() . '/voak_prop_q_' . bin2hex(random_bytes(4));
                [$service] = self::createService($pdo, $quarantineDir);

                $tempPdf = sys_get_temp_dir() . '/purge_tender_' . bin2hex(random_bytes(3)) . '.pdf';
                self::createDummyPdf($tempPdf);

                $file = [
                    'tmp_name' => $tempPdf,
                    'name'     => 'Purge_Specification.pdf',
                    'size'     => filesize($tempPdf),
                    'error'    => 0,
                ];

                $payload = [
                    'organization_name' => 'Purge Test Hospital',
                    'contact_name'      => 'Dr. Purge',
                    'contact_email'     => 'purge@hospital.org',
                    'contact_phone'     => '+1 415 555 8888',
                    'facility_type'     => 'Hospital',
                    'timeline'          => 'Immediate',
                    'budget_band'       => 'Tier 1',
                    'service_ids'       => [1, 2],
                ];
                $init = $service->submitProposal($payload, $file);
                $id = $init['id'];

                // Verify file exists
                $qPath = $pdo->query("SELECT quarantined_file_path FROM proposals WHERE id = {$id}")->fetchColumn();
                T::assertTrue(file_exists($qPath), 'Quarantined file must exist prior to deletion');

                // BD Manager (id=2) cannot delete -> ForbiddenException
                $forbidden = false;
                try {
                    $service->deleteProposal($id, 2);
                } catch (ForbiddenException $e) {
                    $forbidden = true;
                }
                T::assertTrue($forbidden, 'Non-super admin cannot delete proposals');

                // Super Admin (id=1) executes deletion
                $deleted = $service->deleteProposal($id, 1);
                T::assertTrue($deleted, 'Super Admin must successfully delete proposal');

                // Verify row removed from proposals
                $pCount = (int)$pdo->query("SELECT COUNT(*) FROM proposals WHERE id = {$id}")->fetchColumn();
                T::assertEquals(0, $pCount, 'Proposal row must be deleted');

                // Verify junction rows removed from proposal_services
                $jCount = (int)$pdo->query("SELECT COUNT(*) FROM proposal_services WHERE proposal_id = {$id}")->fetchColumn();
                T::assertEquals(0, $jCount, 'Junction rows must be deleted');

                // Verify quarantined file removed from disk
                T::assertFalse(file_exists($qPath), 'Quarantined file must be deleted from disk');

                @unlink($tempPdf);
                self::recursiveDelete($quarantineDir);
            });

        });
    }
}
