<?php

namespace Copot\Core;

final class NetZeroRetirementVerification
{
    public function __construct(private array $evidence)
    {
        if (!isset($evidence['forensic_identity']) || preg_match('/^[a-f0-9]{64}$/D', $evidence['forensic_identity']) !== 1) throw new \InvalidArgumentException('Net-zero forensic identity is invalid.');
    }

    public function evidence(): array { return $this->evidence; }
    public function identity(): string { return $this->evidence['forensic_identity']; }
}
