<?php

declare(strict_types=1);

namespace Voak\Health\Security;

use Voak\Health\Core\Exceptions\ValidationException;

/**
 * Enterprise Password Hashing and Entropy Verification Engine.
 * Supports PASSWORD_ARGON2ID with deterministic fallback to PASSWORD_BCRYPT (cost 12).
 * Enforces strict password complexity (minimum 12 chars, upper, lower, digit, symbol).
 */
class PasswordHasher
{
    private const MIN_LENGTH = 12;
    private const BCRYPT_COST = 12;

    /**
     * Hashes a plaintext password using the strongest available algorithm.
     */
    public function hash(string $password): string
    {
        $this->enforceEntropy($password);

        if (defined('PASSWORD_ARGON2ID')) {
            try {
                $options = [
                    'memory_cost' => 65536,
                    'time_cost'   => 4,
                    'threads'     => 1,
                ];
                $hash = password_hash($password, PASSWORD_ARGON2ID, $options);
                if ($hash !== false) {
                    return $hash;
                }
            } catch (\ValueError $e) {
                // If system memory allocation fails for Argon2id, fall back to Bcrypt
            }
        }

        // Fallback to Bcrypt cost 12
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => self::BCRYPT_COST]);
        if ($hash === false) {
            throw new \RuntimeException("Password hashing failed in runtime environment.");
        }

        return $hash;
    }

    /**
     * Verifies a password against a hash using timing-safe comparison.
     */
    public function verify(string $password, string $hash): bool
    {
        if ($password === '' || $hash === '') {
            return false;
        }

        return password_verify($password, $hash);
    }

    /**
     * Determines if a hash requires upgrading to stronger algorithm or parameters.
     */
    public function needsRehash(string $hash): bool
    {
        if (defined('PASSWORD_ARGON2ID')) {
            $options = [
                'memory_cost' => 65536,
                'time_cost'   => 4,
                'threads'     => 1,
            ];
            return password_needs_rehash($hash, PASSWORD_ARGON2ID, $options);
        }

        return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => self::BCRYPT_COST]);
    }

    /**
     * Validates password entropy against enterprise security standards.
     * Minimum 12 characters, requiring uppercase, lowercase, digit, and special symbol.
     */
    public function validateEntropy(string $password): bool
    {
        if (strlen($password) < self::MIN_LENGTH) {
            return false;
        }

        if (!preg_match('/[A-Z]/', $password)) {
            return false;
        }

        if (!preg_match('/[a-z]/', $password)) {
            return false;
        }

        if (!preg_match('/[0-9]/', $password)) {
            return false;
        }

        if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\\\|,.<>\/?~`]/', $password)) {
            return false;
        }

        return true;
    }

    /**
     * Enforces entropy validation, throwing a ValidationException on failure.
     */
    public function enforceEntropy(string $password): void
    {
        if (!$this->validateEntropy($password)) {
            $errors = $this->getStrengthErrors($password);
            throw new ValidationException([
                'password' => $errors,
            ], "Password does not meet enterprise security requirements (min 12 characters with uppercase, lowercase, numbers, and symbols).");
        }
    }

    /**
     * Alias for validateEntropy() for semantic flexibility.
     */
    public function validateStrength(string $password): bool
    {
        return $this->validateEntropy($password);
    }

    /**
     * Returns detailed list of complexity rule violations for a password.
     *
     * @return array<string>
     */
    public function getStrengthErrors(string $password): array
    {
        $errors = [];
        if (strlen($password) < self::MIN_LENGTH) {
            $errors[] = 'Password must be at least 12 characters long.';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must include at least one uppercase letter.';
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must include at least one lowercase letter.';
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must include at least one numeric digit.';
        }
        if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\\\|,.<>\/?~`]/', $password)) {
            $errors[] = 'Password must include at least one special symbol.';
        }
        return $errors;
    }

    /**
     * Inspects algorithm identifier for the given hash.
     */
    public function getAlgorithmName(string $hash): string
    {
        $info = password_get_info($hash);
        return (string)($info['algoName'] ?? 'unknown');
    }
}
