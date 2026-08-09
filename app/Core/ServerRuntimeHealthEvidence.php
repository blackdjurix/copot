<?php

namespace Copot\Core;

final class ServerRuntimeHealthEvidence
{
    /** @param list<array{name:string,label:string,passed:bool}> $requirements */
    public function __construct(
        private array $requirements,
        private bool $complete = true,
        private ?string $observedAt = null,
        private ?string $freshness = null
    ) {}

    public function requirements(): array { return $this->requirements; }
    public function complete(): bool { return $this->complete; }
    public function observedAt(): ?string { return $this->observedAt; }
    public function freshness(): ?string { return $this->freshness; }
}
