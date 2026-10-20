<?php

declare(strict_types=1);

namespace Voak\Health\Tests\Integration;

use Voak\Health\Core\App;
use Voak\Health\Core\Config;
use Voak\Health\Core\Http\Request;
use Voak\Health\Tests\TestFramework as T;

class AppKernelTest
{
    public static function run(): void
    {
        T::suite('Application Kernel Integration Tests', function () {
            T::test('canonical roadmap gate: GET /api/health returns {"status": "ok"}', function () {
                $config = new Config(['APP_DEBUG' => true]);
                $app = new App($config);

                $req = new Request('GET', '/api/health');
                $res = $app->handle($req);

                T::assertEquals(200, $res->getStatusCode());
                T::assertContains('application/json', $res->getHeader('Content-Type'));

                // Verify exact canonical body
                $decoded = json_decode($res->getBody(), true);
                T::assertEquals('ok', $decoded['status']);

                // Verify security headers and correlation ID
                T::assertEquals('nosniff', $res->getHeader('X-Content-Type-Options'));
                T::assertEquals('DENY', $res->getHeader('X-Frame-Options'));
                T::assertTrue($res->getHeader('X-Correlation-ID') !== null);

                // Verify zero PHP version leak in response
                T::assertFalse(str_contains(strtolower($res->getBody()), 'php'));
                T::assertFalse(str_contains(strtolower($res->getBody()), '8.3'));
                T::assertEquals(null, $res->getHeader('X-Powered-By'));
            });

            T::test('canonical Module 01 endpoint: GET /api/v1/health returns Phase 2 envelope', function () {
                $config = new Config(['APP_NAME' => 'Voak Health Care API', 'APP_ENV' => 'testing', 'APP_DEBUG' => true]);
                $app = new App($config);

                $req = new Request('GET', '/api/v1/health');
                $res = $app->handle($req);

                T::assertEquals(200, $res->getStatusCode());
                $decoded = json_decode($res->getBody(), true);

                T::assertTrue($decoded['success']);
                T::assertEquals('healthy', $decoded['data']['status']);
                T::assertEquals('Voak Health Care API', $decoded['data']['service']);
                T::assertEquals('testing', $decoded['data']['environment']);
                T::assertArrayHasKey('correlation_id', $decoded['meta']);
                T::assertArrayHasKey('timestamp', $decoded['meta']);
            });

            T::test('OPEN-03 modular scaffolding endpoint: GET /api/v1/public/partners-preview', function () {
                $config = new Config();
                $app = new App($config);

                $req = new Request('GET', '/api/v1/public/partners-preview');
                $res = $app->handle($req);

                T::assertEquals(200, $res->getStatusCode());
                $decoded = json_decode($res->getBody(), true);

                T::assertTrue($decoded['success']);
                T::assertEquals('PAGE-PUB-10', $decoded['data']['page_id']);
                T::assertContains('OPEN-03', $decoded['data']['governance_status']);
            });

            T::test('unregistered route returns HTTP 404 with normalized error envelope', function () {
                $config = new Config(['APP_DEBUG' => false]);
                $app = new App($config);

                $req = new Request('GET', '/api/v1/unregistered/route/path');
                $res = $app->handle($req);

                T::assertEquals(404, $res->getStatusCode());
                $decoded = json_decode($res->getBody(), true);

                T::assertFalse($decoded['success']);
                T::assertEquals('NOT_FOUND', $decoded['error']['code']);
                T::assertArrayHasKey('reference_id', $decoded['error']);
                T::assertArrayHasKey('correlation_id', $decoded['meta']);
            });

            T::test('unsupported method returns HTTP 405 with Allow header', function () {
                $config = new Config();
                $app = new App($config);

                $req = new Request('POST', '/api/health');
                $res = $app->handle($req);

                T::assertEquals(405, $res->getStatusCode());
                $decoded = json_decode($res->getBody(), true);

                T::assertFalse($decoded['success']);
                T::assertEquals('METHOD_NOT_ALLOWED', $decoded['error']['code']);
                T::assertArrayHasKey('Allow', $res->getHeaders());
                T::assertContains('GET', $res->getHeader('Allow'));
            });

            T::test('malformed JSON on POST mutation returns HTTP 400 Bad Request', function () {
                $config = new Config();
                $app = new App($config);

                $req = new Request(
                    'POST',
                    '/api/health',
                    [],
                    ['Content-Type' => 'application/json'],
                    '{broken_json_without_closing_bracket'
                );
                $res = $app->handle($req);

                T::assertEquals(400, $res->getStatusCode());
                $decoded = json_decode($res->getBody(), true);

                T::assertFalse($decoded['success']);
                T::assertEquals('BAD_REQUEST', $decoded['error']['code']);
            });
        });
    }
}
