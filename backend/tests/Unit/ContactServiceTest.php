<?php

declare(strict_types=1);

namespace Voak\Health\Tests\Unit;

use Voak\Health\Auth\RbacManager;
use Voak\Health\Core\Exceptions\DatabaseException;
use Voak\Health\Core\Exceptions\ForbiddenException;
use Voak\Health\Core\Exceptions\HttpException;
use Voak\Health\Core\Exceptions\NotFoundException;
use Voak\Health\Core\Exceptions\ValidationException;
use Voak\Health\Database\Connection;
use Voak\Health\Database\Migration\MigrationManager;
use Voak\Health\Security\AuditLogger;
use Voak\Health\Security\HtmlSanitizer;
use Voak\Health\Security\RateLimiter;
use Voak\Health\Services\ContactService;
use Voak\Health\Tests\TestFramework as T;

/**
 * Unit Test Suite for ContactService (Phase 11: FR-ADM-010, FR-WF-001, SYS-005, ADR-008).
 */
class ContactServiceTest
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

        // Seed default Super Admin (id=1) and BD Manager (id=2) and Content Editor (id=3)
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

    private static function createService(\PDO $pdo, ?string $rateLimiterDir = null): ContactService
    {
        $conn = new Connection();
        $conn->setMockPdo($pdo);
        $audit = new AuditLogger();
        $sanitizer = new HtmlSanitizer();
        $rateLimiter = $rateLimiterDir !== null ? new RateLimiter($rateLimiterDir) : new RateLimiter();
        $rbac = new RbacManager();

        return new ContactService($conn, $audit, $sanitizer, $rateLimiter, $rbac);
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
        T::suite('ContactService Unit Tests', function () {

            T::test('Public submission successfully creates inquiry and queues confirmation email', function () {
                $pdo = self::createDatabase();
                $rateLimiterDir = sys_get_temp_dir() . '/voak_rl_test_' . bin2hex(random_bytes(4));
                $service = self::createService($pdo, $rateLimiterDir);

                $payload = [
                    'full_name'         => 'Dr. Sarah Mitchell',
                    'organization_name' => 'Metro Public Health Alliance',
                    'corporate_email'   => 'sarah.mitchell@metrohealth.gov.au',
                    'contact_phone'     => '+61 2 9876 5432',
                    'inquiry_details'   => 'Requesting enterprise proposal for clinical workforce optimization across 4 metropolitan healthcare facilities.',
                ];

                $result = $service->submitInquiry($payload, '192.168.1.100', 'Mozilla/5.0');

                T::assertEquals('success', $result['status'], 'Submission status should be success');
                T::assertTrue(str_starts_with($result['reference_code'], 'CNT-'), 'Reference code must start with CNT-');
                T::assertTrue(isset($result['id']) && $result['id'] > 0, 'Inquiry ID must be returned');

                // Verify database row
                $inquiry = $service->getInquiryById((int)$result['id']);
                T::assertEquals('NEW', $inquiry['status'], 'Initial status must be NEW');
                T::assertEquals('Dr. Sarah Mitchell', $inquiry['full_name'], 'Full name must match');
                T::assertEquals('sarah.mitchell@metrohealth.gov.au', $inquiry['corporate_email'], 'Email must match');
                T::assertTrue($inquiry['assigned_user_id'] === null, 'Assigned user should initially be null');

                // Verify email_queue row
                $queueStmt = $pdo->prepare("SELECT * FROM email_queue WHERE recipient_email = :email");
                $queueStmt->execute(['email' => 'sarah.mitchell@metrohealth.gov.au']);
                $queueRow = $queueStmt->fetch(\PDO::FETCH_ASSOC);
                $queueStmt->closeCursor();

                T::assertTrue($queueRow !== false, 'Email queue row must exist');
                T::assertEquals('CONTACT_INQUIRY_CONFIRMATION', $queueRow['template_key'], 'Template key must match');
                T::assertEquals('QUEUED', $queueRow['status'], 'Queue status must be QUEUED');

                self::recursiveDelete($rateLimiterDir);
            });

            T::test('Honeypot submission drops silently without mutating database or email queue (SYS-005)', function () {
                $pdo = self::createDatabase();
                $rateLimiterDir = sys_get_temp_dir() . '/voak_rl_test_' . bin2hex(random_bytes(4));
                $service = self::createService($pdo, $rateLimiterDir);

                $botPayload = [
                    'full_name'         => 'Spam Bot 3000',
                    'organization_name' => 'SEO Spammer LLC',
                    'corporate_email'   => 'bot@spam.com',
                    'contact_phone'     => '+1 555 123 4567',
                    'inquiry_details'   => 'Buy cheap SEO backlinks fast!',
                    'hp_company_field'  => 'Gotcha Bot Honeypot Field',
                ];

                $result = $service->submitInquiry($botPayload, '10.0.0.99', 'BotAgent/1.0');

                T::assertEquals('success', $result['status'], 'Bot response must simulate success');
                T::assertTrue(str_starts_with($result['reference_code'], 'CNT-'), 'Bot gets simulated reference code');

                // Verify zero records inserted
                $count = (int)$pdo->query("SELECT COUNT(*) FROM contact_inquiries")->fetchColumn();
                T::assertEquals(0, $count, 'No contact inquiry must be persisted for honeypot bot');

                $emailCount = (int)$pdo->query("SELECT COUNT(*) FROM email_queue")->fetchColumn();
                T::assertEquals(0, $emailCount, 'No email must be queued for honeypot bot');

                self::recursiveDelete($rateLimiterDir);
            });

            T::test('IP rate limiter blocks 6th submission within 1 hour window (SYS-005)', function () {
                $pdo = self::createDatabase();
                $rateLimiterDir = sys_get_temp_dir() . '/voak_rl_test_' . bin2hex(random_bytes(4));
                $service = self::createService($pdo, $rateLimiterDir);

                $payload = [
                    'full_name'         => 'Test Submitter',
                    'organization_name' => 'Test Org',
                    'corporate_email'   => 'submitter@test.org',
                    'contact_phone'     => '+61 2 9999 8888',
                    'inquiry_details'   => 'Legitimate testing inquiry details payload.',
                ];

                $ip = '203.0.113.42';

                // First 5 submissions must succeed
                for ($i = 1; $i <= 5; $i++) {
                    $res = $service->submitInquiry($payload, $ip);
                    T::assertEquals('success', $res['status'], "Submission {$i} must succeed");
                }

                // 6th submission must throw 429 HttpException
                $threw429 = false;
                try {
                    $service->submitInquiry($payload, $ip);
                } catch (HttpException $e) {
                    $threw429 = true;
                    T::assertEquals(429, $e->getStatusCode(), 'Status code must be 429');
                    T::assertEquals('ERR_RATE_LIMITED', $e->getErrorCode(), 'Error code must be ERR_RATE_LIMITED');
                }

                T::assertTrue($threw429, 'Rate limiter must enforce max 5 submissions/hour');

                self::recursiveDelete($rateLimiterDir);
            });

            T::test('Validation rejects malformed email, short details, and invalid phone', function () {
                $pdo = self::createDatabase();
                $rateLimiterDir = sys_get_temp_dir() . '/voak_rl_test_' . bin2hex(random_bytes(4));
                $service = self::createService($pdo, $rateLimiterDir);

                // Invalid email
                $threwValidation = false;
                try {
                    $service->submitInquiry([
                        'full_name'         => 'Alice',
                        'organization_name' => 'Alice Org',
                        'corporate_email'   => 'not-an-email',
                        'contact_phone'     => '+61 412 345 678',
                        'inquiry_details'   => 'Valid details with plenty of content.',
                    ]);
                } catch (ValidationException $e) {
                    $threwValidation = true;
                    T::assertTrue(isset($e->getValidationErrors()['corporate_email']), 'corporate_email error expected');
                }
                T::assertTrue($threwValidation, 'Invalid email must fail validation');

                // Short details (< 10 chars)
                $threwValidation = false;
                try {
                    $service->submitInquiry([
                        'full_name'         => 'Alice',
                        'organization_name' => 'Alice Org',
                        'corporate_email'   => 'alice@test.org',
                        'contact_phone'     => '+61 412 345 678',
                        'inquiry_details'   => 'Too short',
                    ]);
                } catch (ValidationException $e) {
                    $threwValidation = true;
                    T::assertTrue(isset($e->getValidationErrors()['inquiry_details']), 'inquiry_details error expected');
                }
                T::assertTrue($threwValidation, 'Short details must fail validation');

                // Invalid phone format
                $threwValidation = false;
                try {
                    $service->submitInquiry([
                        'full_name'         => 'Alice',
                        'organization_name' => 'Alice Org',
                        'corporate_email'   => 'alice@test.org',
                        'contact_phone'     => 'abc',
                        'inquiry_details'   => 'Valid details with plenty of content.',
                    ]);
                } catch (ValidationException $e) {
                    $threwValidation = true;
                    T::assertTrue(isset($e->getValidationErrors()['contact_phone']), 'contact_phone error expected');
                }
                T::assertTrue($threwValidation, 'Invalid phone format must fail validation');

                self::recursiveDelete($rateLimiterDir);
            });

            T::test('DAG Lifecycle state machine enforces valid transitions (FR-WF-001)', function () {
                $pdo = self::createDatabase();
                $rateLimiterDir = sys_get_temp_dir() . '/voak_rl_test_' . bin2hex(random_bytes(4));
                $service = self::createService($pdo, $rateLimiterDir);

                $created = $service->submitInquiry([
                    'full_name'         => 'Director Jones',
                    'organization_name' => 'Regional Health Network',
                    'corporate_email'   => 'jones@regionalhealth.org',
                    'contact_phone'     => '+61 3 9555 1234',
                    'inquiry_details'   => 'Inquiry regarding regional workforce clinical staffing models.',
                ]);
                $id = (int)$created['id'];

                // Step 1: NEW -> TRIAGED
                $s1 = $service->updateStatus($id, 'TRIAGED', 'Initial triage complete by BD team', 2);
                T::assertEquals('TRIAGED', $s1['status'], 'Status must transition to TRIAGED');

                // Step 2: TRIAGED -> IN_REVIEW
                $s2 = $service->updateStatus($id, 'IN_REVIEW', null, 2);
                T::assertEquals('IN_REVIEW', $s2['status'], 'Status must transition to IN_REVIEW');

                // Step 3: IN_REVIEW -> CONTACTED
                $s3 = $service->updateStatus($id, 'CONTACTED', 'Spoke with Director Jones on the phone', 2);
                T::assertEquals('CONTACTED', $s3['status'], 'Status must transition to CONTACTED');

                // Step 4: CONTACTED -> RESOLVED
                $s4 = $service->updateStatus($id, 'RESOLVED', 'Proposal requirements scoped and approved', 2);
                T::assertEquals('RESOLVED', $s4['status'], 'Status must transition to RESOLVED');

                // Step 5: RESOLVED -> CLOSED (with resolution note)
                $s5 = $service->updateStatus($id, 'CLOSED', 'Enterprise commercial proposal contract executed', 1);
                T::assertEquals('CLOSED', $s5['status'], 'Status must transition to CLOSED');

                // Step 6: CLOSED -> IN_REVIEW (reopening)
                $s6 = $service->updateStatus($id, 'IN_REVIEW', 'Reopened due to scope amendment requested', 1);
                T::assertEquals('IN_REVIEW', $s6['status'], 'Status must reopen to IN_REVIEW');

                // Step 7: IN_REVIEW -> SPAM
                $s7 = $service->updateStatus($id, 'SPAM', 'Flagged as spam', 1);
                T::assertEquals('SPAM', $s7['status'], 'Status must transition to SPAM');

                // Step 8: SPAM -> NEW (restoration of false positive)
                $s8 = $service->updateStatus($id, 'NEW', 'Restored false positive', 1);
                T::assertEquals('NEW', $s8['status'], 'Status must restore to NEW');

                self::recursiveDelete($rateLimiterDir);
            });

            T::test('DAG Lifecycle state machine strictly rejects illegal transitions (FR-WF-001)', function () {
                $pdo = self::createDatabase();
                $rateLimiterDir = sys_get_temp_dir() . '/voak_rl_test_' . bin2hex(random_bytes(4));
                $service = self::createService($pdo, $rateLimiterDir);

                $created = $service->submitInquiry([
                    'full_name'         => 'Test Partner',
                    'organization_name' => 'Clinic Beta',
                    'corporate_email'   => 'beta@clinic.org',
                    'contact_phone'     => '+61 7 3123 4567',
                    'inquiry_details'   => 'Clinical inquiry testing invalid DAG jumps.',
                ]);
                $id = (int)$created['id'];

                // 1. NEW cannot jump directly to RESOLVED
                $threwIllegal = false;
                try {
                    $service->updateStatus($id, 'RESOLVED', null, 1);
                } catch (ValidationException $e) {
                    $threwIllegal = true;
                    T::assertEquals('ERR_INVALID_TRANSITION', $e->getErrorCode(), 'Error code must be ERR_INVALID_TRANSITION');
                }
                T::assertTrue($threwIllegal, 'NEW -> RESOLVED must be rejected');

                // 2. NEW cannot jump directly to CLOSED
                $threwIllegal = false;
                try {
                    $service->updateStatus($id, 'CLOSED', 'Quick close', 1);
                } catch (ValidationException $e) {
                    $threwIllegal = true;
                    T::assertEquals('ERR_INVALID_TRANSITION', $e->getErrorCode(), 'Error code must be ERR_INVALID_TRANSITION');
                }
                T::assertTrue($threwIllegal, 'NEW -> CLOSED must be rejected');

                // Move to CLOSED legally
                $service->updateStatus($id, 'IN_REVIEW', null, 1);
                $service->updateStatus($id, 'CONTACTED', null, 1);
                $service->updateStatus($id, 'CLOSED', 'Closing note', 1);

                // 3. CLOSED cannot jump directly to RESOLVED (can only reopen to IN_REVIEW)
                $threwIllegal = false;
                try {
                    $service->updateStatus($id, 'RESOLVED', null, 1);
                } catch (ValidationException $e) {
                    $threwIllegal = true;
                    T::assertEquals('ERR_INVALID_TRANSITION', $e->getErrorCode(), 'Error code must be ERR_INVALID_TRANSITION');
                }
                T::assertTrue($threwIllegal, 'CLOSED -> RESOLVED must be rejected');

                self::recursiveDelete($rateLimiterDir);
            });

            T::test('Transition to CLOSED requires a mandatory internal resolution note', function () {
                $pdo = self::createDatabase();
                $rateLimiterDir = sys_get_temp_dir() . '/voak_rl_test_' . bin2hex(random_bytes(4));
                $service = self::createService($pdo, $rateLimiterDir);

                $created = $service->submitInquiry([
                    'full_name'         => 'Doctor Watson',
                    'organization_name' => 'Baker Street Health',
                    'corporate_email'   => 'watson@baker.org',
                    'contact_phone'     => '+61 2 9111 2222',
                    'inquiry_details'   => 'Clinical inquiry without existing notes.',
                ]);
                $id = (int)$created['id'];

                $service->updateStatus($id, 'IN_REVIEW', null, 1);
                $service->updateStatus($id, 'CONTACTED', null, 1);

                // Attempting to close with empty note and empty existing notes must fail
                $threwNoteRequired = false;
                try {
                    $service->updateStatus($id, 'CLOSED', '   ', 1);
                } catch (ValidationException $e) {
                    $threwNoteRequired = true;
                    T::assertTrue(str_contains($e->getMessage(), 'resolution note'), 'Must complain about missing resolution note');
                }
                T::assertTrue($threwNoteRequired, 'Closing without note must be blocked');

                // Closing with non-empty note must succeed
                $closed = $service->updateStatus($id, 'CLOSED', 'Successfully resolved with client over phone.', 1);
                T::assertEquals('CLOSED', $closed['status'], 'Must succeed when note provided');
                T::assertTrue(str_contains((string)$closed['internal_notes'], 'Successfully resolved with client'), 'Note must be persisted');

                self::recursiveDelete($rateLimiterDir);
            });

            T::test('Internal CRM notes formatting, chronological ordering, and notes_history parser', function () {
                $pdo = self::createDatabase();
                $rateLimiterDir = sys_get_temp_dir() . '/voak_rl_test_' . bin2hex(random_bytes(4));
                $service = self::createService($pdo, $rateLimiterDir);

                $created = $service->submitInquiry([
                    'full_name'         => 'Hospital Director',
                    'organization_name' => 'St Jude Healthcare',
                    'corporate_email'   => 'director@stjude.org',
                    'contact_phone'     => '+61 3 9888 7777',
                    'inquiry_details'   => 'Clinical staff expansion for surgical department.',
                ]);
                $id = (int)$created['id'];

                // Add Note 1 by BD Manager (id=2)
                $service->addNote($id, 'Spoke with Director. Scheduling follow-up call for next Tuesday.', 2);

                // Add Note 2 by Super Admin (id=1)
                $updated = $service->addNote($id, 'Approved rate card discount for preliminary proposal.', 1);

                T::assertTrue(count($updated['notes_history']) === 2, 'Must contain 2 parsed note entries');
                T::assertEquals('BD Manager #2', $updated['notes_history'][0]['author'], 'First note author must be BD Manager');
                T::assertEquals('Super Admin #1', $updated['notes_history'][1]['author'], 'Second note author must be Super Admin');
                T::assertTrue(str_contains($updated['notes_history'][0]['note'], 'Scheduling follow-up call'), 'First note content matches');
                T::assertTrue(str_contains($updated['notes_history'][1]['note'], 'Approved rate card discount'), 'Second note content matches');

                self::recursiveDelete($rateLimiterDir);
            });

            T::test('Team assignment updates assigned_user_id with existence validation', function () {
                $pdo = self::createDatabase();
                $rateLimiterDir = sys_get_temp_dir() . '/voak_rl_test_' . bin2hex(random_bytes(4));
                $service = self::createService($pdo, $rateLimiterDir);

                $created = $service->submitInquiry([
                    'full_name'         => 'Nurse Manager',
                    'organization_name' => 'Regional Clinic',
                    'corporate_email'   => 'nurse@clinic.org',
                    'contact_phone'     => '+61 2 9222 3333',
                    'inquiry_details'   => 'Seeking locum nurses for urgent weekend shifts.',
                ]);
                $id = (int)$created['id'];

                // Assign to BD Manager (user_id = 2)
                $assigned = $service->assignInquiry($id, 2, 1);
                T::assertEquals(2, (int)$assigned['assigned_user_id'], 'Assigned user ID must be 2');
                T::assertEquals('BD Manager', $assigned['assigned_user_name'], 'Assigned user name must be BD Manager');

                // Unassign (user_id = null)
                $unassigned = $service->assignInquiry($id, null, 1);
                T::assertTrue($unassigned['assigned_user_id'] === null, 'Assigned user ID must be null after unassign');

                // Assign to non-existent user ID throws validation exception
                $threwNotFound = false;
                try {
                    $service->assignInquiry($id, 99999, 1);
                } catch (ValidationException $e) {
                    $threwNotFound = true;
                }
                T::assertTrue($threwNotFound, 'Assigning to non-existent user must fail validation');

                self::recursiveDelete($rateLimiterDir);
            });

            T::test('Inquiry deletion is restricted to Super Admin role and denied to unprivileged roles', function () {
                $pdo = self::createDatabase();
                $rateLimiterDir = sys_get_temp_dir() . '/voak_rl_test_' . bin2hex(random_bytes(4));
                $service = self::createService($pdo, $rateLimiterDir);

                $created = $service->submitInquiry([
                    'full_name'         => 'Test Delete',
                    'organization_name' => 'Delete Org',
                    'corporate_email'   => 'delete@test.org',
                    'contact_phone'     => '+61 2 9333 4444',
                    'inquiry_details'   => 'Inquiry meant for deletion authorization testing.',
                ]);
                $id = (int)$created['id'];

                // BD Manager (user_id = 2) lacks delete permission -> ForbiddenException (403)
                $threwForbidden = false;
                try {
                    $service->deleteInquiry($id, 2);
                } catch (ForbiddenException $e) {
                    $threwForbidden = true;
                    T::assertEquals(403, $e->getStatusCode(), 'Must return HTTP 403');
                    T::assertEquals('ERR_FORBIDDEN', $e->getErrorCode(), 'Must return ERR_FORBIDDEN');
                }
                T::assertTrue($threwForbidden, 'BD Manager must be forbidden from deleting enquiries');

                // Super Admin (user_id = 1) is authorized -> succeeds
                $deleted = $service->deleteInquiry($id, 1);
                T::assertTrue($deleted, 'Super Admin must successfully delete enquiry');

                // Verify inquiry is gone
                $threwNotFound = false;
                try {
                    $service->getInquiryById($id);
                } catch (NotFoundException $e) {
                    $threwNotFound = true;
                }
                T::assertTrue($threwNotFound, 'Deleted inquiry must no longer exist');

                self::recursiveDelete($rateLimiterDir);
            });

            T::test('CSV Export generates RFC 4180 CSV with UTF-8 BOM and headers', function () {
                $pdo = self::createDatabase();
                $rateLimiterDir = sys_get_temp_dir() . '/voak_rl_test_' . bin2hex(random_bytes(4));
                $service = self::createService($pdo, $rateLimiterDir);

                $service->submitInquiry([
                    'full_name'         => 'Dr. Emma Watson',
                    'organization_name' => 'Sydney Health Partners',
                    'corporate_email'   => 'emma@sydneyhealth.org',
                    'contact_phone'     => '+61 2 9000 1111',
                    'inquiry_details'   => 'Clinical staffing inquiry details for export verification.',
                ]);

                // BD Manager has export permission
                $csv = $service->exportCsv([], 2);

                T::assertTrue(str_starts_with($csv, "\xEF\xBB\xBF"), 'CSV must begin with UTF-8 BOM');
                T::assertTrue(str_contains($csv, 'Reference Code') && str_contains($csv, 'Corporate Email'), 'CSV header row present');
                T::assertTrue(str_contains($csv, 'Sydney Health Partners'), 'CSV contains organization name');
                T::assertTrue(str_contains($csv, 'emma@sydneyhealth.org'), 'CSV contains corporate email');

                // Content Editor lacks export permission -> ForbiddenException
                $threwForbidden = false;
                try {
                    $service->exportCsv([], 3);
                } catch (ForbiddenException $e) {
                    $threwForbidden = true;
                    T::assertEquals(403, $e->getStatusCode(), 'Must return HTTP 403');
                }
                T::assertTrue($threwForbidden, 'Content Editor must be forbidden from downloading CSV export');

                self::recursiveDelete($rateLimiterDir);
            });

            T::test('List inquiries provides filtering, pagination, and status distribution counts', function () {
                $pdo = self::createDatabase();
                $rateLimiterDir = sys_get_temp_dir() . '/voak_rl_test_' . bin2hex(random_bytes(4));
                $service = self::createService($pdo, $rateLimiterDir);

                for ($i = 1; $i <= 3; $i++) {
                    $service->submitInquiry([
                        'full_name'         => "Doctor {$i}",
                        'organization_name' => "Clinic {$i}",
                        'corporate_email'   => "doc{$i}@clinic.org",
                        'contact_phone'     => "+61 2 9000 000{$i}",
                        'inquiry_details'   => "Clinical inquiry description {$i}",
                    ], "10.0.1.{$i}");
                }

                $list = $service->listInquiries([], 1, 10);
                T::assertEquals(3, $list['pagination']['total_items'], 'Total items must be 3');
                T::assertEquals(3, $list['counts_by_status']['NEW'], 'All 3 items must be in NEW status');
                T::assertEquals(3, $list['counts_by_status']['ALL'], 'ALL status count must be 3');

                // Filter by search
                $searchResult = $service->listInquiries(['search' => 'Clinic 2'], 1, 10);
                T::assertEquals(1, $searchResult['pagination']['total_items'], 'Search for Clinic 2 must return 1 item');
                T::assertEquals('Clinic 2', $searchResult['items'][0]['organization_name'], 'Matched item organization matches');

                self::recursiveDelete($rateLimiterDir);
            });

        });
    }
}
