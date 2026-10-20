<?php

declare(strict_types=1);

namespace Voak\Health\Core\Http;

/**
 * Immutable HTTP Response representation with standard Phase 2 JSON envelopes.
 */
class Response
{
    private int $statusCode;
    private array $headers;
    private string $body;

    public function __construct(int $statusCode = 200, array $headers = [], string $body = '')
    {
        $this->statusCode = $statusCode;
        $this->headers = $headers;
        $this->body = $body;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHeader(string $name, ?string $default = null): ?string
    {
        $lower = strtolower($name);
        foreach ($this->headers as $k => $v) {
            if (strtolower($k) === $lower) {
                return $v;
            }
        }
        return $default;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function withStatus(int $statusCode): self
    {
        $clone = clone $this;
        $clone->statusCode = $statusCode;
        return $clone;
    }

    public function withHeader(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;
        return $clone;
    }

    public function withJson(mixed $data, int $statusCode = 200): self
    {
        $json = json_encode(
            $data,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );

        $clone = clone $this;
        $clone->statusCode = $statusCode;
        $clone->headers['Content-Type'] = 'application/json; charset=utf-8';
        $clone->body = $json;
        return $clone;
    }

    /**
     * Standard Phase 2 Success Envelope factory.
     */
    public static function success(mixed $data = null, int $statusCode = 200, string $correlationId = '', array $meta = []): self
    {
        $envelope = [
            'success' => true,
            'data' => $data,
            'meta' => array_merge([
                'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
                'correlation_id' => $correlationId !== '' ? $correlationId : ('req_' . bin2hex(random_bytes(8))),
            ], $meta),
        ];

        return (new self())->withJson($envelope, $statusCode);
    }

    /**
     * Standard Phase 2 Error Envelope factory.
     */
    public static function error(
        string $message,
        string $errorCode = 'ERROR',
        int $statusCode = 400,
        string $referenceId = '',
        array $details = [],
        string $correlationId = ''
    ): self {
        $errorPayload = [
            'code' => $errorCode,
            'message' => $message,
        ];

        if ($referenceId !== '') {
            $errorPayload['reference_id'] = $referenceId;
        }

        if (!empty($details)) {
            foreach ($details as $k => $v) {
                $errorPayload[$k] = $v;
            }
        }

        $envelope = [
            'success' => false,
            'error' => $errorPayload,
            'meta' => [
                'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
                'correlation_id' => $correlationId !== '' ? $correlationId : ('req_' . bin2hex(random_bytes(8))),
            ],
        ];

        return (new self())->withJson($envelope, $statusCode);
    }

    /**
     * Raw content response factory (for plain text, healthchecks, etc.).
     */
    public static function raw(string $content, int $statusCode = 200, string $contentType = 'text/plain'): self
    {
        return new self($statusCode, ['Content-Type' => $contentType], $content);
    }

    /**
     * Emit response to client.
     */
    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->statusCode);
            foreach ($this->headers as $name => $value) {
                header("{$name}: {$value}", true);
            }
        }
        echo $this->body;
    }
}
