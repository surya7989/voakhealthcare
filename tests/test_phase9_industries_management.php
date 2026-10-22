<?php

declare(strict_types=1);

/**
 * Voak Health Care — Phase 9 Healthcare Industries & Sectors Management Test Suite
 * Executes Phase 9 unit and integration test suites:
 * - IndustryService & Relational Mappings Engine
 * - Public & Admin Industries Management APIs
 */

$backendRoot = dirname(__DIR__) . '/backend';
ini_set('memory_limit', '512M');
require_once $backendRoot . '/vendor/autoload.php';

use Voak\Health\Tests\Integration\IndustriesManagementApiTest;
use Voak\Health\Tests\TestFramework;
use Voak\Health\Tests\Unit\IndustryServiceTest;

echo "======================================================================\n";
echo "VOAK HEALTH CARE — PHASE 9 INDUSTRIES MANAGEMENT TEST SUITE\n";
echo "======================================================================\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Runtime:     " . PHP_OS . " (" . php_uname('s') . ")\n";
echo "Timestamp:   " . gmdate('Y-m-d H:i:s') . " UTC\n";

IndustryServiceTest::run();
IndustriesManagementApiTest::run();

$exitCode = TestFramework::summarize();
exit($exitCode);
