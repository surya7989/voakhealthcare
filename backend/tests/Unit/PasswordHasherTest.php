<?php

declare(strict_types=1);

namespace Voak\Health\Tests\Unit;

use Voak\Health\Security\PasswordHasher;
use Voak\Health\Tests\TestFramework as T;

class PasswordHasherTest
{
    public static function run(): void
    {
        T::suite('Phase 6 — PasswordHasher Security Tests', function () {
            T::test('hashes and verifies valid password successfully', function () {
                $hasher = new PasswordHasher();
                $password = 'VoakHealth#2026!Secure';

                $hash = $hasher->hash($password);
                T::assertTrue(strlen($hash) > 20);
                T::assertTrue($hasher->verify($password, $hash));
                T::assertFalse($hasher->verify('WrongPassword#123!', $hash));
            });

            T::test('enforces password entropy: rejects short passwords (< 12 chars)', function () {
                $hasher = new PasswordHasher();
                $short = 'Pass#123!';
                T::assertFalse($hasher->validateStrength($short));

                $errors = $hasher->getStrengthErrors($short);
                T::assertTrue(count($errors) > 0);
                T::assertContains('12 characters', $errors[0]);
            });

            T::test('enforces password entropy: requires uppercase, lowercase, digit, and symbol', function () {
                $hasher = new PasswordHasher();

                // No uppercase
                T::assertFalse($hasher->validateStrength('lowercase12345!'));
                // No lowercase
                T::assertFalse($hasher->validateStrength('UPPERCASE12345!'));
                // No digit
                T::assertFalse($hasher->validateStrength('NoDigitsHereSecure!'));
                // No symbol
                T::assertFalse($hasher->validateStrength('NoSymbols123456'));

                // All criteria satisfied
                T::assertTrue($hasher->validateStrength('ValidSecureP@ssw0rd!'));
            });

            T::test('detects if hash needs rehash', function () {
                $hasher = new PasswordHasher();
                // A bcrypt hash with cost 4 will need rehash against default cost 12
                $weakBcrypt = password_hash('ValidSecureP@ssw0rd!', PASSWORD_BCRYPT, ['cost' => 4]);
                T::assertTrue($hasher->needsRehash($weakBcrypt));

                // A freshly generated hash with default algorithm should not need rehash
                $fresh = $hasher->hash('ValidSecureP@ssw0rd!');
                T::assertFalse($hasher->needsRehash($fresh));
            });
        });
    }
}
