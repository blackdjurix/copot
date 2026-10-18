<?php

namespace Copot\Core;

final class LegacyBootstrapPackageEvidence
{
    public function __construct(private bool $present, private bool $valid, private ?int $manifestVersion, private ?string $sha256, private string $reason = '') {}
    public function present(): bool { return $this->present; }
    public function valid(): bool { return $this->valid; }
    public function manifestVersion(): ?int { return $this->manifestVersion; }
    public function sha256(): ?string { return $this->sha256; }
    public function reason(): string { return $this->reason; }
    public function toArray(): array { return ['present' => $this->present, 'valid' => $this->valid, 'manifest_version' => $this->manifestVersion, 'sha256' => $this->sha256, 'reason' => $this->reason]; }
}
