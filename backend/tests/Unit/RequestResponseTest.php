<?php

declare(strict_types=1);

namespace Voak\Health\Tests\Unit;

use Voak\Health\Core\Exceptions\BadRequestException;
use Voak\Health\Core\Http\Request;
use Voak\Health\Core\Http\Response;
use Voak\Health\Tests\TestFramework as T;

class RequestResponseTest
{
    public static function run(): void
    {
        T::suite('HTTP Request & Response Abstraction Tests', function () {
            T::test('normalizes method, path, and case-insensitive headers in Request', function () {
                $req = new Request(
                    'post',
                    '/api/v1/public/services/',
                    ['filter' => 'clinical'],
                    ['Content-Type' => 'application/json', 'X-CUSTOM-HEADER' => 'voak-val'],
                    '{"test":123}'
                );

                T::assertEquals('POST', $req->getMethod());
                T::assertEquals('/api/v1/public/services', $req->getPath());
                T::assertEquals('clinical', $req->getQueryParam('filter'));
                T::assertEquals('application/json', $req->getHeader('content-type'));
                T::assertEquals('application/json', $req->getHeader('CONTENT-TYPE'));
                T::assertEquals('voak-val', $req->getHeader('x-custom-header'));
                T::assertTrue($req->hasHeader('content-type'));
                T::assertFalse($req->hasHeader('non-existent'));
            });

            T::test('parses valid JSON request body safely', function () {
                $req = new Request(
                    'POST',
                    '/api/test',
                    [],
                    ['Content-Type' => 'application/json'],
                    '{"facility": "St. Vincent", "urgency": "high", "positions": 5}'
                );

                $json = $req->getJson();
                T::assertEquals('St. Vincent', $json['facility']);
                T::assertEquals('high', $json['urgency']);
                T::assertEquals(5, $json['positions']);
            });

            T::test('throws BadRequestException on malformed JSON payload', function () {
                $req = new Request(
                    'POST',
                    '/api/test',
                    [],
                    ['Content-Type' => 'application/json'],
                    '{"facility": "St. Vincent", "incomplete": '
                );

                T::expectException(
                    BadRequestException::class,
                    fn () => $req->getJson(),
                    'Malformed JSON'
                );
            });

            T::test('generates and echoes correlation ID', function () {
                $reqAuto = new Request('GET', '/api/test');
                T::assertTrue(str_starts_with($reqAuto->getCorrelationId(), 'req_'));

                $reqExplicit = new Request(
                    'GET',
                    '/api/test',
                    [],
                    ['X-Correlation-ID' => 'req_custom_trace_98765']
                );
                T::assertEquals('req_custom_trace_98765', $reqExplicit->getCorrelationId());
            });

            T::test('constructs Phase 2 standard success envelope', function () {
                $data = ['name' => 'Clinical Support', 'id' => 'SRV-01'];
                $res = Response::success($data, 200, 'req_test_123');

                T::assertEquals(200, $res->getStatusCode());
                T::assertContains('application/json', $res->getHeader('Content-Type'));

                $decoded = json_decode($res->getBody(), true);
                T::assertTrue($decoded['success']);
                T::assertEquals('SRV-01', $decoded['data']['id']);
                T::assertEquals('req_test_123', $decoded['meta']['correlation_id']);
                T::assertArrayHasKey('timestamp', $decoded['meta']);
            });

            T::test('constructs Phase 2 standard error envelope', function () {
                $res = Response::error(
                    'Resource not found.',
                    'NOT_FOUND',
                    404,
                    'REF-ERR-TEST404',
                    ['slug' => 'unknown-service'],
                    'req_err_trace'
                );

                T::assertEquals(404, $res->getStatusCode());
                $decoded = json_decode($res->getBody(), true);

                T::assertFalse($decoded['success']);
                T::assertEquals('NOT_FOUND', $decoded['error']['code']);
                T::assertEquals('Resource not found.', $decoded['error']['message']);
                T::assertEquals('REF-ERR-TEST404', $decoded['error']['reference_id']);
                T::assertEquals('unknown-service', $decoded['error']['slug']);
                T::assertEquals('req_err_trace', $decoded['meta']['correlation_id']);
            });
        });
    }
}
