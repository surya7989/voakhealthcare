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
use Voak\Health\Middleware\SecurityHeadersMiddleware;
use Voak\Health\Modules\Admin\AdminAuthController;
use Voak\Health\Modules\Admin\MfaController;
use Voak\Health\Modules\Health\HealthController;
use Voak\Health\Modules\Scaffolding\PartnersClientsScaffolding;
use Voak\Health\Security\AuditLogger;
use Voak\Health\Security\PasswordHasher;
use Voak\Health\Security\TotpEngine;

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
        $authService = new AuthenticationService($hasher, $sessionManager, $totpService, $rbacManager, $auditLogger);

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
        $csrfMiddleware = new CsrfProtectionMiddleware($connection);
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
    }
}
