<?php

declare(strict_types=1);

namespace Voak\Health\Modules\Admin;

use Voak\Health\Core\Http\Request;
use Voak\Health\Core\Http\Response;
use Voak\Health\Database\Connection;

/**
 * Privileged Administrative Users Controller.
 * Protected by RequirePermissionMiddleware('user:list') ensuring least-privilege RBAC.
 */
class AdminUsersController
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function listUsers(Request $request): Response
    {
        $pdo = $this->connection->getPdo();
        $stmt = $pdo->query("SELECT `id`, `name`, `email`, `is_active`, `created_at` FROM `users` WHERE `deleted_at` IS NULL ORDER BY `id` ASC");
        $users = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
        if ($stmt) {
            $stmt->closeCursor();
        }

        return Response::success([
            'users' => $users,
            'count' => count($users),
        ], 200, $request->getCorrelationId());
    }
}
