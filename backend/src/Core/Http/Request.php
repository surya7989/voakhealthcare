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
    private array $uploadedFiles = [];
    private ?array $parsedBody = null;

    public function __construct(
        string $method,
        string $path,
        array $queryParams = [],
        array $headers = [],
        string $rawBody = '',
        ?string $correlationId = null,
        ?string $clientIp = null,
        array $uploadedFiles = [],
        ?array $parsedBody = null
    ) {
        $this->method = strtoupper($method);
        $this->path = $this->normalizePath($path);
        $this->queryParams = $queryParams;
        $this->headers = $this->normalizeHeaders($headers);
        $this->rawBody = $rawBody;
        $this->correlationId = $correlationId ?? $this->resolveCorrelationId();
        $this->clientIp = $clientIp ?? '127.0.0.1';
        $this->uploadedFiles = $uploadedFiles;
        $this->parsedBody = $parsedBody;
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
        $uploadedFiles = $_FILES ?? [];
        $parsedBody = !empty($_POST) ? $_POST : null;

        return new self($method, $path, $_GET, $headers, $rawBody, null, $clientIp, $uploadedFiles, $parsedBody);
    }

    public function getUploadedFiles(): array
    {
        return $this->uploadedFiles;
    }

    public function getUploadedFile(string $key): ?array
    {
        return $this->uploadedFiles[$key] ?? null;
    }

    public function withUploadedFiles(array $files): self
    {
        $clone = clone $this;
        $clone->uploadedFiles = $files;
        return $clone;
    }

    public function getParsedBody(): ?array
    {
        return $this->parsedBody;
    }

    public function withParsedBody(?array $body): self
    {
        $clone = clone $this;
        $clone->parsedBody = $body;
        return $clone;
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

    /**
     * Extracts a named cookie value from the Cookie header.
     */
    public function getCookie(string $name, ?string $default = null): ?string
    {
        $cookieHeader = $this->getHeader('cookie');
        if ($cookieHeader === null) {
            return $default;
        }

        $cookies = explode(';', $cookieHeader);
        foreach ($cookies as $cookie) {
            $parts = explode('=', trim($cookie), 2);
            if (count($parts) === 2 && trim($parts[0]) === $name) {
                return urldecode(trim($parts[1]));
            }
        }

        return $default;
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

    /** @var array<string> */
    private static array $trustedProxies = [];

    /**
     * Configures trusted proxy IP addresses authorized to forward client identity headers.
     *
     * @param array<string> $proxies
     */
    public static function setTrustedProxies(array $proxies): void
    {
        self::$trustedProxies = array_values(array_filter(array_map('trim', $proxies)));
    }

    /**
     * @return array<string>
     */
    public static function getTrustedProxies(): array
    {
        return self::$trustedProxies;
    }

    /**
     * Resolves the verified client IP.
     * Forwarded headers (X-Forwarded-For, X-Real-IP) are strictly ignored unless the direct
     * connection remote address is in the configured trusted proxy roster.
     */
    public function getClientIp(): string
    {
        $isProxyTrusted = !empty(self::$trustedProxies) && in_array($this->clientIp, self::$trustedProxies, true);

        if ($isProxyTrusted) {
            $forwarded = $this->getHeader('x-forwarded-for');
            if ($forwarded !== null && $forwarded !== '') {
                $ips = explode(',', $forwarded);
                $candidate = trim($ips[0]);
                if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                    return $candidate;
                }
            }

            $realIp = $this->getHeader('x-real-ip');
            if ($realIp !== null && $realIp !== '') {
                $candidate = trim($realIp);
                if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                    return $candidate;
                }
            }
        }

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
