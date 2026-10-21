<?php

declare(strict_types=1);

namespace Voak\Health\Security;

/**
 * File-Locking Persistent Rate Limiter.
 * Uses atomic OS-level advisory locks (flock) across worker processes to ensure
 * race-free request throttling and shared persistence across multiple PHP workers.
 */
class RateLimiter
{
    private string $storageDir;

    public function __construct(?string $storageDir = null)
    {
        $this->storageDir = $storageDir ?? (sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'voak_rate_limits');
        if (!is_dir($this->storageDir)) {
            @mkdir($this->storageDir, 0700, true);
        }
    }

    /**
     * Attempts an atomic hit against the key under exclusive advisory lock.
     *
     * @return array{allowed: bool, attempts: int, retry_after: int}
     */
    public function hit(string $key, int $maxAttempts = 10, int $decaySeconds = 60): array
    {
        if (!is_dir($this->storageDir) || !is_writable($this->storageDir)) {
            // Fail closed: deny request if storage cannot be securely maintained
            return [
                'allowed'     => false,
                'attempts'    => $maxAttempts,
                'retry_after' => $decaySeconds,
            ];
        }

        $safeKey = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key);
        $filepath = $this->storageDir . DIRECTORY_SEPARATOR . 'rl_' . $safeKey . '.lock';

        $fp = @fopen($filepath, 'c+');
        if ($fp === false) {
            // Fail closed if lockfile cannot be created or opened
            return [
                'allowed'     => false,
                'attempts'    => $maxAttempts,
                'retry_after' => $decaySeconds,
            ];
        }

        // Acquire exclusive advisory lock (blocks concurrent worker processes)
        if (!@flock($fp, LOCK_EX)) {
            @fclose($fp);
            return [
                'allowed'     => false,
                'attempts'    => $maxAttempts,
                'retry_after' => $decaySeconds,
            ];
        }

        try {
            $now = time();
            $contents = '';
            rewind($fp);
            while (!feof($fp)) {
                $chunk = @fread($fp, 8192);
                if ($chunk === false) {
                    break;
                }
                $contents .= $chunk;
            }

            $timestamps = [];
            if ($contents !== '') {
                $decoded = json_decode($contents, true);
                if (is_array($decoded)) {
                    $timestamps = $decoded;
                }
            }

            // Filter out timestamps older than the decay window
            $timestamps = array_values(array_filter(
                $timestamps,
                static fn(mixed $ts): bool => is_int($ts) && ($now - $ts) < $decaySeconds
            ));

            if (count($timestamps) >= $maxAttempts) {
                $oldestInWindow = min($timestamps);
                $retryAfter = max(1, $decaySeconds - ($now - $oldestInWindow));

                // Rewrite pruned timestamps
                @ftruncate($fp, 0);
                @rewind($fp);
                @fwrite($fp, json_encode($timestamps));
                @fflush($fp);

                return [
                    'allowed'     => false,
                    'attempts'    => count($timestamps),
                    'retry_after' => $retryAfter,
                ];
            }

            // Record this hit
            $timestamps[] = $now;
            if (@ftruncate($fp, 0) === false || @rewind($fp) === false || @fwrite($fp, json_encode($timestamps)) === false || @fflush($fp) === false) {
                return [
                    'allowed'     => false,
                    'attempts'    => $maxAttempts,
                    'retry_after' => $decaySeconds,
                ];
            }

            return [
                'allowed'     => true,
                'attempts'    => count($timestamps),
                'retry_after' => 0,
            ];
        } finally {
            @flock($fp, LOCK_UN);
            @fclose($fp);
        }
    }

    /**
     * Resets limits for a key or all keys (useful for test isolation).
     */
    public function reset(?string $key = null): void
    {
        if ($key !== null) {
            $safeKey = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key);
            $filepath = $this->storageDir . DIRECTORY_SEPARATOR . 'rl_' . $safeKey . '.lock';
            if (file_exists($filepath)) {
                @unlink($filepath);
            }
            return;
        }

        if (is_dir($this->storageDir)) {
            $files = glob($this->storageDir . DIRECTORY_SEPARATOR . 'rl_*.lock');
            if (is_array($files)) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        @unlink($file);
                    }
                }
            }
        }
    }
}
