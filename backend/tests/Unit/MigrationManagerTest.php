<?php

declare(strict_types=1);

namespace Voak\Health\Tests\Unit;

use Voak\Health\Database\Migration\MigrationManager;
use Voak\Health\Tests\TestFramework as T;

class MigrationManagerTest
{
    public static function run(): void
    {
        T::suite('Migration Manager & Blueprint Discovery Tests', function () {
            T::test('discovers mock migration files matching canonical naming conventions', function () {
                $tempDir = sys_get_temp_dir() . '/voak_test_migrations_' . bin2hex(random_bytes(4));
                @mkdir($tempDir, 0755, true);

                // Create mock migration files
                file_put_contents($tempDir . '/20261020_000001_create_users_table.php', '<?php');
                file_put_contents($tempDir . '/20261020_000002_create_pages_table.php', '<?php');
                file_put_contents($tempDir . '/not_a_migration.txt', 'text');

                $manager = new MigrationManager($tempDir);
                $migrations = $manager->discoverMigrations();

                T::assertEquals(2, count($migrations));
                T::assertEquals('20261020_000001', $migrations[0]['version']);
                T::assertEquals('20261020_000002', $migrations[1]['version']);

                // Clean up
                @unlink($tempDir . '/20261020_000001_create_users_table.php');
                @unlink($tempDir . '/20261020_000002_create_pages_table.php');
                @unlink($tempDir . '/not_a_migration.txt');
                @rmdir($tempDir);
            });

            T::test('handles non-existent migration directory safely', function () {
                $manager = new MigrationManager('/path/that/does/not/exist');
                $migrations = $manager->discoverMigrations();
                T::assertEquals([], $migrations);
            });

            T::test('discovers exactly 28 canonical schema blueprints in repository', function () {
                $canonicalDir = dirname(__DIR__, 2) . '/database/migrations';
                $manager = new MigrationManager($canonicalDir, 'migrations');
                $migrations = $manager->discoverMigrations();

                T::assertEquals(28, count($migrations), 'Expected exactly 28 canonical schema blueprints');
                T::assertEquals('20261021_000001', $migrations[0]['version']);
                T::assertEquals('20261021_000028', $migrations[27]['version']);
            });

            T::test('creates tracking table and reports migration status', function () {
                $canonicalDir = dirname(__DIR__, 2) . '/database/migrations';
                $manager = new MigrationManager($canonicalDir, 'migrations');

                $pdo = new \PDO('sqlite::memory:', null, null, [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                ]);

                $manager->ensureTrackingTable($pdo);
                $status = $manager->getStatus($pdo);

                T::assertEquals(28, count($status));
                T::assertEquals('PENDING', $status[0]['status']);
                T::assertEquals('PENDING', $status[27]['status']);
            });

            T::test('executes migrateUp applying all 28 schema blueprints', function () {
                $canonicalDir = dirname(__DIR__, 2) . '/database/migrations';
                $manager = new MigrationManager($canonicalDir, 'migrations');

                $pdo = new \PDO('sqlite::memory:', null, null, [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                ]);

                $migrated = $manager->migrateUp($pdo);
                T::assertEquals(28, count($migrated), 'All 28 blueprints must be applied on up()');

                $status = $manager->getStatus($pdo);
                $applied = array_filter($status, fn ($s) => $s['status'] === 'APPLIED');
                T::assertEquals(28, count($applied), 'All 28 blueprints must show APPLIED status');
            });

            T::test('verifies repeat-run idempotency (zero pending, zero duplicate execution)', function () {
                $canonicalDir = dirname(__DIR__, 2) . '/database/migrations';
                $manager = new MigrationManager($canonicalDir, 'migrations');

                $pdo = new \PDO('sqlite::memory:', null, null, [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                ]);

                $manager->migrateUp($pdo);
                $repeat = $manager->migrateUp($pdo);

                T::assertEquals(0, count($repeat), 'Repeat migrateUp must be a zero-mutation no-op');
            });

            T::test('executes step rollback and batch rollback', function () {
                $canonicalDir = dirname(__DIR__, 2) . '/database/migrations';
                $manager = new MigrationManager($canonicalDir, 'migrations');

                $pdo = new \PDO('sqlite::memory:', null, null, [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                ]);

                $manager->migrateUp($pdo);

                // Roll back 1 step (table 28: audit_logs)
                $rolledBack1 = $manager->migrateDown($pdo, 1);
                T::assertEquals(1, count($rolledBack1));
                T::assertEquals('20261021_000028', $rolledBack1[0]['version']);

                $statusAfter1 = $manager->getStatus($pdo);
                $pending = array_filter($statusAfter1, fn ($s) => $s['status'] === 'PENDING');
                T::assertEquals(1, count($pending));

                // Re-apply table 28 (becomes Batch 2)
                $reapplied = $manager->migrateUp($pdo);
                T::assertEquals(1, count($reapplied));
                T::assertEquals(2, $reapplied[0]['batch']);

                // Roll back latest batch (Batch 2)
                $rolledBackBatch = $manager->migrateDown($pdo, 0);
                T::assertEquals(1, count($rolledBackBatch));
                T::assertEquals('20261021_000028', $rolledBackBatch[0]['version']);
            });

            T::test('executes fresh schema reset and rebuild', function () {
                $canonicalDir = dirname(__DIR__, 2) . '/database/migrations';
                $manager = new MigrationManager($canonicalDir, 'migrations');

                $pdo = new \PDO('sqlite::memory:', null, null, [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                ]);

                $manager->migrateUp($pdo);
                $freshResult = $manager->fresh($pdo);

                T::assertEquals(28, count($freshResult['rolled_back']), 'Must roll back all 28 tables');
                T::assertEquals(28, count($freshResult['migrated']), 'Must re-migrate all 28 tables');

                $status = $manager->getStatus($pdo);
                $applied = array_filter($status, fn ($s) => $s['status'] === 'APPLIED');
                T::assertEquals(28, count($applied));
            });

            T::test('verifies all 28 canonical domain tables exist in database schema', function () {
                $canonicalDir = dirname(__DIR__, 2) . '/database/migrations';
                $manager = new MigrationManager($canonicalDir, 'migrations');

                $pdo = new \PDO('sqlite::memory:', null, null, [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                ]);

                $manager->migrateUp($pdo);

                $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
                $tables = $stmt->fetchAll(\PDO::FETCH_COLUMN);

                $expectedTables = [
                    'migrations',
                    'users', 'roles', 'permissions', 'role_permissions', 'user_roles',
                    'admin_sessions', 'admin_mfa_secrets', 'password_resets',
                    'pages', 'content_revisions', 'homepage_configs',
                    'services', 'industries', 'service_industry_mappings',
                    'media_assets',
                    'contact_inquiries', 'partnership_inquiries', 'proposals', 'proposal_services',
                    'job_postings', 'career_applications', 'articles', 'case_studies', 'faqs',
                    'email_queue', 'url_redirects', 'system_settings', 'audit_logs',
                ];

                foreach ($expectedTables as $expected) {
                    T::assertTrue(in_array($expected, $tables, true), "Expected table [{$expected}] must exist in schema");
                }

                T::assertEquals(29, count($tables), 'Expected exactly 28 domain entities + 1 migrations table = 29 tables');
            });

            T::test('CLI migration runner executes status, up, down, and help via CLI', function () {
                $repoRoot = dirname(__DIR__, 2);
                $runner = $repoRoot . '/cli/migrate.php';
                $tempSqlite = sys_get_temp_dir() . '/voak_cli_test_' . bin2hex(random_bytes(4)) . '.sqlite';

                // Test Help
                $outHelp = [];
                $codeHelp = 0;
                exec("php \"{$runner}\" --help", $outHelp, $codeHelp);
                T::assertEquals(0, $codeHelp);

                // Test Status before up
                $outStatus = [];
                $codeStatus = 0;
                exec("php \"{$runner}\" status --sqlite=\"{$tempSqlite}\"", $outStatus, $codeStatus);
                T::assertEquals(0, $codeStatus);

                // Test Up
                $outUp = [];
                $codeUp = 0;
                exec("php \"{$runner}\" up --sqlite=\"{$tempSqlite}\"", $outUp, $codeUp);
                T::assertEquals(0, $codeUp);

                // Test Repeat Up (idempotent)
                $outUpRepeat = [];
                $codeUpRepeat = 0;
                exec("php \"{$runner}\" up --sqlite=\"{$tempSqlite}\"", $outUpRepeat, $codeUpRepeat);
                T::assertEquals(0, $codeUpRepeat);

                // Test Down 1 step
                $outDown = [];
                $codeDown = 0;
                exec("php \"{$runner}\" down --steps=1 --sqlite=\"{$tempSqlite}\"", $outDown, $codeDown);
                T::assertEquals(0, $codeDown);

                // Clean up temp database
                @unlink($tempSqlite);
            });

            T::test('Phase 0 compatibility shim database/migrate.php functions correctly', function () {
                $repoRoot = dirname(__DIR__, 2);
                $shim = dirname($repoRoot) . '/database/migrate.php';

                T::assertTrue(file_exists($shim), 'Phase 0 compatibility shim database/migrate.php must exist');

                $outShim = [];
                $codeShim = 0;
                exec("php \"{$shim}\" --help", $outShim, $codeShim);
                T::assertEquals(0, $codeShim, 'database/migrate.php --help must exit code 0');
            });
        });
    }
}
