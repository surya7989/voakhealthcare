<?php

declare(strict_types=1);

namespace Voak\Health\Core\Http;

/**
 * PSR-15 inspired RequestHandlerInterface for handling HTTP requests.
 */
interface RequestHandlerInterface
{
    public function handle(Request $request): Response;
}
