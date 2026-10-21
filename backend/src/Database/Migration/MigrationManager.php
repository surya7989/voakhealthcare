<?php

declare(strict_types=1);

namespace Voak\Health\Database\Migration;

use Voak\Health\Core\Exceptions\DatabaseException;

/**
 * MigrationManager discovers schema blueprints and manages the lifecycle of database migrations.
 */
class MigrationManager
{
    private string $migrationsDirectory;
    private string $tableName;

    public function __construct(string $migrationsDirectory, string $tableName = 'migrations')
    {
        $this->migrationsDirectory = rtrim($migrationsDirectory, '/\\');
        $this->tableName = $tableName;
    }

    public function getMigrationsDirectory(): string
    {
        return $this->migrationsDirectory;
    }

    public function getTableName(): string
    {
        return $this->tableName;
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
     * Instantiates a MigrationInterface from the given file path.
     * Supports files returning an anonymous class or declaring a class implementing MigrationInterface.
     */
    public function loadMigration(string $filePath): MigrationInterface
    {
        if (!file_exists($filePath)) {
            throw new DatabaseException("Migration file not found: {$filePath}");
        }

        $declaredBefore = get_declared_classes();
        $returned = require $filePath;

        if ($returned instanceof MigrationInterface) {
            return $returned;
        }

        $declaredAfter = get_declared_classes();
        $diff = array_diff($declaredAfter, $declaredBefore);

        foreach ($diff as $className) {
            $ref = new \ReflectionClass($className);
            if ($ref->implementsInterface(MigrationInterface::class) && !$ref->isAbstract()) {
                return new $className();
            }
        }

        throw new DatabaseException("File [{$filePath}] does not return or declare a class implementing MigrationInterface.");
    }

    /**
     * Ensures the migrations tracking table exists.
     */
    public function ensureTrackingTable(\PDO $pdo): void
    {
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $sql = "CREATE TABLE IF NOT EXISTS `{$this->tableName}` (
                `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                `version` VARCHAR(100) NOT NULL UNIQUE,
                `description` VARCHAR(255) NOT NULL,
                `batch` INTEGER NOT NULL DEFAULT 1,
                `applied_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );";
        } else {
            $sql = "CREATE TABLE IF NOT EXISTS `{$this->tableName}` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `version` VARCHAR(100) NOT NULL UNIQUE,
                `description` VARCHAR(255) NOT NULL,
                `batch` INT UNSIGNED NOT NULL DEFAULT 1,
                `applied_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        }

        $pdo->exec($sql);
    }

    /**
     * Acquires a mutex lock before running migrations to prevent concurrent execution.
     */
    public function acquireLock(\PDO $pdo, int $timeoutSeconds = 10): bool
    {
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            $stmt = $pdo->prepare("SELECT GET_LOCK('voak_migration_mutex', :timeout) AS lck");
            $stmt->execute(['timeout' => $timeoutSeconds]);
            $res = $stmt->fetch(\PDO::FETCH_ASSOC);
            return isset($res['lck']) && (int) $res['lck'] === 1;
        }

        return true;
    }

    /**
     * Releases the mutex lock after migration execution.
     */
    public function releaseLock(\PDO $pdo): void
    {
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            $pdo->exec("SELECT RELEASE_LOCK('voak_migration_mutex')");
        }
    }

    /**
     * Gets the next batch sequence number.
     */
    public function getNextBatchNumber(\PDO $pdo): int
    {
        $this->ensureTrackingTable($pdo);
        $stmt = $pdo->query("SELECT COALESCE(MAX(`batch`), 0) + 1 AS next_batch FROM `{$this->tableName}`");
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        return (int) ($row['next_batch'] ?? 1);
    }

    /**
     * Gets migration status (APPLIED vs PENDING) without executing mutations.
     *
     * @return array<int, array{version: string, filename: string, status: string, batch: ?int, applied_at: ?string, description: ?string}>
     */
    public function getStatus(\PDO $pdo): array
    {
        $this->ensureTrackingTable($pdo);

        $stmt = $pdo->query("SELECT `version`, `description`, `batch`, `applied_at` FROM `{$this->tableName}`");
        $applied = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $applied[$row['version']] = $row;
        }
        $stmt->closeCursor();

        $discovered = $this->discoverMigrations();
        $status = [];

        foreach ($discovered as $item) {
            $isApplied = isset($applied[$item['version']]);
            $appliedData = $applied[$item['version']] ?? null;

            $status[] = [
                'version' => $item['version'],
                'filename' => $item['filename'],
                'status' => $isApplied ? 'APPLIED' : 'PENDING',
                'batch' => $appliedData ? (int) $appliedData['batch'] : null,
                'applied_at' => $appliedData['applied_at'] ?? null,
                'description' => $appliedData['description'] ?? null,
            ];
        }

        return $status;
    }

    /**
     * Runs all pending migrations in order, recording each in the tracking table.
     *
     * @return array<int, array{version: string, filename: string, description: string, batch: int}>
     */
    public function migrateUp(\PDO $pdo): array
    {
        $this->ensureTrackingTable($pdo);

        if (!$this->acquireLock($pdo)) {
            throw new DatabaseException("Failed to acquire migration lock. Another migration process may be running.");
        }

        try {
            $status = $this->getStatus($pdo);
            $pending = array_filter($status, fn ($item) => $item['status'] === 'PENDING');

            if (empty($pending)) {
                return [];
            }

            $batch = $this->getNextBatchNumber($pdo);
            $migrated = [];

            foreach ($pending as $item) {
                $filePath = $this->migrationsDirectory . DIRECTORY_SEPARATOR . $item['filename'];
                $migration = $this->loadMigration($filePath);

                // Execute migration DDL
                $migration->up($pdo);

                // Record execution
                $stmt = $pdo->prepare(
                    "INSERT INTO `{$this->tableName}` (`version`, `description`, `batch`, `applied_at`) VALUES (:version, :desc, :batch, CURRENT_TIMESTAMP)"
                );
                $stmt->execute([
                    'version' => $migration->getVersion(),
                    'desc' => $migration->getDescription(),
                    'batch' => $batch,
                ]);
                $stmt->closeCursor();

                $migrated[] = [
                    'version' => $migration->getVersion(),
                    'filename' => $item['filename'],
                    'description' => $migration->getDescription(),
                    'batch' => $batch,
                ];
            }

            return $migrated;
        } finally {
            $this->releaseLock($pdo);
        }
    }

    /**
     * Rolls back migrations. If $steps is 0 or negative, rolls back the latest batch.
     * Otherwise rolls back the specified number of individual migrations.
     *
     * @return array<int, array{version: string, description: string}>
     */
    public function migrateDown(\PDO $pdo, int $steps = 0): array
    {
        $this->ensureTrackingTable($pdo);

        if (!$this->acquireLock($pdo)) {
            throw new DatabaseException("Failed to acquire migration lock. Another migration process may be running.");
        }

        try {
            // Find migrations to roll back in reverse order
            if ($steps <= 0) {
                // Roll back latest batch
                $stmt = $pdo->query("SELECT MAX(`batch`) as max_batch FROM `{$this->tableName}`");
                $maxBatch = (int) ($stmt->fetchColumn() ?: 0);
                $stmt->closeCursor();

                if ($maxBatch === 0) {
                    return [];
                }
                $stmt = $pdo->prepare("SELECT `version`, `description` FROM `{$this->tableName}` WHERE `batch` = :batch ORDER BY `id` DESC");
                $stmt->execute(['batch' => $maxBatch]);
                $targets = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                $stmt->closeCursor();
            } else {
                $stmt = $pdo->prepare("SELECT `version`, `description` FROM `{$this->tableName}` ORDER BY `id` DESC LIMIT :lim");
                $stmt->bindValue(':lim', $steps, \PDO::PARAM_INT);
                $stmt->execute();
                $targets = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                $stmt->closeCursor();
            }

            if (empty($targets)) {
                return [];
            }

            $discovered = $this->discoverMigrations();
            $indexed = [];
            foreach ($discovered as $d) {
                $indexed[$d['version']] = $d['file'];
            }

            $rolledBack = [];

            foreach ($targets as $target) {
                $version = $target['version'];
                if (!isset($indexed[$version])) {
                    throw new DatabaseException("Migration file for version [{$version}] not found on disk.");
                }

                $migration = $this->loadMigration($indexed[$version]);
                $migration->down($pdo);

                $delStmt = $pdo->prepare("DELETE FROM `{$this->tableName}` WHERE `version` = :version");
                $delStmt->execute(['version' => $version]);
                $delStmt->closeCursor();

                $rolledBack[] = [
                    'version' => $version,
                    'description' => $target['description'],
                ];
            }

            return $rolledBack;
        } finally {
            $this->releaseLock($pdo);
        }
    }

    /**
     * Rolls back all migrations and executes migrateUp.
     *
     * @return array{rolled_back: array, migrated: array}
     */
    public function fresh(\PDO $pdo): array
    {
        $this->ensureTrackingTable($pdo);

        $stmt = $pdo->query("SELECT COUNT(*) FROM `{$this->tableName}`");
        $count = (int) $stmt->fetchColumn();
        $stmt->closeCursor();

        $rolledBack = [];
        if ($count > 0) {
            $rolledBack = $this->migrateDown($pdo, $count);
        }

        $migrated = $this->migrateUp($pdo);

        return [
            'rolled_back' => $rolledBack,
            'migrated' => $migrated,
        ];
    }

}
