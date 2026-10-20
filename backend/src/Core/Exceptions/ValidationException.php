<?php

declare(strict_types=1);

namespace Voak\Health\Core\Exceptions;

/**
 * 422 Unprocessable Entity Exception carrying structured field validation errors
 */
class ValidationException extends HttpException
{
    protected array $validationErrors;

    public function __construct(
        array $validationErrors,
        string $message = 'The provided entity fields failed business validation rules.',
        string $errorCode = 'VALIDATION_FAILED'
    ) {
        parent::__construct(422, $message, $errorCode, [], ['validation_errors' => $validationErrors]);
        $this->validationErrors = $validationErrors;
    }

    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }
}
