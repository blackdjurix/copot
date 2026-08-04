<?php

namespace Copot\Core;

use DateTimeImmutable;

final class AppliedMigrationRecord
{
    public function __construct(
        private string $migrationId,
        private int $sequence,
        private string $targetWebcoreVersion,
        private string $targetSchemaIdentity,
        private string $checksum,
        private DateTimeImmutable $appliedAt
    ) {
        PackageVersion::assertValid($targetWebcoreVersion);

        if ($migrationId === '' || $sequence < 1 || !preg_match('/^[a-f0-9]{64}$/', $checksum)) {
            throw new \InvalidArgumentException('Applied migration record is invalid.');
        }
    }

    public function migrationId(): string { return $this->migrationId; }
    public function sequence(): int { return $this->sequence; }
    public function targetWebcoreVersion(): string { return $this->targetWebcoreVersion; }
    public function targetSchemaIdentity(): string { return $this->targetSchemaIdentity; }
    public function checksum(): string { return $this->checksum; }
    public function appliedAt(): DateTimeImmutable { return $this->appliedAt; }
}
