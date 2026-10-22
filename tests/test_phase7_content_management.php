<?php

declare(strict_types=1);

/**
 * Voak Health Care — Phase 7 Website Content Management Test Suite
 * Executes Phase 7 unit and integration test suites:
 * - HtmlSanitizer & Stored-XSS Defense
 * - PageService & Content Revision Engine
 * - HomepageService & CTA URL Security
 * - SettingService & Secret Isolation
 * - Public & Admin Content Management APIs
 */

$backendRoot = dirname(__DIR__) . '/backend';
ini_set('memory_limit', '512M');
require_once $backendRoot . '/vendor/autoload.php';

use Voak\Health\Tests\Integration\ContentManagementApiTest;
use Voak\Health\Tests\TestFramework;
use Voak\Health\Tests\Unit\HomepageServiceTest;
use Voak\Health\Tests\Unit\HtmlSanitizerTest;
use Voak\Health\Tests\Unit\PageServiceTest;
use Voak\Health\Tests\Unit\SettingServiceTest;

echo "======================================================================\n";
echo "VOAK HEALTH CARE — PHASE 7 WEBSITE CONTENT MANAGEMENT TEST SUITE\n";
echo "======================================================================\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Runtime:     " . PHP_OS . " (" . php_uname('s') . ")\n";
echo "Timestamp:   " . gmdate('Y-m-d H:i:s') . " UTC\n";

HtmlSanitizerTest::run();
PageServiceTest::run();
HomepageServiceTest::run();
SettingServiceTest::run();
ContentManagementApiTest::run();

$exitCode = TestFramework::summarize();
exit($exitCode);
