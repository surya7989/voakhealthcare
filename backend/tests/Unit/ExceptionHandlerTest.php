<?php

declare(strict_types=1);

namespace Voak\Health\Tests\Unit;

use Voak\Health\Core\Config;
use Voak\Health\Core\Exceptions\GlobalExceptionHandler;
use Voak\Health\Core\Exceptions\NotFoundException;
use Voak\Health\Core\Exceptions\ValidationException;
use Voak\Health\Core\Http\Request;
use Voak\Health\Tests\TestFramework as T;

class ExceptionHandlerTest
{
    public static function run(): void
    {
        T::suite('Exception Normalization & Centralized Error Handling Tests', function () {
            T::test('normalizes HttpException into deterministic Phase 2 error envelope', function () {
                $config = new Config(['APP_DEBUG' => true]);
                $handler = new GlobalExceptionHandler($config);
                $req = new Request('GET', '/api/v1/missing', [], ['X-Correlation-ID' => 'req_corr_test']);

                $exception = new NotFoundException('Requested resource does not exist.');
                $res = $handler->handle($exception, $req);

                T::assertEquals(404, $res->getStatusCode());
                $decoded = json_decode($res->getBody(), true);

                T::assertFalse($decoded['success']);
                T::assertEquals('NOT_FOUND', $decoded['error']['code']);
                T::assertEquals('Requested resource does not exist.', $decoded['error']['message']);
                T::assertEquals('req_corr_test', $decoded['meta']['correlation_id']);
                T::assertArrayHasKey('reference_id', $decoded['error']);
            });

            T::test('normalizes ValidationException carrying field errors', function () {
                $config = new Config(['APP_DEBUG' => false]);
                $handler = new GlobalExceptionHandler($config);
                $req = new Request('POST', '/api/v1/enquiry');

                $fieldErrors = [
                    'contact_phone' => ['Invalid phone number format.'],
                    'email' => ['Email is required.'],
                ];
                $exception = new ValidationException($fieldErrors);
                $res = $handler->handle($exception, $req);

                T::assertEquals(422, $res->getStatusCode());
                $decoded = json_decode($res->getBody(), true);

                T::assertFalse($decoded['success']);
                T::assertEquals('VALIDATION_FAILED', $decoded['error']['code']);
                T::assertEquals($fieldErrors, $decoded['error']['validation_errors']);
            });

            T::test('suppresses internal details and stack traces in production (APP_DEBUG=false)', function () {
                $prodConfig = new Config(['APP_ENV' => 'production', 'APP_DEBUG' => false, 'APP_KEY' => 'secure_production_key_32_characters_min']);
                $handler = new GlobalExceptionHandler($prodConfig);
                $req = new Request('GET', '/api/v1/error');

                $internalError = new \RuntimeException('SQLSTATE[HY000]: Database credentials leaked in sensitive error message');
                $res = $handler->handle($internalError, $req);

                T::assertEquals(500, $res->getStatusCode());
                $decoded = json_decode($res->getBody(), true);

                T::assertFalse($decoded['success']);
                T::assertEquals('INTERNAL_SERVER_ERROR', $decoded['error']['code']);
                // Sensitive internal message must be replaced with generic user-safe message
                T::assertEquals('An internal server error occurred. Please quote the reference code to support.', $decoded['error']['message']);
                // Stack trace and file details must NOT be in error envelope
                T::assertArrayHasKey('reference_id', $decoded['error']);
                T::assertFalse(isset($decoded['error']['debug']));
                T::assertFalse(str_contains($res->getBody(), 'SQLSTATE'));
            });

            T::test('includes debug diagnostics only when APP_DEBUG=true', function () {
                $devConfig = new Config(['APP_ENV' => 'development', 'APP_DEBUG' => true]);
                $handler = new GlobalExceptionHandler($devConfig);
                $req = new Request('GET', '/api/v1/error');

                $internalError = new \RuntimeException('Development test error message');
                $res = $handler->handle($internalError, $req);

                T::assertEquals(500, $res->getStatusCode());
                $decoded = json_decode($res->getBody(), true);

                T::assertArrayHasKey('debug', $decoded['error']);
                T::assertEquals('RuntimeException', $decoded['error']['debug']['class']);
            });
        });
    }
}
