<?php

namespace Copot\Core;

final class HealthGateMatrix
{
    private array $gates;

    public function __construct(array $gates)
    {
        foreach ($gates as $gate) {
            if (!$gate instanceof HealthGateResult) { throw new \InvalidArgumentException('Health gate matrix contains an invalid result.'); }
        }
        $this->gates = array_values($gates);
    }

    public function gates(): array { return $this->gates; }
    public function passed(): bool { return array_reduce($this->gates, static fn (bool $ok, HealthGateResult $gate): bool => $ok && $gate->passed(), true); }
    public function failureReason(): string
    {
        foreach ($this->gates as $gate) { if (!$gate->passed()) { return $gate->name() . ': ' . $gate->reason(); } }
        return '';
    }
}
