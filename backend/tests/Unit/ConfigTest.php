<?php

declare(strict_types=1);

namespace Voak\Health\Tests\Unit;

use Voak\Health\Core\Config;
use Voak\Health\Core\Exceptions\ConfigurationException;
use Voak\Health\Tests\TestFramework as T;

class ConfigTest
{
    public static function run(): void
    {
        T::suite('Config & Fail-Fast Environment Tests', function () {
            T::test('loads default configuration when initialized empty', function () {
                $config = new Config();
                T::assertEquals(null, $config->get('NON_EXISTENT_KEY'));
                T::assertEquals('custom_fallback', $config->get('NON_EXISTENT_KEY', 'custom_fallback'));
            });

            T::test('detects production environment and debug state', function () {
                $devConfig = new Config(['APP_ENV' => 'development', 'APP_DEBUG' => true]);
                T::assertFalse($devConfig->isProduction());
                T::assertTrue($devConfig->isDebug());

                $prodConfig = new Config(['APP_ENV' => 'production', 'APP_DEBUG' => false]);
                T::assertTrue($prodConfig->isProduction());
                T::assertFalse($prodConfig->isDebug());
            });

            T::test('allSafe() redacts sensitive credentials and secrets', function () {
                $config = new Config([
                    'APP_NAME' => 'Voak Health Care API',
                    'APP_KEY' => 'secret_app_key_string_value_1234567890',
                    'DB_PASSWORD' => 'super_secret_db_pass',
                    'SESSION_SECRET' => 'session_secret_xyz',
                    'DB_HOST' => '127.0.0.1',
                ]);

                $safe = $config->allSafe();
                T::assertEquals('Voak Health Care API', $safe['APP_NAME']);
                T::assertEquals('127.0.0.1', $safe['DB_HOST']);
                T::assertEquals('[REDACTED]', $safe['APP_KEY']);
                T::assertEquals('[REDACTED]', $safe['DB_PASSWORD']);
                T::assertEquals('[REDACTED]', $safe['SESSION_SECRET']);
            });

            T::test('fails fast if APP_DEBUG is true in production (FR-SEC-005)', function () {
                $config = new Config([
                    'APP_ENV' => 'production',
                    'APP_DEBUG' => true,
                    'APP_KEY' => 'a_very_secure_random_key_of_32_characters_minimum',
                ]);

                T::expectException(
                    ConfigurationException::class,
                    fn () => $config->validate(),
                    'APP_DEBUG cannot be enabled in production'
                );
            });

            T::test('fails fast if APP_KEY is empty or placeholder in production (FR-SEC-005)', function () {
                $config = new Config([
                    'APP_ENV' => 'production',
                    'APP_DEBUG' => false,
                    'APP_KEY' => 'short',
                ]);

                T::expectException(
                    ConfigurationException::class,
                    fn () => $config->validate(),
                    'Secure APP_KEY of at least 32 characters is required'
                );

                $configPlaceholder = new Config([
                    'APP_ENV' => 'production',
                    'APP_DEBUG' => false,
                    'APP_KEY' => 'base64:PLACEHOLDER_DEV_KEY_REPLACE_WITH_SECURE_32_BYTE_SECRET',
                ]);

                T::expectException(
                    ConfigurationException::class,
                    fn () => $configPlaceholder->validate(),
                    'Secure APP_KEY of at least 32 characters is required'
                );
            });

            T::test('passes validation in production when properly configured', function () {
                $config = new Config([
                    'APP_ENV' => 'production',
                    'APP_DEBUG' => false,
                    'APP_KEY' => 'c89316a94ba9461e8f3cabbaf5bd20ad0adb72115e8339be5a39',
                ]);

                $config->validate();
                T::assertTrue($config->isProduction());
                T::assertFalse($config->isDebug());
            });
        });
    }
}
