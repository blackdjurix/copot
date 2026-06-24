<?php

namespace Copot\Core;

class Router
{
    private array $routes = [
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

    public function dispatch(Request $request): Response
    {
        if (!array_key_exists($request->method(), $this->routes)) {
            return Response::html('404 Not Found', 404);
        }

        $handler = $this->routes[$request->method()][$request->path()] ?? null;

        if ($handler === null) {
            return Response::html('404 Not Found', 404);
        }

        $result = $handler($request);

        if ($result instanceof Response) {
            return $result;
        }

        return Response::html((string) $result);
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

        $this->routes[$method][$path] = $handler;
    }
}
