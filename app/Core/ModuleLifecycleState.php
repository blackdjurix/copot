<?php

namespace Copot\Core;

use DateTimeImmutable;

final class ModuleLifecycleState
{
    public function __construct(
        private PackageIdentity $packageIdentity,
        private ModuleIdentity $moduleIdentity,
        private string $packageVersion,
        private string $releaseIdentity,
        private int $manifestContractVersion,
        private string $migrationStateIdentity,
        private string $packageIntegrityIdentity,
        private bool $enabled,
        private string $lastCommittedLifecycleTarget,
        private DateTimeImmutable $committedAt,
        private ?string $operationReconciliationIdentity = null
    ) {
        PackageVersion::assertValid($packageVersion);
        self::assertIdentity($releaseIdentity, 'Release identity');
        self::assertIdentity($migrationStateIdentity, 'Migration-state identity');
        self::assertIdentity($lastCommittedLifecycleTarget, 'Committed lifecycle target');
        if ($manifestContractVersion < 1 || preg_match('/^[a-f0-9]{64}$/', strtolower($packageIntegrityIdentity)) !== 1) {
            throw new \InvalidArgumentException('Module lifecycle state identity is invalid.');
        }
        self::assertIdentity($operationReconciliationIdentity, 'Operation reconciliation identity');
        $this->packageIntegrityIdentity = strtolower($packageIntegrityIdentity);
    }

    public function packageIdentity(): PackageIdentity { return $this->packageIdentity; }
    public function moduleIdentity(): ModuleIdentity { return $this->moduleIdentity; }
    public function packageVersion(): string { return $this->packageVersion; }
    public function releaseIdentity(): string { return $this->releaseIdentity; }
    public function manifestContractVersion(): int { return $this->manifestContractVersion; }
    public function migrationStateIdentity(): string { return $this->migrationStateIdentity; }
    public function packageIntegrityIdentity(): string { return $this->packageIntegrityIdentity; }
    public function enabled(): bool { return $this->enabled; }
    public function lastCommittedLifecycleTarget(): string { return $this->lastCommittedLifecycleTarget; }
    public function committedAt(): DateTimeImmutable { return $this->committedAt; }
    public function operationReconciliationIdentity(): ?string { return $this->operationReconciliationIdentity; }

    public function toArray(): array
    {
        return [
            'package_identity' => $this->packageIdentity->value(),
            'technical_module_identity' => $this->moduleIdentity->value(),
            'package_version' => $this->packageVersion,
            'release_identity' => $this->releaseIdentity,
            'manifest_contract_version' => $this->manifestContractVersion,
            'migration_state_identity' => $this->migrationStateIdentity,
            'package_integrity_identity' => $this->packageIntegrityIdentity,
            'enabled' => $this->enabled,
            'last_committed_lifecycle_target' => $this->lastCommittedLifecycleTarget,
            'committed_at' => $this->committedAt->format(DATE_ATOM),
            'operation_reconciliation_identity' => $this->operationReconciliationIdentity,
        ];
    }

    public static function fromArray(array $data): self
    {
        $keys = ['package_identity', 'technical_module_identity', 'package_version', 'release_identity', 'manifest_contract_version', 'migration_state_identity', 'package_integrity_identity', 'enabled', 'last_committed_lifecycle_target', 'committed_at', 'operation_reconciliation_identity'];
        if (array_keys($data) !== $keys || !is_string($data['package_identity']) || !is_string($data['technical_module_identity'])
            || !is_string($data['package_version']) || !is_string($data['release_identity']) || !is_int($data['manifest_contract_version'])
            || !is_string($data['migration_state_identity']) || !is_string($data['package_integrity_identity']) || !is_bool($data['enabled'])
            || !is_string($data['last_committed_lifecycle_target']) || !is_string($data['committed_at'])
            || ($data['operation_reconciliation_identity'] !== null && !is_string($data['operation_reconciliation_identity']))) {
            throw new \InvalidArgumentException('Module lifecycle state format is invalid.');
        }
        $committedAt = DateTimeImmutable::createFromFormat(DATE_ATOM, $data['committed_at']);
        if (!$committedAt instanceof DateTimeImmutable) throw new \InvalidArgumentException('Module lifecycle timestamp is invalid.');
        return new self(new PackageIdentity($data['package_identity']), new ModuleIdentity($data['technical_module_identity']), $data['package_version'], $data['release_identity'], $data['manifest_contract_version'], $data['migration_state_identity'], $data['package_integrity_identity'], $data['enabled'], $data['last_committed_lifecycle_target'], $committedAt, $data['operation_reconciliation_identity']);
    }

    private static function assertIdentity(?string $identity, string $label): void
    {
        if ($identity !== null && ($identity === '' || trim($identity) !== $identity || preg_match('/[\x00-\x1F\x7F]/', $identity) === 1)) throw new \InvalidArgumentException($label . ' is invalid.');
    }
}
