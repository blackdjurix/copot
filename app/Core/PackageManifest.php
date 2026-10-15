<?php

namespace Copot\Core;

final class PackageManifest
{
    public function __construct(
        private PackageContract $contract,
        private string $metadataPath,
        private StagedPayload $payload,
        private ?string $rawManifestSha256 = null,
        private ?string $packageEvidenceIdentity = null
    ) {
    }

    public function contract(): PackageContract { return $this->contract; }
    public function metadataPath(): string { return $this->metadataPath; }
    public function payload(): StagedPayload { return $this->payload; }
    public function rawManifestSha256(): ?string { return $this->rawManifestSha256; }
    public function packageEvidenceIdentity(): ?string { return $this->packageEvidenceIdentity; }
}
