<?php

declare(strict_types=1);

/**
 * Voak Health Care — Phase 6 Admin Authentication, RBAC, Sessions, MFA & Audit Test Suite
 * Executes all Phase 6 security-focused unit and integration test suites.
 */

$backendRoot = dirname(__DIR__) . '/backend';
require_once $backendRoot . '/vendor/autoload.php';

use Voak\Health\Tests\TestFramework;
use Voak\Health\Tests\Unit\PasswordHasherTest;
use Voak\Health\Tests\Unit\CryptoTest;
use Voak\Health\Tests\Unit\TotpEngineTest;
use Voak\Health\Tests\Unit\AuditLoggerTest;
use Voak\Health\Tests\Unit\RbacManagerTest;
use Voak\Health\Tests\Unit\SessionManagerTest;
use Voak\Health\Tests\Unit\PasswordResetServiceTest;
use Voak\Health\Tests\Unit\TotpServiceTest;
use Voak\Health\Tests\Unit\AuthenticationServiceTest;
use Voak\Health\Tests\Unit\SecurityHardeningTest;
use Voak\Health\Tests\Integration\AdminAuthApiTest;
use Voak\Health\Tests\Integration\CliSuperAdminTest;

echo "======================================================================\n";
echo "VOAK HEALTH CARE — PHASE 6 ADMIN AUTHENTICATION & SECURITY TEST SUITE\n";
echo "======================================================================\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Runtime:     " . PHP_OS . " (" . php_uname('s') . ")\n";
echo "Timestamp:   " . gmdate('Y-m-d H:i:s') . " UTC\n";

PasswordHasherTest::run();
CryptoTest::run();
TotpEngineTest::run();
AuditLoggerTest::run();
RbacManagerTest::run();
SessionManagerTest::run();
PasswordResetServiceTest::run();
TotpServiceTest::run();
AuthenticationServiceTest::run();
AdminAuthApiTest::run();
CliSuperAdminTest::run();
SecurityHardeningTest::run();

$exitCode = TestFramework::summarize();
exit($exitCode);
