<?php

declare(strict_types=1);

use Voak\Health\Database\Migration\MigrationInterface;

return new class implements MigrationInterface
{
    public function getVersion(): string
    {
        return '20261021_000025';
    }

    public function getDescription(): string
    {
        return 'Create email_queue table for durable asynchronous transactional outbox';
    }

    public function up(\PDO $pdo): void
    {
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $sql = "CREATE TABLE IF NOT EXISTS email_queue (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                template_key VARCHAR(50) NOT NULL,
                recipient_email VARCHAR(191) NOT NULL,
                recipient_name VARCHAR(150) NOT NULL,
                payload_json TEXT NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'QUEUED',
                attempts INTEGER NOT NULL DEFAULT 0,
                max_attempts INTEGER NOT NULL DEFAULT 3,
                error_message TEXT NULL,
                claimed_at TIMESTAMP NULL DEFAULT NULL,
                worker_id VARCHAR(50) NULL DEFAULT NULL,
                next_retry_at TIMESTAMP NULL DEFAULT NULL,
                sent_at TIMESTAMP NULL DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
            CREATE INDEX IF NOT EXISTS idx_email_queue_claiming ON email_queue (status, next_retry_at, attempts);
            CREATE INDEX IF NOT EXISTS idx_email_queue_claimed_at ON email_queue (claimed_at);";
            $pdo->exec($sql);
        } else {
            $sql = "CREATE TABLE IF NOT EXISTS email_queue (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                template_key VARCHAR(50) NOT NULL,
                recipient_email VARCHAR(191) NOT NULL,
                recipient_name VARCHAR(150) NOT NULL,
                payload_json JSON NOT NULL,
                status ENUM('QUEUED', 'PROCESSING', 'SENT', 'FAILED') NOT NULL DEFAULT 'QUEUED',
                attempts INT UNSIGNED NOT NULL DEFAULT 0,
                max_attempts INT UNSIGNED NOT NULL DEFAULT 3,
                error_message TEXT NULL,
                claimed_at TIMESTAMP NULL DEFAULT NULL,
                worker_id VARCHAR(50) NULL DEFAULT NULL,
                next_retry_at TIMESTAMP NULL DEFAULT NULL,
                sent_at TIMESTAMP NULL DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_email_queue_claiming (status, next_retry_at, attempts),
                INDEX idx_email_queue_claimed_at (claimed_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
            $pdo->exec($sql);
        }
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS email_queue;");
    }
};
