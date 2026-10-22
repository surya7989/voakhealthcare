<?php

declare(strict_types=1);

namespace Voak\Health\Core\Exceptions;

/**
 * Thrown when a resource operation conflicts with the current system or entity state
 * (e.g. referential integrity deletion guards or concurrent resource modification).
 */
class ConflictException extends HttpException
{
    public function __construct(
        string $message = 'The operation conflicts with current resource state.',
        string $errorCode = 'ERR_CONFLICT',
        array $details = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct(409, $message, $errorCode, [], $details, $previous);
    }
}
