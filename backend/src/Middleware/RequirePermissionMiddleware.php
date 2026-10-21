<?php

declare(strict_types=1);

namespace Voak\Health\Middleware;

use Voak\Health\Auth\RbacManager;
use Voak\Health\Core\Http\Request;
use Voak\Health\Core\Http\Response;
use Voak\Health\Database\Connection;

/**
 * Granular Role-Based Access Control (RBAC) Enforcement Middleware.
 * Enforces least-privilege authorization across the 45 granular action permissions.
 */
class RequirePermissionMiddleware implements MiddlewareInterface
{
    private string $requiredPermission;
    private RbacManager $rbacManager;
    private ?Connection $connection;

    public function __construct(string $requiredPermission, ?RbacManager $rbacManager = null, ?Connection $connection = null)
    {
        $this->requiredPermission = $requiredPermission;
        $this->rbacManager = $rbacManager ?? new RbacManager();
        $this->connection = $connection;
    }

    public static function with(string $permission, ?RbacManager $rbacManager = null, ?Connection $connection = null): self
    {
        return new self($permission, $rbacManager, $connection);
    }

    public function process(Request $request, callable $next): Response
    {
        $userId = $request->getAttribute('admin_user_id');

        if ($userId === null || $this->connection === null) {
            return Response::error(
                "Access denied. Valid administrative authentication required.",
                401,
                'ERR_UNAUTHENTICATED'
            );
        }

        $hasAccess = $this->rbacManager->hasPermission($this->connection->getPdo(), (int)$userId, $this->requiredPermission);

        if (!$hasAccess) {
            return Response::error(
                "Access denied. Your account lacks sufficient privileges for this operational action.",
                403,
                'ERR_FORBIDDEN',
                ['required_permission' => $this->requiredPermission]
            );
        }

        return $next($request);
    }

    public function setConnection(Connection $connection): void
    {
        $this->connection = $connection;
    }
}
