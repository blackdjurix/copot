<?php

namespace Copot\Core;

final class InstallerRoutingPlan
{
    public function __construct(private string $intent, private string $namespace, private string $route, private array $warnings = []) {}
    public function intent(): string { return $this->intent; }
    public function namespace(): string { return $this->namespace; }
    public function route(): string { return $this->route; }
    public function warnings(): array { return $this->warnings; }
}
