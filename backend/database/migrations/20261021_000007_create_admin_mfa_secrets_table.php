<?php

declare(strict_types=1);

use Voak\Health\Database\Migration\MigrationInterface;

return new class implements MigrationInterface
{
    public function getVersion(): string
    {
        return '20261021_000007';
    }

    public function getDescription(): string
    {
        return 'Create admin_mfa_secrets table for isolated TOTP and recovery code storage';
    }

    public function up(\PDO $pdo): void
    {
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $sql = "CREATE TABLE IF NOT EXISTS admin_mfa_secrets (
                user_id INTEGER PRIMARY KEY,
                encrypted_secret VARCHAR(255) NOT NULL,
                backup_codes_json TEXT NOT NULL,
                is_confirmed INTEGER NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            );";
            $pdo->exec($sql);
        } else {
            $sql = "CREATE TABLE IF NOT EXISTS admin_mfa_secrets (
                user_id BIGINT UNSIGNED PRIMARY KEY,
                encrypted_secret VARCHAR(255) NOT NULL,
                backup_codes_json JSON NOT NULL,
                is_confirmed TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_mfa_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
            $pdo->exec($sql);
        }
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS admin_mfa_secrets;");
    }
};
