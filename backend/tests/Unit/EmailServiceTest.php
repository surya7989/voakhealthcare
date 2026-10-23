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
use Voak\Health\Services\EmailService;
use Voak\Health\Services\Mail\ArrayTransport;
use Voak\Health\Tests\TestFramework as T;

/**
 * Unit Test Suite for EmailService (Phase 13: FR-ADM-013, ADR-006, TEST-ADM-013, TEST-WF-001, TEST-SEC-005).
 */
class EmailServiceTest
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

        // Seed administrative users
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

    private static function createService(\PDO $pdo, ?ArrayTransport $transport = null): EmailService
    {
        $conn = new Connection();
        $conn->setMockPdo($pdo);
        $audit = new AuditLogger();
        $sanitizer = new HtmlSanitizer();
        $mailTransport = $transport ?? new ArrayTransport();

        return new EmailService($conn, $audit, $sanitizer, $mailTransport);
    }

    public static function run(): void
    {
        self::runAll();
    }

    public static function runAll(): void
    {
        T::suite('EmailService Unit Tests (Phase 13)', function () {

            T::test('lists all 8 canonical email templates with trigger definitions', function () {
            $pdo = self::createDatabase();
            $service = self::createService($pdo);

            $templates = $service->getTemplates();
            T::assertEquals(8, count($templates), 'Should return exactly 8 canonical email templates.');

            $keys = array_column($templates, 'template_key');
            T::assertTrue(in_array('tmpl_contact_ack', $keys, true), 'tmpl_contact_ack must be present.');
            T::assertTrue(in_array('tmpl_contact_internal', $keys, true), 'tmpl_contact_internal must be present.');
            T::assertTrue(in_array('tmpl_partner_ack', $keys, true), 'tmpl_partner_ack must be present.');
            T::assertTrue(in_array('tmpl_proposal_ack', $keys, true), 'tmpl_proposal_ack must be present.');
            T::assertTrue(in_array('tmpl_careers_ack', $keys, true), 'tmpl_careers_ack must be present.');
            T::assertTrue(in_array('tmpl_admin_invite', $keys, true), 'tmpl_admin_invite must be present.');
            T::assertTrue(in_array('tmpl_password_reset', $keys, true), 'tmpl_password_reset must be present.');
            T::assertTrue(in_array('tmpl_security_alert', $keys, true), 'tmpl_security_alert must be present.');
        });

        T::test('fetches single template by ID, key, and slug', function () {
            $pdo = self::createDatabase();
            $service = self::createService($pdo);

            $byKey = $service->getTemplate('tmpl_contact_ack');
            T::assertEquals('tmpl_contact_ack', $byKey['template_key']);
            T::assertEquals(1, $byKey['id']);

            $byId = $service->getTemplate(1);
            T::assertEquals('tmpl_contact_ack', $byId['template_key']);

            $bySlug = $service->getTemplate('contact-inquiry-ack');
            T::assertEquals('tmpl_contact_ack', $bySlug['template_key']);

            try {
                $service->getTemplate('non_existent_key');
                T::assertTrue(false, 'Should throw NotFoundException for non-existent template.');
            } catch (NotFoundException $e) {
                T::assertTrue(true, 'NotFoundException successfully thrown.');
            }
        });

        T::test('enforces mandatory placeholder guard and blocks save if missing (TEST-ADM-013)', function () {
            $pdo = self::createDatabase();
            $service = self::createService($pdo);

            // Attempt to remove mandatory placeholder {{ref_no}} from tmpl_contact_ack
            try {
                $service->updateTemplate('tmpl_contact_ack', [
                    'subject'   => 'Inquiry Received Without Ref',
                    'body_html' => '<p>Hello {{contact_name}}, thank you for contacting {{legal_entity_name}}.</p>',
                ], 1);
                T::assertTrue(false, 'Should throw ValidationException when mandatory placeholder {{ref_no}} is missing.');
            } catch (ValidationException $e) {
                T::assertTrue(true, 'ValidationException successfully thrown.');
                $errors = $e->getValidationErrors();
                T::assertTrue(isset($errors['placeholders']), 'Error must mention missing placeholder.');
                T::assertTrue(str_contains($errors['placeholders'], '{{ref_no}}'), 'Error message must specify {{ref_no}}.');
            }
        });

        T::test('successfully updates template when mandatory placeholders are present', function () {
            $pdo = self::createDatabase();
            $service = self::createService($pdo);

            $customSubject = 'Welcome to {{legal_entity_name}} - Ref [{{ref_no}}]';
            $customBody = '<div class="email"><h3>Dear {{contact_name}}</h3><p>We received inquiry {{ref_no}} for {{legal_entity_name}}.</p></div>';

            $updated = $service->updateTemplate('tmpl_contact_ack', [
                'subject'   => $customSubject,
                'body_html' => $customBody,
            ], 1);

            T::assertEquals($customSubject, $updated['subject']);
            T::assertEquals($customBody, $updated['body_html']);

            // Verify persistence in system_settings
            $stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'template_tmpl_contact_ack'");
            $val = $stmt ? (string)$stmt->fetchColumn() : '';
            T::assertTrue(!empty($val), 'Override must be saved in system_settings.');
            T::assertTrue(str_contains($val, $customSubject), 'Saved value must contain new subject.');

            // Verify audit logging
            $auditStmt = $pdo->query("SELECT action FROM audit_logs WHERE action = 'EMAIL_TEMPLATE_UPDATED'");
            $loggedAction = $auditStmt ? (string)$auditStmt->fetchColumn() : '';
            T::assertEquals('EMAIL_TEMPLATE_UPDATED', $loggedAction, 'Audit event must be recorded.');
        });

        T::test('sends diagnostic test email with rendered legal variables (TEST-SEC-005)', function () {
            $pdo = self::createDatabase();
            $transport = new ArrayTransport();
            $service = self::createService($pdo, $transport);

            $result = $service->sendTestEmail('tmpl_contact_ack', 'admin@example.com', 1);
            T::assertTrue($result['success'], 'Test send must succeed.');
            T::assertEquals('admin@example.com', $result['recipient']);

            T::assertEquals(1, $transport->count(), 'Transport must have received exactly 1 message.');
            $sent = $transport->getSentMessages()[0];
            T::assertEquals('admin@example.com', $sent['to_email']);
            T::assertTrue(str_contains($sent['html_body'], 'Voak Health Care Pty Ltd'), 'Must render legal_entity_name.');
            T::assertTrue(str_contains($sent['html_body'], 'ABN 45 678 912 345'), 'Must render registration_number.');
        });

        T::test('enqueues transactional message into email_queue (Tier 2 durable outbox)', function () {
            $pdo = self::createDatabase();
            $service = self::createService($pdo);

            $id = $service->enqueue('tmpl_contact_ack', 'client@hospital.test', 'Dr. Smith', [
                'contact_name'      => 'Dr. Smith',
                'organization_name' => 'City Hospital',
                'ref_no'            => 'INQ-20261023-1122',
            ]);

            T::assertTrue($id > 0, 'Enqueue must return valid primary key.');

            $item = $service->getQueueItem($id);
            T::assertEquals('tmpl_contact_ack', $item['template_key']);
            T::assertEquals('client@hospital.test', $item['recipient_email']);
            T::assertEquals('Dr. Smith', $item['recipient_name']);
            T::assertEquals('QUEUED', $item['status']);
            T::assertEquals(0, $item['attempts']);
            T::assertEquals('INQ-20261023-1122', $item['payload']['ref_no']);
        });

        T::test('executes bounded fast-path for urgent security events and marks SENT (Tier 1)', function () {
            $pdo = self::createDatabase();
            $transport = new ArrayTransport();
            $service = self::createService($pdo, $transport);

            $sent = $service->sendUrgentFastPath(
                'tmpl_password_reset',
                'admin@voak.test',
                'Super Admin',
                [
                    'full_name'        => 'Super Admin',
                    'reset_link'       => 'https://voakhealthcare.com/reset?token=xyz123',
                    'request_ip'       => '127.0.0.1',
                    'request_datetime' => '2026-10-23 10:00:00',
                ]
            );

            T::assertTrue($sent, 'Fast-path must return true when transport succeeds.');
            T::assertEquals(1, $transport->count(), 'Message must be dispatched immediately via transport.');

            // Verify outbox row recorded as SENT
            $stmt = $pdo->query("SELECT status, attempts FROM email_queue WHERE template_key = 'tmpl_password_reset'");
            $row = $stmt ? $stmt->fetch(\PDO::FETCH_ASSOC) : null;
            T::assertEquals('SENT', $row['status']);
            T::assertEquals(1, (int)$row['attempts']);
        });

        T::test('fast-path safely falls back to QUEUED on transport failure without crashing web request (ADR-006)', function () {
            $pdo = self::createDatabase();
            $transport = new ArrayTransport();
            $transport->setFailing(true, 'Connection to mail.hostinger.com timed out after 3.0s');
            $service = self::createService($pdo, $transport);

            $sent = $service->sendUrgentFastPath(
                'tmpl_password_reset',
                'admin@voak.test',
                'Super Admin',
                [
                    'full_name'        => 'Super Admin',
                    'reset_link'       => 'https://voakhealthcare.com/reset?token=fallback_token',
                    'request_ip'       => '127.0.0.1',
                    'request_datetime' => '2026-10-23 10:00:00',
                ]
            );

            T::assertFalse($sent, 'Fast-path must return false when transport times out.');
            T::assertEquals(0, $transport->count(), 'Zero messages delivered on failed transport.');

            // Verify outbox row is stored as QUEUED with error captured
            $stmt = $pdo->query("SELECT status, error_message FROM email_queue WHERE template_key = 'tmpl_password_reset'");
            $row = $stmt ? $stmt->fetch(\PDO::FETCH_ASSOC) : null;
            T::assertEquals('QUEUED', $row['status']);
            T::assertTrue(str_contains((string)$row['error_message'], 'timed out'), 'Error message must be captured.');
        });

        T::test('supports filtering and pagination on outbox queue telemetry', function () {
            $pdo = self::createDatabase();
            $service = self::createService($pdo);

            // Enqueue several records
            $service->enqueue('tmpl_contact_ack', 'user1@test.com', 'User One', ['ref_no' => 'REF-1']);
            $service->enqueue('tmpl_partner_ack', 'user2@test.com', 'User Two', ['ref_no' => 'REF-2']);
            $service->enqueue('tmpl_proposal_ack', 'user3@test.com', 'User Three', ['ref_no' => 'REF-3']);

            $listAll = $service->listQueue([], 1, 10);
            T::assertEquals(3, $listAll['pagination']['total']);
            T::assertEquals(3, count($listAll['data']));

            // Filter by template_key
            $filtered = $service->listQueue(['template_key' => 'tmpl_partner_ack'], 1, 10);
            T::assertEquals(1, $filtered['pagination']['total']);
            T::assertEquals('tmpl_partner_ack', $filtered['data'][0]['template_key']);

            // Filter by search string
            $searched = $service->listQueue(['search' => 'user3'], 1, 10);
            T::assertEquals(1, $searched['pagination']['total']);
            T::assertEquals('user3@test.com', $searched['data'][0]['recipient_email']);
        });

        T::test('manually retries a failed outbox message and resets status to QUEUED (TEST-ADM-013)', function () {
            $pdo = self::createDatabase();
            $service = self::createService($pdo);

            $id = $service->enqueue('tmpl_contact_ack', 'client@test.com', 'Dr. Adams', ['ref_no' => 'INQ-100']);
            // Simulate 3 failures
            $pdo->exec("UPDATE email_queue SET status = 'FAILED', attempts = 3, error_message = 'SMTP 550 Mailbox unavailable' WHERE id = {$id}");

            $before = $service->getQueueItem($id);
            T::assertEquals('FAILED', $before['status']);
            T::assertEquals(3, $before['attempts']);

            // Execute manual retry
            $retried = $service->retryMessage($id, 1);
            T::assertEquals('QUEUED', $retried['status'], 'Status must be reset to QUEUED.');
            T::assertEquals(0, $retried['attempts'], 'Attempts counter must be reset to 0.');
            T::assertTrue($retried['error_message'] === null, 'Error message must be cleared.');

            // Verify audit logging
            $auditStmt = $pdo->query("SELECT action FROM audit_logs WHERE action = 'EMAIL_MANUAL_RETRY_TRIGGERED'");
            $action = $auditStmt ? (string)$auditStmt->fetchColumn() : '';
            T::assertEquals('EMAIL_MANUAL_RETRY_TRIGGERED', $action);
        });

        T::test('bulk retries all failed outbox messages', function () {
            $pdo = self::createDatabase();
            $service = self::createService($pdo);

            $id1 = $service->enqueue('tmpl_contact_ack', 'c1@test.com', 'Client 1', ['ref_no' => 'REF-A']);
            $id2 = $service->enqueue('tmpl_contact_ack', 'c2@test.com', 'Client 2', ['ref_no' => 'REF-B']);
            $pdo->exec("UPDATE email_queue SET status = 'FAILED', attempts = 3 WHERE id IN ({$id1}, {$id2})");

            $resetCount = $service->retryAllFailed(1);
            T::assertEquals(2, $resetCount, 'Must reset both failed rows.');

            $item1 = $service->getQueueItem($id1);
            $item2 = $service->getQueueItem($id2);
            T::assertEquals('QUEUED', $item1['status']);
            T::assertEquals('QUEUED', $item2['status']);
        });

        T::test('atomically claims batch of queued emails and recovers stale claims (TEST-WF-001)', function () {
            $pdo = self::createDatabase();
            $service = self::createService($pdo);

            $id1 = $service->enqueue('tmpl_contact_ack', 'q1@test.com', 'Q1', ['ref_no' => 'REF-Q1']);
            $id2 = $service->enqueue('tmpl_contact_ack', 'q2@test.com', 'Q2', ['ref_no' => 'REF-Q2']);

            // Create a stale claim (> 10m old)
            $idStale = $service->enqueue('tmpl_contact_ack', 'stale@test.com', 'Stale', ['ref_no' => 'REF-STALE']);
            $pdo->exec("UPDATE email_queue SET status = 'PROCESSING', claimed_at = datetime('now', '-10 minutes'), worker_id = 'crashed_worker' WHERE id = {$idStale}");

            // Worker 1 claims batch
            $claimed = $service->claimBatch(10, 'worker_alpha');
            T::assertEquals(3, count($claimed), 'Should recover stale item and claim all 3 queued items.');

            foreach ($claimed as $c) {
                T::assertEquals('PROCESSING', $c['status']);
                T::assertEquals('worker_alpha', $c['worker_id']);
            }
        });

        T::test('processes queue batch with exponential backoff on transient failure', function () {
            $pdo = self::createDatabase();
            $transport = new ArrayTransport();
            $service = self::createService($pdo, $transport);

            $id1 = $service->enqueue('tmpl_contact_ack', 'success@test.com', 'Success User', ['ref_no' => 'REF-OK']);
            $report = $service->processQueueBatch(10);

            T::assertEquals(1, $report['processed']);
            T::assertEquals(1, $report['sent']);
            T::assertEquals(0, $report['failed']);

            $item1 = $service->getQueueItem($id1);
            T::assertEquals('SENT', $item1['status']);
            T::assertTrue(!empty($item1['sent_at']), 'sent_at must be populated.');

            // Now test failure backoff
            $transport->setFailing(true, 'SMTP 421 Service not available, closing transmission channel');
            $id2 = $service->enqueue('tmpl_contact_ack', 'fail@test.com', 'Fail User', ['ref_no' => 'REF-FAIL']);

            $reportFail = $service->processQueueBatch(10);
            T::assertEquals(1, $reportFail['processed']);
            T::assertEquals(0, $reportFail['sent']);
            T::assertEquals(1, $reportFail['failed']);

            $item2 = $service->getQueueItem($id2);
            T::assertEquals('FAILED', $item2['status']);
            T::assertEquals(1, $item2['attempts']);
            T::assertTrue(!empty($item2['next_retry_at']), 'next_retry_at must be set for exponential backoff.');
            T::assertTrue(str_contains((string)$item2['error_message'], '421'), 'Error message must capture SMTP response.');
        });

        T::test('neutralizes HTML injection in template variable substitutions', function () {
            $pdo = self::createDatabase();
            $service = self::createService($pdo);

            $payload = [
                'contact_name'      => '<script>alert("xss")</script>Dr. Bob',
                'organization_name' => '<b>Health Corp</b>',
                'ref_no'            => 'INQ-999',
            ];

            $rendered = $service->renderString('<p>Hello {{contact_name}} from {{organization_name}} (Ref: {{ref_no}})</p>', $payload);
            T::assertFalse(str_contains($rendered, '<script>'), 'Must not contain unescaped script tag.');
            T::assertTrue(str_contains($rendered, '&lt;script&gt;'), 'Script tag must be HTML-escaped.');
        });
        });
    }
}

