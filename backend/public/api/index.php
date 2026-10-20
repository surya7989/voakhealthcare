<?php

declare(strict_types=1);

/**
 * Voak Health Care — API Gateway Entry Point (public_html/api/index.php)
 * Canonical REST Gateway specified in Phase 2 Architecture.
 */

$backendRoot = dirname(__DIR__, 2);

// Load Composer Autoloader
$autoloadPath = $backendRoot . '/vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'INITIALIZATION_ERROR',
            'message' => 'Composer dependencies not installed. Please run "composer dump-autoload".',
        ],
    ], JSON_UNESCAPED_SLASHES);
    exit(1);
}

require_once $autoloadPath;

use Voak\Health\Core\App;

// Bootstrap application kernel with optional .env in backend root
$envPath = $backendRoot . '/.env';
$app = App::bootstrap(file_exists($envPath) ? $envPath : null);

// Handle HTTP request and emit response
$app->run();
