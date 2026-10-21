<?php

declare(strict_types=1);

use Voak\Health\Database\Migration\MigrationInterface;

return new class implements MigrationInterface
{
    public function getVersion(): string
    {
        return '20261021_000011';
    }

    public function getDescription(): string
    {
        return 'Create homepage_configs table for hero copy, bento layout, and proof metrics';
    }

    public function up(\PDO $pdo): void
    {
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $sql = "CREATE TABLE IF NOT EXISTS homepage_configs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                hero_headline VARCHAR(255) NOT NULL,
                hero_subheadline TEXT NOT NULL,
                hero_primary_cta_text VARCHAR(50) NOT NULL,
                hero_primary_cta_url VARCHAR(191) NOT NULL,
                hero_secondary_cta_text VARCHAR(50) NOT NULL,
                hero_secondary_cta_url VARCHAR(191) NOT NULL,
                bento_order_json TEXT NOT NULL,
                proof_metrics_json TEXT NOT NULL,
                is_active INTEGER NOT NULL DEFAULT 1,
                updated_by_user_id INTEGER NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (updated_by_user_id) REFERENCES users (id)
            );";
            $pdo->exec($sql);
        } else {
            $sql = "CREATE TABLE IF NOT EXISTS homepage_configs (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                hero_headline VARCHAR(255) NOT NULL,
                hero_subheadline TEXT NOT NULL,
                hero_primary_cta_text VARCHAR(50) NOT NULL,
                hero_primary_cta_url VARCHAR(191) NOT NULL,
                hero_secondary_cta_text VARCHAR(50) NOT NULL,
                hero_secondary_cta_url VARCHAR(191) NOT NULL,
                bento_order_json JSON NOT NULL,
                proof_metrics_json JSON NOT NULL,
                is_active TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
                updated_by_user_id BIGINT UNSIGNED NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_homepage_editor FOREIGN KEY (updated_by_user_id) REFERENCES users (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
            $pdo->exec($sql);
        }
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS homepage_configs;");
    }
};
