<?php

declare(strict_types=1);

namespace Voak\Health\Tests\Unit;

use Voak\Health\Auth\RbacManager;
use Voak\Health\Core\Exceptions\ConflictException;
use Voak\Health\Core\Exceptions\HttpException;
use Voak\Health\Core\Exceptions\NotFoundException;
use Voak\Health\Core\Exceptions\ValidationException;
use Voak\Health\Database\Connection;
use Voak\Health\Database\Migration\MigrationManager;
use Voak\Health\Security\AuditLogger;
use Voak\Health\Security\HtmlSanitizer;
use Voak\Health\Security\RateLimiter;
use Voak\Health\Services\CareerService;
use Voak\Health\Services\EmailService;
use Voak\Health\Services\Mail\ArrayTransport;
use Voak\Health\Services\MediaStorageService;
use Voak\Health\Tests\TestFramework as T;

/**
 * Unit Test Suite for CareerService (Phase 14: FR-ADM-014, FR-ADM-015, FR-WF-004, FR-SEC-004, TEST-ADM-014, TEST-ADM-015, TEST-WF-004).
 */
class CareerServiceTest
{
    private static ?string $tempQuarantineDir = null;

    private static function getQuarantineDir(): string
    {
        if (self::$tempQuarantineDir === null) {
            self::$tempQuarantineDir = sys_get_temp_dir() . '/voak_test_quarantine_' . bin2hex(random_bytes(4));
            @mkdir(self::$tempQuarantineDir, 0700, true);
        }
        return self::$tempQuarantineDir;
    }

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

