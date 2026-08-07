<?php

namespace Copot\Core;

final class CanonicalSchemaBaselineDescriptor
{
    public function __construct(
        private string $identity,
        private string $webcoreVersion,
        private string $schemaPath,
        private string $schemaFileSha256,
        private bool $migrationLedgerPresent
    ) {
        if ($identity === '' || trim($identity) !== $identity || preg_match('/[\x00-\x1F\x7F]/', $identity) === 1) {
            throw new \InvalidArgumentException('Canonical schema baseline identity is invalid.');
        }
        PackageVersion::assertValid($webcoreVersion);
        if (!preg_match('/\A[a-f0-9]{64}\z/', $schemaFileSha256)) {
            throw new \InvalidArgumentException('Canonical schema baseline file identity is invalid.');
        }
    }

    public function identity(): string { return $this->identity; }
    public function webcoreVersion(): string { return $this->webcoreVersion; }
    public function schemaPath(): string { return $this->schemaPath; }
    public function schemaFileSha256(): string { return $this->schemaFileSha256; }
    public function migrationLedgerPresent(): bool { return $this->migrationLedgerPresent; }
}
