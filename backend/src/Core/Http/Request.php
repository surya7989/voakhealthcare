<?php

declare(strict_types=1);

namespace Voak\Health\Core\Http;

use Voak\Health\Core\Exceptions\BadRequestException;

/**
 * Immutable HTTP Request representation with safe JSON parsing and correlation ID.
 */
class Request
{
    private string $method;
    private string $path;
    private array $queryParams;
    private array $headers;
    private string $rawBody;
    private ?array $parsedJson = null;
    private string $correlationId;
    private string $clientIp;
    private array $attributes = [];

    public function __construct(
        string $method,
        string $path,
        array $queryParams = [],
        array $headers = [],
        string $rawBody = '',
        ?string $correlationId = null,
        ?string $clientIp = null
    ) {
        $this->method = strtoupper($method);
        $this->path = $this->normalizePath($path);
        $this->queryParams = $queryParams;
        $this->headers = $this->normalizeHeaders($headers);
        $this->rawBody = $rawBody;
        $this->correlationId = $correlationId ?? $this->resolveCorrelationId();
        $this->clientIp = $clientIp ?? '127.0.0.1';
    }

    public static function fromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        $headers = [];
        if (function_exists('getallheaders')) {
            $headers = getallheaders() ?: [];
        } else {
            foreach ($_SERVER as $k => $v) {
                if (str_starts_with($k, 'HTTP_')) {
                    $headerName = str_replace('_', '-', substr($k, 5));
                    $headers[$headerName] = $v;
                } elseif (in_array($k, ['CONTENT_TYPE', 'CONTENT_LENGTH'])) {
                    $headerName = str_replace('_', '-', $k);
                    $headers[$headerName] = $v;
                }
            }
        }

        $rawBody = (string)file_get_contents('php://input');

        $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        return new self($method, $path, $_GET, $headers, $rawBody, null, $clientIp);
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    public function getQueryParam(string $key, mixed $default = null): mixed
    {
        return $this->queryParams[$key] ?? $default;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHeader(string $name, ?string $default = null): ?string
    {
        $normalized = strtolower($name);
        return $this->headers[$normalized] ?? $default;
    }

    public function hasHeader(string $name): bool
    {
        $normalized = strtolower($name);
        return array_key_exists($normalized, $this->headers);
    }

    public function getRawBody(): string
    {
        return $this->rawBody;
    }

    /**
     * Parse and return request body as JSON array.
     *
     * @throws BadRequestException If body is invalid JSON or contains syntax errors
     */
    public function getJson(): array
    {
        if ($this->parsedJson !== null) {
            return $this->parsedJson;
        }

        $raw = trim($this->rawBody);
        if ($raw === '') {
            $this->parsedJson = [];
            return [];
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($decoded)) {
                throw new BadRequestException("Request body must be a valid JSON object or array.");
            }
            $this->parsedJson = $decoded;
            return $this->parsedJson;
        } catch (\JsonException $e) {
            throw new BadRequestException("Malformed JSON request body: " . $e->getMessage());
        }
    }

    public function getCorrelationId(): string
    {
        return $this->correlationId;
    }

    public function getClientIp(): string
    {
        return $this->clientIp;
    }

    public function withAttribute(string $name, mixed $value): self
    {
        $clone = clone $this;
        $clone->attributes[$name] = $value;
        return $clone;
    }

    public function getAttribute(string $name, mixed $default = null): mixed
    {
        return $this->attributes[$name] ?? $default;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    private function normalizePath(string $path): string
    {
        $path = '/' . trim($path, '/');
        return $path === '' ? '/' : $path;
    }

    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $k => $v) {
            $normalized[strtolower((string)$k)] = is_array($v) ? implode(', ', $v) : (string)$v;
        }
        return $normalized;
    }

    private function resolveCorrelationId(): string
    {
        $header = $this->getHeader('x-correlation-id') ?? $this->getHeader('x-request-id');
        if ($header !== null && preg_match('/^[a-zA-Z0-9_\-]+$/', $header)) {
            return $header;
        }
        return 'req_' . bin2hex(random_bytes(8));
    }
}
