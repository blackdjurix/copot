<?php

namespace Copot\Core;

final class HealthGateResult
{
    private function __construct(private string $name, private bool $passed, private string $reason = '')
    {
        if ($name === '') { throw new \InvalidArgumentException('Health gate name is invalid.'); }
    }

    public static function pass(string $name): self { return new self($name, true); }
    public static function fail(string $name, string $reason): self { return new self($name, false, $reason); }
    public function name(): string { return $this->name; }
    public function passed(): bool { return $this->passed; }
    public function reason(): string { return $this->reason; }
}
