<?php

declare(strict_types=1);

namespace Voak\Health\Core;

use Voak\Health\Auth\AuthenticationService;
use Voak\Health\Auth\PasswordResetService;
use Voak\Health\Auth\RbacManager;
use Voak\Health\Auth\SessionManager;
use Voak\Health\Auth\TotpService;
use Voak\Health\Core\Exceptions\GlobalExceptionHandler;
use Voak\Health\Core\Http\Request;
use Voak\Health\Core\Http\Response;
use Voak\Health\Core\Router\Router;
use Voak\Health\Database\Connection;
use Voak\Health\Middleware\AdminAuthenticateMiddleware;
use Voak\Health\Middleware\CorsMiddleware;
use Voak\Health\Middleware\CsrfProtectionMiddleware;
use Voak\Health\Middleware\JsonBodyParserMiddleware;
use Voak\Health\Middleware\MiddlewarePipeline;
use Voak\Health\Middleware\RateLimitMiddleware;
use Voak\Health\Middleware\RequirePermissionMiddleware;
use Voak\Health\Middleware\SecurityHeadersMiddleware;
use Voak\Health\Modules\Admin\AdminApplicationController;
use Voak\Health\Modules\Admin\AdminAuthController;
use Voak\Health\Modules\Admin\AdminContactController;
use Voak\Health\Modules\Admin\AdminDocumentStreamController;
use Voak\Health\Modules\Admin\AdminEmailQueueController;
use Voak\Health\Modules\Admin\AdminEmailTemplateController;
use Voak\Health\Modules\Admin\AdminHomepageController;
use Voak\Health\Modules\Admin\AdminIndustryController;
use Voak\Health\Modules\Admin\AdminJobController;
use Voak\Health\Modules\Admin\AdminMediaController;
use Voak\Health\Modules\Admin\AdminPageController;
use Voak\Health\Modules\Admin\AdminPartnershipController;
use Voak\Health\Modules\Admin\AdminProposalController;
use Voak\Health\Modules\Admin\AdminServiceController;
use Voak\Health\Modules\Admin\AdminSettingController;
use Voak\Health\Modules\Admin\AdminUsersController;
use Voak\Health\Modules\Admin\MfaController;
use Voak\Health\Modules\Health\HealthController;
use Voak\Health\Modules\PublicContent\PublicContentController;
use Voak\Health\Modules\Scaffolding\PartnersClientsScaffolding;
use Voak\Health\Security\AuditLogger;
use Voak\Health\Security\HtmlSanitizer;
use Voak\Health\Security\PasswordHasher;
use Voak\Health\Security\TotpEngine;
use Voak\Health\Services\CareerService;
use Voak\Health\Services\ContactService;
use Voak\Health\Services\EmailService;
use Voak\Health\Services\HomepageService;
use Voak\Health\Services\IndustryService;
use Voak\Health\Services\Mail\ArrayTransport;
use Voak\Health\Services\Mail\SocketSmtpTransport;
use Voak\Health\Services\MediaStorageService;
use Voak\Health\Services\PageService;
use Voak\Health\Services\PartnershipService;
use Voak\Health\Services\ProposalService;
use Voak\Health\Services\ServiceService;
use Voak\Health\Services\SettingService;

/**
 * Voak Health Care Application Kernel.
 * Establishes one controlled initialization path for the PHP backend foundation and Phase 6 Admin Auth.
 */
class App
{
    private Container $container;
    private Config $config;
    private Router $router;
    private MiddlewarePipeline $pipeline;
    private GlobalExceptionHandler $exceptionHandler;

    public function __construct(Config $config, ?Container $container = null)
    {
        $this->config = $config;
        $this->container = $container ?? new Container();
        $this->router = new Router();
        $this->pipeline = new MiddlewarePipeline();

        $storagePath = (string)$this->config->get('STORAGE_PATH', 'storage');
        $logPath = rtrim($storagePath, '/\\') . '/logs/error.log';
        $this->exceptionHandler = new GlobalExceptionHandler($this->config, $logPath);

        $this->initializeServices();
        $this->initializeMiddleware();
        $this->registerCoreRoutes();
    }

    /**
     * Factory to bootstrap the application kernel.
     */
    public static function bootstrap(?string $envPath = null): self
    {
        $config = Config::load($envPath);
        return new self($config);
    }

    /**
     * Alias for bootstrap() providing interface compatibility with architectural specifications.
     */
    public static function boot(?string $envPath = null): self
    {
        return self::bootstrap($envPath);
    }

