<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Voak\Health\Database\Migration\MigrationManager;

$expectedDatabase = 'voak_staging_ci';
$host = (string) getenv('DB_HOST');
$database = (string) getenv('DB_DATABASE');
$guard = (string) getenv('VOAK_MYSQL_TEST');

if ($guard !== '1' || $database !== $expectedDatabase || !in_array($host, ['127.0.0.1', 'localhost'], true)) {
    fwrite(STDERR, "Refusing to run: this test requires VOAK_MYSQL_TEST=1, localhost, and DB_DATABASE=voak_staging_ci.\n");
    exit(2);
}

if (!extension_loaded('pdo_mysql')) {
    fwrite(STDERR, "pdo_mysql extension is required.\n");
    exit(2);
}

function verify(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = :table AND table_type = 'BASE TABLE'"
    );
    $stmt->execute(['table' => $table]);
    $exists = (int) $stmt->fetchColumn() > 0;
    $stmt->closeCursor();
    return $exists;
}

try {
    $port = (int) (getenv('DB_PORT') ?: '3306');
    $username = (string) getenv('DB_USERNAME');
    $password = (string) getenv('DB_PASSWORD');
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database),
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
        ]
    );

    verify((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql', 'Connected PDO driver is not MySQL.');
    $stmt = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'"
    );
    $initialTables = (int) $stmt->fetchColumn();
    $stmt->closeCursor();
    verify($initialTables === 0, 'Refusing to mutate a non-empty CI schema; use a fresh disposable database.');

    $migrationDirectory = dirname(__DIR__) . '/database/migrations';
    $manager = new MigrationManager($migrationDirectory, 'migrations');
    $expectedMigrations = 28;
    $discovered = $manager->discoverMigrations();
    verify(count($discovered) === $expectedMigrations, sprintf('Expected %d migration blueprints; found %d.', $expectedMigrations, count($discovered)));

    echo "[1/7] Applying all MySQL migration blueprints...\n";
    $freshResult = $manager->fresh($pdo);
    verify(count($freshResult['migrated']) === $expectedMigrations, 'Fresh run did not apply all 28 migration blueprints.');

    $status = $manager->getStatus($pdo);
    $applied = array_filter($status, static fn (array $row): bool => $row['status'] === 'APPLIED');
    verify(count($status) === $expectedMigrations && count($applied) === $expectedMigrations, 'Migration tracking table does not show 28 applied migrations.');

    echo "[2/7] Verifying idempotency...\n";
    verify($manager->migrateUp($pdo) === [], 'A second migrateUp call mutated an already-current schema.');

    echo "[3/7] Verifying InnoDB engine and utf8mb4_unicode_ci collation...\n";
    $stmt = $pdo->query(
        "SELECT TABLE_NAME AS table_name, ENGINE AS engine, TABLE_COLLATION AS table_collation
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_type = 'BASE TABLE'
           AND table_name <> 'migrations'
         ORDER BY table_name"
    );
    $rawTables = $stmt->fetchAll();
    $stmt->closeCursor();
    $tables = array_map(static fn (array $row): array => array_change_key_case($row, CASE_LOWER), $rawTables);
    verify(count($tables) === $expectedMigrations, sprintf('Expected 28 domain tables, found %d.', count($tables)));
    foreach ($tables as $table) {
        verify(($table['engine'] ?? '') === 'InnoDB', sprintf('Table %s is not InnoDB (found: %s).', $table['table_name'] ?? 'unknown', $table['engine'] ?? 'none'));
        verify(($table['table_collation'] ?? '') === 'utf8mb4_unicode_ci', sprintf('Table %s has unexpected collation %s.', $table['table_name'] ?? 'unknown', (string) ($table['table_collation'] ?? 'none')));
    }

    echo "[4/7] Verifying relational foreign-key constraints...\n";
    $stmt = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.table_constraints
         WHERE table_schema = DATABASE() AND constraint_type = 'FOREIGN KEY'"
    );
    $foreignKeys = (int) $stmt->fetchColumn();
    $stmt->closeCursor();
    verify($foreignKeys > 0, 'No foreign-key constraints were created.');

    echo "[5/7] Verifying single-step rollback and reapplication...\n";
    $rolledBackOne = $manager->migrateDown($pdo, 1);
    verify(count($rolledBackOne) === 1, 'Single-step rollback did not roll back exactly one migration.');
    verify(!tableExists($pdo, 'audit_logs'), 'Single-step rollback should remove the latest audit_logs migration.');
    verify(count($manager->migrateUp($pdo)) === 1, 'Reapplication after single-step rollback did not apply exactly one migration.');
    verify(tableExists($pdo, 'audit_logs'), 'audit_logs was not restored after reapplication.');

    echo "[6/7] Verifying complete rollback and fresh reapplication...\n";
    $rolledBackAll = $manager->migrateDown($pdo, $expectedMigrations);
    verify(count($rolledBackAll) === $expectedMigrations, sprintf('Expected rollback of 28 migrations; rolled back %d.', count($rolledBackAll)));
    verify(!tableExists($pdo, 'users') && !tableExists($pdo, 'audit_logs'), 'Expected endpoint tables to be absent after complete rollback.');
    $pendingStatus = $manager->getStatus($pdo);
    verify(count(array_filter($pendingStatus, static fn (array $row): bool => $row['status'] === 'APPLIED')) === 0, 'Tracking table should show zero applied migrations after rollback.');

    $reapplied = $manager->migrateUp($pdo);
    verify(count($reapplied) === $expectedMigrations, 'Full reapplication did not restore all 28 migrations.');
    $finalStatus = $manager->getStatus($pdo);
    verify(count(array_filter($finalStatus, static fn (array $row): bool => $row['status'] === 'APPLIED')) === $expectedMigrations, 'Final migration status is not 28/28 applied.');

    echo "[7/7] Verifying final MySQL schema state...\n";
    verify(tableExists($pdo, 'users') && tableExists($pdo, 'admin_sessions') && tableExists($pdo, 'admin_mfa_secrets') && tableExists($pdo, 'audit_logs'), 'One or more critical Phase 5/6 prerequisite tables are missing.');

    $stmt = $pdo->query('SELECT VERSION()');
    $version = (string) $stmt->fetchColumn();
    $stmt->closeCursor();

    printf(
        "PASS: MySQL %s; %d migrations; %d InnoDB domain tables; %d foreign-key constraints; idempotency, one-step rollback, full rollback, and reapplication verified.\n",
        $version,
        $expectedMigrations,
        count($tables),
        $foreignKeys
    );
} catch (Throwable $error) {
    $msg = "[FAIL] " . $error->getMessage() . "\n" . $error->getFile() . ':' . $error->getLine() . "\n" . $error->getTraceAsString() . "\n";
    echo "::error title=MySQL Integration Test Error::" . str_replace(["\r", "\n"], ["", "%0A"], $msg) . "\n";
    echo $msg;
    fwrite(STDERR, $msg);
    if ($stepSummary = getenv('GITHUB_STEP_SUMMARY')) {
        @file_put_contents($stepSummary, "### MySQL Integration Test Failure\n```\n" . $msg . "```\n", FILE_APPEND);
    }
    exit(1);
}
