<?php

namespace Copot\Core;

final class ModulePackageManifest
{
    public function __construct(
        private ModulePackageContract $contract,
        private string $metadataPath,
        private StagedPayload $livePayload,
        private array $inventory
    ) {
    }

    public function contract(): ModulePackageContract { return $this->contract; }
    public function metadataPath(): string { return $this->metadataPath; }
    public function livePayload(): StagedPayload { return $this->livePayload; }
    public function inventory(): array { return $this->inventory; }
}
