<?php

declare(strict_types=1);

namespace Voak\Health\Middleware;

use Voak\Health\Core\Http\Request;
use Voak\Health\Core\Http\Response;

/**
 * IP-Based Rate Limiting Middleware for Sensitive Administrative Endpoints.
 * Enforces request throttling (default: 10 requests/min per IP) returning HTTP 429.
 */
class RateLimitMiddleware implements MiddlewareInterface
{
    private int $maxAttempts;
    private int $decaySeconds;
    private static array $hits = [];

    public function __construct(int $maxAttempts = 10, int $decaySeconds = 60)
    {
        $this->maxAttempts = $maxAttempts;
        $this->decaySeconds = $decaySeconds;
    }

    public function process(Request $request, callable $next): Response
    {
        $path = $request->getPath();

        // Enforce rate limiting specifically on authentication endpoints
        if (!str_starts_with($path, '/api/v1/admin/auth/')) {
            return $next($request);
        }

        $ip = $request->getClientIp();
        $now = time();
        $key = md5($ip . ':' . $path);

        // Clean expired records
        if (!isset(self::$hits[$key])) {
            self::$hits[$key] = [];
        }

        self::$hits[$key] = array_filter(
            self::$hits[$key],
            fn(int $timestamp): bool => ($now - $timestamp) < $this->decaySeconds
        );

        if (count(self::$hits[$key]) >= $this->maxAttempts) {
            $retryAfter = $this->decaySeconds - ($now - min(self::$hits[$key]));
            $retryAfter = max(1, $retryAfter);

            $response = Response::error(
                "Too many authentication attempts. Please try again in {$retryAfter} seconds.",
                'ERR_RATE_LIMITED',
                429,
                '',
                ['retry_after' => $retryAfter]
            );

            return $response->withHeader('Retry-After', (string)$retryAfter);
        }

        self::$hits[$key][] = $now;

        return $next($request);
    }

    public static function reset(): void
    {
        self::$hits = [];
    }
}
