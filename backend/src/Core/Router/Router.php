<?php

declare(strict_types=1);

namespace Voak\Health\Core\Router;

use Voak\Health\Core\Container;
use Voak\Health\Core\Exceptions\MethodNotAllowedException;
use Voak\Health\Core\Exceptions\NotFoundException;
use Voak\Health\Core\Http\Request;
use Voak\Health\Core\Http\Response;

/**
 * Lightweight, zero-dependency REST Router supporting route parameters and standard HTTP exceptions.
 */
class Router
{
    /** @var Route[] */
    private array $routes = [];

    public function addRoute(string $method, string $pattern, callable|array $handler, array $middleware = []): Route
    {
        $route = new Route($method, $pattern, $handler, $middleware);
        $this->routes[] = $route;
        return $route;
    }

    public function get(string $pattern, callable|array $handler, array $middleware = []): Route
    {
        return $this->addRoute('GET', $pattern, $handler, $middleware);
    }

    public function post(string $pattern, callable|array $handler, array $middleware = []): Route
    {
        return $this->addRoute('POST', $pattern, $handler, $middleware);
    }

    public function put(string $pattern, callable|array $handler, array $middleware = []): Route
    {
        return $this->addRoute('PUT', $pattern, $handler, $middleware);
    }

    public function patch(string $pattern, callable|array $handler, array $middleware = []): Route
    {
        return $this->addRoute('PATCH', $pattern, $handler, $middleware);
    }

    public function delete(string $pattern, callable|array $handler, array $middleware = []): Route
    {
        return $this->addRoute('DELETE', $pattern, $handler, $middleware);
    }

    public function options(string $pattern, callable|array $handler, array $middleware = []): Route
    {
        return $this->addRoute('OPTIONS', $pattern, $handler, $middleware);
    }

    /**
     * @return Route[]
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * Dispatches request to matching route handler.
     *
     * @throws NotFoundException If path does not match any route
     * @throws MethodNotAllowedException If path matches but method is not registered
     */
    public function dispatch(Request $request, ?Container $container = null): Response
    {
        $matchedPathRoutes = [];

        foreach ($this->routes as $route) {
            if ($route->matches($request)) {
                $parameters = $route->extractParameters($request->getPath());
                foreach ($parameters as $name => $val) {
                    $request = $request->withAttribute($name, $val);
                }

                $routeMiddlewares = $route->getMiddleware();
                if (empty($routeMiddlewares)) {
                    return $this->executeHandler($route->getHandler(), $request, $container);
                }

                $coreHandler = fn(Request $req): Response => $this->executeHandler($route->getHandler(), $req, $container);

                $pipeline = array_reduce(
                    array_reverse($routeMiddlewares),
                    function (callable $next, mixed $middleware) use ($container): callable {
                        return function (Request $req) use ($next, $middleware, $container): Response {
                            $mw = is_string($middleware) && $container !== null ? $container->get($middleware) : $middleware;
                            if ($mw instanceof \Voak\Health\Middleware\MiddlewareInterface) {
                                return $mw->process($req, $next);
                            }
                            if (is_callable($mw)) {
                                return $mw($req, $next);
                            }
                            return $next($req);
                        };
                    },
                    $coreHandler
                );

                return $pipeline($request);
            }

            if ($route->matchesPath($request->getPath())) {
                $matchedPathRoutes[] = $route->getMethod();
            }
        }

        // Check if path exists for other methods -> 405 Method Not Allowed
        if (!empty($matchedPathRoutes)) {
            $uniqueMethods = array_values(array_unique($matchedPathRoutes));
            // Always include OPTIONS if not already present
            if (!in_array('OPTIONS', $uniqueMethods, true)) {
                $uniqueMethods[] = 'OPTIONS';
            }
            throw new MethodNotAllowedException($uniqueMethods);
        }

        // Path not found -> 404 Not Found
        throw new NotFoundException("Endpoint not found: {$request->getMethod()} {$request->getPath()}");
    }

    private function executeHandler(callable|array $handler, Request $request, ?Container $container = null): Response
    {
        if (is_callable($handler)) {
            $result = $handler($request);
        } elseif (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            $controller = $container !== null ? $container->get($class) : new $class();
            $result = $controller->$method($request);
        } else {
            throw new \RuntimeException("Invalid route handler registered.");
        }

        if ($result instanceof Response) {
            return $result;
        }

        if (is_array($result)) {
            return Response::success($result, 200, $request->getCorrelationId());
        }

        if (is_string($result)) {
            return Response::raw($result);
        }

        return (new Response())->withStatus(204);
    }
}
