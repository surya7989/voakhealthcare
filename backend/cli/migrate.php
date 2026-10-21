<?php

declare(strict_types=1);

/**
 * Voak Health Care — Canonical CLI Database Migration Runner
 * Usage:
 *   php backend/cli/migrate.php up
 *   php backend/cli/migrate.php down [--steps=N]
 *   php backend/cli/migrate.php status
 *   php backend/cli/migrate.php fresh
 *   php backend/cli/migrate.php --help
 */

namespace Voak\Health\CLI;

use Voak\Health\Core\Config;
use Voak\Health\Database\Connection;
use Voak\Health\Database\Migration\MigrationManager;
use Voak\Health\Core\Exceptions\DatabaseException;

// Autoloader resolution
$autoloadPaths = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php',
];

$autoloaderLoaded = false;
foreach ($autoloadPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $autoloaderLoaded = true;
        break;
    }
}

if (!$autoloaderLoaded) {
    fwrite(STDERR, "[ERROR] Composer autoloader not found. Run 'composer install' in backend directory.\n");
    exit(1);
}

// Ensure CLI execution only
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo json_encode(['error' => 'Direct web access to migration runner is strictly forbidden (FR-SEC-007).']);
    exit(1);
}

// ANSI styling helpers
function formatText(string $text, string $color = 'none'): string
{
    // Check if terminal supports ANSI colors
    $hasColor = false;
    if (DIRECTORY_SEPARATOR === '\\') {
        $hasColor = getenv('ANSICON') !== false || getenv('ConEmuANSI') === 'ON' || getenv('TERM') === 'xterm' || (function_exists('sapi_windows_vt100_support') && sapi_windows_vt100_support(STDOUT));
    } else {
        $hasColor = function_exists('posix_isatty') && posix_isatty(STDOUT);
    }

    if (!$hasColor) {
        return $text;
    }

    $codes = [
        'green'  => "\033[32m",
        'red'    => "\033[31m",
        'yellow' => "\033[33m",
        'cyan'   => "\033[36m",
        'bold'   => "\033[1m",
        'reset'  => "\033[0m",
    ];

    return ($codes[$color] ?? '') . $text . $codes['reset'];
}

function printHeader(): void
{
    echo formatText("======================================================================\n", 'cyan');
    echo formatText("VOAK HEALTH CARE — DATABASE MIGRATION ENGINE (PHASE 5)\n", 'bold');
    echo formatText("======================================================================\n", 'cyan');
}

function printUsage(): void
{
    printHeader();
    echo "Usage: php " . basename(__FILE__) . " <command> [options]\n\n";
    echo "Commands:\n";
    echo "  " . formatText("up", 'green') . "               Execute all pending migrations\n";
    echo "  " . formatText("down", 'yellow') . "             Roll back the last batch of migrations\n";
    echo "  " . formatText("down --steps=N", 'yellow') . "   Roll back the last N migrations\n";
    echo "  " . formatText("status", 'cyan') . "           Display the status of all migrations\n";
    echo "  " . formatText("fresh", 'red') . "            Roll back all migrations and re-execute all\n";
    echo "  " . formatText("--help, -h", 'bold') . "        Display this help message\n\n";
    echo "Options:\n";
    echo "  --sqlite[=path]    Run migrations against SQLite database (default: :memory: or storage/database.sqlite)\n";
    echo "  --steps=N          Specify number of migrations to roll back (for 'down')\n";
    echo "======================================================================\n";
}

// Parse command line arguments
$args = array_slice($argv, 1);
if (empty($args) || in_array('--help', $args, true) || in_array('-h', $args, true)) {
    printUsage();
    exit(0);
}

$command = strtolower($args[0]);
$useSqlite = false;
$sqlitePath = ':memory:';
$steps = 0;

foreach ($args as $arg) {
    if (str_starts_with($arg, '--sqlite')) {
        $useSqlite = true;
        if (str_contains($arg, '=')) {
            $sqlitePath = substr($arg, strpos($arg, '=') + 1);
        }
    }
    if (str_starts_with($arg, '--steps=')) {
        $steps = (int) substr($arg, 8);
    }
}

// Bootstrap Configuration and PDO
$migrationsDir = __DIR__ . '/../database/migrations';
if (!is_dir($migrationsDir)) {
    @mkdir($migrationsDir, 0755, true);
}

$manager = new MigrationManager($migrationsDir, 'migrations');

