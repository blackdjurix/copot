<?php

namespace Copot\Core;

final class PackageManifest
{
    public function __construct(
        private PackageContract $contract,
        private string $metadataPath,
        private StagedPayload $payload
    ) {
    }

    public function contract(): PackageContract { return $this->contract; }
    public function metadataPath(): string { return $this->metadataPath; }
    public function payload(): StagedPayload { return $this->payload; }
}
