<?php

declare(strict_types=1);

namespace Voak\Health\Middleware;

use Voak\Health\Auth\SessionManager;
use Voak\Health\Core\Http\Request;
use Voak\Health\Core\Http\Response;
use Voak\Health\Database\Connection;

/**
 * Administrative Authentication Verification Middleware.
 * Validates voak_admin_session cookie or Bearer token for protected /api/v1/admin/* endpoints.
 */
class AdminAuthenticateMiddleware implements MiddlewareInterface
{
    private SessionManager $sessionManager;
    private ?Connection $connection;

    // Public admin endpoints that do not require an active session
    private const PUBLIC_ADMIN_PATHS = [
        '/api/v1/admin/auth/login',
        '/api/v1/admin/auth/mfa/verify',
        '/api/v1/admin/auth/mfa/recovery',
        '/api/v1/admin/auth/password-reset/request',
        '/api/v1/admin/auth/password-reset/confirm',
        '/api/v1/admin/auth/csrf',
    ];

    public function __construct(?SessionManager $sessionManager = null, ?Connection $connection = null)
    {
        $this->sessionManager = $sessionManager ?? new SessionManager();
        $this->connection = $connection;
    }

    public function process(Request $request, callable $next): Response
    {
        $path = $request->getPath();

        // Only enforce for /api/v1/admin routes
        if (!str_starts_with($path, '/api/v1/admin')) {
            return $next($request);
        }

        // Allow public admin auth endpoints
        foreach (self::PUBLIC_ADMIN_PATHS as $publicPath) {
            if ($path === $publicPath) {
                return $next($request);
            }
        }

        // Extract session ID from cookie or Bearer token
        $sessionId = $request->getCookie(SessionManager::COOKIE_NAME);
        if ($sessionId === null) {
            $authHeader = $request->getHeader('authorization');
            if ($authHeader !== null && str_starts_with($authHeader, 'Bearer ')) {
                $sessionId = trim(substr($authHeader, 7));
            }
        }

        if ($sessionId === null || $this->connection === null) {
            return $this->unauthorizedResponse("Unauthenticated. Valid administrative session required.");
        }

        $session = $this->sessionManager->validateSession($this->connection->getPdo(), $sessionId);
        if ($session === null) {
            return $this->unauthorizedResponse("Session expired or invalid. Please log in again.");
        }

        // Attach authenticated context to request
        $request = $request
            ->withAttribute('admin_session_id', $sessionId)
            ->withAttribute('admin_user_id', (int)$session['user_id'])
            ->withAttribute('admin_user', [
                'id'    => (int)$session['user_id'],
                'name'  => (string)$session['user_name'],
                'email' => (string)$session['user_email'],
            ]);

        return $next($request);
    }

    private function unauthorizedResponse(string $message): Response
    {
        return Response::error(
            $message,
            401,
            'ERR_UNAUTHENTICATED'
        );
    }

    public function setConnection(Connection $connection): void
    {
        $this->connection = $connection;
    }
}
