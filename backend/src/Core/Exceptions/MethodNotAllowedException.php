<?php

declare(strict_types=1);

namespace Voak\Health\Core\Exceptions;

/**
 * 405 Method Not Allowed Exception emitting standard Allow header
 */
class MethodNotAllowedException extends HttpException
{
    public function __construct(array $allowedMethods, string $message = 'HTTP method not allowed for this route.', string $errorCode = 'METHOD_NOT_ALLOWED')
    {
        $allowHeader = implode(', ', $allowedMethods);
        parent::__construct(405, $message, $errorCode, ['Allow' => $allowHeader], ['allowed_methods' => $allowedMethods]);
    }
}
