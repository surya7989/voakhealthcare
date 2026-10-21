<?php

declare(strict_types=1);

namespace Voak\Health\Tests\Unit;

use Voak\Health\Security\TotpEngine;
use Voak\Health\Tests\TestFramework as T;

class TotpEngineTest
{
    public static function run(): void
    {
        T::suite('Phase 6 — TotpEngine RFC 6238 Tests', function () {
            T::test('generates valid Base32 secret', function () {
                $engine = new TotpEngine();
                $secret = $engine->generateSecret(16);

                T::assertTrue(strlen($secret) >= 16);
                // Base32 characters: A-Z and 2-7
                T::assertTrue((bool)preg_match('/^[A-Z2-7]+$/', $secret));
            });

            T::test('generates 6-digit TOTP code matching timestamp', function () {
                $engine = new TotpEngine();
                $secret = 'JBSWY3DPEHPK3PXP'; // Standard Base32 test secret
                $timestamp = 1111111109; // Fixed timestamp

                $code = $engine->generateCode($secret, $timestamp);
                T::assertEquals(6, strlen($code));
                T::assertTrue((bool)preg_match('/^[0-9]{6}$/', $code));
            });

            T::test('verifies code within clock drift window (window=1)', function () {
                $engine = new TotpEngine();
                $secret = 'JBSWY3DPEHPK3PXP';
                $now = time();

                // Code for current time
                $currentCode = $engine->generateCode($secret, $now);
                T::assertTrue($engine->verify($secret, $currentCode, 1, $now));

                // Code for previous window (30 seconds ago)
                $prevCode = $engine->generateCode($secret, $now - 30);
                T::assertTrue($engine->verify($secret, $prevCode, 1, $now));

                // Code for next window (30 seconds in future)
                $nextCode = $engine->generateCode($secret, $now + 30);
                T::assertTrue($engine->verify($secret, $nextCode, 1, $now));

                // Code outside drift window (90 seconds in past)
                $oldCode = $engine->generateCode($secret, $now - 90);
                T::assertFalse($engine->verify($secret, $oldCode, 1, $now));
            });

            T::test('rejects incorrect codes and malformed inputs', function () {
                $engine = new TotpEngine();
                $secret = 'JBSWY3DPEHPK3PXP';

                T::assertFalse($engine->verify($secret, '000000'));
                T::assertFalse($engine->verify($secret, '12345')); // 5 digits
                T::assertFalse($engine->verify($secret, 'abcdef')); // Non-numeric
            });

            T::test('generates standard otpauth provisioning URI', function () {
                $engine = new TotpEngine();
                $secret = 'JBSWY3DPEHPK3PXP';
                $uri = $engine->getProvisioningUri($secret, 'admin@voakhealthcare.com', 'Voak Health Care');

                T::assertContains('otpauth://totp/', $uri);
                T::assertContains('secret=JBSWY3DPEHPK3PXP', $uri);
                T::assertContains('issuer=Voak%20Health%20Care', $uri);
            });
        });
    }
}
