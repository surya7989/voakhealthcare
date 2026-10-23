<?php

declare(strict_types=1);

/**
 * Voak Health Care — Phase 15 Case Studies & Resources Backend Test Suite
 * Executes Phase 15 unit and integration test suites:
 * - ArticleService Unit Tests (ADM-016, FR-ADM-017, TEST-ADM-017, TEST-PUB-016, TEST-PUB-017)
 * - CaseStudyService Unit Tests (ADM-015, FR-ADM-016, TEST-ADM-016, TEST-PUB-013)
 * - FaqService Unit Tests (Module 16, FR-PUB-018, TEST-PUB-018)
 * - CaseStudiesAndResourcesApi Integration Tests (ADM-015, ADM-016, FR-ADM-016, FR-ADM-017, FR-WF-005)
 */

$backendRoot = dirname(__DIR__) . '/backend';
ini_set('memory_limit', '512M');
require_once $backendRoot . '/vendor/autoload.php';

use Voak\Health\Tests\Integration\CaseStudiesAndResourcesApiTest;
use Voak\Health\Tests\TestFramework;
use Voak\Health\Tests\Unit\ArticleServiceTest;
use Voak\Health\Tests\Unit\CaseStudyServiceTest;
use Voak\Health\Tests\Unit\FaqServiceTest;

echo "======================================================================\n";
echo "VOAK HEALTH CARE — PHASE 15 CASE STUDIES & RESOURCES TEST SUITE\n";
echo "======================================================================\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Runtime:     " . PHP_OS . " (" . php_uname('s') . ")\n";
echo "Timestamp:   " . gmdate('Y-m-d H:i:s') . " UTC\n";

ArticleServiceTest::run();
CaseStudyServiceTest::run();
FaqServiceTest::run();
CaseStudiesAndResourcesApiTest::run();

$exitCode = TestFramework::summarize();
exit($exitCode);
