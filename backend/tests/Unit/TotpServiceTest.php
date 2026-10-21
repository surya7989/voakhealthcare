<?php

declare(strict_types=1);

namespace Voak\Health\Tests\Unit;

use Voak\Health\Auth\TotpService;
use Voak\Health\Core\Config;
use Voak\Health\Database\Migration\MigrationManager;
use Voak\Health\Security\TotpEngine;
use Voak\Health\Tests\TestFramework as T;

class TotpServiceTest
{
    private static function createDatabase(): \PDO
    {
        $pdo = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ]);
        $migrationsDir = dirname(__DIR__, 2) . '/database/migrations';
        $manager = new MigrationManager($migrationsDir, 'migrations');
        $manager->migrateUp($pdo);

        $pdo->exec("INSERT INTO users (id, name, email, password_hash, is_active, created_at, updated_at) VALUES (1, 'MFA User', 'mfa_user@voak.test', 'hash', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");

        return $pdo;
    }

    public static function run(): void
    {
        T::suite('Phase 6 — TotpService MFA Setup & Recovery Tests', function () {
            $config = new Config([
                'APP_KEY' => 'base64:' . base64_encode('test-encryption-key-32-chars-long!'),
                'MFA_ENCRYPTION_KEY' => 'test-encryption-key-32-chars-long!',
            ]);

            T::test('initiates MFA setup with encrypted secret and 8 recovery codes', function () use ($config) {
                $pdo = self::createDatabase();
                $engine = new TotpEngine();
                $service = new TotpService($config, $engine);

                $setup = $service->initiateSetup($pdo, 1, 'mfa_user@voak.test');

                T::assertTrue(strlen($setup['secret']) >= 16);
                T::assertContains('otpauth://totp/', $setup['provisioning_uri']);
                T::assertEquals(8, count($setup['backup_codes']));

                // MFA is not enabled until confirmed
                T::assertFalse($service->isMfaActive($pdo, 1));

                // Verify secret stored in DB is encrypted
                $stmt = $pdo->query('SELECT encrypted_secret FROM admin_mfa_secrets WHERE user_id = 1');
                $storedEncrypted = $stmt->fetchColumn();
                T::assertFalse(str_contains($storedEncrypted, $setup['secret']));
            });

            T::test('confirms MFA setup with valid TOTP code', function () use ($config) {
                $pdo = self::createDatabase();
                $engine = new TotpEngine();
                $service = new TotpService($config, $engine);

                $setup = $service->initiateSetup($pdo, 1, 'mfa_user@voak.test');
                $code = $engine->generateCode($setup['secret']);

                $confirmed = $service->confirmSetup($pdo, 1, $code);
                T::assertTrue($confirmed);
                T::assertTrue($service->isMfaActive($pdo, 1));

                // Verify login code works
                T::assertTrue($service->verifyLoginCode($pdo, 1, $code));
                T::assertFalse($service->verifyLoginCode($pdo, 1, '999999'));
            });

            T::test('authenticates and burns single-use backup recovery codes', function () use ($config) {
                $pdo = self::createDatabase();
                $engine = new TotpEngine();
                $service = new TotpService($config, $engine);

                $setup = $service->initiateSetup($pdo, 1, 'mfa_user@voak.test');
                $code = $engine->generateCode($setup['secret']);
                $service->confirmSetup($pdo, 1, $code);

                $backupCode = $setup['backup_codes'][0];

                // First use succeeds
                $valid = $service->verifyAndConsumeRecoveryCode($pdo, 1, $backupCode);
                T::assertTrue($valid);

                // Second use of the same code must fail (single-use burned)
                $repeatUse = $service->verifyAndConsumeRecoveryCode($pdo, 1, $backupCode);
                T::assertFalse($repeatUse, 'Used backup code must not be reusable');

                // Other backup codes still work
                $secondBackupCode = $setup['backup_codes'][1];
                $valid2 = $service->verifyAndConsumeRecoveryCode($pdo, 1, $secondBackupCode);
                T::assertTrue($valid2);
            });
        });
    }
}
