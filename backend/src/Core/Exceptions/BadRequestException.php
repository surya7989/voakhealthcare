<?php

declare(strict_types=1);

namespace Voak\Health\Core\Exceptions;

/**
 * 400 Bad Request Exception (e.g. malformed JSON, invalid encoding)
 */
class BadRequestException extends HttpException
{
    public function __construct(string $message = 'Malformed or invalid HTTP request.', string $errorCode = 'BAD_REQUEST', array $details = [])
    {
        parent::__construct(400, $message, $errorCode, [], $details);
    }
}
