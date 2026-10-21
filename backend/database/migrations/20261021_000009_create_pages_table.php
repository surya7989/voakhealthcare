<?php

declare(strict_types=1);

use Voak\Health\Database\Migration\MigrationInterface;

return new class implements MigrationInterface
{
    public function getVersion(): string
    {
        return '20261021_000009';
    }

    public function getDescription(): string
    {
        return 'Create pages table for static corporate and public website content';
    }

    public function up(\PDO $pdo): void
    {
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $sql = "CREATE TABLE IF NOT EXISTS pages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                slug VARCHAR(100) NOT NULL UNIQUE,
                title VARCHAR(150) NOT NULL,
                meta_title VARCHAR(150) NOT NULL,
                meta_description VARCHAR(255) NOT NULL,
                content_json TEXT NOT NULL,
                is_published INTEGER NOT NULL DEFAULT 0,
                published_at TIMESTAMP NULL DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
            CREATE INDEX IF NOT EXISTS idx_pages_slug_published ON pages (slug, is_published);";
            $pdo->exec($sql);
        } else {
            $sql = "CREATE TABLE IF NOT EXISTS pages (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                slug VARCHAR(100) NOT NULL UNIQUE,
                title VARCHAR(150) NOT NULL,
                meta_title VARCHAR(150) NOT NULL,
                meta_description VARCHAR(255) NOT NULL,
                content_json JSON NOT NULL,
                is_published TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                published_at TIMESTAMP NULL DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_pages_slug_published (slug, is_published)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
            $pdo->exec($sql);
        }
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS pages;");
    }
};
