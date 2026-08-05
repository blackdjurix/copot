<?php

namespace Copot\Core;

use DateTimeImmutable;

final class ModuleMigrationLedgerRecord
{
    public function __construct(
        private ModuleIdentity $owner,
        private string $migrationId,
        private int $sequence,
        private string $targetPackageVersion,
        private string $targetSchemaIdentity,
        private string $checksum,
        private string $executableIdentity,
        private DateTimeImmutable $appliedAt
    ) {
        PackageVersion::assertValid($targetPackageVersion);
        if ($migrationId === '' || $sequence < 1 || !preg_match('/^[a-f0-9]{64}$/', $checksum)
            || $executableIdentity === '') {
            throw new \InvalidArgumentException('Module migration ledger record is invalid.');
        }
    }

    public function owner(): ModuleIdentity { return $this->owner; }
    public function migrationId(): string { return $this->migrationId; }
    public function sequence(): int { return $this->sequence; }
    public function targetPackageVersion(): string { return $this->targetPackageVersion; }
    public function targetSchemaIdentity(): string { return $this->targetSchemaIdentity; }
    public function checksum(): string { return $this->checksum; }
    public function executableIdentity(): string { return $this->executableIdentity; }
    public function appliedAt(): DateTimeImmutable { return $this->appliedAt; }

    public function toArray(): array
    {
        return [
            'module' => $this->owner->value(), 'migration_id' => $this->migrationId,
            'sequence' => $this->sequence, 'target_package_version' => $this->targetPackageVersion,
            'target_schema_identity' => $this->targetSchemaIdentity, 'checksum' => $this->checksum,
            'executable_identity' => $this->executableIdentity, 'applied_at' => $this->appliedAt->format(DATE_ATOM),
        ];
    }
}
