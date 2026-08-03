<?php

namespace Copot\Core;

final class RouteDispatchResult
{
    private function __construct(
        private bool $routeMatched,
        private Response $response
    ) {
    }

    public static function matched(Response $response): self
    {
        return new self(true, $response);
    }

    public static function unmatched(Response $response): self
    {
        return new self(false, $response);
    }

    public function routeMatched(): bool
    {
        return $this->routeMatched;
    }

    public function response(): Response
    {
        return $this->response;
    }
}
