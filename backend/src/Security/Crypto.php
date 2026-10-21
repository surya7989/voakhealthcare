<?php

declare(strict_types=1);

namespace Voak\Health\Security;

use Voak\Health\Core\Exceptions\SecurityException;

/**
 * Enterprise Cryptographic Helper Engine.
 * Implements AES-256-GCM authenticated symmetric encryption with PBKDF2/SHA-256 key derivation.
 */
class Crypto
{
    private const CIPHER = 'aes-256-gcm';
    private const IV_LENGTH = 12;
    private const TAG_LENGTH = 16;
    private const PBKDF2_ITERATIONS = 10000;

    /**
     * Encrypts plaintext using AES-256-GCM authenticated encryption.
     * Returns a base64 string formatted as: base64(iv . tag . ciphertext).
     */
    public static function encrypt(string $plaintext, string $masterKey): string
    {
        if ($plaintext === '') {
            return '';
        }

        $key = self::deriveKey($masterKey, 'voak-mfa-gcm-salt');
        $iv = random_bytes(self::IV_LENGTH);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH
        );

        if ($ciphertext === false || strlen($tag) !== self::TAG_LENGTH) {
            throw new SecurityException("Cryptographic encryption failure.");
        }

        return base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * Decrypts an AES-256-GCM authenticated payload.
     * Returns null if tag validation fails or payload is corrupt.
     */
    public static function decrypt(string $payload, string $masterKey): ?string
    {
        if ($payload === '') {
            return '';
        }

        $raw = base64_decode($payload, true);
        if ($raw === false || strlen($raw) < (self::IV_LENGTH + self::TAG_LENGTH)) {
            return null;
        }

        $iv = substr($raw, 0, self::IV_LENGTH);
        $tag = substr($raw, self::IV_LENGTH, self::TAG_LENGTH);
        $ciphertext = substr($raw, self::IV_LENGTH + self::TAG_LENGTH);

        $key = self::deriveKey($masterKey, 'voak-mfa-gcm-salt');

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        return $plaintext !== false ? $plaintext : null;
    }

    /**
     * Derives a 32-byte cryptographic key using PBKDF2 with SHA-256.
     */
    public static function deriveKey(string $secret, string $salt): string
    {
        return hash_pbkdf2('sha256', $secret, $salt, self::PBKDF2_ITERATIONS, 32, true);
    }

    /**
     * Constant-time string comparison preventing side-channel timing attacks.
     */
    public static function hashEquals(string $known, string $user): bool
    {
        return hash_equals($known, $user);
    }

    /**
     * Generates a cryptographically secure random token in hexadecimal format.
     */
    public static function randomToken(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }
}
