<?php

declare(strict_types=1);

/**
 * Voak Health Care — Phase 12 Proposals & Partnerships Management Test Suite
 * Executes Phase 12 unit and integration test suites:
 * - PartnershipService Unit Tests (FR-ADM-011, FR-WF-002, FR-PUB-020, SYS-005, ADR-008)
 * - ProposalService Unit Tests (FR-ADM-012, FR-WF-003, FR-PUB-021, FR-SEC-003, FR-SEC-004)
 * - Partnership & Proposal API Integration Tests (TEST-ADM-011, TEST-ADM-012, TEST-WF-002, TEST-WF-003)
 */

$backendRoot = dirname(__DIR__) . '/backend';
ini_set('memory_limit', '512M');
require_once $backendRoot . '/vendor/autoload.php';

use Voak\Health\Tests\Integration\PartnershipAndProposalApiTest;
use Voak\Health\Tests\TestFramework;
use Voak\Health\Tests\Unit\PartnershipServiceTest;
use Voak\Health\Tests\Unit\ProposalServiceTest;

echo "======================================================================\n";
echo "VOAK HEALTH CARE — PHASE 12 PROPOSALS & PARTNERSHIPS MANAGEMENT TEST SUITE\n";
echo "======================================================================\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Runtime:     " . PHP_OS . " (" . php_uname('s') . ")\n";
echo "Timestamp:   " . gmdate('Y-m-d H:i:s') . " UTC\n";

PartnershipServiceTest::run();
ProposalServiceTest::run();
PartnershipAndProposalApiTest::run();

$exitCode = TestFramework::summarize();
exit($exitCode);
