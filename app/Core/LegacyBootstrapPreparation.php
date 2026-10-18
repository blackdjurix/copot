<?php

namespace Copot\Core;

final class LegacyBootstrapPreparation
{
    public const OPERATION_TYPE = 'LEGACY_TO_V3_BOOTSTRAP';
    public const ELIGIBLE = 'eligible';
    public const BLOCKED = 'blocked';
    public const INCOMPLETE = 'incomplete';

    public function __construct(
        private string $status,
        private string $reason,
        private string $operationIdentity,
        private string $planIdentity,
        private string $installationIdentity,
        private LegacyClassificationResult $legacyClassification,
        private ?LegacyBootstrapSourceInventory $sourceInventory,
        private array $collisions,
        private ?string $artifactPath
    ) {
        if (!in_array($status, [self::ELIGIBLE, self::BLOCKED, self::INCOMPLETE], true)) throw new \InvalidArgumentException('Legacy bootstrap status is invalid.');
    }
    public function status(): string { return $this->status; }
    public function eligible(): bool { return $this->status === self::ELIGIBLE; }
    public function reason(): string { return $this->reason; }
    public function operationIdentity(): string { return $this->operationIdentity; }
    public function planIdentity(): string { return $this->planIdentity; }
    public function installationIdentity(): string { return $this->installationIdentity; }
    public function legacyClassification(): LegacyClassificationResult { return $this->legacyClassification; }
    public function sourceInventory(): ?LegacyBootstrapSourceInventory { return $this->sourceInventory; }
    public function collisions(): array { return $this->collisions; }
    public function artifactPath(): ?string { return $this->artifactPath; }
}
