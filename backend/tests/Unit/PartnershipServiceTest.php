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
use Voak\Health\Services\PartnershipService;
use Voak\Health\Tests\TestFramework as T;

/**
 * Unit Test Suite for PartnershipService (Phase 12: FR-ADM-011, FR-WF-002, FR-PUB-020, SYS-005, ADR-008).
 */
class PartnershipServiceTest
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
                    VALUES (2, 'BD Manager', 'bd@voak.test', 'hash', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
        $pdo->exec("INSERT INTO users (id, name, email, password_hash, is_active, created_at, updated_at) 
                    VALUES (3, 'Content Editor', 'editor@voak.test', 'hash', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");

        $rbac = new RbacManager();
        $rbac->seedRbac($pdo);
        $rbac->assignRole($pdo, 1, RbacManager::ROLE_SUPER_ADMIN);
        $rbac->assignRole($pdo, 2, RbacManager::ROLE_BD_MANAGER);
        $rbac->assignRole($pdo, 3, RbacManager::ROLE_CONTENT_EDITOR);

        return $pdo;
    }

    private static function createService(\PDO $pdo, ?string $rateLimiterDir = null): PartnershipService
    {
        $conn = new Connection();
        $conn->setMockPdo($pdo);
        $audit = new AuditLogger();
        $sanitizer = new HtmlSanitizer();
        $dir = $rateLimiterDir ?? (sys_get_temp_dir() . '/voak_ptn_rl_' . bin2hex(random_bytes(4)));
        $rateLimiter = new RateLimiter($dir);
        $rbac = new RbacManager();

        return new PartnershipService($conn, $audit, $sanitizer, $rateLimiter, $rbac);
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
        T::suite('PartnershipService Unit Tests', function () {

            T::test('Public alliance submission successfully creates inquiry and stages email confirmation', function () {
                $pdo = self::createDatabase();
                $rateLimiterDir = sys_get_temp_dir() . '/voak_ptn_rl_' . bin2hex(random_bytes(4));
                $service = self::createService($pdo, $rateLimiterDir);

                $payload = [
                    'organization_name' => 'State Healthcare Network',
                    'contact_name'      => 'Dr. Jonathan Vance',
                    'corporate_email'   => 'jvance@statehealth.gov.au',
                    'contact_phone'     => '+61 2 9555 1234',
                    'alliance_type'     => 'Regional Health Authority',
                    'proposed_scope'    => 'Clinical workforce joint venture across regional oncology and tertiary care facilities.',
                ];

                $result = $service->submitPartnership($payload, '10.0.0.50', 'Public/Agent');

                T::assertEquals('success', $result['status'], 'Status must be success');
                T::assertTrue(str_starts_with($result['reference_code'], 'PTN-'), 'Reference code must start with PTN-');
                T::assertTrue(isset($result['id']) && $result['id'] > 0, 'Inquiry ID must be returned');

                // Verify DB
                $stmt = $pdo->prepare("SELECT * FROM partnership_inquiries WHERE id = :id");
                $stmt->execute(['id' => $result['id']]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);

                T::assertTrue($row !== false, 'Row must exist');
                T::assertEquals('NEW', $row['status'], 'Initial status must be NEW');
                T::assertEquals('State Healthcare Network', $row['organization_name']);
                T::assertEquals('jvance@statehealth.gov.au', $row['corporate_email']);

                // Verify email staged in email_queue
                $qStmt = $pdo->prepare("SELECT * FROM email_queue WHERE recipient_email = :email");
                $qStmt->execute(['email' => 'jvance@statehealth.gov.au']);
                $mail = $qStmt->fetch(\PDO::FETCH_ASSOC);

                T::assertTrue($mail !== false, 'Email must be staged');
                T::assertEquals('QUEUED', $mail['status']);
                T::assertTrue(str_contains($mail['payload_json'], $result['reference_code']), 'Email payload must contain reference code');

                // Verify audit log
                $aStmt = $pdo->prepare("SELECT * FROM audit_logs WHERE action = 'PTN_INQUIRY_SUBMITTED' AND entity_id = :id");
                $aStmt->execute(['id' => $result['id']]);
                $audit = $aStmt->fetch(\PDO::FETCH_ASSOC);
                T::assertTrue($audit !== false, 'Audit log entry must exist');

                self::recursiveDelete($rateLimiterDir);
            });

            T::test('Honeypot detection silently returns fake reference without database persistence', function () {
                $pdo = self::createDatabase();
                $service = self::createService($pdo);

                $payload = [
                    'organization_name' => 'Spam Bot Org',
                    'contact_name'      => 'Bot Spammer',
                    'corporate_email'   => 'bot@spam.com',
                    'contact_phone'     => '+1 800 555 0199',
                    'alliance_type'     => 'Spam',
                    'proposed_scope'    => 'Buy cheap pharmaceuticals now',
                    'hp_company_field'  => 'I am a spam bot',
                ];

                $result = $service->submitPartnership($payload, '185.220.101.5', 'SpamBot/1.0');

                T::assertEquals('success', $result['status'], 'Honeypot returns success');
                T::assertTrue(str_starts_with($result['reference_code'], 'PTN-'), 'Fake reference code starts with PTN-');
                T::assertFalse(isset($result['id']), 'Fake submission must not return database ID');

                $count = (int)$pdo->query("SELECT COUNT(*) FROM partnership_inquiries WHERE corporate_email = 'bot@spam.com'")->fetchColumn();
                T::assertEquals(0, $count, 'Zero rows should be inserted into partnership_inquiries');
            });

            T::test('IP rate limiter blocks submissions after 5 attempts per hour', function () {
                $pdo = self::createDatabase();
                $rateLimiterDir = sys_get_temp_dir() . '/voak_ptn_rl_' . bin2hex(random_bytes(4));
                $service = self::createService($pdo, $rateLimiterDir);

                $ip = '198.51.100.42';
                $payload = [
                    'organization_name' => 'Rate Limited Health',
                    'contact_name'      => 'Dr. Rate Limit',
                    'corporate_email'   => 'ratelimit@health.org',
                    'contact_phone'     => '+1 212 555 4321',
                    'alliance_type'     => 'Hospital Network',
                    'proposed_scope'    => 'Valid clinical collaboration testing rate limiter functionality.',
                ];

                for ($i = 1; $i <= 5; $i++) {
                    $res = $service->submitPartnership($payload, $ip);
                    T::assertEquals('success', $res['status']);
                }

                // 6th attempt must be rejected
                $rejected = false;
                try {
                    $service->submitPartnership($payload, $ip);
                } catch (HttpException $e) {
                    $rejected = true;
                    T::assertEquals(429, $e->getStatusCode(), 'Must return HTTP 429');
                    T::assertEquals('ERR_RATE_LIMITED', $e->getErrorCode());
                }

                T::assertTrue($rejected, '6th attempt must throw HTTP 429 rate limit exception');
                self::recursiveDelete($rateLimiterDir);
            });

            T::test('Validation rejects missing or malformed fields', function () {
                $pdo = self::createDatabase();
                $service = self::createService($pdo);

                $payload = [
                    'organization_name' => 'A', // too short
                    'contact_name'      => '',  // empty
                    'corporate_email'   => 'not-an-email',
                    'contact_phone'     => '12', // too short
                    'alliance_type'     => '',
                    'proposed_scope'    => 'Short', // < 10 chars
                ];

                $failed = false;
                try {
                    $service->submitPartnership($payload);
                } catch (ValidationException $e) {
                    $failed = true;
                    $errors = $e->getValidationErrors();
                    T::assertTrue(isset($errors['organization_name']), 'organization_name error expected');
                    T::assertTrue(isset($errors['contact_name']), 'contact_name error expected');
                    T::assertTrue(isset($errors['corporate_email']), 'corporate_email error expected');
                    T::assertTrue(isset($errors['contact_phone']), 'contact_phone error expected');
                    T::assertTrue(isset($errors['alliance_type']), 'alliance_type error expected');
                    T::assertTrue(isset($errors['proposed_scope']), 'proposed_scope error expected');
                }

                T::assertTrue($failed, 'Must fail with validation errors');
            });

            T::test('FSM enforces valid stage progression: NEW -> EVALUATION -> DISCUSSION -> LEGAL_REVIEW -> ACTIVE_ALLIANCE', function () {
                $pdo = self::createDatabase();
                $service = self::createService($pdo);

                $payload = [
                    'organization_name' => 'FSM Validation Corp',
                    'contact_name'      => 'Director Vance',
                    'corporate_email'   => 'vance@fsm.org',
                    'contact_phone'     => '+61 2 9111 2222',
                    'alliance_type'     => 'Tertiary Care Provider',
                    'proposed_scope'    => 'Strategic alliance across multiple metropolitan clinical hubs.',
                ];
                $init = $service->submitPartnership($payload);
                $id = $init['id'];

                // 1. Advance to EVALUATION
                $step1 = $service->updateStage($id, 'EVALUATION', 'Initial qualification passed', null, 2);
                T::assertEquals('EVALUATION', $step1['status']);

                // 2. Advance to DISCUSSION (alias: DISCOVERY_SCHEDULED)
                $step2 = $service->updateStage($id, 'DISCOVERY_SCHEDULED', 'Executive discovery meeting arranged', 2, 2);
                T::assertEquals('DISCUSSION', $step2['status']);

                // 3. Advance to LEGAL_REVIEW (alias: NEGOTIATING)
                $step3 = $service->updateStage($id, 'NEGOTIATING', 'Commercial terms and SLA drafted', null, 2);
                T::assertEquals('LEGAL_REVIEW', $step3['status']);

                // 4. Advance to ACTIVE_ALLIANCE (alias: PARTNERED)
                $step4 = $service->updateStage($id, 'PARTNERED', 'Master agreement executed by both parties', null, 2);
                T::assertEquals('ACTIVE_ALLIANCE', $step4['status']);
            });

            T::test('FSM rejects invalid transition jumping from NEW to ACTIVE_ALLIANCE', function () {
                $pdo = self::createDatabase();
                $service = self::createService($pdo);

                $payload = [
                    'organization_name' => 'Illegal Jump Org',
                    'contact_name'      => 'Dr. Jumper',
                    'corporate_email'   => 'jumper@test.org',
                    'contact_phone'     => '+61 2 9222 3333',
                    'alliance_type'     => 'Regional Care',
                    'proposed_scope'    => 'Attempting direct leap to active alliance status.',
                ];
                $init = $service->submitPartnership($payload);
                $id = $init['id'];

                $blocked = false;
                try {
                    $service->updateStage($id, 'ACTIVE_ALLIANCE', 'Attempting illegal leap', null, 2);
                } catch (ValidationException $e) {
                    $blocked = true;
                    T::assertEquals('ERR_INVALID_TRANSITION', $e->getErrorCode());
                }

                T::assertTrue($blocked, 'Direct transition from NEW to ACTIVE_ALLIANCE must be blocked with ERR_INVALID_TRANSITION');
            });

            T::test('DECLINED is a terminal state with no further transitions allowed', function () {
                $pdo = self::createDatabase();
                $service = self::createService($pdo);

                $payload = [
                    'organization_name' => 'Decline Test Org',
                    'contact_name'      => 'Dr. Declined',
                    'corporate_email'   => 'declined@test.org',
                    'contact_phone'     => '+61 2 9333 4444',
                    'alliance_type'     => 'Clinic',
                    'proposed_scope'    => 'Proposal destined for commercial decline.',
                ];
                $init = $service->submitPartnership($payload);
                $id = $init['id'];

                // Transition to DECLINED
                $declined = $service->updateStage($id, 'DECLINED', 'Capacity mismatch', null, 2);
                T::assertEquals('DECLINED', $declined['status']);

                // Attempt to resurrect to EVALUATION
                $blocked = false;
                try {
                    $service->updateStage($id, 'EVALUATION', 'Attempting resurrection', null, 2);
                } catch (ValidationException $e) {
                    $blocked = true;
                    T::assertEquals('ERR_INVALID_TRANSITION', $e->getErrorCode());
                }

                T::assertTrue($blocked, 'Transition from DECLINED must be rejected');
            });

            T::test('Adding internal notes records author attribution and chronological entries', function () {
                $pdo = self::createDatabase();
                $service = self::createService($pdo);

                $payload = [
                    'organization_name' => 'Notes Test Org',
                    'contact_name'      => 'Dr. Note Maker',
                    'corporate_email'   => 'notemaker@test.org',
                    'contact_phone'     => '+61 2 9444 5555',
                    'alliance_type'     => 'Healthcare Authority',
                    'proposed_scope'    => 'Checking internal note addition ledger.',
                ];
                $init = $service->submitPartnership($payload);
                $id = $init['id'];

                $updated = $service->addNote($id, 'Spoke with CFO regarding joint venture timeline.', 2);

                T::assertEquals(1, count($updated['internal_notes']));
                T::assertEquals('Spoke with CFO regarding joint venture timeline.', $updated['internal_notes'][0]['note']);
                T::assertEquals(2, $updated['internal_notes'][0]['author_id']);
                T::assertEquals('BD Manager', $updated['internal_notes'][0]['author_name']);
            });

            T::test('Assign lead specialist updates assigned_user_id and rejects invalid user', function () {
                $pdo = self::createDatabase();
                $service = self::createService($pdo);

                $payload = [
                    'organization_name' => 'Assign Test Org',
                    'contact_name'      => 'Dr. Assignee',
                    'corporate_email'   => 'assignee@test.org',
                    'contact_phone'     => '+61 2 9555 6666',
                    'alliance_type'     => 'Provider',
                    'proposed_scope'    => 'Testing lead specialist assignment.',
                ];
                $init = $service->submitPartnership($payload);
                $id = $init['id'];

                // Assign to BD Manager (id=2)
                $assigned = $service->assign($id, 2, 1);
                T::assertEquals(2, $assigned['assigned_user_id']);
                T::assertEquals('BD Manager', $assigned['assigned_user_name']);

                // Attempt to assign to nonexistent user id=999
                $rejected = false;
                try {
                    $service->assign($id, 999, 1);
                } catch (ValidationException $e) {
                    $rejected = true;
                }
                T::assertTrue($rejected, 'Assigning to nonexistent user must fail validation');
            });

            T::test('RFC 4180 CSV export neutralizes formula injection cells', function () {
                $pdo = self::createDatabase();
                $service = self::createService($pdo);

                $payload = [
                    'organization_name' => '=CMD|/C calc!A0', // formula injection payload
                    'contact_name'      => '+1234567890',
                    'corporate_email'   => 'formula@test.org',
                    'contact_phone'     => '+61 2 9666 7777',
                    'alliance_type'     => '@SUM(A1:A10)',
                    'proposed_scope'    => '-FormulaPrefix',
                ];
                $service->submitPartnership($payload);

                $csv = $service->exportCsv([], 2);

                T::assertTrue(str_contains($csv, "'=CMD|/C calc!A0"), 'Formula = prefix must be escaped with single quote');
                T::assertTrue(str_contains($csv, "'@SUM(A1:A10)"), 'Formula @ prefix must be escaped with single quote');
                T::assertTrue(str_contains($csv, "'-FormulaPrefix"), 'Formula - prefix must be escaped with single quote');
            });

            T::test('Super Admin can purge partnership while non-super admin is forbidden', function () {
                $pdo = self::createDatabase();
                $service = self::createService($pdo);

                $payload = [
                    'organization_name' => 'Purge Test Org',
                    'contact_name'      => 'Dr. Purge',
                    'corporate_email'   => 'purge@test.org',
                    'contact_phone'     => '+61 2 9777 8888',
                    'alliance_type'     => 'Delete Candidate',
                    'proposed_scope'    => 'Inquiry to test hard deletion security restrictions.',
                ];
                $init = $service->submitPartnership($payload);
                $id = $init['id'];

                // BD Manager (id=2) attempts delete -> must throw ForbiddenException
                $forbidden = false;
                try {
                    $service->deletePartnership($id, 2);
                } catch (ForbiddenException $e) {
                    $forbidden = true;
                }
                T::assertTrue($forbidden, 'BD Manager cannot delete inquiry');

                // Super Admin (id=1) executes delete -> success
                $deleted = $service->deletePartnership($id, 1);
                T::assertTrue($deleted, 'Super Admin successfully deletes inquiry');

                // Verify row gone
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM partnership_inquiries WHERE id = :id");
                $stmt->execute(['id' => $id]);
                T::assertEquals(0, (int)$stmt->fetchColumn(), 'Row must be purged from database');
            });

        });
    }
}
