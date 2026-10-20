<?php

declare(strict_types=1);

namespace Voak\Health\Middleware;

use Voak\Health\Core\Http\Request;
use Voak\Health\Core\Http\Response;

/**
 * Attaches enterprise security response headers to all HTTP responses.
 */
class SecurityHeadersMiddleware implements MiddlewareInterface
{
    private array $headers;

    public function __construct(array $customHeaders = [])
    {
        $this->headers = array_merge([
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
            'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'none';",
        ], $customHeaders);
    }

    public function process(Request $request, callable $next): Response
    {
        $response = $next($request);

        foreach ($this->headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        // Also ensure X-Correlation-ID is echoed back on response
        if (!$response->getHeader('X-Correlation-ID')) {
            $response = $response->withHeader('X-Correlation-ID', $request->getCorrelationId());
        }

        return $response;
    }
}
