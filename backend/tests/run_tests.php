<?php

declare(strict_types=1);

/**
 * Voak Health Care — Backend Automated Test Suite Runner
 * Executes unit, integration, and security verification tests for Phase 4 & Phase 6.
 */

$backendRoot = dirname(__DIR__);
ini_set('memory_limit', '512M');
require_once $backendRoot . '/vendor/autoload.php';

use Voak\Health\Tests\TestFramework;
use Voak\Health\Tests\Unit\ConfigTest;
use Voak\Health\Tests\Unit\DatabaseConnectionTest;
use Voak\Health\Tests\Unit\ExceptionHandlerTest;
use Voak\Health\Tests\Unit\MiddlewareTest;
use Voak\Health\Tests\Unit\MigrationManagerTest;
use Voak\Health\Tests\Unit\RequestResponseTest;
use Voak\Health\Tests\Unit\RouterTest;
use Voak\Health\Tests\Integration\AppKernelTest;
use Voak\Health\Tests\Integration\HttpSmokeTest;
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
use Voak\Health\Tests\Unit\HtmlSanitizerTest;
use Voak\Health\Tests\Unit\PageServiceTest;
use Voak\Health\Tests\Unit\HomepageServiceTest;
use Voak\Health\Tests\Unit\SettingServiceTest;
use Voak\Health\Tests\Integration\ContentManagementApiTest;
use Voak\Health\Tests\Unit\ServiceServiceTest;
use Voak\Health\Tests\Integration\ServicesManagementApiTest;
use Voak\Health\Tests\Unit\IndustryServiceTest;
use Voak\Health\Tests\Integration\IndustriesManagementApiTest;
use Voak\Health\Tests\Unit\MediaStorageServiceTest;
use Voak\Health\Tests\Integration\MediaAndDocumentApiTest;
use Voak\Health\Tests\Unit\ContactServiceTest;
use Voak\Health\Tests\Integration\ContactInquiryApiTest;
use Voak\Health\Tests\Unit\PartnershipServiceTest;
use Voak\Health\Tests\Unit\ProposalServiceTest;
use Voak\Health\Tests\Integration\PartnershipAndProposalApiTest;

echo "======================================================================\n";
echo "VOAK HEALTH CARE — BACKEND AUTOMATED TEST SUITE (PHASES 4, 6, 7, 8, 9, 10, 11 & 12)\n";
echo "======================================================================\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Runtime:     " . PHP_OS . " (" . php_uname('s') . ")\n";
echo "Timestamp:   " . gmdate('Y-m-d H:i:s') . " UTC\n";

// Phase 4 Backend Foundation Tests
ConfigTest::run();
RequestResponseTest::run();
RouterTest::run();
MiddlewareTest::run();
ExceptionHandlerTest::run();
DatabaseConnectionTest::run();
MigrationManagerTest::run();
AppKernelTest::run();
HttpSmokeTest::run();

// Phase 6 Admin Auth, RBAC, Sessions, MFA & Audit Security Tests
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

// Phase 7 Website Content Management Tests
HtmlSanitizerTest::run();
PageServiceTest::run();
HomepageServiceTest::run();
SettingServiceTest::run();
ContentManagementApiTest::run();

// Phase 8 Services Catalog Management Tests
ServiceServiceTest::run();
ServicesManagementApiTest::run();

// Phase 9 Healthcare Industries & Sectors Management Tests
IndustryServiceTest::run();
IndustriesManagementApiTest::run();

// Phase 10 Media Library & Secure Document Quarantine Tests
MediaStorageServiceTest::run();
MediaAndDocumentApiTest::run();

// Phase 11 Contact Enquiry CRM & Lifecycle State Machine Tests
ContactServiceTest::run();
ContactInquiryApiTest::run();

// Phase 12 Proposals & Partnerships CRM Management Tests
PartnershipServiceTest::run();
ProposalServiceTest::run();
PartnershipAndProposalApiTest::run();

$exitCode = TestFramework::summarize();
exit($exitCode);
