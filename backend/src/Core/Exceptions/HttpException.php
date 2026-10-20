<?php

declare(strict_types=1);

namespace Voak\Health\Core\Exceptions;

/**
 * Base HTTP exception carrying status code, machine-readable error code, and response headers.
 */
class HttpException extends AppException
{
    protected int $statusCode;
    protected array $headers;

    public function __construct(
        int $statusCode = 500,
        string $message = 'An unexpected HTTP error occurred.',
        string $errorCode = 'HTTP_ERROR',
        array $headers = [],
        array $details = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $errorCode, $statusCode, $details, $previous);
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }
}
