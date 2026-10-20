<?php

declare(strict_types=1);

namespace Voak\Health\Database\Migration;

use Voak\Health\Core\Exceptions\DatabaseException;

/**
 * MigrationManager discovers schema blueprints and manages tracking without executing unauthorized production mutations.
 */
class MigrationManager
{
    private string $migrationsDirectory;
    private string $tableName;

    public function __construct(string $migrationsDirectory, string $tableName = 'schema_migrations')
    {
        $this->migrationsDirectory = rtrim($migrationsDirectory, '/\\');
        $this->tableName = $tableName;
    }

    /**
     * Safely discovers all migration files adhering to canonical naming conventions.
     * Naming standard: YYYYMMDD_HHMMSS_name.php or vXX_name.php
     *
     * @return array<int, array{file: string, version: string, filename: string}>
     */
    public function discoverMigrations(): array
    {
        if (!is_dir($this->migrationsDirectory)) {
            return [];
        }

        $files = scandir($this->migrationsDirectory) ?: [];
        $migrations = [];

        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || !str_ends_with($file, '.php')) {
                continue;
            }

            $version = '';
            if (preg_match('/^([0-9]{8}_[0-9]{6}|v[0-9]+)_(.+)\.php$/', $file, $m)) {
                $version = $m[1];
            } else {
                $version = pathinfo($file, PATHINFO_FILENAME);
            }

            $migrations[] = [
                'file' => $this->migrationsDirectory . DIRECTORY_SEPARATOR . $file,
                'version' => $version,
                'filename' => $file,
            ];
        }

        // Sort chronologically by version
        usort($migrations, fn ($a, $b) => strcmp($a['version'], $b['version']));

        return $migrations;
    }

    /**
     * Ensures the migrations tracking table blueprint exists.
     */
    public function ensureTrackingTable(\PDO $pdo): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS `{$this->tableName}` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `version` VARCHAR(100) NOT NULL UNIQUE,
            `description` VARCHAR(255) NOT NULL,
            `applied_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        $pdo->exec($sql);
    }

    /**
     * Gets migration status (APPLIED vs PENDING) without executing mutations.
     */
    public function getStatus(\PDO $pdo): array
    {
        $this->ensureTrackingTable($pdo);

        $stmt = $pdo->query("SELECT `version`, `applied_at` FROM `{$this->tableName}`");
        $applied = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $applied[$row['version']] = $row['applied_at'];
        }

        $discovered = $this->discoverMigrations();
        $status = [];

        foreach ($discovered as $item) {
            $isApplied = isset($applied[$item['version']]);
            $status[] = [
                'version' => $item['version'],
                'filename' => $item['filename'],
                'status' => $isApplied ? 'APPLIED' : 'PENDING',
                'applied_at' => $applied[$item['version']] ?? null,
            ];
        }

        return $status;
    }
}
