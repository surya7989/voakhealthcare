<?php

declare(strict_types=1);

/**
 * Voak Health Care — Phase 4 Automated Test Suite Runner
 * Executes unit, integration, and security verification tests.
 */

$backendRoot = dirname(__DIR__);
require_once $backendRoot . '/vendor/autoload.php';

use Voak\Health\Tests\TestFramework;
use Voak\Health\Tests\Unit\ConfigTest;
use Voak\Health\Tests\Unit\DatabaseConnectionTest;
use Voak\Health\Tests\Unit\ExceptionHandlerTest;
use Voak\Health\Tests\Unit\MiddlewareTest;
use Voak\Health\Tests\Unit\MigrationManagerTest;
use Voak\Health\Tests\Unit\RequestResponseTest;
use Voak\Health\Tests\Unit\RouterTest;
use Voak\Health\Tests\Integration\AppKernelTest;
use Voak\Health\Tests\Integration\HttpSmokeTest;

echo "======================================================================\n";
echo "VOAK HEALTH CARE — PHASE 4 PHP BACKEND FOUNDATION TEST SUITE\n";
echo "======================================================================\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Runtime:     " . PHP_OS . " (" . php_uname('s') . ")\n";
echo "Timestamp:   " . gmdate('Y-m-d H:i:s') . " UTC\n";

// Execute all unit, integration, and HTTP smoke test suites
ConfigTest::run();
RequestResponseTest::run();
RouterTest::run();
MiddlewareTest::run();
ExceptionHandlerTest::run();
DatabaseConnectionTest::run();
MigrationManagerTest::run();
AppKernelTest::run();
HttpSmokeTest::run();

$exitCode = TestFramework::summarize();
exit($exitCode);
