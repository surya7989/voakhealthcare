<?php

declare(strict_types=1);

use Voak\Health\Database\Migration\MigrationInterface;

return new class implements MigrationInterface
{
    public function getVersion(): string
    {
        return '20261021_000023';
    }

    public function getDescription(): string
    {
        return 'Create case_studies table for client evidence proof and quantified KPIs';
    }

    public function up(\PDO $pdo): void
    {
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $sql = "CREATE TABLE IF NOT EXISTS case_studies (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title VARCHAR(200) NOT NULL,
                slug VARCHAR(150) NOT NULL UNIQUE,
                client_sector VARCHAR(100) NOT NULL,
                challenge_summary TEXT NOT NULL,
                intervention_summary TEXT NOT NULL,
                outcome_metrics_json TEXT NOT NULL,
                consent_verified INTEGER NOT NULL DEFAULT 0,
                is_published INTEGER NOT NULL DEFAULT 0,
                published_at TIMESTAMP NULL DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
            CREATE INDEX IF NOT EXISTS idx_cases_slug_published ON case_studies (slug, is_published);";
            $pdo->exec($sql);
        } else {
            $sql = "CREATE TABLE IF NOT EXISTS case_studies (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(200) NOT NULL,
                slug VARCHAR(150) NOT NULL UNIQUE,
                client_sector VARCHAR(100) NOT NULL,
                challenge_summary TEXT NOT NULL,
                intervention_summary TEXT NOT NULL,
                outcome_metrics_json JSON NOT NULL,
                consent_verified TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                is_published TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                published_at TIMESTAMP NULL DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_cases_slug_published (slug, is_published)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
            $pdo->exec($sql);
        }
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS case_studies;");
    }
};
