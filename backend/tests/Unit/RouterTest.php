<?php

declare(strict_types=1);

namespace Voak\Health\Tests\Unit;

use Voak\Health\Core\Container;
use Voak\Health\Core\Exceptions\MethodNotAllowedException;
use Voak\Health\Core\Exceptions\NotFoundException;
use Voak\Health\Core\Http\Request;
use Voak\Health\Core\Http\Response;
use Voak\Health\Core\Router\Router;
use Voak\Health\Tests\TestFramework as T;

class DummyController
{
    public function show(Request $request): Response
    {
        $id = $request->getAttribute('id');
        return Response::success(['controller_id' => $id]);
    }
}

class RouterTest
{
    public static function run(): void
    {
        T::suite('REST Router & Route Dispatch Tests', function () {
            T::test('matches exact routes and dispatches callable handler', function () {
                $router = new Router();
                $router->get('/api/health', function (Request $req): Response {
                    return (new Response())->withJson(['status' => 'ok'], 200);
                });

                $req = new Request('GET', '/api/health');
                $res = $router->dispatch($req);

                T::assertEquals(200, $res->getStatusCode());
                $data = json_decode($res->getBody(), true);
                T::assertEquals('ok', $data['status']);
            });

            T::test('extracts dynamic route path parameters into request attributes', function () {
                $router = new Router();
                $capturedSlug = null;

                $router->get('/api/v1/public/pages/{slug}', function (Request $req) use (&$capturedSlug): Response {
                    $capturedSlug = $req->getAttribute('slug');
                    return Response::success(['slug' => $capturedSlug]);
                });

                $req = new Request('GET', '/api/v1/public/pages/about-us');
                $res = $router->dispatch($req);

                T::assertEquals('about-us', $capturedSlug);
                T::assertEquals(200, $res->getStatusCode());
            });

            T::test('dispatches [Controller::class, method] with container dependency injection', function () {
                $container = new Container();
                $router = new Router();
                $router->get('/api/dummy/{id}', [DummyController::class, 'show']);

                $req = new Request('GET', '/api/dummy/42');
                $res = $router->dispatch($req, $container);

                T::assertEquals(200, $res->getStatusCode());
                $data = json_decode($res->getBody(), true);
                T::assertEquals('42', $data['data']['controller_id']);
            });

            T::test('throws NotFoundException on unregistered route path', function () {
                $router = new Router();
                $router->get('/api/valid', fn () => new Response());

                $req = new Request('GET', '/api/unregistered-path');
                T::expectException(
                    NotFoundException::class,
                    fn () => $router->dispatch($req),
                    'Endpoint not found'
                );
            });

            T::test('throws MethodNotAllowedException and sets Allow header for wrong method', function () {
                $router = new Router();
                $router->get('/api/resource', fn () => new Response());
                $router->put('/api/resource', fn () => new Response());

                $req = new Request('POST', '/api/resource');

                try {
                    $router->dispatch($req);
                    T::assertTrue(false, 'Should have thrown MethodNotAllowedException');
                } catch (MethodNotAllowedException $e) {
                    T::assertEquals(405, $e->getStatusCode());
                    $headers = $e->getHeaders();
                    T::assertArrayHasKey('Allow', $headers);
                    T::assertContains('GET', $headers['Allow']);
                    T::assertContains('PUT', $headers['Allow']);
                }
            });
        });
    }
}
