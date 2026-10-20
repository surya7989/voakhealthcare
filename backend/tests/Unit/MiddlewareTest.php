<?php

declare(strict_types=1);

namespace Voak\Health\Tests\Unit;

use Voak\Health\Core\Exceptions\BadRequestException;
use Voak\Health\Core\Http\Request;
use Voak\Health\Core\Http\Response;
use Voak\Health\Middleware\CorsMiddleware;
use Voak\Health\Middleware\JsonBodyParserMiddleware;
use Voak\Health\Middleware\MiddlewareInterface;
use Voak\Health\Middleware\MiddlewarePipeline;
use Voak\Health\Middleware\SecurityHeadersMiddleware;
use Voak\Health\Tests\TestFramework as T;

class StepOneMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        $request = $request->withAttribute('trace', array_merge($request->getAttribute('trace', []), ['step1_before']));
        $response = $next($request);
        return $response->withHeader('X-Trace-Step1', 'done');
    }
}

class StepTwoMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        $request = $request->withAttribute('trace', array_merge($request->getAttribute('trace', []), ['step2_before']));
        $response = $next($request);
        return $response->withHeader('X-Trace-Step2', 'done');
    }
}

class ShortCircuitMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        if ($request->getHeader('x-block-me') === 'yes') {
            return Response::error('Blocked by middleware', 'FORBIDDEN', 403);
        }
        return $next($request);
    }
}

class MiddlewareTest
{
    public static function run(): void
    {
        T::suite('Middleware Pipeline & Security Controls Tests', function () {
            T::test('executes middleware pipeline in predictable order', function () {
                $pipeline = new MiddlewarePipeline();
                $pipeline->pipe(new StepOneMiddleware());
                $pipeline->pipe(new StepTwoMiddleware());

                $capturedTrace = [];
                $req = new Request('GET', '/test');

                $res = $pipeline->handle($req, function (Request $r) use (&$capturedTrace): Response {
                    $capturedTrace = $r->getAttribute('trace');
                    return new Response(200);
                });

                T::assertEquals(['step1_before', 'step2_before'], $capturedTrace);
                T::assertEquals('done', $res->getHeader('X-Trace-Step1'));
                T::assertEquals('done', $res->getHeader('X-Trace-Step2'));
            });

            T::test('supports early short-circuit response', function () {
                $pipeline = new MiddlewarePipeline();
                $pipeline->pipe(new ShortCircuitMiddleware());

                $req = new Request('GET', '/test', [], ['X-Block-Me' => 'yes']);
                $handlerCalled = false;

                $res = $pipeline->handle($req, function () use (&$handlerCalled): Response {
                    $handlerCalled = true;
                    return new Response(200);
                });

                T::assertFalse($handlerCalled);
                T::assertEquals(403, $res->getStatusCode());
            });

            T::test('SecurityHeadersMiddleware attaches enterprise security headers and correlation ID', function () {
                $middleware = new SecurityHeadersMiddleware();
                $req = new Request('GET', '/api/test', [], ['X-Correlation-ID' => 'req_test_sec_header']);

                $res = $middleware->process($req, fn () => new Response(200));

                T::assertEquals('nosniff', $res->getHeader('X-Content-Type-Options'));
                T::assertEquals('DENY', $res->getHeader('X-Frame-Options'));
                T::assertEquals('1; mode=block', $res->getHeader('X-XSS-Protection'));
                T::assertEquals('strict-origin-when-cross-origin', $res->getHeader('Referrer-Policy'));
                T::assertEquals('req_test_sec_header', $res->getHeader('X-Correlation-ID'));
                T::assertContains("default-src 'none'", $res->getHeader('Content-Security-Policy'));
            });

            T::test('CorsMiddleware applies strict CORS headers for allowed origins', function () {
                $middleware = new CorsMiddleware(['http://localhost:3000', 'https://voakhealthcare.com']);

                $reqAllowed = new Request('GET', '/api/test', [], ['Origin' => 'http://localhost:3000']);
                $resAllowed = $middleware->process($reqAllowed, fn () => new Response(200));

                T::assertEquals('http://localhost:3000', $resAllowed->getHeader('Access-Control-Allow-Origin'));
                T::assertEquals('true', $resAllowed->getHeader('Access-Control-Allow-Credentials'));

                $reqDenied = new Request('GET', '/api/test', [], ['Origin' => 'http://malicious-site.example']);
                $resDenied = $middleware->process($reqDenied, fn () => new Response(200));

                T::assertEquals(null, $resDenied->getHeader('Access-Control-Allow-Origin'));
            });

            T::test('CorsMiddleware intercepts OPTIONS preflight requests', function () {
                $middleware = new CorsMiddleware(['http://localhost:3000']);
                $req = new Request('OPTIONS', '/api/test', [], ['Origin' => 'http://localhost:3000']);

                $handlerCalled = false;
                $res = $middleware->process($req, function () use (&$handlerCalled): Response {
                    $handlerCalled = true;
                    return new Response(200);
                });

                T::assertFalse($handlerCalled);
                T::assertEquals(204, $res->getStatusCode());
                T::assertEquals('http://localhost:3000', $res->getHeader('Access-Control-Allow-Origin'));
                T::assertContains('GET', $res->getHeader('Access-Control-Allow-Methods'));
            });

            T::test('JsonBodyParserMiddleware rejects malformed JSON on mutation methods', function () {
                $middleware = new JsonBodyParserMiddleware();
                $req = new Request(
                    'POST',
                    '/api/enquiries',
                    [],
                    ['Content-Type' => 'application/json'],
                    '{not_valid_json: 123'
                );

                T::expectException(
                    BadRequestException::class,
                    fn () => $middleware->process($req, fn () => new Response(200)),
                    'Malformed JSON'
                );
            });
        });
    }
}
