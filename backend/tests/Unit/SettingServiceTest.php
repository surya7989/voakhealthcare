<?php

declare(strict_types=1);

namespace Voak\Health\Tests\Unit;

use Voak\Health\Core\Exceptions\BadRequestException;
use Voak\Health\Core\Exceptions\NotFoundException;
use Voak\Health\Database\Connection;
use Voak\Health\Database\Migration\MigrationManager;
use Voak\Health\Security\AuditLogger;
use Voak\Health\Services\SettingService;
use Voak\Health\Tests\TestFramework as T;

class SettingServiceTest
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
        T::suite('Phase 7 — SettingService & Secret Isolation Tests', function () {
            T::test('ensureDefaultSettings seeds default platform settings on empty table', function () {
                $pdo = self::createDatabase();
                $connection = new Connection();
                $connection->setMockPdo($pdo);
                $service = new SettingService($connection, new AuditLogger());

                $service->ensureDefaultSettings(1);

                $stmt = $pdo->query("SELECT COUNT(*) FROM system_settings");
                $count = (int)$stmt->fetchColumn();
                T::assertTrue($count >= 8);
            });

            T::test('getPublicSettings strictly exposes only is_secret = 0 settings', function () {
                $pdo = self::createDatabase();
                $connection = new Connection();
                $connection->setMockPdo($pdo);
                $service = new SettingService($connection, new AuditLogger());

                $public = $service->getPublicSettings();
                $settings = $public['settings'];

                T::assertTrue(isset($settings['site_name']));
                T::assertTrue(isset($settings['contact_email']));
                T::assertTrue(isset($settings['contact_phone']));

                // Critical security verification: verify ZERO secret settings are present
                T::assertFalse(isset($settings['session_idle_timeout_minutes']));
                T::assertFalse(isset($settings['max_login_attempts']));

                foreach ($settings as $key => $item) {
                    T::assertTrue($item['is_public']);
                    T::assertFalse(str_contains(strtolower($key), 'secret'));
                    T::assertFalse(str_contains(strtolower($key), 'password'));
                    T::assertFalse(str_contains(strtolower($key), 'key'));
                    T::assertFalse(str_contains(strtolower($key), 'token'));
                }
            });

            T::test('defense-in-depth: public settings isolates any keyword match even if misconfigured in database', function () {
                $pdo = self::createDatabase();
                $connection = new Connection();
                $connection->setMockPdo($pdo);
                $service = new SettingService($connection, new AuditLogger());
                $service->ensureDefaultSettings(1);

                // Inject a dangerous key marked is_secret = 0 by accidental operator error
                $pdo->exec("INSERT INTO system_settings (setting_group, setting_key, setting_value, is_secret, description, updated_by_user_id) 
                            VALUES ('security', 'app_signing_secret_key', 'super-secret-hex-key', 0, 'Dangerous misconfigured key', 1)");

                $public = $service->getPublicSettings();
                $settings = $public['settings'];

                // Must be quarantined by defense-in-depth keyword filter
                T::assertFalse(isset($settings['app_signing_secret_key']));
            });

            T::test('getAllSettings returns all settings for admin with masked secret values', function () {
                $pdo = self::createDatabase();
                $connection = new Connection();
                $connection->setMockPdo($pdo);
                $service = new SettingService($connection, new AuditLogger());

                $all = $service->getAllSettings();
                T::assertTrue(count($all) >= 8);

                $secretFound = false;
                foreach ($all as $item) {
                    if ($item['is_secret']) {
                        $secretFound = true;
                        T::assertEquals('[PROTECTED SECRET]', $item['setting_value']);
                    }
                }
                T::assertTrue($secretFound, "Secret setting was expected in admin list.");
            });

            T::test('updateSettings updates values transactionally and records audit event', function () {
                $pdo = self::createDatabase();
                $connection = new Connection();
                $connection->setMockPdo($pdo);
                $audit = new AuditLogger();
                $service = new SettingService($connection, $audit);

                $result = $service->updateSettings([
                    'site_name'     => 'Voak Health Care Enterprise',
                    'contact_email' => 'operations@voak.com.au',
                ], 1);

                T::assertEquals(2, $result['updated_count']);

                // Verify updated in database
                $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'site_name'");
                $stmt->execute();
                T::assertEquals('Voak Health Care Enterprise', $stmt->fetchColumn());

                // Verify audit logging
                $stmt = $pdo->query("SELECT * FROM audit_logs WHERE action = 'settings_updated'");
                $auditRow = $stmt ? $stmt->fetch(\PDO::FETCH_ASSOC) : false;
                T::assertTrue($auditRow !== false);
            });

            T::test('updateSettings rejects non-existent setting key with NotFoundException', function () {
                $pdo = self::createDatabase();
                $connection = new Connection();
                $connection->setMockPdo($pdo);
                $service = new SettingService($connection, new AuditLogger());

                T::expectException(NotFoundException::class, function () use ($service) {
                    $service->updateSettings([
                        'non_existent_key_xyz' => 'value',
                    ], 1);
                });
            });

            T::test('updateSettings rejects oversized values (>4096 characters)', function () {
                $pdo = self::createDatabase();
                $connection = new Connection();
                $connection->setMockPdo($pdo);
                $service = new SettingService($connection, new AuditLogger());

                T::expectException(BadRequestException::class, function () use ($service) {
                    $service->updateSettings([
                        'site_name' => str_repeat('A', 4097),
                    ], 1);
                });
            });
        });
    }
}
