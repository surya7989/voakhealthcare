<?php

declare(strict_types=1);

/**
 * Voak Health Care — Phase 10 Media Library & Secure Document Quarantine Test Suite
 * Executes Phase 10 unit and integration test suites:
 * - MediaStorageService & Secure Document Quarantine Unit Tests (FR-ADM-009, FR-SEC-003, FR-SEC-004)
 * - Media Library & Document Quarantine API Tests (TEST-ADM-009, TEST-SEC-003, TEST-SEC-004)
 */

$backendRoot = dirname(__DIR__) . '/backend';
ini_set('memory_limit', '512M');
require_once $backendRoot . '/vendor/autoload.php';

use Voak\Health\Tests\Integration\MediaAndDocumentApiTest;
use Voak\Health\Tests\TestFramework;
use Voak\Health\Tests\Unit\MediaStorageServiceTest;

echo "======================================================================\n";
echo "VOAK HEALTH CARE — PHASE 10 MEDIA & DOCUMENT MANAGEMENT TEST SUITE\n";
echo "======================================================================\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Runtime:     " . PHP_OS . " (" . php_uname('s') . ")\n";
echo "Timestamp:   " . gmdate('Y-m-d H:i:s') . " UTC\n";

MediaStorageServiceTest::run();
MediaAndDocumentApiTest::run();

$exitCode = TestFramework::summarize();
exit($exitCode);
