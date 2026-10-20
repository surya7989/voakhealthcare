<?php

declare(strict_types=1);

namespace Voak\Health\Core\Router;

use Voak\Health\Core\Http\Request;

/**
 * Route entity representing an HTTP method, path pattern, and handler.
 */
class Route
{
    private string $method;
    private string $pattern;
    /** @var callable|array */
    private $handler;
    private array $middleware = [];
    private string $regex;
    private array $parameterNames = [];

    public function __construct(string $method, string $pattern, callable|array $handler, array $middleware = [])
    {
        $this->method = strtoupper($method);
        $this->pattern = '/' . trim($pattern, '/');
        if ($this->pattern === '') {
            $this->pattern = '/';
        }
        $this->handler = $handler;
        $this->middleware = $middleware;
        $this->compileRegex();
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPattern(): string
    {
        return $this->pattern;
    }

    public function getHandler(): callable|array
    {
        return $this->handler;
    }

    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * Checks if the route pattern matches the given request path.
     */
    public function matchesPath(string $path): bool
    {
        $path = '/' . trim($path, '/');
        if ($path === '') {
            $path = '/';
        }
        return (bool)preg_match($this->regex, $path);
    }

    /**
     * Checks if both method and path match.
     */
    public function matches(Request $request): bool
    {
        if ($this->method !== $request->getMethod()) {
            return false;
        }
        return $this->matchesPath($request->getPath());
    }

    /**
     * Extracts named route parameters from the request path.
     */
    public function extractParameters(string $path): array
    {
        $path = '/' . trim($path, '/');
        if ($path === '') {
            $path = '/';
        }

        if (preg_match($this->regex, $path, $matches)) {
            $params = [];
            foreach ($this->parameterNames as $name) {
                if (isset($matches[$name])) {
                    $params[$name] = $matches[$name];
                }
            }
            return $params;
        }

        return [];
    }

    private function compileRegex(): void
    {
        $pattern = $this->pattern;

        // Convert {param} placeholders to regex named capture groups
        $regex = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_-]*)\}/', function ($matches) {
            $this->parameterNames[] = $matches[1];
            return '(?P<' . $matches[1] . '>[^/]+)';
        }, $pattern);

        $this->regex = '#^' . $regex . '$#u';
    }
}
