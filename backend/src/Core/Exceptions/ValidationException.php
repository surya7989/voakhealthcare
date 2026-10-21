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
        array|string $errorsOrMessage = [],
        array|string $messageOrErrors = 'The provided entity fields failed business validation rules.',
        string $errorCode = 'VALIDATION_FAILED'
    ) {
        if (is_array($errorsOrMessage)) {
            $validationErrors = $errorsOrMessage;
            $message = is_string($messageOrErrors) ? $messageOrErrors : 'The provided entity fields failed business validation rules.';
        } else {
            $message = $errorsOrMessage;
            $validationErrors = is_array($messageOrErrors) ? $messageOrErrors : [];
        }

        parent::__construct(422, $message, $errorCode, [], ['validation_errors' => $validationErrors]);
        $this->validationErrors = $validationErrors;
    }

    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }
}
