<?php

declare(strict_types=1);

use Voak\Health\Database\Migration\MigrationInterface;

return new class implements MigrationInterface
{
    public function getVersion(): string
    {
        return '20261021_000016';
    }

    public function getDescription(): string
    {
        return 'Create contact_inquiries table with triage FSM status machine';
    }

    public function up(\PDO $pdo): void
    {
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $sql = "CREATE TABLE IF NOT EXISTS contact_inquiries (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                reference_code VARCHAR(30) NOT NULL UNIQUE,
                full_name VARCHAR(150) NOT NULL,
                organization_name VARCHAR(150) NOT NULL,
                corporate_email VARCHAR(191) NOT NULL,
                contact_phone VARCHAR(25) NOT NULL,
                inquiry_details TEXT NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'NEW',
                assigned_user_id INTEGER NULL,
                internal_notes TEXT NULL,
                ip_address VARCHAR(45) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (assigned_user_id) REFERENCES users (id) ON DELETE SET NULL
            );
            CREATE INDEX IF NOT EXISTS idx_inquiries_status ON contact_inquiries (status, created_at);
            CREATE INDEX IF NOT EXISTS idx_inquiries_ref ON contact_inquiries (reference_code);";
            $pdo->exec($sql);
        } else {
            $sql = "CREATE TABLE IF NOT EXISTS contact_inquiries (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                reference_code VARCHAR(30) NOT NULL UNIQUE,
                full_name VARCHAR(150) NOT NULL,
                organization_name VARCHAR(150) NOT NULL,
                corporate_email VARCHAR(191) NOT NULL,
                contact_phone VARCHAR(25) NOT NULL,
                inquiry_details TEXT NOT NULL,
                status ENUM('NEW', 'TRIAGED', 'IN_REVIEW', 'CONTACTED', 'RESOLVED', 'CLOSED', 'SPAM') NOT NULL DEFAULT 'NEW',
                assigned_user_id BIGINT UNSIGNED NULL,
                internal_notes TEXT NULL,
                ip_address VARCHAR(45) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_inquiries_assignee FOREIGN KEY (assigned_user_id) REFERENCES users (id) ON DELETE SET NULL,
                INDEX idx_inquiries_status (status, created_at),
                INDEX idx_inquiries_ref (reference_code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
            $pdo->exec($sql);
        }
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS contact_inquiries;");
    }
};
