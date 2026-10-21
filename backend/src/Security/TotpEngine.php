<?php

declare(strict_types=1);

namespace Voak\Health\Security;

/**
 * RFC 6238 Time-Based One-Time Password (TOTP) Engine.
 * Implements HMAC-SHA1 algorithm with 30-second step and clock drift tolerance.
 * Zero-dependency pure PHP Base32 encoding and decoding.
 */
class TotpEngine
{
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const TIME_STEP = 30;
    private const CODE_LENGTH = 6;

    /**
     * Generates a cryptographically random Base32 TOTP secret key.
     */
    public function generateSecret(int $byteLength = 20): string
    {
        $bytes = random_bytes($byteLength);
        return $this->base32Encode($bytes);
    }

    /**
     * Computes the 6-digit TOTP code for a secret at a specific Unix timestamp.
     */
    public function generateCode(string $secret, ?int $timestamp = null): string
    {
        $time = $timestamp ?? time();
        $timeCounter = (int)floor($time / self::TIME_STEP);

        // Pack into 8-byte 64-bit big-endian binary string
        $binaryTime = pack('N*', 0, $timeCounter);

        $binarySecret = $this->base32Decode($secret);
        $hash = hash_hmac('sha1', $binaryTime, $binarySecret, true);

        // Dynamic truncation (RFC 4226 / RFC 6238)
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $binaryCode = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        );

        $mod = 10 ** self::CODE_LENGTH;
        $otp = $binaryCode % $mod;

        return str_pad((string)$otp, self::CODE_LENGTH, '0', STR_PAD_LEFT);
    }

    /**
     * Verifies a 6-digit TOTP code against the secret, allowing +/- 1 window drift (RFC 6238).
     */
    public function verify(string $secret, string $code, int $discrepancy = 1, ?int $timestamp = null): bool
    {
        $code = trim($code);
        if (strlen($code) !== self::CODE_LENGTH || !ctype_digit($code)) {
            return false;
        }

        $currentTime = $timestamp ?? time();

        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $checkTime = $currentTime + ($i * self::TIME_STEP);
            $calculatedCode = $this->generateCode($secret, $checkTime);
            if (hash_equals($calculatedCode, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generates an otpauth:// URL for QR code generation in authenticator apps.
     */
    public function getProvisioningUri(string $secret, string $accountName, string $issuer = 'Voak Health Care'): string
    {
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=%d&period=%d',
            rawurlencode($issuer),
            rawurlencode($accountName),
            $secret,
            rawurlencode($issuer),
            self::CODE_LENGTH,
            self::TIME_STEP
        );
    }

    /**
     * Encodes binary data to standard Base32 (RFC 4648).
     */
    public function base32Encode(string $data): string
    {
        if ($data === '') {
            return '';
        }

        $binary = '';
        $len = strlen($data);
        for ($i = 0; $i < $len; $i++) {
            $binary .= str_pad(decbin(ord($data[$i])), 8, '0', STR_PAD_LEFT);
        }

        $output = '';
        $chunks = str_split($binary, 5);
        foreach ($chunks as $chunk) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $output .= self::BASE32_ALPHABET[bindec($chunk)];
        }

        return $output;
    }

    /**
     * Decodes standard Base32 data to binary (RFC 4648).
     */
    public function base32Decode(string $base32): string
    {
        $base32 = strtoupper(trim(str_replace(['=', ' '], '', $base32)));
        if ($base32 === '') {
            return '';
        }

        $binary = '';
        $len = strlen($base32);
        for ($i = 0; $i < $len; $i++) {
            $char = $base32[$i];
            $pos = strpos(self::BASE32_ALPHABET, $char);
            if ($pos === false) {
                continue; // Ignore invalid chars
            }
            $binary .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }

        $output = '';
        $chunks = str_split($binary, 8);
        foreach ($chunks as $chunk) {
            if (strlen($chunk) === 8) {
                $output .= chr(bindec($chunk));
            }
        }

        return $output;
    }
}
