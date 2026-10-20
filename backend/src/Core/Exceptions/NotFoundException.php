<?php

declare(strict_types=1);

namespace Voak\Health\Core\Exceptions;

/**
 * 404 Not Found Exception
 */
class NotFoundException extends HttpException
{
    public function __construct(string $message = 'The requested resource was not found.', string $errorCode = 'NOT_FOUND', array $details = [])
    {
        parent::__construct(404, $message, $errorCode, [], $details);
    }
}
