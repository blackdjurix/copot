<?php

namespace Copot\Core;

final class CoreMigrationPlan
{
    private function __construct(
        private bool $accepted,
        private string $reason,
        private string $initialWebcoreVersion,
        private string $virtualFinalWebcoreVersion,
        private ?string $initialSchemaIdentity,
        private ?string $virtualFinalSchemaIdentity,
        private array $migrations,
        private bool $freshBaseline
    ) {
    }

    public static function allow(string $initialVersion, string $finalVersion, ?string $initialSchema, ?string $finalSchema, array $migrations, bool $freshBaseline = false): self
    {
        return new self(true, '', $initialVersion, $finalVersion, $initialSchema, $finalSchema, $migrations, $freshBaseline);
    }

    public static function rejected(string $reason): self
    {
        return new self(false, $reason, '', '', null, null, [], false);
    }

    public function isAccepted(): bool { return $this->accepted; }
    public function reason(): string { return $this->reason; }
    public function initialWebcoreVersion(): string { return $this->initialWebcoreVersion; }
    public function virtualFinalWebcoreVersion(): string { return $this->virtualFinalWebcoreVersion; }
    public function initialSchemaIdentity(): ?string { return $this->initialSchemaIdentity; }
    public function virtualFinalSchemaIdentity(): ?string { return $this->virtualFinalSchemaIdentity; }
    public function migrations(): array { return $this->migrations; }
    public function isFreshBaseline(): bool { return $this->freshBaseline; }
}
