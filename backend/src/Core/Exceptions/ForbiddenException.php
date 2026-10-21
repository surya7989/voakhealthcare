<?php

declare(strict_types=1);

namespace Voak\Health\Core\Exceptions;

/**
 * 403 Forbidden Exception for unauthorized permission or CSRF violations.
 */
class ForbiddenException extends HttpException
{
    public function __construct(
        string $message = 'Forbidden.',
        string $errorCode = 'FORBIDDEN',
        array $headers = [],
        array $details = []
    ) {
        parent::__construct(403, $message, $errorCode, $headers, $details);
    }
}
