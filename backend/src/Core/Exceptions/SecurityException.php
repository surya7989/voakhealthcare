<?php

declare(strict_types=1);

namespace Voak\Health\Core\Exceptions;

/**
 * 403 Security Exception for tampering or cryptographic validation failures.
 */
class SecurityException extends HttpException
{
    public function __construct(
        string $message = 'Security violation detected.',
        string $errorCode = 'SECURITY_ERROR',
        array $headers = [],
        array $details = []
    ) {
        parent::__construct(403, $message, $errorCode, $headers, $details);
    }
}