        // Seed administrative users
        $pdo->exec("INSERT INTO users (id, name, email, password_hash, is_active, created_at, updated_at) 
                    VALUES (1, 'Super Admin', 'admin@voak.test', 'hash', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
        $pdo->exec("INSERT INTO users (id, name, email, password_hash, is_active, created_at, updated_at) 
                    VALUES (2, 'HR Manager', 'hr@voak.test', 'hash', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
        $pdo->exec("INSERT INTO users (id, name, email, password_hash, is_active, created_at, updated_at) 
                    VALUES (3, 'Content Editor', 'editor@voak.test', 'hash', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");

        $rbac = new RbacManager();
        $rbac->seedRbac($pdo);
        $rbac->assignRole($pdo, 1, RbacManager::ROLE_SUPER_ADMIN);
        $rbac->assignRole($pdo, 2, RbacManager::ROLE_HR_MANAGER);
        $rbac->assignRole($pdo, 3, RbacManager::ROLE_CONTENT_EDITOR);

        return $pdo;
    }

    private static function createService(\PDO $pdo, ?RateLimiter $rateLimiter = null): CareerService
    {
        $conn = new Connection();
        $conn->setMockPdo($pdo);
        $audit = new AuditLogger();
        $sanitizer = new HtmlSanitizer();

        $quarantineDir = self::getQuarantineDir();
        $publicDir = sys_get_temp_dir() . '/voak_test_public_' . bin2hex(random_bytes(4));
        @mkdir($publicDir, 0755, true);

        $mediaStorage = new MediaStorageService($conn, $audit, $publicDir, $quarantineDir);
        $transport = new ArrayTransport();
        $emailService = new EmailService($conn, $audit, $sanitizer, $transport);
        $rlDir = sys_get_temp_dir() . '/voak_test_rl_' . bin2hex(random_bytes(6));
        $rateLimiter = $rateLimiter ?? new RateLimiter($rlDir);

        return new CareerService($conn, $mediaStorage, $audit, $sanitizer, $emailService, $rateLimiter);
    }

    private static function createMockResumeFile(string $extension = 'pdf', string $prefix = '%PDF-1.5 test resume'): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'test_resume_');
        file_put_contents($tmp, $prefix . "\nCandidate resume content");
        return [
            'tmp_name' => $tmp,
            'name'     => "jane_doe_cv.{$extension}",
            'size'     => filesize($tmp),
            'error'    => UPLOAD_ERR_OK,
        ];
    }

    public static function run(): void
    {
        T::suite("CareerService Unit Tests (Phase 14)", function () {
            // 1. Job Vacancy Creation
            T::test("creates a job vacancy with validated data and writes audit record (FR-ADM-014)", function () {
            $pdo = self::createDatabase();
            $service = self::createService($pdo);

            $jobData = [
                'title'            => 'Senior Critical Care Registered Nurse',
                'slug'             => 'senior-critical-care-rn',
                'department'       => 'Intensive Care Unit',
                'employment_type'  => 'FULL_TIME',
                'location'         => 'Sydney Hospital Campus',
                'summary'          => 'Provide comprehensive high-acuity nursing care to critically ill patients.',
                'responsibilities' => ['Administer intensive care interventions', 'Lead junior nursing staff'],
                'requirements'     => ['Current AHPRA registration', 'Postgraduate ICU certificate'],
                'is_active'        => 1,
                'closing_date'     => date('Y-m-d', strtotime('+30 days')),
            ];

            $created = $service->createJob($jobData, 2);

            T::assertIsArray($created);
            T::assertEquals('Senior Critical Care Registered Nurse', $created['title']);
            T::assertEquals('senior-critical-care-rn', $created['slug']);
            T::assertEquals('Intensive Care Unit', $created['department']);
            T::assertEquals('FULL_TIME', $created['employment_type']);
            T::assertEquals(1, $created['is_active']);
            T::assertEquals('PUBLISHED', $created['status']);
            T::assertCount(2, $created['responsibilities']);
            T::assertCount(2, $created['requirements']);

            // Verify audit log
            $stmt = $pdo->query("SELECT * FROM audit_logs WHERE action = 'JOB_POSTED'");
            $audit = $stmt->fetch(\PDO::FETCH_ASSOC);
            T::assertNotEmpty($audit);
            T::assertEquals('job_postings', $audit['entity_type']);
        });

        // 2. Slug Uniqueness Enforcement
        T::test("rejects duplicate active slug with ValidationException (FR-ADM-014)", function () {
            $pdo = self::createDatabase();
            $service = self::createService($pdo);

            $jobData = [
                'title'           => 'Clinical Specialist',
                'slug'            => 'clinical-specialist',
                'department'      => 'Emergency',
                'employment_type' => 'FULL_TIME',
                'location'        => 'Melbourne',
                'summary'         => 'Emergency department specialist clinical support.',
            ];

            $service->createJob($jobData, 1);

            $caught = false;
            try {
                $service->createJob($jobData, 1);
            } catch (ValidationException $e) {
                $caught = true;
                T::assertEquals('ERR_VALIDATION', $e->getErrorCode());
            }

            T::assertTrue($caught, "Expected ValidationException on duplicate slug.");
        });

        // 3. Job Vacancy Update
        T::test("successfully updates an existing job vacancy (FR-ADM-014)", function () {
            $pdo = self::createDatabase();
            $service = self::createService($pdo);

            $job = $service->createJob([
                'title'           => 'Nurse Lead',
                'department'      => 'Aged Care',
                'employment_type' => 'PART_TIME',
                'location'        => 'Brisbane',
                'summary'         => 'Lead nurse for residential aged care.',
            ], 2);

            $updated = $service->updateJob((int)$job['id'], [
                'title'           => 'Lead Clinical Nurse Practitioner',
                'department'      => 'Aged Care & Rehabilitation',
                'employment_type' => 'FULL_TIME',
                'is_active'       => 0,
            ], 2);

            T::assertEquals('Lead Clinical Nurse Practitioner', $updated['title']);
            T::assertEquals('Aged Care & Rehabilitation', $updated['department']);
            T::assertEquals('FULL_TIME', $updated['employment_type']);
            T::assertEquals(0, $updated['is_active']);
            T::assertEquals('CLOSED', $updated['status']);
        });

        // 4. Job Deletion without Applicants
        T::test("allows deleting job vacancy when zero applicant records exist (TEST-ADM-014)", function () {
            $pdo = self::createDatabase();
            $service = self::createService($pdo);

            $job = $service->createJob([
                'title'           => 'Draft Vacancy',
                'department'      => 'Operations',
                'employment_type' => 'CASUAL',
                'location'        => 'Perth',
                'summary'         => 'Operational temporary coverage support.',
            ], 1);

            $res = $service->deleteJob((int)$job['id'], 1);
            T::assertTrue($res);

            T::assertNull($service->getJobById((int)$job['id']));
        });

        // 5. Referential Deletion Guard
        T::test("strictly blocks deleting a job vacancy with active applicant records (TEST-ADM-014)", function () {
            $pdo = self::createDatabase();
            $service = self::createService($pdo);

            $job = $service->createJob([
                'title'           => 'Clinical Nurse Lead',
                'department'      => 'Cardiology',
                'employment_type' => 'FULL_TIME',
                'location'        => 'Sydney',
                'summary'         => 'Cardiology nurse leadership opening.',
            ], 1);

            $file = self::createMockResumeFile();
            $service->submitApplication([
                'job_id'    => (int)$job['id'],
                'full_name' => 'Jane Candidate',
                'email'     => 'jane@example.test',
                'phone'     => '+61412345678',
            ], $file);
            @unlink($file['tmp_name']);

            $caught = false;
            try {
                $service->deleteJob((int)$job['id'], 1);
            } catch (ConflictException $e) {
                $caught = true;
                T::assertEquals('ERR_CONFLICT', $e->getErrorCode());
            }

            T::assertTrue($caught, "Expected ConflictException when deleting job with active applicant records.");
        });

        // 6. Public Application Submission & Email Queue Staging
        T::test("ingests candidate application with resume quarantine and 6-month retention date (FR-ADM-015 / FR-SEC-004)", function () {
            $pdo = self::createDatabase();
            $service = self::createService($pdo);

            $job = $service->createJob([
                'title'           => 'Registered Midwife',
                'department'      => 'Maternity',
                'employment_type' => 'FULL_TIME',
                'location'        => 'Adelaide',
                'summary'         => 'Midwifery services for regional hospital.',
            ], 1);

            $file = self::createMockResumeFile();
            $appResult = $service->submitApplication([
                'job_id'       => (int)$job['id'],
                'full_name'    => 'Sarah Connor, RN',
                'email'        => 'sarah.connor@hospital.test',
                'phone'        => '+61499887766',
                'country_code' => 'AU',
                'ahpra_number' => 'NMW0001234567',
            ], $file);
            @unlink($file['tmp_name']);

            T::assertEquals('success', $appResult['status']);
            T::assertStringStartsWith('APP-', $appResult['reference_code']);
            T::assertGreaterThan(0, $appResult['id']);

            // Verify retention expiry date is 6 months ahead
            $expectedRetention = date('Y-m-d', strtotime('+6 months'));
            T::assertEquals($expectedRetention, $appResult['retention_expiry_date']);

            // Verify database record
            $app = $service->getApplicationById((int)$appResult['id']);
            T::assertNotEmpty($app);
            T::assertEquals('Sarah Connor, RN', $app['full_name']);
            T::assertEquals('SUBMITTED', $app['status']);
            T::assertEquals('Registered Midwife', $app['job_title']);
            T::assertNotEmpty($app['quarantined_resume_path']);

            // Verify email queued
            $stmt = $pdo->query("SELECT * FROM email_queue WHERE recipient_email = 'sarah.connor@hospital.test'");
            $queueRow = $stmt->fetch(\PDO::FETCH_ASSOC);
            T::assertNotEmpty($queueRow);
            T::assertEquals('QUEUED', $queueRow['status']);
        });

        // 7. Honeypot Spam Protection
        T::test("silently drops honeypot bot submissions without mutating database (SYS-005)", function () {
            $pdo = self::createDatabase();
            $service = self::createService($pdo);

            $job = $service->createJob([
                'title'           => 'Clinical Staff',
                'department'      => 'General',
                'employment_type' => 'FULL_TIME',
                'location'        => 'Sydney',
                'summary'         => 'Staff role.',
            ], 1);

            $file = self::createMockResumeFile();
            $res = $service->submitApplication([
                'job_id'     => (int)$job['id'],
                'full_name'  => 'Spam Bot',
                'email'      => 'spambot@spam.com',
                'phone'      => '1234567890',
                'website'    => 'https://spam-link.ru', // Honeypot trap
            ], $file);
            @unlink($file['tmp_name']);

            T::assertEquals('success', $res['status']);
            T::assertEquals(0, $res['id']);

            $count = (int)$pdo->query("SELECT COUNT(*) FROM career_applications WHERE email = 'spambot@spam.com'")->fetchColumn();
            T::assertEquals(0, $count);
        });

        // 8. IP Rate Limiting
        T::test("blocks submissions exceeding 5 per hour per IP (SYS-005)", function () {
            $pdo = self::createDatabase();
            $rateLimiter = new RateLimiter();
            $service = self::createService($pdo, $rateLimiter);

            $job = $service->createJob([
                'title'           => 'Staff Nurse',
                'department'      => 'General',
                'employment_type' => 'FULL_TIME',
                'location'        => 'Sydney',
                'summary'         => 'Staff role.',
            ], 1);

            $ip = '203.0.113.88';

            // First 5 pass
            for ($i = 0; $i < 5; $i++) {
                $file = self::createMockResumeFile();
                $service->submitApplication([
                    'job_id'    => (int)$job['id'],
                    'full_name' => "Applicant {$i}",
                    'email'     => "applicant{$i}@test.com",
                    'phone'     => '+6140000000' . $i,
                ], $file, $ip);
                @unlink($file['tmp_name']);
            }

            // 6th is blocked
            $blocked = false;
            try {
                $file = self::createMockResumeFile();
                $service->submitApplication([
                    'job_id'    => (int)$job['id'],
                    'full_name' => "Applicant 6",
                    'email'     => "applicant6@test.com",
                    'phone'     => '+61400000006',
                ], $file, $ip);
                @unlink($file['tmp_name']);
            } catch (HttpException $e) {
                $blocked = true;
                T::assertEquals(429, $e->getStatusCode());
                T::assertEquals('ERR_RATE_LIMITED', $e->getErrorCode());
            }

            T::assertTrue($blocked, "Expected 6th application to be rate-limited (HTTP 429).");
        });

        // 9. Candidate Recruitment FSM Sequence (FR-WF-004 / TEST-WF-004)
        T::test("enforces strict recruitment progression: SUBMITTED -> REVIEWING -> SCREENING -> INTERVIEWING -> OFFER -> HIRED", function () {
            $pdo = self::createDatabase();
            $service = self::createService($pdo);

            $job = $service->createJob([
                'title'           => 'Surgeon Consultant',
                'department'      => 'Surgical',
                'employment_type' => 'FULL_TIME',
                'location'        => 'Canberra',
                'summary'         => 'Consultant surgeon posting.',
            ], 1);

            $file = self::createMockResumeFile();
            $app = $service->submitApplication([
                'job_id'    => (int)$job['id'],
                'full_name' => 'Dr. Gregory House',
                'email'     => 'house@princeton.test',
                'phone'     => '+61411223344',
            ], $file);
            @unlink($file['tmp_name']);

            $appId = (int)$app['id'];

            // 1. Advance to REVIEWING
            $step1 = $service->advanceCandidateStage($appId, 'REVIEWING', 'Resume credentials verified', null, 2);
            T::assertEquals('REVIEWING', $step1['status']);

            // 2. Advance to SCREENING
            $step2 = $service->advanceCandidateStage($appId, 'SCREENING', 'Phone screen scheduled', null, 2);
            T::assertEquals('SCREENING', $step2['status']);

            // 3. Advance to INTERVIEWING
            $step3 = $service->advanceCandidateStage($appId, 'INTERVIEWING', 'Formal panel interview conducted', null, 2);
            T::assertEquals('INTERVIEWING', $step3['status']);

            // 4. Advance to OFFER
            $step4 = $service->advanceCandidateStage($appId, 'OFFER', 'Formal employment offer dispatched', null, 2);
            T::assertEquals('OFFER', $step4['status']);

            // 5. Advance to HIRED
            $step5 = $service->advanceCandidateStage($appId, 'HIRED', 'Signed contract received and AHPRA validated', null, 1);
            T::assertEquals('HIRED', $step5['status']);
        });

        // 10. Rejection of Illegal FSM Transitions
        T::test("strictly rejects illegal state transitions skipping hiring stages (TEST-WF-004)", function () {
            $pdo = self::createDatabase();
            $service = self::createService($pdo);

            $job = $service->createJob([
                'title'           => 'Nurse',
                'department'      => 'General',
                'employment_type' => 'FULL_TIME',
                'location'        => 'Sydney',
                'summary'         => 'Nurse position.',
            ], 1);

            $file = self::createMockResumeFile();
            $app = $service->submitApplication([
                'job_id'    => (int)$job['id'],
                'full_name' => 'Candidate Alpha',
                'email'     => 'alpha@test.com',
                'phone'     => '+61411000111',
            ], $file);
            @unlink($file['tmp_name']);

            $appId = (int)$app['id'];

            // Attempt jumping directly from SUBMITTED to HIRED
            $caught = false;
            try {
                $service->advanceCandidateStage($appId, 'HIRED', 'Direct skip', null, 2);
            } catch (ValidationException $e) {
                $caught = true;
                T::assertEquals('ERR_INVALID_TRANSITION', $e->getErrorCode());
            }

            T::assertTrue($caught, "Expected ValidationException on illegal skip SUBMITTED -> HIRED.");
        });

        // 11. Mandatory Rejection Reason
        T::test("strictly requires rejection reason when moving candidate to REJECTED (TEST-WF-004)", function () {
            $pdo = self::createDatabase();
            $service = self::createService($pdo);

            $job = $service->createJob([
                'title'           => 'Specialist',
                'department'      => 'ICU',
                'employment_type' => 'FULL_TIME',
                'location'        => 'Sydney',
                'summary'         => 'Specialist job.',
            ], 1);

            $file = self::createMockResumeFile();
            $app = $service->submitApplication([
                'job_id'    => (int)$job['id'],
                'full_name' => 'Candidate Beta',
                'email'     => 'beta@test.com',
                'phone'     => '+61411000222',
            ], $file);
            @unlink($file['tmp_name']);

            $appId = (int)$app['id'];

            // Without reason -> fail
            $failed = false;
            try {
                $service->advanceCandidateStage($appId, 'REJECTED', 'Decline candidate', null, 2);
            } catch (ValidationException $e) {
                $failed = true;
                T::assertEquals('ERR_VALIDATION', $e->getErrorCode());
            }
            T::assertTrue($failed, "Expected error when rejection reason is missing.");

            // With mandatory reason -> success
            $rejected = $service->advanceCandidateStage($appId, 'REJECTED', 'Decline candidate', 'QUALIFICATION_MISMATCH', 2);
            T::assertEquals('REJECTED', $rejected['status']);
            T::assertEquals('QUALIFICATION_MISMATCH', $rejected['rejection_reason']);
        });

        // 12. Authenticated Quarantined Resume Streaming
        T::test("streams quarantined candidate resume with security headers (FR-ADM-015 / TEST-SEC-004)", function () {
            $pdo = self::createDatabase();
            $service = self::createService($pdo);

            $job = $service->createJob([
                'title'           => 'Physiotherapist',
                'department'      => 'Allied Health',
                'employment_type' => 'FULL_TIME',
                'location'        => 'Sydney',
                'summary'         => 'Physio posting.',
            ], 1);

            $file = self::createMockResumeFile('pdf', "%PDF-1.4 authentic pdf content\n");
            $app = $service->submitApplication([
                'job_id'    => (int)$job['id'],
                'full_name' => 'Alex Taylor',
                'email'     => 'alex@physio.test',
                'phone'     => '+61422334455',
            ], $file);
            @unlink($file['tmp_name']);

            $appId = (int)$app['id'];

            $streamResponse = $service->streamCandidateResume($appId, 2);

            T::assertEquals(200, $streamResponse->getStatusCode());
            T::assertEquals('application/pdf', $streamResponse->getHeader('Content-Type'));
            T::assertEquals('nosniff', $streamResponse->getHeader('X-Content-Type-Options'));
            T::assertStringContainsString('attachment', (string)$streamResponse->getHeader('Content-Disposition'));
            T::assertStringContainsString('jane_doe_cv.pdf', (string)$streamResponse->getHeader('Content-Disposition'));
        });

        // 13. CSV Export Formula Injection Defense
        T::test("exports applications to CSV neutralizing spreadsheet formula injection characters", function () {
            $pdo = self::createDatabase();
            $service = self::createService($pdo);

            $job = $service->createJob([
                'title'           => 'Lead Nurse',
                'department'      => 'Oncology',
                'employment_type' => 'FULL_TIME',
                'location'        => 'Sydney',
                'summary'         => 'Oncology nurse.',
            ], 1);

            $file = self::createMockResumeFile();
            $service->submitApplication([
                'job_id'    => (int)$job['id'],
                'full_name' => '=1+1 Dangerous Formula',
                'email'     => '+61400@injection.test',
                'phone'     => '-cmd| /C calc',
            ], $file);
            @unlink($file['tmp_name']);

            $csv = $service->exportApplicationsCsv([], 2);

            T::assertStringStartsWith("\xEF\xBB\xBF", $csv); // UTF-8 BOM
            T::assertStringContainsString("'=1+1 Dangerous Formula", $csv);
            T::assertStringContainsString("'+61400@injection.test", $csv);
            T::assertStringContainsString("'-cmd| /C calc", $csv);
        });

        // 14. Data Retention Cleanup & Anonymization
        T::test("anonymizes expired candidate PII and shreds physical files on disk (FR-SEC-004 / TEST-WF-004)", function () {
            $pdo = self::createDatabase();
            $service = self::createService($pdo);

            $job = $service->createJob([
                'title'           => 'Senior Clinician',
                'department'      => 'Clinical',
                'employment_type' => 'FULL_TIME',
                'location'        => 'Melbourne',
                'summary'         => 'Clinician role.',
            ], 1);

            $file = self::createMockResumeFile();
            $app = $service->submitApplication([
                'job_id'    => (int)$job['id'],
                'full_name' => 'Expired Applicant',
                'email'     => 'expired@privacy.test',
                'phone'     => '+61400111222',
            ], $file);
            @unlink($file['tmp_name']);

            $appId = (int)$app['id'];

            // Simulate expired retention date: set retention_expiry_date to yesterday
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            $pdo->exec("UPDATE career_applications SET retention_expiry_date = '{$yesterday}' WHERE id = {$appId}");

            $report = $service->cleanupExpiredApplications();

            T::assertEquals(1, $report['anonymized_count']);
            T::assertGreaterThanOrEqual(1, $report['shredded_files_count']);

            // Verify record is anonymized
            $cleaned = $service->getApplicationById($appId);
            T::assertEquals('[ANONYMIZED]', $cleaned['full_name']);
            T::assertEquals('[ANONYMIZED]', $cleaned['email']);
            T::assertEquals('[ANONYMIZED]', $cleaned['phone']);
            T::assertNull($cleaned['ahpra_number']);

            // Verify physical file was shredded/removed
            T::assertFalse(file_exists($cleaned['quarantined_resume_path']));

            // Verify audit event logged
            $stmt = $pdo->query("SELECT * FROM audit_logs WHERE action = 'RECRUITMENT_RECORD_ANONYMIZED'");
            $audit = $stmt->fetch(\PDO::FETCH_ASSOC);
            T::assertNotEmpty($audit);
        });
        });
    }
}
