<?php

declare(strict_types=1);

/**
 * Voak Health Care — Public Web Root Entry Point (public_html/index.php)
 * In production, this pre-renders or routes API/health requests.
 */

$backendRoot = dirname(__DIR__);

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

// Bootstrap and run application
$envPath = $backendRoot . '/.env';
$app = App::bootstrap(file_exists($envPath) ? $envPath : null);
$app->run();
