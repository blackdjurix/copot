<?php

namespace Copot\Core;

final class DatabaseTableOwnership
{
    private DatabaseTableOwner $targetOwner;

    public function __construct(
        private string $logicalName,
        private DatabaseTableOwner $owner,
        private string $canonicalSchemaSource,
        private ?string $historicalProvisioningSource = null,
        ?DatabaseTableOwner $targetOwner = null,
        private ?string $targetTransitionWorkUnit = null
    ) {
        $this->targetOwner = $targetOwner ?? $owner;
        if (preg_match('/\A[a-z][a-z0-9_]{0,63}\z/', $logicalName) !== 1) {
            throw new \InvalidArgumentException('Logical table identity is invalid.');
        }
        if ($canonicalSchemaSource === '' || trim($canonicalSchemaSource) !== $canonicalSchemaSource) {
            throw new \InvalidArgumentException('Canonical schema source is invalid.');
        }
        if ($historicalProvisioningSource !== null && ($historicalProvisioningSource === '' || trim($historicalProvisioningSource) !== $historicalProvisioningSource)) {
            throw new \InvalidArgumentException('Historical provisioning source is invalid.');
        }
        if ($owner->isWebcore() && $historicalProvisioningSource !== null) {
            throw new \InvalidArgumentException('Webcore ownership cannot claim Module pre-provisioning history.');
        }
        if ($this->targetTransitionWorkUnit !== null
            && ($this->targetTransitionWorkUnit === '' || trim($this->targetTransitionWorkUnit) !== $this->targetTransitionWorkUnit)) {
            throw new \InvalidArgumentException('Target ownership transition work unit is invalid.');
        }
        if ($this->targetOwner->key() !== $owner->key() && $this->targetTransitionWorkUnit === null) {
            throw new \InvalidArgumentException('Changed target ownership requires an explicit transition work unit.');
        }
    }

    public function logicalName(): string { return $this->logicalName; }
    public function owner(): DatabaseTableOwner { return $this->owner; }
    public function canonicalSchemaSource(): string { return $this->canonicalSchemaSource; }
    public function historicalProvisioningSource(): ?string { return $this->historicalProvisioningSource; }
    public function targetOwner(): DatabaseTableOwner { return $this->targetOwner; }
    public function targetTransitionWorkUnit(): ?string { return $this->targetTransitionWorkUnit; }
    public function isTargetTransitionPending(): bool { return $this->targetOwner->key() !== $this->owner->key(); }
    public function isHistoricallyPreProvisioned(): bool { return $this->historicalProvisioningSource !== null; }
    public function physicalName(DatabaseTableNames $tables): string
    {
        return $tables->resolve($this->logicalName);
    }
}
