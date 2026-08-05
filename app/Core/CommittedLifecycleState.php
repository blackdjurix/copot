<?php

namespace Copot\Core;

use DateTimeImmutable;

final class CommittedLifecycleState
{
    public function __construct(
        private string $webcoreVersion,
        private string $releaseIdentity,
        private ?string $sourceTreeIdentity,
        private int $manifestContractVersion,
        private string $schemaStateIdentity,
        private string $migrationStateIdentity,
        private DateTimeImmutable $committedAt,
        private ?string $packageIntegrityIdentity = null
    ) {
        PackageVersion::assertValid($webcoreVersion);
        self::assertIdentity($releaseIdentity, 'Release identity');
        self::assertIdentity($sourceTreeIdentity, 'Source-tree identity');
        self::assertIdentity($schemaStateIdentity, 'Schema-state identity');
        self::assertIdentity($migrationStateIdentity, 'Migration-state identity');
        self::assertIdentity($packageIntegrityIdentity, 'Package-integrity identity');
        if ($manifestContractVersion < 1) {
            throw new \InvalidArgumentException('Manifest contract version is invalid.');
        }
    }

    public function webcoreVersion(): string { return $this->webcoreVersion; }
    public function releaseIdentity(): string { return $this->releaseIdentity; }
    public function sourceTreeIdentity(): ?string { return $this->sourceTreeIdentity; }
    public function manifestContractVersion(): int { return $this->manifestContractVersion; }
    public function schemaStateIdentity(): string { return $this->schemaStateIdentity; }
    public function migrationStateIdentity(): string { return $this->migrationStateIdentity; }
    public function committedAt(): DateTimeImmutable { return $this->committedAt; }
    public function packageIntegrityIdentity(): ?string { return $this->packageIntegrityIdentity; }

    public function snapshot(): InstalledStateSnapshot
    {
        return new InstalledStateSnapshot(
            $this->webcoreVersion,
            $this->committedAt,
            $this->releaseIdentity,
            $this->sourceTreeIdentity,
            $this->manifestContractVersion,
            $this->schemaStateIdentity,
            $this->migrationStateIdentity
        );
    }

    public function toArray(): array
    {
        return [
            'webcore_version' => $this->webcoreVersion,
            'release_identity' => $this->releaseIdentity,
            'source_tree_identity' => $this->sourceTreeIdentity,
            'manifest_contract_version' => $this->manifestContractVersion,
            'schema_state_identity' => $this->schemaStateIdentity,
            'migration_state_identity' => $this->migrationStateIdentity,
            'committed_at' => $this->committedAt->format(DATE_ATOM),
            'package_integrity_identity' => $this->packageIntegrityIdentity,
        ];
    }

    public static function fromArray(array $data): self
    {
        $keys = ['webcore_version', 'release_identity', 'source_tree_identity', 'manifest_contract_version', 'schema_state_identity', 'migration_state_identity', 'committed_at'];
        $extendedKeys = [...$keys, 'package_integrity_identity'];
        if (!in_array(array_keys($data), [$keys, $extendedKeys], true)
            || !is_string($data['webcore_version'])
            || !is_string($data['release_identity'])
            || ($data['source_tree_identity'] !== null && !is_string($data['source_tree_identity']))
            || !is_int($data['manifest_contract_version'])
            || !is_string($data['schema_state_identity'])
            || !is_string($data['migration_state_identity'])
            || !is_string($data['committed_at'])) {
            throw new \InvalidArgumentException('Committed lifecycle state format is invalid.');
        }

        $committedAt = DateTimeImmutable::createFromFormat(DATE_ATOM, $data['committed_at']);
        if (!$committedAt instanceof DateTimeImmutable) {
            throw new \InvalidArgumentException('Committed lifecycle timestamp is invalid.');
        }

        return new self(
            $data['webcore_version'],
            $data['release_identity'],
            $data['source_tree_identity'],
            $data['manifest_contract_version'],
            $data['schema_state_identity'],
            $data['migration_state_identity'],
            $committedAt,
            $data['package_integrity_identity'] ?? null
        );
    }

    private static function assertIdentity(?string $identity, string $label): void
    {
        if ($identity === null) { return; }
        if ($identity === '' || trim($identity) !== $identity || preg_match('/[\x00-\x1F\x7F]/', $identity) === 1) {
            throw new \InvalidArgumentException($label . ' is invalid.');
        }
    }
}
