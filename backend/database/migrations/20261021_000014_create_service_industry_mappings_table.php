<?php

declare(strict_types=1);

use Voak\Health\Database\Migration\MigrationInterface;

return new class implements MigrationInterface
{
    public function getVersion(): string
    {
        return '20261021_000014';
    }

    public function getDescription(): string
    {
        return 'Create service_industry_mappings table connecting services to verticals';
    }

    public function up(\PDO $pdo): void
    {
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $sql = "CREATE TABLE IF NOT EXISTS service_industry_mappings (
                service_id INTEGER NOT NULL,
                industry_id INTEGER NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (service_id, industry_id),
                FOREIGN KEY (service_id) REFERENCES services (id) ON DELETE CASCADE,
                FOREIGN KEY (industry_id) REFERENCES industries (id) ON DELETE CASCADE
            );";
            $pdo->exec($sql);
        } else {
            $sql = "CREATE TABLE IF NOT EXISTS service_industry_mappings (
                service_id INT UNSIGNED NOT NULL,
                industry_id INT UNSIGNED NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (service_id, industry_id),
                CONSTRAINT fk_sim_service FOREIGN KEY (service_id) REFERENCES services (id) ON DELETE CASCADE,
                CONSTRAINT fk_sim_industry FOREIGN KEY (industry_id) REFERENCES industries (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
            $pdo->exec($sql);
        }
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS service_industry_mappings;");
    }
};
