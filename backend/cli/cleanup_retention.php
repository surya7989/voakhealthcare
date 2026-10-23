<?php

declare(strict_types=1);

/**
 * Hostinger Scheduled Task CLI Runner for Candidate Privacy & Data Retention Disposal (FR-SEC-004).
 * Locates candidate applications past retention expiry, shreds physical files, and anonymizes PII.
 *
 * Usage:
 *   php backend/cli/cleanup_retention.php [--env=/path/to/.env]
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Voak\Health\Core\App;
use Voak\Health\Services\CareerService;

// Ensure CLI execution only
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Error: cleanup_retention.php may only be executed via CLI.\n");
    exit(1);
}

$shortopts = '';
$longopts = [
    'env::',
];

$options = getopt($shortopts, $longopts);
$envPath = isset($options['env']) ? (string)$options['env'] : null;

try {
    $app = App::bootstrap($envPath);
    /** @var CareerService $careerService */
    $careerService = $app->getContainer()->get(CareerService::class);

    $startTime = microtime(true);
    echo "[" . date('Y-m-d H:i:s') . "] Starting candidate data retention disposal cycle (FR-SEC-004)...\n";

    $report = $careerService->cleanupExpiredApplications();
    $elapsed = round(microtime(true) - $startTime, 4);

    echo "[" . date('Y-m-d H:i:s') . "] Retention cleanup complete in {$elapsed}s.\n";
    echo "  Anonymized Applications: " . $report['anonymized_count'] . "\n";
    echo "  Shredded Physical Files: " . $report['shredded_files_count'] . "\n";

    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, "[" . date('Y-m-d H:i:s') . "] CRITICAL ERROR in retention cleanup runner: " . $e->getMessage() . "\n");
    fwrite(STDERR, $e->getTraceAsString() . "\n");
    exit(2);
}
