<?php

namespace Copot\Core;

/** Immutable input for later canonical legacy migration planning. */
final class LegacyBootstrapMigrationInput
{
    private string $identity;

    public function __construct(
        private LegacyClassificationResult $classification,
        private string $registryIdentity,
        private string $targetWebcoreVersion,
        private PackageMigrationDeclaration $declaration
    ) {
        if (!$classification->isLegacyCandidate() || $classification->sourceWebcoreVersion() === null || $classification->sourceSchemaIdentity() === null || $registryIdentity === '') {
            throw new \InvalidArgumentException('Bootstrap migration input is incomplete.');
        }
        PackageVersion::assertValid($targetWebcoreVersion);
        if (PackageVersion::compare($targetWebcoreVersion, $classification->sourceWebcoreVersion()) < 0) {
            throw new \InvalidArgumentException('Bootstrap migration input cannot describe a downgrade.');
        }
        $this->identity = hash('sha256', json_encode($this->toIdentityArray(), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    public function identity(): string { return $this->identity; }
    public function classification(): LegacyClassificationResult { return $this->classification; }
    public function registryIdentity(): string { return $this->registryIdentity; }
    public function targetWebcoreVersion(): string { return $this->targetWebcoreVersion; }
    public function declaration(): PackageMigrationDeclaration { return $this->declaration; }
    public function sourceMigrationStateIdentity(): string { return CoreMigrationStateIdentity::fromRecords($this->classification->records()); }

    public function toArray(): array
    {
        return $this->toIdentityArray() + ['migration_input_identity' => $this->identity];
    }

    private function toIdentityArray(): array
    {
        return [
            'classification' => $this->classification->classification(),
            'source_webcore_version' => $this->classification->sourceWebcoreVersion(),
            'source_schema_identity' => $this->classification->sourceSchemaIdentity(),
            'source_migration_state_identity' => $this->sourceMigrationStateIdentity(),
            'migration_registry_identity' => $this->registryIdentity,
            'target_webcore_version' => $this->targetWebcoreVersion,
            'migration_declaration' => $this->declaration->toArray(),
        ];
    }
}
