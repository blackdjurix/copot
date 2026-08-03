<?php

namespace Copot\Core;

class Router
{
    private ?UnresolvedRouteResolver $unresolvedRouteResolver = null;

    private array $routes = [
        'GET' => [],
        'POST' => [],
    ];
    private array $patternRoutes = [
        'GET' => [],
        'POST' => [],
    ];

    public function get(string $path, callable $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    public function setUnresolvedRouteResolver(UnresolvedRouteResolver $resolver): void
    {
        if ($this->unresolvedRouteResolver !== null) {
            throw new \RuntimeException('An unresolved-route resolver is already registered.');
        }

        $this->unresolvedRouteResolver = $resolver;
    }

    public function dispatch(Request $request): Response
    {
        return $this->dispatchResult($request)->response();
    }

    public function dispatchResult(Request $request): RouteDispatchResult
    {
        if (!array_key_exists($request->method(), $this->routes)) {
            return RouteDispatchResult::unmatched(Response::html('404 Not Found', 404));
        }

        $handler = $this->routes[$request->method()][$request->path()] ?? null;

        if ($handler !== null) {
            $result = $handler($request);

            if ($result instanceof Response) {
                return RouteDispatchResult::matched($result);
            }

            return RouteDispatchResult::matched(Response::html((string) $result));
        }

        foreach ($this->patternRoutes[$request->method()] as $route) {
            if (!preg_match($route['regex'], $request->path(), $matches)) {
                continue;
            }

            $params = [];

            foreach ($route['params'] as $index => $name) {
                $params[$name] = $matches[$index + 1] ?? '';
            }

            $result = $route['handler']($request, $params);

            if ($result instanceof Response) {
                return RouteDispatchResult::matched($result);
            }

            return RouteDispatchResult::matched(Response::html((string) $result));
        }

        if ($request->method() === 'GET' && $this->unresolvedRouteResolver !== null) {
            try {
                $fallback = $this->unresolvedRouteResolver->resolve($request);

                if ($fallback instanceof Response) {
                    return RouteDispatchResult::unmatched($fallback);
                }
            } catch (\Throwable) {
                // Optional fallback resolution fails closed to the normal 404.
            }
        }

        return RouteDispatchResult::unmatched(Response::html('404 Not Found', 404));
    }

    private function normalizePath(string $path): string
    {
        $path = '/' . trim($path, '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    private function addRoute(string $method, string $path, callable $handler): void
    {
        $path = $this->normalizePath($path);

        if (isset($this->routes[$method][$path])) {
            throw new \RuntimeException("Route [{$method} {$path}] is already registered.");
        }

        if (str_contains($path, '{')) {
            $this->patternRoutes[$method][] = $this->compilePatternRoute($path, $handler);

            return;
        }

        $this->routes[$method][$path] = $handler;
    }

    private function compilePatternRoute(string $path, callable $handler): array
    {
        $segments = explode('/', trim($path, '/'));
        $params = [];
        $regexSegments = [];
        $lastIndex = count($segments) - 1;

        foreach ($segments as $index => $segment) {
            if (preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_]*)\}$/', $segment, $matches)) {
                $params[] = $matches[1];
                $regexSegments[] = $index === $lastIndex ? '(.+)' : '([^/]+)';

                continue;
            }

            $regexSegments[] = preg_quote($segment, '#');
        }

        return [
            'regex' => '#^/' . implode('/', $regexSegments) . '$#',
            'params' => $params,
            'handler' => $handler,
        ];
    }
}
