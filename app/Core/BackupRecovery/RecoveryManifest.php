<?php

namespace Copot\Core\BackupRecovery;

final class RecoveryManifest
{
    /** @var array<int, RecoveryDomainIdentity> */
    private array $domainIdentities;

    /**
     * @param array<int, RecoveryDomainIdentity> $domainIdentities
     */
    public function __construct(
        private RecoveryIdentity $recoveryIdentity,
        private string $operationIdentity,
        private string $targetPackageIdentity,
        private string $targetReleaseIdentity,
        private string $archiveIdentity,
        private string $applyPlanIdentity,
        array $domainIdentities,
        private string $preOperationLifecycleIdentity,
        private string $preOperationMigrationLedgerIdentity
    ) {
        self::assertOpaque($operationIdentity, 'Lifecycle operation identity');
        self::assertOpaque($targetPackageIdentity, 'Target package identity');
        self::assertOpaque($targetReleaseIdentity, 'Target release identity');
        self::assertHash($archiveIdentity, 'Package/archive identity');
        self::assertHash($applyPlanIdentity, 'Apply-plan identity');
        self::assertOpaque($preOperationLifecycleIdentity, 'Pre-operation lifecycle identity');
        self::assertOpaque($preOperationMigrationLedgerIdentity, 'Pre-operation migration-ledger identity');

        $byIdentifier = [];
        $byOwnership = [];

        foreach ($domainIdentities as $domainIdentity) {
            if (!$domainIdentity instanceof RecoveryDomainIdentity) {
                throw new RecoveryInvariantException('Recovery manifest contains an invalid domain identity.');
            }

            if (isset($byIdentifier[$domainIdentity->identifier()]) || isset($byOwnership[$domainIdentity->ownershipKey()])) {
                throw new RecoveryInvariantException('Recovery manifest contains duplicate or ambiguous domain ownership.');
            }

            $byIdentifier[$domainIdentity->identifier()] = $domainIdentity;
            $byOwnership[$domainIdentity->ownershipKey()] = true;
        }

        if ($byIdentifier === []) {
            throw new RecoveryInvariantException('Recovery manifest must contain domain identities.');
        }

        ksort($byIdentifier, SORT_STRING);
        $this->domainIdentities = array_values($byIdentifier);
    }

    public function recoveryIdentity(): RecoveryIdentity
    {
        return $this->recoveryIdentity;
    }

    public function operationIdentity(): string
    {
        return $this->operationIdentity;
    }

    public function targetPackageIdentity(): string
    {
        return $this->targetPackageIdentity;
    }

    public function targetReleaseIdentity(): string
    {
        return $this->targetReleaseIdentity;
    }

    public function archiveIdentity(): string
    {
        return $this->archiveIdentity;
    }

    public function applyPlanIdentity(): string
    {
        return $this->applyPlanIdentity;
    }

    /** @return array<int, RecoveryDomainIdentity> */
    public function domainIdentities(): array
    {
        return $this->domainIdentities;
    }

    public function preOperationLifecycleIdentity(): string
    {
        return $this->preOperationLifecycleIdentity;
    }

    public function preOperationMigrationLedgerIdentity(): string
    {
        return $this->preOperationMigrationLedgerIdentity;
    }

    public function identity(): string
    {
        return hash('sha256', json_encode([
            'recovery_identity' => $this->recoveryIdentity->value(),
            'operation_identity' => $this->operationIdentity,
            'target_package_identity' => $this->targetPackageIdentity,
            'target_release_identity' => $this->targetReleaseIdentity,
            'archive_identity' => $this->archiveIdentity,
            'apply_plan_identity' => $this->applyPlanIdentity,
            'domain_identities' => array_map(static fn (RecoveryDomainIdentity $domain): array => [
                'identifier' => $domain->identifier(),
                'ownership_key' => $domain->ownershipKey(),
                'scope_identity' => $domain->scopeIdentity(),
                'artifact_identity' => $domain->artifactIdentity(),
            ], $this->domainIdentities),
            'pre_operation_lifecycle_identity' => $this->preOperationLifecycleIdentity,
            'pre_operation_migration_ledger_identity' => $this->preOperationMigrationLedgerIdentity,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private static function assertOpaque(string $value, string $label): void
    {
        if ($value === '' || trim($value) !== $value || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            throw new RecoveryInvariantException($label . ' is invalid.');
        }
    }

    private static function assertHash(string $value, string $label): void
    {
        if (preg_match('/^[a-f0-9]{64}$/D', strtolower($value)) !== 1) {
            throw new RecoveryInvariantException($label . ' is invalid.');
        }
    }
}
