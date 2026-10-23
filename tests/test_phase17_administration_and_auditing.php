<?php

declare(strict_types=1);

/**
 * Voak Health Care — Phase 17 Administration, Auditing & API Verification Test Suite
 * Executes Phase 17 unit and integration test suites:
 * - AuditService Unit Tests (Module 19, ADM-019, FR-ADM-019, TEST-ADM-019)
 * - AdminAuditAndTelemetryApi Integration Tests (ADM-019, ADM-018, ADM-001, SYS-004)
 */

$backendRoot = dirname(__DIR__) . '/backend';
ini_set('memory_limit', '512M');
require_once $backendRoot . '/vendor/autoload.php';

use Voak\Health\Tests\Integration\AdminAuditAndTelemetryApiTest;
use Voak\Health\Tests\TestFramework;
use Voak\Health\Tests\Unit\AuditServiceTest;

echo "======================================================================\n";
echo "VOAK HEALTH CARE — PHASE 17 ADMINISTRATION, AUDITING & TELEMETRY TEST SUITE\n";
echo "======================================================================\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Runtime:     " . PHP_OS . " (" . php_uname('s') . ")\n";
echo "Timestamp:   " . gmdate('Y-m-d H:i:s') . " UTC\n";

AuditServiceTest::run();
AdminAuditAndTelemetryApiTest::run();

$exitCode = TestFramework::summarize();
exit($exitCode);
