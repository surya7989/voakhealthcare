<?php

declare(strict_types=1);

namespace Voak\Health\Tests\Unit;

use Voak\Health\Auth\RbacManager;
use Voak\Health\Database\Connection;
use Voak\Health\Database\Migration\MigrationManager;
use Voak\Health\Security\AuditLogger;
use Voak\Health\Services\AuditService;
use Voak\Health\Tests\TestFramework as T;

/**
 * Unit Test Suite for AuditService (Module 19 / ADM-019 / FR-ADM-019 / TEST-ADM-019).
 */
class AuditServiceTest
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
                    VALUES (1, 'Super Admin', 'admin@voakhealthcare.com', 'hash', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
        $pdo->exec("INSERT INTO users (id, name, email, password_hash, is_active, created_at, updated_at) 
                    VALUES (2, 'Website Admin', 'webadmin@voakhealthcare.com', 'hash', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");

        $rbac = new RbacManager();
        $rbac->seedRbac($pdo);
        $rbac->assignRole($pdo, 1, RbacManager::ROLE_SUPER_ADMIN);
        $rbac->assignRole($pdo, 2, RbacManager::ROLE_WEBSITE_ADMIN);

        return $pdo;
    }

    private static function createConnection(\PDO $pdo): Connection
    {
        $conn = new Connection();
        $conn->setMockPdo($pdo);
        return $conn;
    }

    public static function run(): void
    {
        T::suite('Phase 17 — AuditService Unit Tests', function () {

            T::test('queryLogs returns empty structure when no records exist', function () {
                $pdo = self::createDatabase();
                $connection = self::createConnection($pdo);
                $auditService = new AuditService($connection);

                $result = $auditService->queryLogs();
                T::assertEquals([], $result['records']);
                T::assertEquals(0, $result['pagination']['total_records']);
                T::assertEquals(1, $result['pagination']['current_page']);
                T::assertEquals(0, $result['pagination']['total_pages']);
            });

            T::test('queryLogs filters by domain, action, and user_id', function () {
                $pdo = self::createDatabase();
                $logger = new AuditLogger();
                $connection = self::createConnection($pdo);
                $auditService = new AuditService($connection, $logger);

                $logger->log($pdo, 'AUTH', 'LOGIN_SUCCESS', 1, '192.168.1.1', 'Mozilla', 'users', 1, ['login' => 'ok']);
                $logger->log($pdo, 'PAGES', 'PAGE_UPDATED', 1, '192.168.1.1', 'Mozilla', 'pages', 10, ['title' => ['old' => 'A', 'new' => 'B']]);
                $logger->log($pdo, 'AUTH', 'LOGOUT', 2, '192.168.1.2', 'Mozilla', 'users', 2, []);

                // Filter by domain
                $authLogs = $auditService->queryLogs(['domain' => 'AUTH']);
                T::assertEquals(2, $authLogs['pagination']['total_records']);

                // Filter by action
                $pageUpdates = $auditService->queryLogs(['action' => 'PAGE_UPDATED']);
                T::assertEquals(1, $pageUpdates['pagination']['total_records']);
                T::assertEquals('PAGE_UPDATED', $pageUpdates['records'][0]['action']);
                T::assertEquals('Super Admin', $pageUpdates['records'][0]['actor_name']);

                // Filter by user_id
                $user2Logs = $auditService->queryLogs(['user_id' => 2]);
                T::assertEquals(1, $user2Logs['pagination']['total_records']);
                T::assertEquals('Website Admin', $user2Logs['records'][0]['actor_name']);
            });

            T::test('queryLogs pagination limits and page calculation', function () {
                $pdo = self::createDatabase();
                $logger = new AuditLogger();
                $connection = self::createConnection($pdo);
                $auditService = new AuditService($connection, $logger);

                for ($i = 1; $i <= 25; $i++) {
                    $logger->log($pdo, 'SYSTEM', "EVENT_{$i}", 1, '10.0.0.1', 'Internal', 'system', $i, ['index' => $i]);
                }

                $page1 = $auditService->queryLogs(['page' => 1, 'per_page' => 10]);
                T::assertEquals(25, $page1['pagination']['total_records']);
                T::assertEquals(3, $page1['pagination']['total_pages']);
                T::assertEquals(10, count($page1['records']));
                T::assertEquals('EVENT_25', $page1['records'][0]['action']); // Sorted DESC

                $page3 = $auditService->queryLogs(['page' => 3, 'per_page' => 10]);
                T::assertEquals(5, count($page3['records']));
            });

            T::test('queryLogs searches across domain, action, and diff payload', function () {
                $pdo = self::createDatabase();
                $logger = new AuditLogger();
                $connection = self::createConnection($pdo);
                $auditService = new AuditService($connection, $logger);

                $logger->log($pdo, 'SERVICES', 'SERVICE_PUBLISHED', 1, '127.0.0.1', 'Chrome', 'services', 5, ['secret_keyword' => 'clinical-trial-omega']);
                $logger->log($pdo, 'ARTICLES', 'ARTICLE_CREATED', 2, '127.0.0.1', 'Chrome', 'articles', 12, ['topic' => 'general-cardiology']);

                $searchMatch = $auditService->queryLogs(['search' => 'clinical-trial-omega']);
                T::assertEquals(1, $searchMatch['pagination']['total_records']);
                T::assertEquals('SERVICE_PUBLISHED', $searchMatch['records'][0]['action']);
            });

            T::test('getLogById retrieves event with parsed diff and returns null for missing', function () {
                $pdo = self::createDatabase();
                $logger = new AuditLogger();
                $connection = self::createConnection($pdo);
                $auditService = new AuditService($connection, $logger);

                $entry = $logger->log($pdo, 'SETTINGS', 'SETTING_MODIFIED', 1, '10.0.0.5', 'Agent', 'system_settings', 1, [
                    'setting_key' => 'site.name',
                    'before'      => 'VOAK',
                    'after'       => 'VOAK Health Care',
                ]);

                $log = $auditService->getLogById($entry['id']);
                T::assertTrue($log !== null);
                T::assertEquals('SETTING_MODIFIED', $log['action']);
                T::assertEquals('Super Admin', $log['actor_name']);
                T::assertEquals('VOAK Health Care', $log['diff']['after']);
                T::assertEquals($entry['row_hash'], $log['row_hash']);

                $missing = $auditService->getLogById(999999);
                T::assertNull($missing);
            });

            T::test('verifyChainIntegrity confirms untampered cryptographic chain (TEST-ADM-019)', function () {
                $pdo = self::createDatabase();
                $logger = new AuditLogger();
                $connection = self::createConnection($pdo);
                $auditService = new AuditService($connection, $logger);

                // Build a 5-node cryptographic chain
                for ($i = 1; $i <= 5; $i++) {
                    $logger->log($pdo, 'CHAIN', "ACTION_{$i}", 1, '127.0.0.1', 'CLI', 'entity', $i, ['step' => $i]);
                }

                $report = $auditService->verifyChainIntegrity();
                T::assertTrue($report['valid']);
                T::assertEquals(5, $report['total_records']);
                T::assertEquals(AuditLogger::GENESIS_HASH, $report['genesis_hash']);
                T::assertTrue($report['head_hash'] !== null);
                T::assertNull($report['tampered_at_id']);
                T::assertNull($report['error']);
            });

            T::test('verifyChainIntegrity detects tampering in historical record (TEST-ADM-019)', function () {
                $pdo = self::createDatabase();
                $logger = new AuditLogger();
                $connection = self::createConnection($pdo);
                $auditService = new AuditService($connection, $logger);

                for ($i = 1; $i <= 4; $i++) {
                    $logger->log($pdo, 'LEDGER', "TX_{$i}", 1, '127.0.0.1', 'CLI', 'record', $i, ['val' => $i * 100]);
                }

                // Tamper with record ID 2: modify the diff out-of-band directly via SQL
                $pdo->exec("UPDATE audit_logs SET diff_json = '{\"val\":99999}' WHERE id = 2");

                $tamperedReport = $auditService->verifyChainIntegrity();
                T::assertFalse($tamperedReport['valid']);
                T::assertEquals(2, $tamperedReport['tampered_at_id']);
                T::assertTrue(str_contains((string)$tamperedReport['error'], 'Hash validation failed at record ID 2'));
            });

            T::test('verifyChainIntegrity detects broken predecessor link (TEST-ADM-019)', function () {
                $pdo = self::createDatabase();
                $logger = new AuditLogger();
                $connection = self::createConnection($pdo);
                $auditService = new AuditService($connection, $logger);

                for ($i = 1; $i <= 3; $i++) {
                    $logger->log($pdo, 'LEDGER', "STEP_{$i}", 1, '127.0.0.1', 'CLI', 'record', $i, ['v' => $i]);
                }

                // Tamper with record ID 3's prev_row_hash
                $fakeHash = hash('sha256', 'fake-pred');
                $pdo->exec("UPDATE audit_logs SET prev_row_hash = '{$fakeHash}' WHERE id = 3");

                $brokenReport = $auditService->verifyChainIntegrity();
                T::assertFalse($brokenReport['valid']);
                T::assertEquals(3, $brokenReport['tampered_at_id']);
                T::assertTrue(str_contains((string)$brokenReport['error'], 'Cryptographic chain broken at record ID 3'));
            });

            T::test('exportCsv applies formula injection defense and UTF-8 BOM', function () {
                $pdo = self::createDatabase();
                $logger = new AuditLogger();
                $connection = self::createConnection($pdo);
                $auditService = new AuditService($connection, $logger);

                // Insert event with potential spreadsheet formula injection payload
                $logger->log($pdo, 'INJECTION_TEST', '=SUM(1,2)', 1, '127.0.0.1', '+cmd|/C calc', 'formula', 1, [
                    'payload' => '@dangerous_macro',
                ]);

                $csv = $auditService->exportCsv();

                // 1. Verify UTF-8 BOM prefix
                T::assertTrue(str_starts_with($csv, "\xEF\xBB\xBF"));

                // 2. Verify headers
                T::assertTrue(str_contains($csv, 'Log ID'));
                T::assertTrue(str_contains($csv, 'Row Hash'));

                // 3. Verify formula injection characters are escaped with leading single quote
                T::assertTrue(str_contains($csv, "'=SUM(1,2)"));
                T::assertTrue(str_contains($csv, "'+cmd|/C calc"));
            });

            T::test('AuditService strictly exposes zero update or delete methods (Immutability Reflection)', function () {
                $reflection = new \ReflectionClass(AuditService::class);
                $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);

                $forbiddenKeywords = ['update', 'delete', 'destroy', 'purge', 'truncate', 'drop', 'remove', 'modify'];
                foreach ($methods as $method) {
                    $name = strtolower($method->getName());
                    foreach ($forbiddenKeywords as $bad) {
                        T::assertFalse(
                            str_contains($name, $bad),
                            "AuditService must remain strictly append-only: found mutating method [{$method->getName()}]."
                        );
                    }
                }
            });

            T::test('logAccess and logExport write operational telemetry into audit_logs', function () {
                $pdo = self::createDatabase();
                $connection = self::createConnection($pdo);
                $auditService = new AuditService($connection);

                $auditService->logAccess(1, '10.0.1.20', 'AdminBrowser/1.0');
                $auditService->logExport(1, '10.0.1.20', 'AdminBrowser/1.0', 42);

                $stmt = $pdo->query("SELECT action, domain FROM audit_logs ORDER BY id ASC");
                $actions = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];

                T::assertEquals(2, count($actions));
                T::assertEquals('AUDIT_LOGS_ACCESSED', $actions[0]['action']);
                T::assertEquals('AUDIT_LOGS_EXPORTED', $actions[1]['action']);
            });

        });
    }
}
