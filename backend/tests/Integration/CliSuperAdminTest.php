<?php

declare(strict_types=1);

namespace Voak\Health\Tests\Integration;

use Voak\Health\Database\Migration\MigrationManager;
use Voak\Health\Tests\TestFramework as T;

class CliSuperAdminTest
{
    public static function run(): void
    {
        T::suite('Phase 6 — CLI Super Admin Provisioner Tests', function () {
            $cliScript = dirname(__DIR__, 2) . '/backend/cli/create_super_admin.php';
            if (!file_exists($cliScript)) {
                $cliScript = dirname(__DIR__, 2) . '/cli/create_super_admin.php';
            }

            T::test('CLI provisioner --help exits code 0', function () use ($cliScript) {
                $out = [];
                $code = 0;
                exec("php \"{$cliScript}\" --help", $out, $code);
                T::assertEquals(0, $code);
                T::assertContains('Initial Super Admin CLI Provisioner', implode("\n", $out));
            });

            T::test('CLI provisioner rejects missing --email or --name with exit code 1', function () use ($cliScript) {
                $out = [];
                $code = 0;
                exec("php \"{$cliScript}\" --email=\"admin@voak.test\"", $out, $code);
                T::assertEquals(1, $code);
            });

            T::test('CLI provisioner rejects weak password failing entropy standards', function () use ($cliScript) {
                $tempSqlite = sys_get_temp_dir() . '/voak_cli_admin_weak_' . bin2hex(random_bytes(4)) . '.sqlite';
                $pdo = new \PDO("sqlite:{$tempSqlite}");
                $migrationsDir = dirname(__DIR__, 2) . '/database/migrations';
                $manager = new MigrationManager($migrationsDir, 'migrations');
                $manager->migrateUp($pdo);
                unset($pdo);

                $out = [];
                $code = 0;
                exec("php \"{$cliScript}\" --sqlite=\"{$tempSqlite}\" --email=\"weak@voak.test\" --name=\"Weak Admin\" --password=\"weakpass\"", $out, $code);
                T::assertEquals(1, $code);

                @unlink($tempSqlite);
            });

            T::test('CLI provisioner creates super admin with valid credentials and audit log', function () use ($cliScript) {
                $tempSqlite = sys_get_temp_dir() . '/voak_cli_admin_valid_' . bin2hex(random_bytes(4)) . '.sqlite';
                $pdo = new \PDO("sqlite:{$tempSqlite}");
                $migrationsDir = dirname(__DIR__, 2) . '/database/migrations';
                $manager = new MigrationManager($migrationsDir, 'migrations');
                $manager->migrateUp($pdo);
                unset($pdo);

                $out = [];
                $code = 0;
                exec("php \"{$cliScript}\" --sqlite=\"{$tempSqlite}\" --email=\"superadmin@voak.test\" --name=\"Lead Admin\" --password=\"SuperAdmin#2026!Secure\"", $out, $code);
                T::assertEquals(0, $code);
                T::assertContains('SUCCESS: Super Administrator provisioned successfully', implode("\n", $out));

                // Verify user created in sqlite database
                $pdoCheck = new \PDO("sqlite:{$tempSqlite}");
                $stmt = $pdoCheck->query("SELECT id, name, email, is_active FROM users WHERE email = 'superadmin@voak.test'");
                $user = $stmt->fetch(\PDO::FETCH_ASSOC);
                T::assertTrue($user !== false);
                T::assertEquals('Lead Admin', $user['name']);
                T::assertEquals(1, (int)$user['is_active']);

                // Verify role assignment
                $stmtRole = $pdoCheck->query("SELECT r.slug FROM roles r INNER JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = {$user['id']}");
                $roleSlug = $stmtRole->fetchColumn();
                T::assertEquals('super_admin', $roleSlug);

                // Verify audit log entry
                $stmtAudit = $pdoCheck->query("SELECT action FROM audit_logs WHERE action = 'ADMIN_BOOTSTRAP_PROVISIONED'");
                $action = $stmtAudit->fetchColumn();
                T::assertEquals('ADMIN_BOOTSTRAP_PROVISIONED', $action);

                unset($pdoCheck);
                @unlink($tempSqlite);
            });
        });
    }
}
