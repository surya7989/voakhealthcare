<?php

declare(strict_types=1);

namespace Voak\Health\Middleware;

use Voak\Health\Core\Http\Request;
use Voak\Health\Core\Http\Response;
use Voak\Health\Database\Connection;

/**
 * Cross-Site Request Forgery (CSRF) Defense-in-Depth Middleware.
 * Validates X-CSRF-Token on state-mutating administrative HTTP requests.
 */
class CsrfProtectionMiddleware implements MiddlewareInterface
{
    private ?Connection $connection;

    // Mutating methods that require CSRF protection
    private const MUTATING_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    // Public authentication endpoints that generate their own CSRF token
    private const EXEMPT_PATHS = [
        '/api/v1/admin/auth/login',
        '/api/v1/admin/auth/mfa/verify',
        '/api/v1/admin/auth/mfa/recovery',
        '/api/v1/admin/auth/password-reset/request',
        '/api/v1/admin/auth/password-reset/confirm',
        '/api/v1/admin/auth/csrf',
    ];

    public function __construct(?Connection $connection = null)
    {
        $this->connection = $connection;
    }

    public function process(Request $request, callable $next): Response
    {
        $method = $request->getMethod();
        $path = $request->getPath();

        // Only enforce for mutating requests under /api/v1/admin
        if (!in_array($method, self::MUTATING_METHODS, true) || !str_starts_with($path, '/api/v1/admin')) {
            return $next($request);
        }

        // Check if path is exempt
        if (in_array($path, self::EXEMPT_PATHS, true)) {
            return $next($request);
        }

        $sessionId = $request->getAttribute('admin_session_id');
        if ($sessionId === null) {
            // Unauthenticated requests are handled by AdminAuthenticateMiddleware
            return $next($request);
        }

        $csrfToken = $request->getHeader('x-csrf-token');
        if ($csrfToken === null || $csrfToken === '' || $this->connection === null) {
            return Response::error(
                "CSRF token missing or invalid. Include valid X-CSRF-Token header.",
                'ERR_CSRF_MISSING',
                403
            );
        }

        $stmt = $this->connection->getPdo()->prepare("SELECT `csrf_token_hash` FROM `admin_sessions` WHERE `id` = :id");
        $stmt->execute(['id' => $sessionId]);
        $expectedHash = $stmt->fetchColumn();
        $stmt->closeCursor();

        if ($expectedHash === false || !hash_equals((string)$expectedHash, hash('sha256', $csrfToken))) {
            return Response::error(
                "CSRF token validation failed. Token mismatch.",
                'ERR_CSRF_INVALID',
                403
            );
        }

        return $next($request);
    }

    public function setConnection(Connection $connection): void
    {
        $this->connection = $connection;
    }
}
