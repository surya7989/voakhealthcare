<?php

declare(strict_types=1);

/**
 * Hostinger Scheduled Task CLI Runner for Transactional Email Outbox.
 * Usage:
 *   php backend/cli/process_email_queue.php [--limit=20] [--worker-id=custom_id]
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Voak\Health\Core\App;
use Voak\Health\Services\EmailService;

// Ensure CLI execution only
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Error: process_email_queue.php may only be executed via CLI.\n");
    exit(1);
}

$shortopts = '';
$longopts = [
    'limit::',
    'worker-id::',
    'env::',
];

$options = getopt($shortopts, $longopts);

$limit = isset($options['limit']) ? (int)$options['limit'] : 20;
$limit = max(1, min(100, $limit));

$workerId = isset($options['worker-id']) ? (string)$options['worker-id'] : null;
$envPath = isset($options['env']) ? (string)$options['env'] : null;

try {
    $app = App::bootstrap($envPath);
    /** @var EmailService $emailService */
    $emailService = $app->getContainer()->get(EmailService::class);

    $startTime = microtime(true);
    echo "[" . date('Y-m-d H:i:s') . "] Starting email outbox queue dispatch...\n";

    $report = $emailService->processQueueBatch($limit, $workerId);
    $elapsed = round(microtime(true) - $startTime, 4);

    echo "[" . date('Y-m-d H:i:s') . "] Dispatch cycle complete in {$elapsed}s.\n";
    echo "  Worker ID: " . $report['worker_id'] . "\n";
    echo "  Processed: " . $report['processed'] . "\n";
    echo "  Sent:      " . $report['sent'] . "\n";
    echo "  Failed:    " . $report['failed'] . "\n";

    exit($report['failed'] > 0 ? 0 : 0); // Clean exit for cron
} catch (\Throwable $e) {
    fwrite(STDERR, "[" . date('Y-m-d H:i:s') . "] CRITICAL ERROR in email queue runner: " . $e->getMessage() . "\n");
    fwrite(STDERR, $e->getTraceAsString() . "\n");
    exit(2);
}
