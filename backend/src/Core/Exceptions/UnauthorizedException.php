<?php

declare(strict_types=1);

namespace Voak\Health\Core\Exceptions;

/**
 * 401 Unauthorized Exception for missing or invalid authentication credentials.
 */
class UnauthorizedException extends HttpException
{
    public function __construct(
        string $message = 'Unauthorized.',
        string $errorCode = 'UNAUTHORIZED',
        array $headers = [],
        array $details = []
    ) {
        parent::__construct(401, $message, $errorCode, $headers, $details);
    }
}
