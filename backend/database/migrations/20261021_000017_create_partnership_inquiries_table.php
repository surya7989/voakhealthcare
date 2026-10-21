<?php

declare(strict_types=1);

use Voak\Health\Database\Migration\MigrationInterface;

return new class implements MigrationInterface
{
    public function getVersion(): string
    {
        return '20261021_000017';
    }

    public function getDescription(): string
    {
        return 'Create partnership_inquiries table for healthcare alliance intake pipeline';
    }

    public function up(\PDO $pdo): void
    {
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $sql = "CREATE TABLE IF NOT EXISTS partnership_inquiries (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                reference_code VARCHAR(30) NOT NULL UNIQUE,
                organization_name VARCHAR(150) NOT NULL,
                contact_name VARCHAR(150) NOT NULL,
                corporate_email VARCHAR(191) NOT NULL,
                contact_phone VARCHAR(25) NOT NULL,
                alliance_type VARCHAR(50) NOT NULL,
                proposed_scope TEXT NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'NEW',
                assigned_user_id INTEGER NULL,
                internal_notes TEXT NULL,
                ip_address VARCHAR(45) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (assigned_user_id) REFERENCES users (id) ON DELETE SET NULL
            );
            CREATE INDEX IF NOT EXISTS idx_partnerships_status ON partnership_inquiries (status, created_at);
            CREATE INDEX IF NOT EXISTS idx_partnerships_ref ON partnership_inquiries (reference_code);";
            $pdo->exec($sql);
        } else {
            $sql = "CREATE TABLE IF NOT EXISTS partnership_inquiries (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                reference_code VARCHAR(30) NOT NULL UNIQUE,
                organization_name VARCHAR(150) NOT NULL,
                contact_name VARCHAR(150) NOT NULL,
                corporate_email VARCHAR(191) NOT NULL,
                contact_phone VARCHAR(25) NOT NULL,
                alliance_type VARCHAR(50) NOT NULL,
                proposed_scope TEXT NOT NULL,
                status ENUM('NEW', 'EVALUATION', 'DISCUSSION', 'LEGAL_REVIEW', 'ACTIVE_ALLIANCE', 'DECLINED') NOT NULL DEFAULT 'NEW',
                assigned_user_id BIGINT UNSIGNED NULL,
                internal_notes TEXT NULL,
                ip_address VARCHAR(45) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_partnerships_assignee FOREIGN KEY (assigned_user_id) REFERENCES users (id) ON DELETE SET NULL,
                INDEX idx_partnerships_status (status, created_at),
                INDEX idx_partnerships_ref (reference_code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
            $pdo->exec($sql);
        }
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS partnership_inquiries;");
    }
};
