<?php

declare(strict_types=1);

use Voak\Health\Database\Migration\MigrationInterface;

return new class implements MigrationInterface
{
    public function getVersion(): string
    {
        return '20261021_000015';
    }

    public function getDescription(): string
    {
        return 'Create media_assets table for public WebP media library with alt-text enforcement';
    }

    public function up(\PDO $pdo): void
    {
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $sql = "CREATE TABLE IF NOT EXISTS media_assets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                original_name VARCHAR(255) NOT NULL,
                stored_name VARCHAR(100) NOT NULL UNIQUE,
                file_path VARCHAR(255) NOT NULL,
                file_size_bytes INTEGER NOT NULL,
                mime_type VARCHAR(50) NOT NULL,
                dimensions VARCHAR(30) NOT NULL,
                alt_text VARCHAR(255) NOT NULL,
                uploaded_by_user_id INTEGER NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (uploaded_by_user_id) REFERENCES users (id)
            );
            CREATE INDEX IF NOT EXISTS idx_media_mime ON media_assets (mime_type);";
            $pdo->exec($sql);
        } else {
            $sql = "CREATE TABLE IF NOT EXISTS media_assets (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                original_name VARCHAR(255) NOT NULL,
                stored_name VARCHAR(100) NOT NULL UNIQUE,
                file_path VARCHAR(255) NOT NULL,
                file_size_bytes INT UNSIGNED NOT NULL,
                mime_type VARCHAR(50) NOT NULL,
                dimensions VARCHAR(30) NOT NULL,
                alt_text VARCHAR(255) NOT NULL,
                uploaded_by_user_id BIGINT UNSIGNED NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_media_user FOREIGN KEY (uploaded_by_user_id) REFERENCES users (id),
                INDEX idx_media_mime (mime_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
            $pdo->exec($sql);
        }
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS media_assets;");
    }
};
