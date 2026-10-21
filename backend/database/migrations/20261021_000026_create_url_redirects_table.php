<?php

declare(strict_types=1);

use Voak\Health\Database\Migration\MigrationInterface;

return new class implements MigrationInterface
{
    public function getVersion(): string
    {
        return '20261021_000026';
    }

    public function getDescription(): string
    {
        return 'Create url_redirects table for automated 301 SEO redirects';
    }

    public function up(\PDO $pdo): void
    {
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $sql = "CREATE TABLE IF NOT EXISTS url_redirects (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                source_path VARCHAR(191) NOT NULL UNIQUE,
                target_path VARCHAR(191) NOT NULL,
                http_status_code INTEGER NOT NULL DEFAULT 301,
                is_active INTEGER NOT NULL DEFAULT 1,
                hit_count INTEGER NOT NULL DEFAULT 0,
                last_accessed_at TIMESTAMP NULL DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
            CREATE INDEX IF NOT EXISTS idx_redirects_source ON url_redirects (source_path, is_active);";
            $pdo->exec($sql);
        } else {
            $sql = "CREATE TABLE IF NOT EXISTS url_redirects (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                source_path VARCHAR(191) NOT NULL UNIQUE,
                target_path VARCHAR(191) NOT NULL,
                http_status_code SMALLINT UNSIGNED NOT NULL DEFAULT 301,
                is_active TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
                hit_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
                last_accessed_at TIMESTAMP NULL DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_redirects_source (source_path, is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
            $pdo->exec($sql);
        }
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS url_redirects;");
    }
};
