<?php

declare(strict_types=1);

use Voak\Health\Database\Migration\MigrationInterface;

return new class implements MigrationInterface
{
    public function getVersion(): string
    {
        return '20261021_000027';
    }

    public function getDescription(): string
    {
        return 'Create system_settings table for dynamic corporate configuration variables';
    }

    public function up(\PDO $pdo): void
    {
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $sql = "CREATE TABLE IF NOT EXISTS system_settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                setting_group VARCHAR(50) NOT NULL,
                setting_key VARCHAR(50) NOT NULL UNIQUE,
                setting_value TEXT NULL,
                is_secret INTEGER NOT NULL DEFAULT 0,
                description VARCHAR(255) NOT NULL,
                updated_by_user_id INTEGER NOT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (updated_by_user_id) REFERENCES users (id)
            );
            CREATE INDEX IF NOT EXISTS idx_settings_group ON system_settings (setting_group);";
            $pdo->exec($sql);
        } else {
            $sql = "CREATE TABLE IF NOT EXISTS system_settings (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                setting_group VARCHAR(50) NOT NULL,
                setting_key VARCHAR(50) NOT NULL UNIQUE,
                setting_value TEXT NULL,
                is_secret TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                description VARCHAR(255) NOT NULL,
                updated_by_user_id BIGINT UNSIGNED NOT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_settings_user FOREIGN KEY (updated_by_user_id) REFERENCES users (id),
                INDEX idx_settings_group (setting_group)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
            $pdo->exec($sql);
        }
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS system_settings;");
    }
};
