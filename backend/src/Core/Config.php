<?php

declare(strict_types=1);

namespace Voak\Health\Core;

use Voak\Health\Core\Exceptions\ConfigurationException;

/**
 * Environment configuration manager with production fail-fast rules and secret masking.
 */
class Config
{
    private static ?Config $instance = null;
    private array $data = [];

    private const SENSITIVE_KEYS = [
        'APP_KEY',
        'DB_PASSWORD',
        'SESSION_SECRET',
        'MFA_ENCRYPTION_KEY',
        'JWT_SECRET',
        'SMTP_PASSWORD',
        'API_SECRET',
    ];

    public function __construct(array $initialData = [])
    {
        $this->data = $initialData;
    }

    /**
     * Singleton accessor for global configuration state.
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function setInstance(Config $instance): void
    {
        self::$instance = $instance;
    }

    /**
     * Load environment variables from a .env file and environment.
     *
     * @throws ConfigurationException If required file is corrupt or unreadable
     */
    public static function load(?string $envPath = null): self
    {
        $config = new self();

        // 1. Load from file if provided and exists
        if ($envPath !== null && file_exists($envPath)) {
            $lines = @file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines === false) {
                throw new ConfigurationException("Failed to read environment configuration file at: {$envPath}");
            }

            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }

                $parts = explode('=', $line, 2);
                if (count($parts) === 2) {
                    $key = trim($parts[0]);
                    $val = trim($parts[1]);

                    // Remove outer quotes if present
                    if ((str_starts_with($val, '"') && str_ends_with($val, '"')) ||
                        (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
                        $val = substr($val, 1, -1);
                    }

                    $config->set($key, $val);
                }
            }
        }

        // 2. Overlay system environment variables
        foreach ($_ENV as $k => $v) {
            if (is_string($v)) {
                $config->set($k, $v);
            }
        }
        foreach ($_SERVER as $k => $v) {
            if (is_string($v) && !str_starts_with($k, 'HTTP_')) {
                // Only consider non-HTTP header server vars
                if ($config->has($k) || in_array($k, ['APP_ENV', 'APP_DEBUG', 'APP_KEY', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'], true)) {
                    $config->set($k, $v);
                }
            }
        }

        // 3. Overlay getenv() for CLI environments where $_ENV might be unpopulated
        $wellKnownEnv = [
            'APP_NAME', 'APP_ENV', 'APP_DEBUG', 'APP_KEY', 'APP_URL',
            'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'DB_CHARSET',
            'SESSION_SECRET', 'MFA_ENCRYPTION_KEY', 'JWT_SECRET', 'CORS_ALLOWED_ORIGINS'
        ];
        foreach ($wellKnownEnv as $key) {
            $val = getenv($key);
            if ($val !== false) {
                $config->set($key, $val);
            }
        }

        // Apply defaults for unconfigured optional keys
        $config->applyDefaults();

        // Execute fail-fast validation rules
        $config->validate();

        self::$instance = $config;
        return $config;
    }

    public static function fromGlobals(): self
    {
        return self::load();
    }

    public static function fromEnvFile(string $envPath): self
    {
        return self::load($envPath);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public static function getItem(string $key, mixed $default = null): mixed
    {
        return self::getInstance()->get($key, $default);
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function isProduction(): bool
    {
        return strtolower((string)$this->get('APP_ENV', 'development')) === 'production';
    }

    public function isDebug(): bool
    {
        $debug = $this->get('APP_DEBUG', false);
        if (is_bool($debug)) {
            return $debug;
        }
        return in_array(strtolower((string)$debug), ['true', '1', 'yes', 'on'], true);
    }

    /**
     * Fail-fast security validation (FR-SEC-005).
     *
     * @throws ConfigurationException
     */
    public function validate(): void
    {
        $env = strtolower((string)$this->get('APP_ENV', 'development'));
        $debug = $this->isDebug();
        $appKey = (string)$this->get('APP_KEY', '');

        // Rule 1: In production mode, APP_DEBUG must strictly be FALSE
        if ($env === 'production' && $debug) {
            throw new ConfigurationException(
                "SECURITY VIOLATION: APP_DEBUG cannot be enabled in production environment (FR-SEC-005)."
            );
        }

        // Rule 2: In production, APP_KEY must be a secure key (not empty or default placeholder)
        if ($env === 'production') {
            if ($appKey === '' || strlen($appKey) < 32 || str_contains($appKey, 'PLACEHOLDER') || str_contains($appKey, 'CHANGE_ME')) {
                throw new ConfigurationException(
                    "SECURITY VIOLATION: Secure APP_KEY of at least 32 characters is required in production (FR-SEC-005)."
                );
            }
        }
    }

    private function applyDefaults(): void
    {
        $defaults = [
            'APP_NAME' => 'Voak Health Care API',
            'APP_ENV' => 'development',
            'APP_DEBUG' => true,
            'APP_URL' => 'http://localhost:8080',
            'DB_HOST' => '127.0.0.1',
            'DB_PORT' => 3306,
            'DB_DATABASE' => 'voak_healthcare',
            'DB_USERNAME' => 'root',
            'DB_PASSWORD' => '',
            'DB_CHARSET' => 'utf8mb4',
            'CORS_ALLOWED_ORIGINS' => 'http://localhost:3000,http://localhost:5173',
            'LOG_CHANNEL' => 'daily',
            'STORAGE_PATH' => 'storage',
        ];

        foreach ($defaults as $k => $v) {
            if (!isset($this->data[$k])) {
                $this->data[$k] = $v;
            }
        }
    }

    /**
     * Returns all configuration with sensitive values safely redacted.
     */
    public function allSafe(): array
    {
        $safe = [];
        foreach ($this->data as $k => $v) {
            if ($this->isSensitive($k)) {
                $safe[$k] = '[REDACTED]';
            } else {
                $safe[$k] = $v;
            }
        }
        return $safe;
    }

    private function isSensitive(string $key): bool
    {
        $upper = strtoupper($key);
        foreach (self::SENSITIVE_KEYS as $sensitive) {
            if ($upper === $sensitive || str_contains($upper, 'SECRET') || str_contains($upper, 'PASSWORD')) {
                return true;
            }
        }
        return false;
    }
}
