<?php

namespace Copot\Core;

final class InstallerNamespaceResult
{
    public function __construct(private string $namespace, private string $availability, private array $collisions = []) {}
    public function namespace(): string { return $this->namespace; }
    public function availability(): string { return $this->availability; }
    public function collisions(): array { return $this->collisions; }
    public function usable(): bool { return $this->availability === InstallerNamespaceAvailability::AVAILABLE; }
}
