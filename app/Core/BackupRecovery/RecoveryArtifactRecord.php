<?php

namespace Copot\Core\BackupRecovery;

final class RecoveryArtifactRecord
{
    public function __construct(
        private string $domainIdentifier,
        private string $artifactIdentity,
        private int $byteSize,
        private ?string $metadataIdentity = null
    ) {
        if (preg_match('/^[a-z][a-z0-9._-]*$/D', $domainIdentifier) !== 1) {
            throw new RecoveryInvariantException('Recovery artifact domain is invalid.');
        }
        self::assertHash($artifactIdentity, 'Recovery artifact identity');
        if ($byteSize < 0) {
            throw new RecoveryInvariantException('Recovery artifact size is invalid.');
        }
        if ($metadataIdentity !== null) {
            self::assertHash($metadataIdentity, 'Recovery artifact metadata identity');
        }
    }

    public function domainIdentifier(): string { return $this->domainIdentifier; }
    public function artifactIdentity(): string { return $this->artifactIdentity; }
    public function byteSize(): int { return $this->byteSize; }
    public function metadataIdentity(): ?string { return $this->metadataIdentity; }

    /** @return array<string, int|string|null> */
    public function toArray(): array
    {
        return [
            'domain_identifier' => $this->domainIdentifier,
            'artifact_identity' => $this->artifactIdentity,
            'byte_size' => $this->byteSize,
            'metadata_identity' => $this->metadataIdentity,
        ];
    }

    private static function assertHash(string $value, string $label): void
    {
        if (preg_match('/^[a-f0-9]{64}$/D', $value) !== 1) {
            throw new RecoveryInvariantException($label . ' is invalid.');
        }
    }
}
