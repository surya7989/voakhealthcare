<?php

declare(strict_types=1);

use Voak\Health\Database\Migration\MigrationInterface;

return new class implements MigrationInterface
{
    public function getVersion(): string
    {
        return '20261021_000028';
    }

    public function getDescription(): string
    {
        return 'Create audit_logs table for immutable cryptographically chained SHA-256 ledger';
    }

    public function up(\PDO $pdo): void
    {
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $sql = "CREATE TABLE IF NOT EXISTS audit_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                prev_row_hash CHAR(64) NOT NULL,
                row_hash CHAR(64) NOT NULL UNIQUE,
                domain VARCHAR(50) NOT NULL,
                action VARCHAR(100) NOT NULL,
                user_id INTEGER NULL,
                ip_address VARCHAR(45) NOT NULL,
                user_agent VARCHAR(255) NOT NULL,
                entity_type VARCHAR(50) NULL,
                entity_id INTEGER NULL,
                diff_json TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
            );
            CREATE INDEX IF NOT EXISTS idx_audit_domain_action ON audit_logs (domain, action);
            CREATE INDEX IF NOT EXISTS idx_audit_user ON audit_logs (user_id);
            CREATE INDEX IF NOT EXISTS idx_audit_created_at ON audit_logs (created_at);";
            $pdo->exec($sql);
        } else {
            $sql = "CREATE TABLE IF NOT EXISTS audit_logs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                prev_row_hash CHAR(64) NOT NULL,
                row_hash CHAR(64) NOT NULL UNIQUE,
                domain VARCHAR(50) NOT NULL,
                action VARCHAR(100) NOT NULL,
                user_id BIGINT UNSIGNED NULL,
                ip_address VARCHAR(45) NOT NULL,
                user_agent VARCHAR(255) NOT NULL,
                entity_type VARCHAR(50) NULL,
                entity_id BIGINT UNSIGNED NULL,
                diff_json JSON NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
                INDEX idx_audit_domain_action (domain, action),
                INDEX idx_audit_user (user_id),
                INDEX idx_audit_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
            $pdo->exec($sql);
        }
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS audit_logs;");
    }
};
