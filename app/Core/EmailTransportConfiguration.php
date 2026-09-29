<?php

namespace Copot\Core;

final class EmailTransportConfiguration
{
    public const SECURITY_MODES = ['none', 'ssl', 'tls'];

    public function __construct(
        private string $host,
        private int $port,
        private string $security,
        private ?string $username,
    ) {
    }

    public function host(): string
    {
        return $this->host;
    }

    public function port(): int
    {
        return $this->port;
    }

    public function security(): string
    {
        return $this->security;
    }

    public function username(): ?string
    {
        return $this->username;
    }

    public function isComplete(): bool
    {
        return $this->host !== '';
    }
}
