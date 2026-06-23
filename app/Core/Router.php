<?php

namespace Copot\Core;

class Router
{
    private array $routes = [
        'GET' => [],
    ];

    public function get(string $path, callable $handler): void
    {
        $this->routes['GET'][$this->normalizePath($path)] = $handler;
    }

    public function dispatch(Request $request): Response
    {
        if ($request->method() !== 'GET') {
            return Response::html('404 Not Found', 404);
        }

        $handler = $this->routes['GET'][$request->path()] ?? null;

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
}