    public function getContainer(): Container
    {
        return $this->container;
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    public function getRouter(): Router
    {
        return $this->router;
    }

    public function getMiddlewarePipeline(): MiddlewarePipeline
    {
        return $this->pipeline;
    }

    public function getExceptionHandler(): GlobalExceptionHandler
    {
        return $this->exceptionHandler;
    }

    /**
     * Handles an incoming HTTP request and returns a Response.
     */
    public function handle(Request $request): Response
    {
        try {
            return $this->pipeline->handle($request, function (Request $req): Response {
                return $this->router->dispatch($req, $this->container);
            });
        } catch (\Throwable $e) {
            return $this->exceptionHandler->handle($e, $request);
        }
    }

    /**
     * Runs the application using globals, handles the request, and emits the response.
     */
    public function run(?Request $request = null): Response
    {
        $req = $request ?? Request::fromGlobals();
        $response = $this->handle($req);

        // Only send if not running under a unit test environment
        if ($request === null && PHP_SAPI !== 'cli') {
            $response->send();
        }

        return $response;
    }

    private function initializeServices(): void
    {
        $this->container->set(Config::class, $this->config);
        $this->container->set(Container::class, $this->container);
        $this->container->set(Router::class, $this->router);

        // Lazy Database Connection service
        $this->container->singleton(Connection::class, function () {
            return Connection::fromConfig($this->config);
        });

        // Security & Cryptography services
        $hasher = new PasswordHasher();
        $totpEngine = new TotpEngine();
        $auditLogger = new AuditLogger();
        $sessionManager = new SessionManager();
        $totpService = new TotpService($this->config, $totpEngine);
        $rbacManager = new RbacManager();
        $resetService = new PasswordResetService($hasher, $sessionManager, $auditLogger);
        $authService = new AuthenticationService($hasher, $sessionManager, $totpService, $rbacManager, $auditLogger, $this->config);

        $this->container->set(PasswordHasher::class, $hasher);
        $this->container->set(TotpEngine::class, $totpEngine);
        $this->container->set(AuditLogger::class, $auditLogger);
        $this->container->set(SessionManager::class, $sessionManager);
        $this->container->set(TotpService::class, $totpService);
        $this->container->set(RbacManager::class, $rbacManager);
        $this->container->set(PasswordResetService::class, $resetService);
        $this->container->set(AuthenticationService::class, $authService);

        /** @var Connection $connection */
        $connection = $this->container->get(Connection::class);

        // Controller bindings
        $this->container->set(HealthController::class, new HealthController($this->config));
        $this->container->set(PartnersClientsScaffolding::class, new PartnersClientsScaffolding());
        $this->container->set(AdminAuthController::class, new AdminAuthController(
            $connection,
            $authService,
            $sessionManager,
            $resetService,
            $rbacManager,
            $this->config
        ));
        $this->container->set(MfaController::class, new MfaController(
            $connection,
            $authService,
            $totpService,
            $sessionManager,
            $this->config
        ));
        $this->container->set(AdminUsersController::class, new AdminUsersController($connection));

        // Phase 7, 8, 9, 10, 11 & 12 Services
        $sanitizer = new HtmlSanitizer();
        $pageService = new PageService($connection, $auditLogger, $sanitizer);
        $homepageService = new HomepageService($connection, $auditLogger, $sanitizer);
        $settingService = new SettingService($connection, $auditLogger);
        $serviceService = new ServiceService($connection, $auditLogger, $sanitizer);
        $industryService = new IndustryService($connection, $auditLogger, $sanitizer);
        $mediaService = $this->container->has(MediaStorageService::class)
            ? $this->container->get(MediaStorageService::class)
            : new MediaStorageService($connection, $auditLogger);
        $contactService = new ContactService($connection, $auditLogger, $sanitizer, null, $rbacManager);
        $partnershipService = new PartnershipService($connection, $auditLogger, $sanitizer, null, $rbacManager);
        $proposalService = new ProposalService($connection, $mediaService, $auditLogger, $sanitizer, null, $rbacManager);

        $this->container->set(HtmlSanitizer::class, $sanitizer);
        $this->container->set(PageService::class, $pageService);
        $this->container->set(HomepageService::class, $homepageService);
        $this->container->set(SettingService::class, $settingService);
        $this->container->set(ServiceService::class, $serviceService);
        $this->container->set(IndustryService::class, $industryService);
        $this->container->set(MediaStorageService::class, $mediaService);
        $this->container->set(ContactService::class, $contactService);
        $this->container->set(PartnershipService::class, $partnershipService);
        $this->container->set(ProposalService::class, $proposalService);

        // Phase 7, 8, 9, 10, 11 & 12 Controllers
        $this->container->set(AdminPageController::class, new AdminPageController($pageService));
        $this->container->set(AdminHomepageController::class, new AdminHomepageController($homepageService));
        $this->container->set(AdminSettingController::class, new AdminSettingController($settingService));
        $this->container->set(AdminServiceController::class, new AdminServiceController($serviceService));
        $this->container->set(AdminIndustryController::class, new AdminIndustryController($industryService));
        $this->container->set(AdminMediaController::class, new AdminMediaController($mediaService));
        $this->container->set(AdminDocumentStreamController::class, new AdminDocumentStreamController($connection, $mediaService, $this->container));
        $this->container->set(AdminContactController::class, new AdminContactController($contactService));
        $this->container->set(AdminPartnershipController::class, new AdminPartnershipController($partnershipService));
        $this->container->set(AdminProposalController::class, new AdminProposalController($proposalService));

        // Phase 13 Email Notification Service & Controllers
        $mailTransportType = (string)$this->config->get('MAIL_TRANSPORT', 'array');
        if ($mailTransportType === 'smtp') {
            $transport = new SocketSmtpTransport(
                (string)$this->config->get('SMTP_HOST', '127.0.0.1'),
                (int)$this->config->get('SMTP_PORT', 465),
                (string)$this->config->get('SMTP_USER', ''),
                (string)$this->config->get('SMTP_PASS', ''),
                (string)$this->config->get('SMTP_ENCRYPTION', 'ssl'),
                (string)$this->config->get('MAIL_FROM_ADDRESS', 'noreply@voak.com.au'),
                (string)$this->config->get('MAIL_FROM_NAME', 'Voak Health Care')
            );
        } else {
            $transport = new ArrayTransport();
        }

        $emailService = new EmailService(
            $connection,
            $auditLogger,
            $sanitizer,
            $transport
        );
        $this->container->set(EmailService::class, $emailService);
        $this->container->set(AdminEmailTemplateController::class, new AdminEmailTemplateController($emailService));
        $this->container->set(AdminEmailQueueController::class, new AdminEmailQueueController($emailService));

        // Phase 14 Careers & Recruitment Service & Controllers
        $careerService = new CareerService(
            $connection,
            $mediaService,
            $auditLogger,
            $sanitizer,
            $emailService,
            null,
            $rbacManager
        );
        $this->container->set(CareerService::class, $careerService);
        $this->container->set(AdminJobController::class, new AdminJobController($careerService));
        $this->container->set(AdminApplicationController::class, new AdminApplicationController($careerService));

        $this->container->set(PublicContentController::class, new PublicContentController(
            $pageService,
            $homepageService,
            $settingService,
            $serviceService,
            $industryService,
            $mediaService,
            $contactService,
            $partnershipService,
            $proposalService,
            $careerService
        ));
    }

    private function initializeMiddleware(): void
    {
        // 1. Security Response Headers
        $this->pipeline->pipe(new SecurityHeadersMiddleware());

        // 2. Strict CORS Handling
        $allowedOrigins = (string)$this->config->get('CORS_ALLOWED_ORIGINS', '');
        $this->pipeline->pipe(new CorsMiddleware($allowedOrigins));

        // 3. Rate Limiting for Auth Endpoints
        $this->pipeline->pipe(new RateLimitMiddleware(10, 60));

        // 4. JSON Request Body Parser & Validator
        $this->pipeline->pipe(new JsonBodyParserMiddleware());

        /** @var Connection $connection */
        $connection = $this->container->get(Connection::class);
        /** @var SessionManager $sessionManager */
        $sessionManager = $this->container->get(SessionManager::class);

        // 5. Admin Authentication Middleware
        $adminAuthMiddleware = new AdminAuthenticateMiddleware($sessionManager, $connection);
        $this->pipeline->pipe($adminAuthMiddleware);

        // 6. Admin CSRF Protection Middleware
        $csrfMiddleware = new CsrfProtectionMiddleware($connection, $this->config);
        $this->pipeline->pipe($csrfMiddleware);
    }

    private function registerCoreRoutes(): void
    {
        // Canonical Phase 4 Roadmap Acceptance Gate
        $this->router->get('/api/health', [HealthController::class, 'legacyHealth']);

        // Canonical Module 01 Endpoint
        $this->router->get('/api/v1/health', [HealthController::class, 'healthV1']);

        // OPEN-03 Decoupled Scaffolding Preview Endpoint
        $this->router->get('/api/v1/public/partners-preview', [PartnersClientsScaffolding::class, 'getPartnersPreview']);

        // Phase 6 Admin Authentication & Security Endpoints
        $this->router->post('/api/v1/admin/auth/login', [AdminAuthController::class, 'login']);
        $this->router->post('/api/v1/admin/auth/logout', [AdminAuthController::class, 'logout']);
        $this->router->get('/api/v1/admin/auth/me', [AdminAuthController::class, 'me']);
        $this->router->get('/api/v1/admin/auth/csrf', [AdminAuthController::class, 'csrf']);
        $this->router->post('/api/v1/admin/auth/password-reset/request', [AdminAuthController::class, 'requestPasswordReset']);
        $this->router->post('/api/v1/admin/auth/password-reset/confirm', [AdminAuthController::class, 'confirmPasswordReset']);
        $this->router->post('/api/v1/admin/auth/mfa/verify', [MfaController::class, 'verifyMfa']);
        $this->router->post('/api/v1/admin/auth/mfa/recovery', [MfaController::class, 'verifyRecovery']);
        $this->router->post('/api/v1/admin/auth/mfa/setup', [MfaController::class, 'setupMfa']);
        $this->router->post('/api/v1/admin/auth/mfa/confirm', [MfaController::class, 'confirmMfa']);

        // Phase 6 Privileged Admin Endpoints (Require Specific RBAC Permissions)
        /** @var RbacManager $rbacManager */
        $rbacManager = $this->container->get(RbacManager::class);
        /** @var Connection $connection */
        $connection = $this->container->get(Connection::class);
        $this->router->get(
            '/api/v1/admin/users',
            [AdminUsersController::class, 'listUsers'],
            [RequirePermissionMiddleware::with('user:list', $rbacManager, $connection)]
        );

        // Phase 7, 8 & 9 Public Content Delivery Routes (Rate-limited, ETag caching)
        $this->router->get('/api/v1/public/pages/:slug', [PublicContentController::class, 'getPage']);
        $this->router->get('/api/v1/public/homepage', [PublicContentController::class, 'getHomepage']);
        $this->router->get('/api/v1/public/settings', [PublicContentController::class, 'getSettings']);
        $this->router->get('/api/v1/public/services', [PublicContentController::class, 'getServices']);
        $this->router->get('/api/v1/public/services/:slug', [PublicContentController::class, 'getService']);
        $this->router->get('/api/services', [PublicContentController::class, 'getServices']);
        $this->router->get('/api/services/:slug', [PublicContentController::class, 'getService']);
        $this->router->get('/api/v1/public/industries', [PublicContentController::class, 'getIndustries']);
        $this->router->get('/api/v1/public/industries/:slug', [PublicContentController::class, 'getIndustry']);
        $this->router->get('/api/industries', [PublicContentController::class, 'getIndustries']);
        $this->router->get('/api/industries/:slug', [PublicContentController::class, 'getIndustry']);
        $this->router->get('/api/v1/public/media/:id', [PublicContentController::class, 'getMedia']);
        $this->router->get('/api/media/:id', [PublicContentController::class, 'getMedia']);

        // Phase 11 Public Healthcare Contact Submission Routes (Rate-limited, Honeypot filtered)
        $this->router->post('/api/v1/public/contact', [PublicContentController::class, 'submitContact']);
        $this->router->post('/api/enquiries/contact', [PublicContentController::class, 'submitContact']);

        // Phase 12 Public Inbound Routes (Partnerships & Proposals)
        $this->router->post('/api/v1/public/partnerships/inquire', [PublicContentController::class, 'submitPartnership']);
        $this->router->post('/api/enquiries/partner', [PublicContentController::class, 'submitPartnership']);
        $this->router->post('/api/v1/public/proposals/submit', [PublicContentController::class, 'submitProposal']);
        $this->router->post('/api/enquiries/proposal', [PublicContentController::class, 'submitProposal']);

        // Phase 14 Public Careers & Application Intake Routes
        $this->router->get('/api/v1/public/jobs', [PublicContentController::class, 'getJobs']);
        $this->router->get('/api/v1/public/careers', [PublicContentController::class, 'getJobs']);
        $this->router->get('/api/careers', [PublicContentController::class, 'getJobs']);
        $this->router->get('/api/v1/public/jobs/:slug', [PublicContentController::class, 'getJob']);
        $this->router->get('/api/v1/public/careers/:slug', [PublicContentController::class, 'getJob']);
        $this->router->get('/api/careers/:slug', [PublicContentController::class, 'getJob']);
        $this->router->post('/api/v1/public/careers/apply', [PublicContentController::class, 'submitApplication']);
        $this->router->post('/api/v1/public/careers/:slug/apply', [PublicContentController::class, 'submitApplication']);
        $this->router->post('/api/careers/:slug/apply', [PublicContentController::class, 'submitApplication']);
        $this->router->post('/api/v1/public/jobs/:slug/apply', [PublicContentController::class, 'submitApplication']);

        // Phase 7 Administrative CMS Routes (Authenticated, CSRF-protected, RBAC-enforced)
        // 1. Pages CRUD, Workflow & Revisions
        $this->router->get(
            '/api/v1/admin/pages',
            [AdminPageController::class, 'index'],
            [RequirePermissionMiddleware::with('pages.view', $rbacManager, $connection)]
        );
        $this->router->post(
            '/api/v1/admin/pages',
            [AdminPageController::class, 'create'],
            [RequirePermissionMiddleware::with('pages.create', $rbacManager, $connection)]
        );
        $this->router->get(
            '/api/v1/admin/pages/:id',
            [AdminPageController::class, 'show'],
            [RequirePermissionMiddleware::with('pages.view', $rbacManager, $connection)]
        );
        $this->router->put(
            '/api/v1/admin/pages/:id',
            [AdminPageController::class, 'update'],
            [RequirePermissionMiddleware::with('pages.edit', $rbacManager, $connection)]
        );
        $this->router->patch(
            '/api/v1/admin/pages/:id/status',
            [AdminPageController::class, 'updateStatus'],
            [RequirePermissionMiddleware::with('pages.publish', $rbacManager, $connection)]
        );
        $this->router->post(
            '/api/v1/admin/pages/:id/rollback/:rev',
            [AdminPageController::class, 'rollback'],
            [RequirePermissionMiddleware::with('pages.publish', $rbacManager, $connection)]
        );
        $this->router->delete(
            '/api/v1/admin/pages/:id',
            [AdminPageController::class, 'delete'],
            [RequirePermissionMiddleware::with('pages.delete', $rbacManager, $connection)]
        );
        $this->router->get(
            '/api/v1/admin/pages/:id/revisions',
            [AdminPageController::class, 'revisions'],
            [RequirePermissionMiddleware::with('pages.view', $rbacManager, $connection)]
        );

        // 2. Homepage CMS Configuration
        $this->router->get(
            '/api/v1/admin/homepage',
            [AdminHomepageController::class, 'show'],
            [RequirePermissionMiddleware::with('pages.view', $rbacManager, $connection)]
        );
        $this->router->put(
            '/api/v1/admin/homepage',
            [AdminHomepageController::class, 'update'],
            [RequirePermissionMiddleware::with('pages.edit', $rbacManager, $connection)]
        );

        // 3. System and Platform Settings CMS
        $this->router->get(
            '/api/v1/admin/settings',
            [AdminSettingController::class, 'index'],
            [RequirePermissionMiddleware::with('settings.view', $rbacManager, $connection)]
        );
        $this->router->put(
            '/api/v1/admin/settings',
            [AdminSettingController::class, 'update'],
            [RequirePermissionMiddleware::with('settings.edit', $rbacManager, $connection)]
        );

        // 4. Services Catalog Management Console (FR-ADM-007)
        $this->router->get(
            '/api/v1/admin/services',
            [AdminServiceController::class, 'index'],
            [RequirePermissionMiddleware::with('service:update', $rbacManager, $connection)]
        );
        $this->router->post(
            '/api/v1/admin/services',
            [AdminServiceController::class, 'create'],
            [RequirePermissionMiddleware::with('service:create', $rbacManager, $connection)]
        );
        $this->router->get(
            '/api/v1/admin/services/:id',
            [AdminServiceController::class, 'show'],
            [RequirePermissionMiddleware::with('service:update', $rbacManager, $connection)]
        );
        $this->router->put(
            '/api/v1/admin/services/:id',
            [AdminServiceController::class, 'update'],
            [RequirePermissionMiddleware::with('service:update', $rbacManager, $connection)]
        );
        $this->router->patch(
            '/api/v1/admin/services/:id/status',
            [AdminServiceController::class, 'updateStatus'],
            [RequirePermissionMiddleware::with('service:publish', $rbacManager, $connection)]
        );
        $this->router->patch(
            '/api/v1/admin/services/reorder',
            [AdminServiceController::class, 'reorder'],
            [RequirePermissionMiddleware::with('service:update', $rbacManager, $connection)]
        );
        $this->router->delete(
            '/api/v1/admin/services/:id',
            [AdminServiceController::class, 'delete'],
            [RequirePermissionMiddleware::with('service:delete', $rbacManager, $connection)]
        );

        // 5. Healthcare Industries & Verticals Management Console (ADM-007 / FR-ADM-008)
        $this->router->get(
            '/api/v1/admin/industries',
            [AdminIndustryController::class, 'index'],
            [RequirePermissionMiddleware::with('industry:update', $rbacManager, $connection)]
        );
        $this->router->post(
            '/api/v1/admin/industries',
            [AdminIndustryController::class, 'create'],
            [RequirePermissionMiddleware::with('industry:create', $rbacManager, $connection)]
        );
        $this->router->get(
            '/api/v1/admin/industries/:id',
            [AdminIndustryController::class, 'show'],
            [RequirePermissionMiddleware::with('industry:update', $rbacManager, $connection)]
        );
        $this->router->put(
            '/api/v1/admin/industries/:id',
            [AdminIndustryController::class, 'update'],
            [RequirePermissionMiddleware::with('industry:update', $rbacManager, $connection)]
        );
        $this->router->patch(
            '/api/v1/admin/industries/:id/status',
            [AdminIndustryController::class, 'updateStatus'],
            [RequirePermissionMiddleware::with('industry:delete', $rbacManager, $connection)]
        );
        $this->router->patch(
            '/api/v1/admin/industries/reorder',
            [AdminIndustryController::class, 'reorder'],
            [RequirePermissionMiddleware::with('industry:update', $rbacManager, $connection)]
        );
        $this->router->delete(
            '/api/v1/admin/industries/:id',
            [AdminIndustryController::class, 'delete'],
            [RequirePermissionMiddleware::with('industry:delete', $rbacManager, $connection)]
        );

        // 6. Media Library Console (ADM-009 / FR-ADM-009)
        $this->router->get(
            '/api/v1/admin/media',
            [AdminMediaController::class, 'index'],
            [RequirePermissionMiddleware::with('media.view', $rbacManager, $connection)]
        );
        $this->router->get(
            '/api/v1/admin/media/:id',
            [AdminMediaController::class, 'show'],
            [RequirePermissionMiddleware::with('media.view', $rbacManager, $connection)]
        );
        $this->router->post(
            '/api/v1/admin/media/upload',
            [AdminMediaController::class, 'upload'],
            [RequirePermissionMiddleware::with('media.upload', $rbacManager, $connection)]
        );
        $this->router->post(
            '/api/admin/media/upload',
            [AdminMediaController::class, 'upload'],
            [RequirePermissionMiddleware::with('media.upload', $rbacManager, $connection)]
        );
        $this->router->patch(
            '/api/v1/admin/media/:id',
            [AdminMediaController::class, 'update'],
            [RequirePermissionMiddleware::with('media.edit', $rbacManager, $connection)]
        );
        $this->router->put(
            '/api/v1/admin/media/:id',
            [AdminMediaController::class, 'update'],
            [RequirePermissionMiddleware::with('media.edit', $rbacManager, $connection)]
        );
        $this->router->delete(
            '/api/v1/admin/media/:id',
            [AdminMediaController::class, 'delete'],
            [RequirePermissionMiddleware::with('media.delete', $rbacManager, $connection)]
        );

        // 7. Authenticated Document Streaming Proxy (FR-SEC-004)
        $this->router->get(
            '/api/v1/admin/documents/:id/stream',
            [AdminDocumentStreamController::class, 'streamDocument'],
            [RequirePermissionMiddleware::with('proposals.manage', $rbacManager, $connection)]
        );
        $this->router->get(
            '/api/admin/documents/:id/stream',
            [AdminDocumentStreamController::class, 'streamDocument'],
            [RequirePermissionMiddleware::with('proposals.manage', $rbacManager, $connection)]
        );
        $this->router->get(
            '/api/v1/admin/proposals/:id/document',
            [AdminDocumentStreamController::class, 'streamProposal'],
            [RequirePermissionMiddleware::with('proposals.manage', $rbacManager, $connection)]
        );
        $this->router->get(
            '/api/admin/enquiries/proposals/:id/document',
            [AdminDocumentStreamController::class, 'streamProposal'],
            [RequirePermissionMiddleware::with('proposals.manage', $rbacManager, $connection)]
        );
        $this->router->get(
            '/api/v1/admin/careers/applications/:id/resume',
            [AdminDocumentStreamController::class, 'streamResume'],
            [RequirePermissionMiddleware::with('applications.view', $rbacManager, $connection)]
        );
        $this->router->get(
            '/api/admin/careers/applications/:id/resume',
            [AdminDocumentStreamController::class, 'streamResume'],
            [RequirePermissionMiddleware::with('applications.view', $rbacManager, $connection)]
        );

        // 8. Contact Enquiries CRM Management (ADM-010 / FR-ADM-010)
        $this->router->get(
            '/api/v1/admin/inquiries/contact',
            [AdminContactController::class, 'index'],
            [RequirePermissionMiddleware::with('contact:list', $rbacManager, $connection)]
        );
        $this->router->get(
            '/api/admin/enquiries/contact',
            [AdminContactController::class, 'index'],
            [RequirePermissionMiddleware::with('contact:list', $rbacManager, $connection)]
        );
        $this->router->get(
            '/api/v1/admin/inquiries/contact/export',
            [AdminContactController::class, 'export'],
            [RequirePermissionMiddleware::with('contact:export_csv', $rbacManager, $connection)]
        );
        $this->router->get(
            '/api/admin/enquiries/contact/export',
            [AdminContactController::class, 'export'],
            [RequirePermissionMiddleware::with('contact:export_csv', $rbacManager, $connection)]
        );
        $this->router->get(
            '/api/v1/admin/inquiries/contact/:id',
            [AdminContactController::class, 'show'],
            [RequirePermissionMiddleware::with('contact:list', $rbacManager, $connection)]
        );
        $this->router->get(
            '/api/admin/enquiries/contact/:id',
            [AdminContactController::class, 'show'],
            [RequirePermissionMiddleware::with('contact:list', $rbacManager, $connection)]
        );
        $this->router->patch(
            '/api/v1/admin/inquiries/contact/:id/status',
            [AdminContactController::class, 'updateStatus'],
            [RequirePermissionMiddleware::with('contact:update_status', $rbacManager, $connection)]
        );
        $this->router->patch(
            '/api/admin/enquiries/contact/:id',
            [AdminContactController::class, 'updateStatus'],
            [RequirePermissionMiddleware::with('contact:update_status', $rbacManager, $connection)]
        );
        $this->router->post(
            '/api/v1/admin/inquiries/contact/:id/notes',
            [AdminContactController::class, 'addNote'],
            [RequirePermissionMiddleware::with('contact:add_note', $rbacManager, $connection)]
        );
        $this->router->post(
            '/api/v1/admin/inquiries/contact/:id/assign',
            [AdminContactController::class, 'assign'],
            [RequirePermissionMiddleware::with('contact:update_status', $rbacManager, $connection)]
        );
        $this->router->delete(
            '/api/v1/admin/inquiries/contact/:id',
            [AdminContactController::class, 'delete'],
            [RequirePermissionMiddleware::with('contact:delete', $rbacManager, $connection)]
        );

        // 9. Institutional Partnership Pipeline Management (ADM-011 / FR-ADM-011 / FR-WF-002)
        $this->router->get(
            '/api/v1/admin/partnerships',
            [AdminPartnershipController::class, 'index'],
            [RequirePermissionMiddleware::with('partner:list', $rbacManager, $connection)]
        );
        $this->router->get(
            '/api/admin/enquiries/partnerships',
            [AdminPartnershipController::class, 'index'],
            [RequirePermissionMiddleware::with('partner:list', $rbacManager, $connection)]
        );
        $this->router->get(
            '/api/v1/admin/partnerships/export',
            [AdminPartnershipController::class, 'export'],
            [RequirePermissionMiddleware::with('partner:export_csv', $rbacManager, $connection)]
        );
        $this->router->get(
            '/api/admin/enquiries/partnerships/export',
            [AdminPartnershipController::class, 'export'],
            [RequirePermissionMiddleware::with('partner:export_csv', $rbacManager, $connection)]
        );
        $this->router->get(
            '/api/v1/admin/partnerships/:id',
            [AdminPartnershipController::class, 'show'],
            [RequirePermissionMiddleware::with('partner:list', $rbacManager, $connection)]
        );
        $this->router->get(
            '/api/admin/enquiries/partnerships/:id',
            [AdminPartnershipController::class, 'show'],
            [RequirePermissionMiddleware::with('partner:list', $rbacManager, $connection)]
        );
        $this->router->patch(
            '/api/v1/admin/partnerships/:id/status',
            [AdminPartnershipController::class, 'updateStage'],
            [RequirePermissionMiddleware::with('partner:update_stage', $rbacManager, $connection)]
        );
        $this->router->patch(
            '/api/admin/enquiries/partnerships/:id',
            [AdminPartnershipController::class, 'updateStage'],
            [RequirePermissionMiddleware::with('partner:update_stage', $rbacManager, $connection)]
        );
        $this->router->post(
            '/api/v1/admin/partnerships/:id/notes',
            [AdminPartnershipController::class, 'addNote'],
            [RequirePermissionMiddleware::with('partner:add_note', $rbacManager, $connection)]
        );
        $this->router->post(
            '/api/v1/admin/partnerships/:id/assign',
            [AdminPartnershipController::class, 'assign'],
            [RequirePermissionMiddleware::with('partner:update_stage', $rbacManager, $connection)]
        );
        $this->router->patch(
            '/api/v1/admin/partnerships/:id/assign',
            [AdminPartnershipController::class, 'assign'],
            [RequirePermissionMiddleware::with('partner:update_stage', $rbacManager, $connection)]
        );
        $this->router->delete(
            '/api/v1/admin/partnerships/:id',
            [AdminPartnershipController::class, 'delete'],
            [RequirePermissionMiddleware::with('partner:delete', $rbacManager, $connection)]
        );

        // 10. Enterprise Proposal & RFP Desk Management (ADM-012 / FR-ADM-012 / FR-WF-003)
        $this->router->get(
            '/api/v1/admin/proposals',
            [AdminProposalController::class, 'index'],
            [RequirePermissionMiddleware::with('proposal:list', $rbacManager, $connection)]
        );
        $this->router->get(
            '/api/admin/enquiries/proposals',
            [AdminProposalController::class, 'index'],
            [RequirePermissionMiddleware::with('proposal:list', $rbacManager, $connection)]
        );
        $this->router->get(
            '/api/v1/admin/proposals/export',
            [AdminProposalController::class, 'export'],
            [RequirePermissionMiddleware::with('proposal:export_csv', $rbacManager, $connection)]
        );
        $this->router->get(
            '/api/admin/enquiries/proposals/export',
            [AdminProposalController::class, 'export'],
            [RequirePermissionMiddleware::with('proposal:export_csv', $rbacManager, $connection)]
        );
        $this->router->get(
            '/api/v1/admin/proposals/:id',
            [AdminProposalController::class, 'show'],
            [RequirePermissionMiddleware::with('proposal:list', $rbacManager, $connection)]
        );
        $this->router->get(
            '/api/admin/enquiries/proposals/:id',
            [AdminProposalController::class, 'show'],
            [RequirePermissionMiddleware::with('proposal:list', $rbacManager, $connection)]
        );
        $this->router->patch(
            '/api/v1/admin/proposals/:id/status',
            [AdminProposalController::class, 'updateStatus'],
            [RequirePermissionMiddleware::with('proposal:update_status', $rbacManager, $connection)]
        );
        $this->router->patch(
            '/api/admin/enquiries/proposals/:id',
            [AdminProposalController::class, 'updateStatus'],
            [RequirePermissionMiddleware::with('proposal:update_status', $rbacManager, $connection)]
        );
        $this->router->post(
            '/api/v1/admin/proposals/:id/assign',
            [AdminProposalController::class, 'assignLead'],
            [RequirePermissionMiddleware::with('proposal:update_status', $rbacManager, $connection)]
        );
        $this->router->patch(
            '/api/v1/admin/proposals/:id/assign',
            [AdminProposalController::class, 'assignLead'],
            [RequirePermissionMiddleware::with('proposal:update_status', $rbacManager, $connection)]
        );
        $this->router->get(
            '/api/v1/admin/proposals/:id/download',
            [AdminProposalController::class, 'streamDocument'],
            [RequirePermissionMiddleware::with('proposal:download_tender', $rbacManager, $connection)]
        );
        $this->router->delete(
            '/api/v1/admin/proposals/:id',
            [AdminProposalController::class, 'delete'],
            [RequirePermissionMiddleware::with('proposal:delete', $rbacManager, $connection)]
        );

        // Phase 13 Administrative Email Templates & Outbox Telemetry Routes
        // Templates
        $this->router->get(
            '/api/v1/admin/email-templates',
            [AdminEmailTemplateController::class, 'index'],
            [RequirePermissionMiddleware::with('email:read', $rbacManager, $connection)]
        );
        $this->router->get(
            '/api/admin/email-templates',
            [AdminEmailTemplateController::class, 'index'],
            [RequirePermissionMiddleware::with('email:read', $rbacManager, $connection)]
        );
        $this->router->get(
            '/api/v1/admin/email-templates/:id',
            [AdminEmailTemplateController::class, 'show'],
            [RequirePermissionMiddleware::with('email:read', $rbacManager, $connection)]
        );
        $this->router->get(
            '/api/admin/email-templates/:id',
            [AdminEmailTemplateController::class, 'show'],
            [RequirePermissionMiddleware::with('email:read', $rbacManager, $connection)]
        );
        $this->router->put(
            '/api/v1/admin/email-templates/:id',
            [AdminEmailTemplateController::class, 'update'],
            [RequirePermissionMiddleware::with('email:update', $rbacManager, $connection)]
        );
        $this->router->patch(
            '/api/v1/admin/email-templates/:id',
            [AdminEmailTemplateController::class, 'update'],
            [RequirePermissionMiddleware::with('email:update', $rbacManager, $connection)]
        );
        $this->router->put(
            '/api/admin/email-templates/:id',
            [AdminEmailTemplateController::class, 'update'],
            [RequirePermissionMiddleware::with('email:update', $rbacManager, $connection)]
        );
        $this->router->patch(
            '/api/admin/email-templates/:id',
            [AdminEmailTemplateController::class, 'update'],
            [RequirePermissionMiddleware::with('email:update', $rbacManager, $connection)]
        );
        $this->router->post(
            '/api/v1/admin/email-templates/:id/test-send',
            [AdminEmailTemplateController::class, 'testSend'],
            [RequirePermissionMiddleware::with('email:test_send', $rbacManager, $connection)]
        );
        $this->router->post(
            '/api/admin/email-templates/:id/test-send',
            [AdminEmailTemplateController::class, 'testSend'],
            [RequirePermissionMiddleware::with('email:test_send', $rbacManager, $connection)]
        );

        // Outbox Telemetry & Retries
        $this->router->get(
            '/api/v1/admin/email-queue',
            [AdminEmailQueueController::class, 'index'],
            [RequirePermissionMiddleware::with('email:read', $rbacManager, $connection)]
        );
        $this->router->get(
            '/api/admin/email-queue',
            [AdminEmailQueueController::class, 'index'],
            [RequirePermissionMiddleware::with('email:read', $rbacManager, $connection)]
        );
        $this->router->get(
            '/api/v1/admin/email-templates/outbox',
            [AdminEmailQueueController::class, 'index'],
            [RequirePermissionMiddleware::with('email:read', $rbacManager, $connection)]
        );
        $this->router->get(
            '/api/v1/admin/email-queue/:id',
            [AdminEmailQueueController::class, 'show'],
            [RequirePermissionMiddleware::with('email:read', $rbacManager, $connection)]
        );
        $this->router->get(
            '/api/admin/email-queue/:id',
            [AdminEmailQueueController::class, 'show'],
            [RequirePermissionMiddleware::with('email:read', $rbacManager, $connection)]
        );
        $this->router->post(
            '/api/v1/admin/email-queue/:id/retry',
            [AdminEmailQueueController::class, 'retry'],
            [RequirePermissionMiddleware::with('email:update', $rbacManager, $connection)]
        );
        $this->router->post(
            '/api/admin/email-queue/:id/retry',
            [AdminEmailQueueController::class, 'retry'],
            [RequirePermissionMiddleware::with('email:update', $rbacManager, $connection)]
        );
        $this->router->post(
            '/api/v1/admin/email-templates/outbox/:id/retry',
            [AdminEmailQueueController::class, 'retry'],
            [RequirePermissionMiddleware::with('email:update', $rbacManager, $connection)]
        );
        $this->router->post(
            '/api/v1/admin/email-queue/retry-all-failed',
            [AdminEmailQueueController::class, 'retryAllFailed'],
            [RequirePermissionMiddleware::with('email:update', $rbacManager, $connection)]
        );
        $this->router->post(
            '/api/admin/email-queue/retry-all-failed',
            [AdminEmailQueueController::class, 'retryAllFailed'],
            [RequirePermissionMiddleware::with('email:update', $rbacManager, $connection)]
        );
        $this->router->post(
            '/api/v1/admin/email-queue/dispatch',
            [AdminEmailQueueController::class, 'dispatch'],
            [RequirePermissionMiddleware::with('email:update', $rbacManager, $connection)]
        );
        $this->router->post(
            '/api/admin/email-queue/dispatch',
            [AdminEmailQueueController::class, 'dispatch'],
            [RequirePermissionMiddleware::with('email:update', $rbacManager, $connection)]
        );

        // Phase 14 Careers & Job Openings Management Routes (ADM-013 / FR-ADM-014)
        $this->router->get(
            '/api/v1/admin/jobs',
            [AdminJobController::class, 'index'],
            [RequirePermissionMiddleware::with('job:create', $rbacManager, $connection)]
        );
        $this->router->get(
            '/api/admin/jobs',
            [AdminJobController::class, 'index'],
            [RequirePermissionMiddleware::with('job:create', $rbacManager, $connection)]
        );
        $this->router->get(
            '/api/v1/admin/jobs/:id',
            [AdminJobController::class, 'show'],
            [RequirePermissionMiddleware::with('job:create', $rbacManager, $connection)]
        );
        $this->router->get(
            '/api/admin/jobs/:id',
            [AdminJobController::class, 'show'],
            [RequirePermissionMiddleware::with('job:create', $rbacManager, $connection)]
        );
        $this->router->post(
            '/api/v1/admin/jobs',
            [AdminJobController::class, 'create'],
            [RequirePermissionMiddleware::with('job:create', $rbacManager, $connection)]
        );
        $this->router->post(
            '/api/admin/jobs',
            [AdminJobController::class, 'create'],
            [RequirePermissionMiddleware::with('job:create', $rbacManager, $connection)]
        );
        $this->router->put(
            '/api/v1/admin/jobs/:id',
            [AdminJobController::class, 'update'],
            [RequirePermissionMiddleware::with('job:update', $rbacManager, $connection)]
        );
        $this->router->patch(
            '/api/v1/admin/jobs/:id',
            [AdminJobController::class, 'update'],
            [RequirePermissionMiddleware::with('job:update', $rbacManager, $connection)]
        );
        $this->router->put(
            '/api/admin/jobs/:id',
            [AdminJobController::class, 'update'],
            [RequirePermissionMiddleware::with('job:update', $rbacManager, $connection)]
        );
        $this->router->delete(
            '/api/v1/admin/jobs/:id',
            [AdminJobController::class, 'delete'],
            [RequirePermissionMiddleware::with('job:close', $rbacManager, $connection)]
        );
        $this->router->delete(
            '/api/admin/jobs/:id',
            [AdminJobController::class, 'delete'],
            [RequirePermissionMiddleware::with('job:close', $rbacManager, $connection)]
        );

        // Phase 14 Candidate Applications & Recruitment Review Desk Routes (ADM-014 / FR-ADM-015 / FR-WF-004)
        $this->router->get(
            '/api/v1/admin/applications',
            [AdminApplicationController::class, 'index'],
            [RequirePermissionMiddleware::with('candidate:list', $rbacManager, $connection)]
        );
        $this->router->get(
            '/api/admin/careers/applications',
            [AdminApplicationController::class, 'index'],
            [RequirePermissionMiddleware::with('candidate:list', $rbacManager, $connection)]
        );
        $this->router->get(
            '/api/v1/admin/applications/export',
            [AdminApplicationController::class, 'export'],
            [RequirePermissionMiddleware::with('candidate:export_csv', $rbacManager, $connection)]
        );
        $this->router->get(
            '/api/admin/careers/applications/export',
            [AdminApplicationController::class, 'export'],
            [RequirePermissionMiddleware::with('candidate:export_csv', $rbacManager, $connection)]
        );
        $this->router->get(
            '/api/v1/admin/applications/:id',
            [AdminApplicationController::class, 'show'],
            [RequirePermissionMiddleware::with('candidate:list', $rbacManager, $connection)]
        );
        $this->router->get(
            '/api/admin/careers/applications/:id',
            [AdminApplicationController::class, 'show'],
            [RequirePermissionMiddleware::with('candidate:list', $rbacManager, $connection)]
        );
        $this->router->patch(
            '/api/v1/admin/applications/:id/status',
            [AdminApplicationController::class, 'updateStatus'],
            [RequirePermissionMiddleware::with('candidate:update_stage', $rbacManager, $connection)]
        );
        $this->router->patch(
            '/api/admin/careers/applications/:id/status',
            [AdminApplicationController::class, 'updateStatus'],
            [RequirePermissionMiddleware::with('candidate:update_stage', $rbacManager, $connection)]
        );
        $this->router->get(
            '/api/v1/admin/applications/:id/download',
            [AdminApplicationController::class, 'streamResume'],
            [RequirePermissionMiddleware::with('candidate:download_cv', $rbacManager, $connection)]
        );
        $this->router->get(
            '/api/v1/admin/applications/:id/resume',
            [AdminApplicationController::class, 'streamResume'],
            [RequirePermissionMiddleware::with('candidate:download_cv', $rbacManager, $connection)]
        );
        $this->router->get(
            '/api/admin/careers/applications/:id/resume',
            [AdminApplicationController::class, 'streamResume'],
            [RequirePermissionMiddleware::with('candidate:download_cv', $rbacManager, $connection)]
        );
    }
}
