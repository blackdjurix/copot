<?php

namespace Copot\Core;

final class LegacyBootstrapSourceEntry
{
    public function __construct(
        private string $root,
        private string $path,
        private int $byteSize,
        private string $sha256,
        private string $provenance = 'legacy-live-tree-scan',
        private string $ownershipEvidence = 'unverified'
    ) {
        if (!in_array($root, [CanonicalTargetInventoryEntry::APP_ROOT, CanonicalTargetInventoryEntry::PUBLIC_ROOT], true)) {
            throw new \InvalidArgumentException('Legacy bootstrap source root is invalid.');
        }
        CanonicalTargetInventoryEntry::assertPath($path);
        if ($byteSize < 0 || preg_match('/^[a-f0-9]{64}$/D', $sha256) !== 1 || $provenance === '' || $ownershipEvidence === '') {
            throw new \InvalidArgumentException('Legacy bootstrap source identity is invalid.');
        }
    }

    public function root(): string { return $this->root; }
    public function path(): string { return $this->path; }
    public function byteSize(): int { return $this->byteSize; }
    public function sha256(): string { return $this->sha256; }
    public function provenance(): string { return $this->provenance; }
    public function ownershipEvidence(): string { return $this->ownershipEvidence; }

    public function toArray(): array
    {
        return ['root' => $this->root, 'path' => $this->path, 'byte_size' => $this->byteSize,
            'sha256' => $this->sha256, 'provenance' => $this->provenance,
            'ownership_evidence' => $this->ownershipEvidence];
    }
}
