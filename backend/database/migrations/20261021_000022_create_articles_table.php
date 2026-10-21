<?php

declare(strict_types=1);

use Voak\Health\Database\Migration\MigrationInterface;

return new class implements MigrationInterface
{
    public function getVersion(): string
    {
        return '20261021_000022';
    }

    public function getDescription(): string
    {
        return 'Create articles table for insights whitepapers and thought leadership';
    }

    public function up(\PDO $pdo): void
    {
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $sql = "CREATE TABLE IF NOT EXISTS articles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title VARCHAR(200) NOT NULL,
                slug VARCHAR(150) NOT NULL UNIQUE,
                summary TEXT NOT NULL,
                content_html TEXT NOT NULL,
                featured_image_id INTEGER NULL,
                author_id INTEGER NOT NULL,
                category VARCHAR(50) NOT NULL,
                tags_json TEXT NOT NULL,
                reading_time_minutes INTEGER NOT NULL DEFAULT 5,
                is_published INTEGER NOT NULL DEFAULT 0,
                published_at TIMESTAMP NULL DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (featured_image_id) REFERENCES media_assets (id) ON DELETE SET NULL,
                FOREIGN KEY (author_id) REFERENCES users (id)
            );
            CREATE INDEX IF NOT EXISTS idx_articles_slug_published ON articles (slug, is_published, published_at);
            CREATE INDEX IF NOT EXISTS idx_articles_category ON articles (category);";
            $pdo->exec($sql);
        } else {
            $sql = "CREATE TABLE IF NOT EXISTS articles (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(200) NOT NULL,
                slug VARCHAR(150) NOT NULL UNIQUE,
                summary TEXT NOT NULL,
                content_html MEDIUMTEXT NOT NULL,
                featured_image_id BIGINT UNSIGNED NULL,
                author_id BIGINT UNSIGNED NOT NULL,
                category VARCHAR(50) NOT NULL,
                tags_json JSON NOT NULL,
                reading_time_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 5,
                is_published TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                published_at TIMESTAMP NULL DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_articles_image FOREIGN KEY (featured_image_id) REFERENCES media_assets (id) ON DELETE SET NULL,
                CONSTRAINT fk_articles_author FOREIGN KEY (author_id) REFERENCES users (id),
                INDEX idx_articles_slug_published (slug, is_published, published_at),
                INDEX idx_articles_category (category)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
            $pdo->exec($sql);
        }
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS articles;");
    }
};
