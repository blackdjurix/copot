<?php

namespace Copot\Core;

/** Exact, immutable linkage metadata stored with committed lifecycle state. */
final class SourceInventoryReference
{
    public const SERIALIZATION_VERSION = 1;
    public const OWNER_POLICY = CanonicalTargetInventoryEntry::PACKAGE_OWNED;
    public const ENCODING = 'canonical-target-inventory-v1-json';

    public function __construct(
        private string $artifactIdentity,
        private int $byteSize,
        private string $fingerprint,
        private string $releaseIdentity,
        private int $manifestContractVersion,
        private ?string $sourceTreeIdentity = null,
        private array $rootMapping = ['APP_ROOT' => 'APP_ROOT', 'PUBLIC_ROOT' => 'PUBLIC_ROOT']
    ) {
        if (preg_match('/^[a-f0-9]{64}$/D', $artifactIdentity) !== 1
            || preg_match('/^[a-f0-9]{64}$/D', $fingerprint) !== 1
            || $byteSize < 1
            || $manifestContractVersion < 1
            || $rootMapping !== ['APP_ROOT' => 'APP_ROOT', 'PUBLIC_ROOT' => 'PUBLIC_ROOT']) {
            throw new \InvalidArgumentException('Source inventory reference is invalid.');
        }
        self::assertOpaque($releaseIdentity, 'Release identity');
        if ($sourceTreeIdentity !== null) { self::assertOpaque($sourceTreeIdentity, 'Source-tree identity'); }
    }

    public function artifactIdentity(): string { return $this->artifactIdentity; }
    public function byteSize(): int { return $this->byteSize; }
    public function fingerprint(): string { return $this->fingerprint; }
    public function releaseIdentity(): string { return $this->releaseIdentity; }
    public function manifestContractVersion(): int { return $this->manifestContractVersion; }
    public function sourceTreeIdentity(): ?string { return $this->sourceTreeIdentity; }
    public function ownerPolicy(): string { return self::OWNER_POLICY; }
    public function rootMapping(): array { return $this->rootMapping; }
    public function encoding(): string { return self::ENCODING; }

    public function toArray(): array
    {
        return [
            'serialization_version' => self::SERIALIZATION_VERSION,
            'artifact_identity' => $this->artifactIdentity,
            'byte_size' => $this->byteSize,
            'fingerprint' => $this->fingerprint,
            'release_identity' => $this->releaseIdentity,
            'manifest_contract_version' => $this->manifestContractVersion,
            'owner_policy' => self::OWNER_POLICY,
            'root_mapping' => $this->rootMapping,
            'encoding' => self::ENCODING,
            'source_tree_identity' => $this->sourceTreeIdentity,
        ];
    }

    public static function fromArray(array $data): self
    {
        $keys = ['serialization_version', 'artifact_identity', 'byte_size', 'fingerprint', 'release_identity', 'manifest_contract_version', 'owner_policy', 'root_mapping', 'encoding', 'source_tree_identity'];
        if (array_keys($data) !== $keys || $data['serialization_version'] !== 1 || !is_string($data['artifact_identity']) || !is_int($data['byte_size']) || !is_string($data['fingerprint']) || !is_string($data['release_identity']) || !is_int($data['manifest_contract_version']) || $data['owner_policy'] !== self::OWNER_POLICY || $data['root_mapping'] !== ['APP_ROOT' => 'APP_ROOT', 'PUBLIC_ROOT' => 'PUBLIC_ROOT'] || $data['encoding'] !== self::ENCODING || ($data['source_tree_identity'] !== null && !is_string($data['source_tree_identity']))) {
            throw new \InvalidArgumentException('Source inventory reference format is invalid.');
        }
        return new self($data['artifact_identity'], $data['byte_size'], $data['fingerprint'], $data['release_identity'], $data['manifest_contract_version'], $data['source_tree_identity']);
    }

    private static function assertOpaque(string $value, string $label): void
    {
        if ($value === '' || trim($value) !== $value || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) { throw new \InvalidArgumentException($label . ' is invalid.'); }
    }
}
