<?php

declare(strict_types=1);

namespace Voak\Health\Modules\Health;

use Voak\Health\Core\Config;
use Voak\Health\Core\Http\Request;
use Voak\Health\Core\Http\Response;

/**
 * Health & Liveness Controller.
 * Provides lightweight operational liveness endpoints without opening database connections
 * or disclosing sensitive internal environment details.
 */
class HealthController
{
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Canonical Phase 4 Roadmap Gate Endpoint: GET /api/health
     * Returns exact {"status": "ok"} without leaking PHP version or server headers.
     */
    public function legacyHealth(Request $request): Response
    {
        return (new Response())
            ->withJson(['status' => 'ok'], 200)
            ->withHeader('X-Correlation-ID', $request->getCorrelationId());
    }

    /**
     * Canonical Module 01 Endpoint: GET /api/v1/health
     * Returns standard Phase 2 JSON envelope.
     */
    public function healthV1(Request $request): Response
    {
        $data = [
            'status' => 'healthy',
            'service' => (string)$this->config->get('APP_NAME', 'Voak Health Care API'),
            'version' => '1.0.0',
            'environment' => (string)$this->config->get('APP_ENV', 'development'),
        ];

        return Response::success($data, 200, $request->getCorrelationId());
    }
}
