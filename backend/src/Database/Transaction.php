<?php

declare(strict_types=1);

namespace Voak\Health\Database;

use Voak\Health\Core\Exceptions\DatabaseException;

/**
 * Encapsulates database transactions with automatic rollback on exception.
 */
class Transaction
{
    /**
     * Executes a callback within a managed database transaction.
     *
     * @template T
     * @param \PDO $pdo
     * @param callable(\PDO): T $callback
     * @return T
     * @throws \Throwable
     */
    public static function run(\PDO $pdo, callable $callback): mixed
    {
        $pdo->beginTransaction();

        try {
            $result = $callback($pdo);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
