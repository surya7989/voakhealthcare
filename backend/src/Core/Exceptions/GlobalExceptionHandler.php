<?php

declare(strict_types=1);

namespace Voak\Health\Core\Exceptions;

use Voak\Health\Core\Config;
use Voak\Health\Core\Http\Request;
use Voak\Health\Core\Http\Response;

/**
 * Centralized exception handler normalizing exceptions into deterministic Phase 2 JSON envelopes.
 * Completely suppresses stack traces, credentials, and SQL details in production.
 */
class GlobalExceptionHandler
{
    private Config $config;
    private ?string $logPath;

    public function __construct(Config $config, ?string $logPath = null)
    {
        $this->config = $config;
        $this->logPath = $logPath;
    }

    /**
     * Converts any Throwable into a secure, normalized HTTP Response.
     */
    public function handle(\Throwable $e, ?Request $request = null): Response
    {
        $correlationId = $request !== null ? $request->getCorrelationId() : ('req_' . bin2hex(random_bytes(8)));
        $referenceId = 'REF-ERR-' . strtoupper(bin2hex(random_bytes(4)));

        $statusCode = 500;
        $errorCode = 'INTERNAL_SERVER_ERROR';
        $message = 'An unexpected error occurred. Please quote the reference code to support.';
        $details = [];
        $headers = [];

        if ($e instanceof HttpException) {
            $statusCode = $e->getStatusCode();
            $errorCode = $e->getErrorCode();
            $message = $e->getMessage();
            $details = $e->getDetails();
            $headers = $e->getHeaders();
        } elseif ($e instanceof AppException) {
            $errorCode = $e->getErrorCode();
            $message = $e->getMessage();
            $details = $e->getDetails();
        }

        // Production vs Debug Error Masking
        $isDebug = $this->config->isDebug();
        if ($statusCode >= 500) {
            if ($isDebug) {
                $message = $e->getMessage();
                $details['debug'] = [
                    'class' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => array_slice($e->getTrace(), 0, 5),
                ];
            } else {
                // Production: sanitize message to prevent internal data disclosure
                if (!($e instanceof HttpException)) {
                    $message = 'An internal server error occurred. Please quote the reference code to support.';
                }
            }
        }

        // Log error securely
        $this->logException($e, $referenceId, $correlationId, $statusCode);

        $response = Response::error(
            $message,
            $errorCode,
            $statusCode,
            $referenceId,
            $details,
            $correlationId
        );

        foreach ($headers as $k => $v) {
            $response = $response->withHeader($k, $v);
        }

        // Ensure security and correlation headers
        return $response
            ->withHeader('X-Correlation-ID', $correlationId)
            ->withHeader('X-Content-Type-Options', 'nosniff');
    }

    private function logException(\Throwable $e, string $referenceId, string $correlationId, int $status): void
    {
        if ($this->logPath === null) {
            return;
        }

        $logDir = dirname($this->logPath);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $entry = sprintf(
            "[%s] [%s] [%s] HTTP %d - %s: %s in %s:%d\n",
            gmdate('Y-m-d H:i:s'),
            $correlationId,
            $referenceId,
            $status,
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        );

        @file_put_contents($this->logPath, $entry, FILE_APPEND | LOCK_EX);
    }
}
