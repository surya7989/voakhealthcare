<?php

declare(strict_types=1);

use Voak\Health\Database\Migration\MigrationInterface;

return new class implements MigrationInterface
{
    public function getVersion(): string
    {
        return '20261021_000010';
    }

    public function getDescription(): string
    {
        return 'Create content_revisions table for audit snapshots and rollback history';
    }

    public function up(\PDO $pdo): void
    {
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $sql = "CREATE TABLE IF NOT EXISTS content_revisions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                entity_type VARCHAR(50) NOT NULL,
                entity_id INTEGER NOT NULL,
                editor_user_id INTEGER NOT NULL,
                payload_snapshot TEXT NOT NULL,
                revision_note VARCHAR(255) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (editor_user_id) REFERENCES users (id)
            );
            CREATE INDEX IF NOT EXISTS idx_revisions_entity ON content_revisions (entity_type, entity_id, created_at);";
            $pdo->exec($sql);
        } else {
            $sql = "CREATE TABLE IF NOT EXISTS content_revisions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                entity_type VARCHAR(50) NOT NULL,
                entity_id BIGINT UNSIGNED NOT NULL,
                editor_user_id BIGINT UNSIGNED NOT NULL,
                payload_snapshot JSON NOT NULL,
                revision_note VARCHAR(255) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_revisions_editor FOREIGN KEY (editor_user_id) REFERENCES users (id),
                INDEX idx_revisions_entity (entity_type, entity_id, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
            $pdo->exec($sql);
        }
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS content_revisions;");
    }
};
