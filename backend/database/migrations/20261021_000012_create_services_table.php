<?php

declare(strict_types=1);

use Voak\Health\Database\Migration\MigrationInterface;

return new class implements MigrationInterface
{
    public function getVersion(): string
    {
        return '20261021_000012';
    }

    public function getDescription(): string
    {
        return 'Create services table for B2B clinical and healthcare service pillars';
    }

    public function up(\PDO $pdo): void
    {
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $sql = "CREATE TABLE IF NOT EXISTS services (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title VARCHAR(150) NOT NULL,
                slug VARCHAR(100) NOT NULL UNIQUE,
                tagline VARCHAR(255) NOT NULL,
                description TEXT NOT NULL,
                sla_metrics_json TEXT NOT NULL,
                deliverables_json TEXT NOT NULL,
                is_published INTEGER NOT NULL DEFAULT 0,
                display_order INTEGER NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
            CREATE INDEX IF NOT EXISTS idx_services_slug_published ON services (slug, is_published);
            CREATE INDEX IF NOT EXISTS idx_services_order ON services (display_order);";
            $pdo->exec($sql);
        } else {
            $sql = "CREATE TABLE IF NOT EXISTS services (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(150) NOT NULL,
                slug VARCHAR(100) NOT NULL UNIQUE,
                tagline VARCHAR(255) NOT NULL,
                description TEXT NOT NULL,
                sla_metrics_json JSON NOT NULL,
                deliverables_json JSON NOT NULL,
                is_published TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_services_slug_published (slug, is_published),
                INDEX idx_services_order (display_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
            $pdo->exec($sql);
        }
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS services;");
    }
};
