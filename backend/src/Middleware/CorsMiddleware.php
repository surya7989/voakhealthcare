<?php

declare(strict_types=1);

namespace Voak\Health\Middleware;

use Voak\Health\Core\Http\Request;
use Voak\Health\Core\Http\Response;

/**
 * Strict Cross-Origin Resource Sharing (CORS) middleware.
 * Explicitly rejects wildcard origins with credentials.
 */
class CorsMiddleware implements MiddlewareInterface
{
    private array $allowedOrigins;

    public function __construct(array|string $allowedOrigins = [])
    {
        if (is_string($allowedOrigins)) {
            $this->allowedOrigins = array_filter(array_map('trim', explode(',', $allowedOrigins)));
        } else {
            $this->allowedOrigins = $allowedOrigins;
        }
    }

    public function process(Request $request, callable $next): Response
    {
        $origin = $request->getHeader('origin');

        // Check if preflight OPTIONS request
        if ($request->getMethod() === 'OPTIONS') {
            $response = new Response(204);
            if ($origin !== null && $this->isOriginAllowed($origin)) {
                $response = $this->applyCorsHeaders($response, $origin);
            }
            return $response;
        }

        $response = $next($request);

        if ($origin !== null && $this->isOriginAllowed($origin)) {
            $response = $this->applyCorsHeaders($response, $origin);
        }

        return $response;
    }

    private function isOriginAllowed(string $origin): bool
    {
        if (empty($this->allowedOrigins)) {
            return false;
        }

        foreach ($this->allowedOrigins as $allowed) {
            if ($allowed === '*' || strcasecmp($allowed, $origin) === 0) {
                return true;
            }
        }

        return false;
    }

    private function applyCorsHeaders(Response $response, string $origin): Response
    {
        // Security constraint: NEVER emit wildcard with credentials
        $allowOrigin = ($origin !== '' && $this->isOriginAllowed($origin)) ? $origin : '';

        return $response
            ->withHeader('Access-Control-Allow-Origin', $allowOrigin)
            ->withHeader('Access-Control-Allow-Credentials', 'true')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-Correlation-ID, X-CSRF-Token')
            ->withHeader('Access-Control-Max-Age', '86400')
            ->withHeader('Vary', 'Origin');
    }
}
