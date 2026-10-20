<?php

declare(strict_types=1);

namespace Voak\Health\Core\Exceptions;

/**
 * Configuration exception triggered on boot or invalid environment parameters
 */
class ConfigurationException extends AppException
{
    public function __construct(string $message = 'Invalid or missing configuration.', array $details = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, 'CONFIGURATION_ERROR', 500, $details, $previous);
    }
}
