<?php

declare(strict_types=1);

/**
 * Voak Health Care — Phase 16 SEO Management Backend Test Suite
 * Executes Phase 16 unit and integration test suites:
 * - SeoService Unit Tests (Module 17, ADM-017, ADM-018, FR-ADM-018, FR-WF-006, FR-SEO-001..004)
 * - SeoManagementApi Integration Tests (ADM-017, ADM-018, FR-ADM-018, FR-WF-006, FR-SEO-003)
 */

$backendRoot = dirname(__DIR__) . '/backend';
ini_set('memory_limit', '512M');
require_once $backendRoot . '/vendor/autoload.php';

use Voak\Health\Tests\Integration\SeoManagementApiTest;
use Voak\Health\Tests\TestFramework;
use Voak\Health\Tests\Unit\SeoServiceTest;

echo "======================================================================\n";
echo "VOAK HEALTH CARE — PHASE 16 SEO MANAGEMENT BACKEND TEST SUITE\n";
echo "======================================================================\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Runtime:     " . PHP_OS . " (" . php_uname('s') . ")\n";
echo "Timestamp:   " . gmdate('Y-m-d H:i:s') . " UTC\n";

SeoServiceTest::run();
SeoManagementApiTest::run();

$exitCode = TestFramework::summarize();
exit($exitCode);
