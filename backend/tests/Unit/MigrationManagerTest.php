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
            T::test('discovers migration files matching canonical naming conventions', function () {
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
        });
    }
}
