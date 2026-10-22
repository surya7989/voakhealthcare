<?php

declare(strict_types=1);

/**
 * Voak Health Care — Phase 11 Contact Enquiry Management & Lifecycle State Machine Test Suite
 * Executes Phase 11 unit and integration test suites:
 * - ContactService Unit Tests (FR-ADM-010, FR-WF-001, SYS-005, ADR-008)
 * - Contact Inquiry & CRM API Integration Tests (TEST-ADM-010, TEST-WF-001)
 */

$backendRoot = dirname(__DIR__) . '/backend';
ini_set('memory_limit', '512M');
require_once $backendRoot . '/vendor/autoload.php';

use Voak\Health\Tests\Integration\ContactInquiryApiTest;
use Voak\Health\Tests\TestFramework;
use Voak\Health\Tests\Unit\ContactServiceTest;

echo "======================================================================\n";
echo "VOAK HEALTH CARE — PHASE 11 CONTACT ENQUIRIES MANAGEMENT TEST SUITE\n";
echo "======================================================================\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Runtime:     " . PHP_OS . " (" . php_uname('s') . ")\n";
echo "Timestamp:   " . gmdate('Y-m-d H:i:s') . " UTC\n";

ContactServiceTest::run();
ContactInquiryApiTest::run();

$exitCode = TestFramework::summarize();
exit($exitCode);
