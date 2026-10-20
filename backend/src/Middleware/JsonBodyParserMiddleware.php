<?php

declare(strict_types=1);

namespace Voak\Health\Middleware;

use Voak\Health\Core\Http\Request;
use Voak\Health\Core\Http\Response;

/**
 * Validates and safely parses JSON request bodies for state-mutating requests.
 */
class JsonBodyParserMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        $method = $request->getMethod();
        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $contentType = $request->getHeader('content-type', '');
            if (str_contains(strtolower($contentType), 'application/json')) {
                // Trigger JSON parsing validation (will throw BadRequestException if malformed)
                $request->getJson();
            }
        }

        return $next($request);
    }
}