try {
    if ($useSqlite || getenv('DB_DRIVER') === 'sqlite') {
        echo formatText("[INFO] Using SQLite persistence: {$sqlitePath}\n", 'cyan');
        if ($sqlitePath !== ':memory:') {
            $dir = dirname($sqlitePath);
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
        }
        $pdo = new \PDO("sqlite:{$sqlitePath}", null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        $pdo->exec("PRAGMA foreign_keys = ON;");
    } else {
        // Load production/environment config
        $envFile = __DIR__ . '/../.env';
        if (file_exists($envFile)) {
            $config = Config::fromEnvFile($envFile);
        } else {
            $config = Config::fromGlobals();
        }

        $connection = Connection::fromConfig($config);
        $pdo = $connection->getPdo();
    }
} catch (\Throwable $e) {
    printHeader();
    fwrite(STDERR, formatText("[ERROR] Database connection failed: ", 'red') . $e->getMessage() . "\n");
    fwrite(STDERR, "Verify your DB configuration in backend/.env or use '--sqlite' for isolated local execution.\n");
    exit(1);
}

// Execute Command
printHeader();

try {
    switch ($command) {
        case 'status':
            $statusList = $manager->getStatus($pdo);
            if (empty($statusList)) {
                echo formatText("No migration blueprints discovered in {$migrationsDir}.\n", 'yellow');
                exit(0);
            }

            echo sprintf("%-20s | %-10s | %-8s | %-19s | %s\n", "Version", "Status", "Batch", "Applied At", "Filename");
            echo str_repeat("-", 80) . "\n";

            $appliedCount = 0;
            $pendingCount = 0;

            foreach ($statusList as $s) {
                $statusBadge = $s['status'] === 'APPLIED' 
                    ? formatText("APPLIED", 'green') 
                    : formatText("PENDING", 'yellow');

                $batchStr = $s['batch'] !== null ? (string) $s['batch'] : '-';
                $appliedAtStr = $s['applied_at'] ?? '-';

                echo sprintf("%-20s | %-19s | %-8s | %-19s | %s\n",
                    $s['version'],
                    $statusBadge,
                    $batchStr,
                    $appliedAtStr,
                    $s['filename']
                );

                if ($s['status'] === 'APPLIED') {
                    $appliedCount++;
                } else {
                    $pendingCount++;
                }
            }

            echo str_repeat("-", 80) . "\n";
            echo "Summary: " . formatText("{$appliedCount} Applied", 'green') . ", " . formatText("{$pendingCount} Pending", 'yellow') . "\n";
            break;

        case 'up':
            echo formatText("Running pending migrations...\n", 'cyan');
            $migrated = $manager->migrateUp($pdo);

            if (empty($migrated)) {
                echo formatText("No pending migrations. Database schema is fully up to date.\n", 'green');
            } else {
                foreach ($migrated as $m) {
                    echo "  [" . formatText("MIGRATED", 'green') . "] {$m['version']} - {$m['description']} (Batch {$m['batch']})\n";
                }
                echo formatText("\nSuccessfully migrated " . count($migrated) . " schema blueprint(s).\n", 'green');
            }
            break;

        case 'down':
            echo formatText("Rolling back migrations...\n", 'yellow');
            $rolledBack = $manager->migrateDown($pdo, $steps);

            if (empty($rolledBack)) {
                echo formatText("Nothing to roll back.\n", 'yellow');
            } else {
                foreach ($rolledBack as $r) {
                    echo "  [" . formatText("ROLLED BACK", 'red') . "] {$r['version']} - {$r['description']}\n";
                }
                echo formatText("\nSuccessfully rolled back " . count($rolledBack) . " migration(s).\n", 'green');
            }
            break;

        case 'fresh':
            echo formatText("Resetting database schema and re-running all migrations...\n", 'red');
            $result = $manager->fresh($pdo);

            if (!empty($result['rolled_back'])) {
                echo "Rolled back " . count($result['rolled_back']) . " migration(s).\n";
            }
            foreach ($result['migrated'] as $m) {
                echo "  [" . formatText("MIGRATED", 'green') . "] {$m['version']} - {$m['description']}\n";
            }
            echo formatText("\nFresh migration complete: " . count($result['migrated']) . " blueprint(s) applied.\n", 'green');
            break;

        default:
            fwrite(STDERR, formatText("[ERROR] Unknown command: '{$command}'. Run with '--help' for available commands.\n", 'red'));
            exit(1);
    }

    echo formatText("======================================================================\n", 'cyan');
    exit(0);

} catch (\Throwable $e) {
    fwrite(STDERR, "\n" . formatText("[MIGRATION ERROR] " . $e->getMessage(), 'red') . "\n");
    if ($e instanceof DatabaseException) {
        fwrite(STDERR, "Context: {$e->getCode()}\n");
    }
    fwrite(STDERR, formatText("======================================================================\n", 'cyan'));
    exit(1);
}
