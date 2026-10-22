<?php

declare(strict_types=1);

namespace Voak\Health\Tests\Unit;

use Voak\Health\Security\Crypto;
use Voak\Health\Tests\TestFramework as T;

class CryptoTest
{
    public static function run(): void
    {
        T::suite('Phase 6 — Crypto & AES-256-GCM Security Tests', function () {
            $masterKey = 'test-master-key-32-chars-long-123456';

            T::test('encrypts and decrypts string payload with AES-256-GCM', function () use ($masterKey) {
                $plaintext = 'Secret-MFA-Seed-Data-For-Admin';

                $encrypted = Crypto::encrypt($plaintext, $masterKey);
                T::assertTrue(strlen($encrypted) > 20);
                T::assertFalse(str_contains($encrypted, $plaintext));

                $decrypted = Crypto::decrypt($encrypted, $masterKey);
                T::assertEquals($plaintext, $decrypted);
            });

            T::test('rejects tampered ciphertext payload returning null or throwing exception', function () use ($masterKey) {
                $encrypted = Crypto::encrypt('Confidential Data', $masterKey);

                // Guaranteed tamper with payload: ensure replacement character differs
                $replacement = ($encrypted[10] === 'X') ? 'Y' : 'X';
                $tampered = substr_replace($encrypted, $replacement, 10, 1);
                $decrypted = Crypto::decrypt($tampered, $masterKey);
                T::assertEquals(null, $decrypted);
            });

            T::test('fails decryption when wrong master key is used', function () use ($masterKey) {
                $encrypted = Crypto::encrypt('Confidential Data', $masterKey);
                $decrypted = Crypto::decrypt($encrypted, 'completely-different-master-key-xyz');
                T::assertEquals(null, $decrypted);
            });

            T::test('performs constant-time hash comparison', function () {
                T::assertTrue(Crypto::hashEquals('secret_hash_value_123', 'secret_hash_value_123'));
                T::assertFalse(Crypto::hashEquals('secret_hash_value_123', 'secret_hash_value_456'));
                T::assertFalse(Crypto::hashEquals('secret_hash_value_123', 'short'));
            });

            T::test('generates secure random hex tokens with specified byte lengths', function () {
                $token16 = Crypto::randomToken(16);
                T::assertEquals(32, strlen($token16));

                $token32 = Crypto::randomToken(32);
                T::assertEquals(64, strlen($token32));

                $tokenAlt = Crypto::randomToken(32);
                T::assertFalse($token32 === $tokenAlt);
            });
        });
    }
}
