<?php

namespace Copot\Core;

/** Explicit evidence for retaining a reviewed runtime-owned target byte. */
final class CommittedSourceAuthorityPreservationPolicy
{
    public const PRESERVE_EXCEPTION = 'PRESERVE_EXCEPTION';

    public function __construct(
        private string $root,
        private string $path,
        private string $sourceOwnership,
        private string $deploymentDisposition,
        private string $retainedTargetOwnership,
        private int $retainedTargetByteSize,
        private string $retainedTargetSha256,
        private string $effectiveTargetFingerprint
    ) {
        if (!in_array($root, [CanonicalTargetInventoryEntry::APP_ROOT, CanonicalTargetInventoryEntry::PUBLIC_ROOT], true)) { throw new \InvalidArgumentException('Preservation root is invalid.'); }
        CanonicalTargetInventoryEntry::assertPath($path);
        if ($sourceOwnership !== CanonicalTargetInventoryEntry::PACKAGE_OWNED || $deploymentDisposition !== self::PRESERVE_EXCEPTION || $retainedTargetOwnership !== 'runtime-owned') { throw new \InvalidArgumentException('Preservation ownership or disposition is invalid.'); }
        if ($retainedTargetByteSize < 0 || preg_match('/^[a-f0-9]{64}$/D', $retainedTargetSha256) !== 1 || preg_match('/^[a-f0-9]{64}$/D', $effectiveTargetFingerprint) !== 1) { throw new \InvalidArgumentException('Preservation byte or fingerprint evidence is invalid.'); }
    }

    public function root(): string { return $this->root; }
    public function path(): string { return $this->path; }
    public function sourceOwnership(): string { return $this->sourceOwnership; }
    public function deploymentDisposition(): string { return $this->deploymentDisposition; }
    public function retainedTargetOwnership(): string { return $this->retainedTargetOwnership; }
    public function retainedTargetByteSize(): int { return $this->retainedTargetByteSize; }
    public function retainedTargetSha256(): string { return $this->retainedTargetSha256; }
    public function effectiveTargetFingerprint(): string { return $this->effectiveTargetFingerprint; }

    public function matches(CanonicalTargetInventory $actual): bool
    {
        foreach ($actual->entries() as $entry) {
            if ($entry->root() === $this->root && $entry->path() === $this->path) {
                return $entry->byteSize() === $this->retainedTargetByteSize
                    && hash_equals($entry->sha256(), $this->retainedTargetSha256)
                    && hash_equals($actual->fingerprint(), $this->effectiveTargetFingerprint);
            }
        }
        return false;
    }

    public function toArray(): array
    {
        return [
            'root' => $this->root,
            'path' => $this->path,
            'source_ownership' => $this->sourceOwnership,
            'deployment_disposition' => $this->deploymentDisposition,
            'retained_target_ownership' => $this->retainedTargetOwnership,
            'retained_target_byte_size' => $this->retainedTargetByteSize,
            'retained_target_sha256' => $this->retainedTargetSha256,
            'effective_target_fingerprint' => $this->effectiveTargetFingerprint,
        ];
    }
}
