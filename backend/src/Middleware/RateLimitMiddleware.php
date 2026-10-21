<?php

declare(strict_types=1);

namespace Voak\Health\Middleware;

use Voak\Health\Core\Http\Request;
use Voak\Health\Core\Http\Response;
use Voak\Health\Security\RateLimiter;

/**
 * IP-Based Persistent Rate Limiting Middleware for Sensitive Administrative Endpoints.
 * Enforces shared persistent throttling (default: 10 requests/min per IP) returning HTTP 429.
 */
class RateLimitMiddleware implements MiddlewareInterface
{
    private int $maxAttempts;
    private int $decaySeconds;
    private RateLimiter $limiter;

    public function __construct(int $maxAttempts = 10, int $decaySeconds = 60, ?RateLimiter $limiter = null)
    {
        $this->maxAttempts = $maxAttempts;
        $this->decaySeconds = $decaySeconds;
        $this->limiter = $limiter ?? new RateLimiter();
    }

    public function process(Request $request, callable $next): Response
    {
        $path = $request->getPath();

        // Enforce rate limiting specifically on authentication endpoints
        if (!str_starts_with($path, '/api/v1/admin/auth/')) {
            return $next($request);
        }

        $ip = $request->getClientIp();
        $key = 'ip_' . md5($ip . ':' . $path);

        $result = $this->limiter->hit($key, $this->maxAttempts, $this->decaySeconds);

        if (!$result['allowed']) {
            $retryAfter = $result['retry_after'];
            $response = Response::error(
                "Too many authentication attempts. Please try again in {$retryAfter} seconds.",
                'ERR_RATE_LIMITED',
                429,
                '',
                ['retry_after' => $retryAfter]
            );

            return $response->withHeader('Retry-After', (string)$retryAfter);
        }

        return $next($request);
    }

    public static function reset(): void
    {
        (new RateLimiter())->reset();
    }
}
