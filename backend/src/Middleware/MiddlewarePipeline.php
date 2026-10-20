<?php

declare(strict_types=1);

namespace Voak\Health\Middleware;

use Voak\Health\Core\Http\Request;
use Voak\Health\Core\Http\Response;

/**
 * Executes a sequence of middleware layers around a core request dispatcher.
 */
class MiddlewarePipeline
{
    /** @var MiddlewareInterface[] */
    private array $middlewares = [];

    public function pipe(MiddlewareInterface $middleware): self
    {
        $this->middlewares[] = $middleware;
        return $this;
    }

    /**
     * Executes the middleware pipeline with the given terminal handler.
     */
    public function handle(Request $request, callable $coreHandler): Response
    {
        $pipeline = array_reverse($this->middlewares);

        $next = $coreHandler;
        foreach ($pipeline as $middleware) {
            $currentNext = $next;
            $next = function (Request $req) use ($middleware, $currentNext): Response {
                return $middleware->process($req, $currentNext);
            };
        }

        return $next($request);
    }
}
