<?php

declare(strict_types=1);

namespace Voak\Health\Middleware;

use Voak\Health\Core\Http\Request;
use Voak\Health\Core\Http\Response;

/**
 * Standard Middleware Interface for the HTTP processing pipeline.
 */
interface MiddlewareInterface
{
    /**
     * Process an incoming server request and return a response.
     *
     * @param Request $request
     * @param callable $next Callable taking (Request $request): Response
     * @return Response
     */
    public function process(Request $request, callable $next): Response;
}
