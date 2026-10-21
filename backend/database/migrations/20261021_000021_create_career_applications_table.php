<?php

declare(strict_types=1);

use Voak\Health\Database\Migration\MigrationInterface;

return new class implements MigrationInterface
{
    public function getVersion(): string
    {
        return '20261021_000021';
    }

    public function getDescription(): string
    {
        return 'Create career_applications table with quarantined resume references and GDPR retention';
    }

    public function up(\PDO $pdo): void
    {
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $sql = "CREATE TABLE IF NOT EXISTS career_applications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                reference_code VARCHAR(30) NOT NULL UNIQUE,
                job_id INTEGER NOT NULL,
                full_name VARCHAR(150) NOT NULL,
                email VARCHAR(191) NOT NULL,
                phone VARCHAR(25) NOT NULL,
                country_code CHAR(2) NOT NULL DEFAULT 'AU',
                ahpra_number VARCHAR(50) NULL,
                quarantined_resume_path VARCHAR(255) NOT NULL,
                original_resume_name VARCHAR(255) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'SUBMITTED',
                rejection_reason VARCHAR(100) NULL,
                retention_expiry_date DATE NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (job_id) REFERENCES job_postings (id)
            );
            CREATE INDEX IF NOT EXISTS idx_applications_job ON career_applications (job_id, status);
            CREATE INDEX IF NOT EXISTS idx_applications_retention ON career_applications (retention_expiry_date);";
            $pdo->exec($sql);
        } else {
            $sql = "CREATE TABLE IF NOT EXISTS career_applications (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                reference_code VARCHAR(30) NOT NULL UNIQUE,
                job_id INT UNSIGNED NOT NULL,
                full_name VARCHAR(150) NOT NULL,
                email VARCHAR(191) NOT NULL,
                phone VARCHAR(25) NOT NULL,
                country_code CHAR(2) NOT NULL DEFAULT 'AU',
                ahpra_number VARCHAR(50) NULL,
                quarantined_resume_path VARCHAR(255) NOT NULL,
                original_resume_name VARCHAR(255) NOT NULL,
                status ENUM('SUBMITTED', 'REVIEWING', 'SCREENING', 'INTERVIEWING', 'OFFER', 'HIRED', 'REJECTED', 'WITHDRAWN') NOT NULL DEFAULT 'SUBMITTED',
                rejection_reason VARCHAR(100) NULL,
                retention_expiry_date DATE NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_applications_job FOREIGN KEY (job_id) REFERENCES job_postings (id),
                INDEX idx_applications_job (job_id, status),
                INDEX idx_applications_retention (retention_expiry_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
            $pdo->exec($sql);
        }
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS career_applications;");
    }
};
