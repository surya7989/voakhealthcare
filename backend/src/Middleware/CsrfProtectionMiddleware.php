<?php

declare(strict_types=1);

namespace Voak\Health\Middleware;

use Voak\Health\Core\Config;
use Voak\Health\Core\Exceptions\SecurityException;
use Voak\Health\Core\Http\Request;
use Voak\Health\Core\Http\Response;
use Voak\Health\Database\Connection;
use Voak\Health\Security\Crypto;

/**
 * Cross-Site Request Forgery (CSRF) Defense-in-Depth Middleware.
 * Validates X-CSRF-Token on all state-mutating administrative HTTP requests.
 * Reconciled with authoritative architecture in docs/phase-02/06_AUTHENTICATION_RBAC_AND_SESSION_ARCHITECTURE.md.
 */
class CsrfProtectionMiddleware implements MiddlewareInterface
{
    private ?Connection $connection;
    private ?Config $config;
    private ?string $signingKeyOverride = null;

    // Mutating methods requiring strict CSRF validation
    private const MUTATING_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    // Unauthenticated bootstrap TTL: 2 hours (7200 seconds)
    public const BOOTSTRAP_TOKEN_TTL_SECONDS = 7200;

    public function __construct(?Connection $connection = null, ?Config $config = null)
    {
        $this->connection = $connection;
        $this->config = $config;
    }

    public function setSigningKey(?string $key): void
    {
        $this->signingKeyOverride = $key;
    }

    public function getSigningKey(): string
    {
        if ($this->signingKeyOverride !== null && $this->signingKeyOverride !== '') {
            return $this->signingKeyOverride;
        }

        $key = '';
        if ($this->config !== null) {
            $key = (string)$this->config->get('CSRF_SIGNING_KEY', '');
        }

        if ($key === '') {
            $envKey = getenv('CSRF_SIGNING_KEY');
            if ($envKey) {
                $key = (string)$envKey;
            }
        }

        $env = $this->config ? (string)$this->config->get('APP_ENV', 'development') : (getenv('APP_ENV') ?: 'development');
        $isProdOrStaging = in_array(strtolower($env), ['production', 'staging'], true);

        if ($key === '' || strlen($key) < ($isProdOrStaging ? 32 : 16)) {
            throw new SecurityException("CSRF signing key is not configured or insecure (min " . ($isProdOrStaging ? "32" : "16") . " chars). Set CSRF_SIGNING_KEY in configuration.");
        }

        if ($isProdOrStaging) {
            if (str_contains($key, 'PLACEHOLDER') || str_contains($key, 'CHANGE_ME') || str_contains($key, 'YOUR_')) {
                throw new SecurityException("CSRF signing key cannot use known placeholder values in {$env}.");
            }
            $authKey = $this->config ? (string)$this->config->get('AUTH_SIGNING_KEY', '') : '';
            $mfaKey = $this->config ? (string)$this->config->get('MFA_ENCRYPTION_KEY', '') : '';
            if (($authKey !== '' && hash_equals($key, $authKey)) || ($mfaKey !== '' && hash_equals($key, $mfaKey))) {
                throw new SecurityException("CSRF_SIGNING_KEY must be distinct and independent from AUTH_SIGNING_KEY and MFA_ENCRYPTION_KEY.");
            }
        }

        return $key;
    }

    /**
     * Generates a signed unauthenticated CSRF bootstrap token and corresponding cookie payload.
     *
     * @return array{token: string, cookie_value: string, cookie_header: string}
     */
    public function generateBootstrapToken(): array
    {
        $rawToken = Crypto::randomToken(32); // 64-char hex string
        $timestamp = time();
        $signingKey = $this->getSigningKey();

        $payload = "{$rawToken}:{$timestamp}";
        $signature = hash_hmac('sha256', $payload, $signingKey);
        $cookieValue = base64_encode("{$payload}:{$signature}");

        $isProduction = $this->config !== null && (string)$this->config->get('APP_ENV') === 'production';
        $secureFlag = $isProduction ? '; Secure' : '';
        $cookieHeader = "voak_csrf={$cookieValue}; Path=/api/v1/admin; HttpOnly; SameSite=Lax; Max-Age=" . self::BOOTSTRAP_TOKEN_TTL_SECONDS . $secureFlag;

        return [
            'token'         => $rawToken,
            'cookie_value'  => $cookieValue,
            'cookie_header' => $cookieHeader,
        ];
    }

