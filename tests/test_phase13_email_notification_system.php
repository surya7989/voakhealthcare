<?php

declare(strict_types=1);

/**
 * Voak Health Care — Phase 13 Transactional Email & Outbox Telemetry Test Suite
 * Executes Phase 13 unit and integration test suites:
 * - EmailService Unit Tests (FR-ADM-013, ADR-006, TEST-ADM-013, TEST-WF-001, TEST-SEC-005)
 * - EmailTemplateAndQueueApi Integration Tests (ADM-012, FR-ADM-013, ADR-006, TEST-ADM-013)
 */

$backendRoot = dirname(__DIR__) . '/backend';
ini_set('memory_limit', '512M');
require_once $backendRoot . '/vendor/autoload.php';

use Voak\Health\Tests\Integration\EmailTemplateAndQueueApiTest;
use Voak\Health\Tests\TestFramework;
use Voak\Health\Tests\Unit\EmailServiceTest;

echo "======================================================================\n";
echo "VOAK HEALTH CARE — PHASE 13 EMAIL NOTIFICATION & OUTBOX TELEMETRY TEST SUITE\n";
echo "======================================================================\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Runtime:     " . PHP_OS . " (" . php_uname('s') . ")\n";
echo "Timestamp:   " . gmdate('Y-m-d H:i:s') . " UTC\n";

EmailServiceTest::run();
EmailTemplateAndQueueApiTest::run();

$exitCode = TestFramework::summarize();
exit($exitCode);
