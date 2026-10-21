<?php

declare(strict_types=1);

namespace Voak\Health\Core\Exceptions;

/**
 * 401 Unauthorized Exception for authentication failure.
 */
class AuthenticationException extends HttpException
{
    public function __construct(
        string $message = 'Authentication failed.',
        string $errorCode = 'UNAUTHORIZED',
        array $headers = [],
        array $details = []
    ) {
        parent::__construct(401, $message, $errorCode, $headers, $details);
    }
}