    public function process(Request $request, callable $next): Response
    {
        $method = $request->getMethod();
        $path = $request->getPath();

        // Only enforce for mutating requests under /api/v1/admin/*
        if (!in_array($method, self::MUTATING_METHODS, true) || !str_starts_with($path, '/api/v1/admin')) {
            return $next($request);
        }

        $csrfHeader = $request->getHeader('x-csrf-token');
        if ($csrfHeader === null || trim($csrfHeader) === '') {
            return Response::error(
                "CSRF token missing. Include valid X-CSRF-Token header.",
                'ERR_CSRF_MISSING',
                403
            );
        }
        $csrfHeader = trim($csrfHeader);

        $sessionId = $request->getAttribute('admin_session_id');

        // Case 1: Authenticated session active -> check against admin_sessions database
        if ($sessionId !== null) {
            if ($this->connection === null) {
                return Response::error("Database connection unavailable for session CSRF check.", 'ERR_INTERNAL', 500);
            }

            $stmt = $this->connection->getPdo()->prepare("SELECT `csrf_token_hash` FROM `admin_sessions` WHERE `id` = :id");
            $stmt->execute(['id' => $sessionId]);
            $expectedHash = $stmt->fetchColumn();
            $stmt->closeCursor();

            if ($expectedHash === false || !hash_equals((string)$expectedHash, hash('sha256', $csrfHeader))) {
                return Response::error(
                    "CSRF token validation failed. Token mismatch.",
                    'ERR_CSRF_INVALID',
                    403
                );
            }

            return $next($request);
        }

        // Case 2: Unauthenticated state (Login, MFA challenge, Password Reset bootstrap flow)
        $cookieVal = $request->getCookie('voak_csrf');
        if ($cookieVal === null || trim($cookieVal) === '') {
            return Response::error(
                "CSRF bootstrap cookie missing. Call GET /api/v1/admin/auth/csrf before initiating state-mutating requests.",
                'ERR_CSRF_MISSING',
                403
            );
        }

        $decoded = base64_decode(trim($cookieVal), true);
        if ($decoded === false) {
            return Response::error("Invalid CSRF cookie format.", 'ERR_CSRF_INVALID', 403);
        }

        $parts = explode(':', $decoded);
        if (count($parts) !== 3) {
            return Response::error("Invalid CSRF cookie structure.", 'ERR_CSRF_INVALID', 403);
        }

        [$cookieToken, $timestampStr, $signature] = $parts;
        $timestamp = (int)$timestampStr;
        $now = time();

        // Validate expiry
        if (($now - $timestamp) > self::BOOTSTRAP_TOKEN_TTL_SECONDS || $now < ($timestamp - 60)) {
            return Response::error("CSRF bootstrap token expired.", 'ERR_CSRF_EXPIRED', 403);
        }

        // Validate signature and match against header
        $signingKey = $this->getSigningKey();
        $expectedSig = hash_hmac('sha256', "{$cookieToken}:{$timestampStr}", $signingKey);

        if (!hash_equals($expectedSig, $signature) || !hash_equals($cookieToken, $csrfHeader)) {
            return Response::error("CSRF token validation failed. Token mismatch.", 'ERR_CSRF_INVALID', 403);
        }

        return $next($request);
    }

    public function setConnection(Connection $connection): void
    {
        $this->connection = $connection;
    }

    public function setConfig(Config $config): void
    {
        $this->config = $config;
    }
}
