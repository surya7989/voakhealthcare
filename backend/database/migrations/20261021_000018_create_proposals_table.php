<?php

declare(strict_types=1);

use Voak\Health\Database\Migration\MigrationInterface;

return new class implements MigrationInterface
{
    public function getVersion(): string
    {
        return '20261021_000018';
    }

    public function getDescription(): string
    {
        return 'Create proposals table for enterprise 4-step RFP wizard submissions';
    }

    public function up(\PDO $pdo): void
    {
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $sql = "CREATE TABLE IF NOT EXISTS proposals (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                reference_code VARCHAR(30) NOT NULL UNIQUE,
                organization_name VARCHAR(150) NOT NULL,
                contact_name VARCHAR(150) NOT NULL,
                contact_email VARCHAR(191) NOT NULL,
                contact_phone VARCHAR(25) NOT NULL,
                facility_type VARCHAR(100) NOT NULL,
                timeline VARCHAR(50) NOT NULL,
                budget_band VARCHAR(50) NOT NULL,
                quarantined_file_path VARCHAR(255) NOT NULL,
                original_file_name VARCHAR(255) NOT NULL,
                file_size_bytes INTEGER NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'SUBMITTED',
                estimated_commercial_value DECIMAL(12,2) NULL,
                assigned_lead_id INTEGER NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (assigned_lead_id) REFERENCES users (id) ON DELETE SET NULL
            );
            CREATE INDEX IF NOT EXISTS idx_proposals_status ON proposals (status);
            CREATE INDEX IF NOT EXISTS idx_proposals_ref ON proposals (reference_code);";
            $pdo->exec($sql);
        } else {
            $sql = "CREATE TABLE IF NOT EXISTS proposals (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                reference_code VARCHAR(30) NOT NULL UNIQUE,
                organization_name VARCHAR(150) NOT NULL,
                contact_name VARCHAR(150) NOT NULL,
                contact_email VARCHAR(191) NOT NULL,
                contact_phone VARCHAR(25) NOT NULL,
                facility_type VARCHAR(100) NOT NULL,
                timeline VARCHAR(50) NOT NULL,
                budget_band VARCHAR(50) NOT NULL,
                quarantined_file_path VARCHAR(255) NOT NULL,
                original_file_name VARCHAR(255) NOT NULL,
                file_size_bytes INT UNSIGNED NOT NULL,
                status ENUM('SUBMITTED', 'UNDER_REVIEW', 'SCOPING', 'PROPOSAL_SENT', 'ACCEPTED', 'REJECTED', 'EXPIRED') NOT NULL DEFAULT 'SUBMITTED',
                estimated_commercial_value DECIMAL(12,2) NULL,
                assigned_lead_id BIGINT UNSIGNED NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_proposals_lead FOREIGN KEY (assigned_lead_id) REFERENCES users (id) ON DELETE SET NULL,
                INDEX idx_proposals_status (status),
                INDEX idx_proposals_ref (reference_code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
            $pdo->exec($sql);
        }
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS proposals;");
    }
};
