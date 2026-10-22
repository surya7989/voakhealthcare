<?php

declare(strict_types=1);

/**
 * Voak Health Care — Phase 8 Services Catalog Management Test Suite
 * Executes Phase 8 unit and integration test suites:
 * - ServiceService & 301 SEO Redirect Engine
 * - Public & Admin Services Catalog Management APIs
 */

$backendRoot = dirname(__DIR__) . '/backend';
ini_set('memory_limit', '512M');
require_once $backendRoot . '/vendor/autoload.php';

use Voak\Health\Tests\Integration\ServicesManagementApiTest;
use Voak\Health\Tests\TestFramework;
use Voak\Health\Tests\Unit\ServiceServiceTest;

echo "======================================================================\n";
echo "VOAK HEALTH CARE — PHASE 8 SERVICES CATALOG MANAGEMENT TEST SUITE\n";
echo "======================================================================\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Runtime:     " . PHP_OS . " (" . php_uname('s') . ")\n";
echo "Timestamp:   " . gmdate('Y-m-d H:i:s') . " UTC\n";

ServiceServiceTest::run();
ServicesManagementApiTest::run();

$exitCode = TestFramework::summarize();
exit($exitCode);
