<?php

declare(strict_types=1);

namespace Voak\Health\Core;

use Voak\Health\Core\Exceptions\GlobalExceptionHandler;
use Voak\Health\Core\Http\Request;
use Voak\Health\Core\Http\Response;
use Voak\Health\Core\Router\Router;
use Voak\Health\Database\Connection;
use Voak\Health\Middleware\CorsMiddleware;
use Voak\Health\Middleware\JsonBodyParserMiddleware;
use Voak\Health\Middleware\MiddlewarePipeline;
use Voak\Health\Middleware\SecurityHeadersMiddleware;
use Voak\Health\Modules\Health\HealthController;
use Voak\Health\Modules\Scaffolding\PartnersClientsScaffolding;

/**
 * Voak Health Care Application Kernel.
 * Establishes one controlled initialization path for the PHP backend foundation.
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

        // Controller bindings
        $this->container->set(HealthController::class, new HealthController($this->config));
        $this->container->set(PartnersClientsScaffolding::class, new PartnersClientsScaffolding());
    }

    private function initializeMiddleware(): void
    {
        // 1. Security Response Headers
        $this->pipeline->pipe(new SecurityHeadersMiddleware());

        // 2. Strict CORS Handling
        $allowedOrigins = (string)$this->config->get('CORS_ALLOWED_ORIGINS', '');
        $this->pipeline->pipe(new CorsMiddleware($allowedOrigins));

        // 3. JSON Request Body Parser & Validator
        $this->pipeline->pipe(new JsonBodyParserMiddleware());
    }

    private function registerCoreRoutes(): void
    {
        // Canonical Phase 4 Roadmap Acceptance Gate
        $this->router->get('/api/health', [HealthController::class, 'legacyHealth']);

        // Canonical Module 01 Endpoint
        $this->router->get('/api/v1/health', [HealthController::class, 'healthV1']);

        // OPEN-03 Decoupled Scaffolding Preview Endpoint
        $this->router->get('/api/v1/public/partners-preview', [PartnersClientsScaffolding::class, 'getPartnersPreview']);
    }
}
