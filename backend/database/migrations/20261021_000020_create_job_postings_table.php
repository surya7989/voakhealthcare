<?php

declare(strict_types=1);

use Voak\Health\Database\Migration\MigrationInterface;

return new class implements MigrationInterface
{
    public function getVersion(): string
    {
        return '20261021_000020';
    }

    public function getDescription(): string
    {
        return 'Create job_postings table for careers vacancies and specifications';
    }

    public function up(\PDO $pdo): void
    {
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $sql = "CREATE TABLE IF NOT EXISTS job_postings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title VARCHAR(150) NOT NULL,
                slug VARCHAR(100) NOT NULL UNIQUE,
                department VARCHAR(100) NOT NULL,
                employment_type VARCHAR(20) NOT NULL,
                location VARCHAR(100) NOT NULL,
                summary TEXT NOT NULL,
                responsibilities_json TEXT NOT NULL,
                requirements_json TEXT NOT NULL,
                is_active INTEGER NOT NULL DEFAULT 1,
                closing_date DATE NULL,
                created_by_user_id INTEGER NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (created_by_user_id) REFERENCES users (id)
            );
            CREATE INDEX IF NOT EXISTS idx_jobs_active_dept ON job_postings (is_active, department);";
            $pdo->exec($sql);
        } else {
            $sql = "CREATE TABLE IF NOT EXISTS job_postings (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(150) NOT NULL,
                slug VARCHAR(100) NOT NULL UNIQUE,
                department VARCHAR(100) NOT NULL,
                employment_type ENUM('FULL_TIME', 'PART_TIME', 'CASUAL', 'CONTRACT') NOT NULL,
                location VARCHAR(100) NOT NULL,
                summary TEXT NOT NULL,
                responsibilities_json JSON NOT NULL,
                requirements_json JSON NOT NULL,
                is_active TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
                closing_date DATE NULL,
                created_by_user_id BIGINT UNSIGNED NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_jobs_creator FOREIGN KEY (created_by_user_id) REFERENCES users (id),
                INDEX idx_jobs_active_dept (is_active, department)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
            $pdo->exec($sql);
        }
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS job_postings;");
    }
};
