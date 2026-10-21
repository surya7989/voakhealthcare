<?php

declare(strict_types=1);

use Voak\Health\Database\Migration\MigrationInterface;

return new class implements MigrationInterface
{
    public function getVersion(): string
    {
        return '20261021_000019';
    }

    public function getDescription(): string
    {
        return 'Create proposal_services junction table mapping RFP submissions to services';
    }

    public function up(\PDO $pdo): void
    {
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $sql = "CREATE TABLE IF NOT EXISTS proposal_services (
                proposal_id INTEGER NOT NULL,
                service_id INTEGER NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (proposal_id, service_id),
                FOREIGN KEY (proposal_id) REFERENCES proposals (id) ON DELETE CASCADE,
                FOREIGN KEY (service_id) REFERENCES services (id) ON DELETE CASCADE
            );";
            $pdo->exec($sql);
        } else {
            $sql = "CREATE TABLE IF NOT EXISTS proposal_services (
                proposal_id BIGINT UNSIGNED NOT NULL,
                service_id INT UNSIGNED NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (proposal_id, service_id),
                CONSTRAINT fk_prop_serv_proposal FOREIGN KEY (proposal_id) REFERENCES proposals (id) ON DELETE CASCADE,
                CONSTRAINT fk_prop_serv_service FOREIGN KEY (service_id) REFERENCES services (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
            $pdo->exec($sql);
        }
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS proposal_services;");
    }
};
