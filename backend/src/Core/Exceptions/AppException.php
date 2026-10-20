<?php

declare(strict_types=1);

namespace Voak\Health\Core\Exceptions;

/**
 * Base application exception for all Voak Health Care domain and infrastructure errors.
 */
class AppException extends \Exception
{
    protected string $errorCode;
    protected array $details;

    public function __construct(
        string $message = '',
        string $errorCode = 'INTERNAL_SERVER_ERROR',
        int $code = 0,
        array $details = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->errorCode = $errorCode;
        $this->details = $details;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getDetails(): array
    {
        return $this->details;
    }
}
