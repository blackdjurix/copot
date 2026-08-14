<?php

namespace Copot\Core;

final class DatabaseTableOwnership
{
    public function __construct(
        private string $logicalName,
        private DatabaseTableOwner $owner,
        private string $canonicalSchemaSource,
        private ?string $historicalProvisioningSource = null
    ) {
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
    }

    public function logicalName(): string { return $this->logicalName; }
    public function owner(): DatabaseTableOwner { return $this->owner; }
    public function canonicalSchemaSource(): string { return $this->canonicalSchemaSource; }
    public function historicalProvisioningSource(): ?string { return $this->historicalProvisioningSource; }
    public function isHistoricallyPreProvisioned(): bool { return $this->historicalProvisioningSource !== null; }
    public function physicalName(DatabaseTableNames $tables): string
    {
        return $tables->resolve($this->logicalName);
    }
}
