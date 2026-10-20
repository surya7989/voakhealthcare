<?php

declare(strict_types=1);

namespace Voak\Health\Core\Exceptions;

/**
 * Database exception masking raw credentials and SQL syntax in production
 */
class DatabaseException extends AppException
{
    public function __construct(string $message = 'Database operation failed.', array $details = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, 'DATABASE_ERROR', 500, $details, $previous);
    }
}
