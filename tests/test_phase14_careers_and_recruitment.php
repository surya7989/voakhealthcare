<?php

declare(strict_types=1);

/**
 * Voak Health Care — Phase 14 Careers & Recruitment Backend Test Suite
 * Executes Phase 14 unit and integration test suites:
 * - CareerService Unit Tests (FR-ADM-014, FR-ADM-015, FR-WF-004, FR-SEC-004, TEST-ADM-014, TEST-ADM-015, TEST-WF-004)
 * - CareerAndRecruitmentApi Integration Tests (ADM-013, ADM-014, FR-ADM-014, FR-ADM-015, FR-WF-004)
 */

$backendRoot = dirname(__DIR__) . '/backend';
ini_set('memory_limit', '512M');
require_once $backendRoot . '/vendor/autoload.php';

use Voak\Health\Tests\Integration\CareerAndRecruitmentApiTest;
use Voak\Health\Tests\TestFramework;
use Voak\Health\Tests\Unit\CareerServiceTest;

echo "======================================================================\n";
echo "VOAK HEALTH CARE — PHASE 14 CAREERS & RECRUITMENT TEST SUITE\n";
echo "======================================================================\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Runtime:     " . PHP_OS . " (" . php_uname('s') . ")\n";
echo "Timestamp:   " . gmdate('Y-m-d H:i:s') . " UTC\n";

CareerServiceTest::run();
CareerAndRecruitmentApiTest::run();

$exitCode = TestFramework::summarize();
exit($exitCode);
