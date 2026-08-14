<?php

namespace Copot\Core;

final class ModuleMigrationDescriptor
{
    public const TRANSACTIONAL = 'transactional';
    public const NON_TRANSACTIONAL = 'non_transactional';

    public function __construct(
        private string $id,
        private int $sequence,
        private PackageCompatibility $sourceVersionConstraint,
        private string $targetPackageVersion,
        private string $targetSchemaIdentity,
        private string $transactionMode = self::TRANSACTIONAL,
        private string $executableSource = '',
        private $executor = null,
        private $precondition = null,
        private $postcondition = null,
        private bool $retryable = false,
        private ?MigrationSchemaSurface $schemaSurface = null
    ) {
        if (preg_match('/^[a-z0-9][a-z0-9._-]*$/', $id) !== 1 || $sequence < 1) {
            throw new \InvalidArgumentException('Module migration identity is invalid.');
        }

        PackageVersion::assertValid($targetPackageVersion);

        if ($targetSchemaIdentity === ''
            || trim($targetSchemaIdentity) !== $targetSchemaIdentity
            || preg_match('/[\x00-\x1F\x7F]/', $targetSchemaIdentity) === 1) {
            throw new \InvalidArgumentException('Module migration schema identity is invalid.');
        }

        if ($executableSource === '') {
            $this->executableSource = 'declaration:' . $id;
        }
        if ($executor === null) {
            $this->executor = static function (\PDO $connection): void {};
        }

        if (!in_array($transactionMode, [self::TRANSACTIONAL, self::NON_TRANSACTIONAL], true)
            || !is_callable($this->executor)
            || ($precondition !== null && !is_callable($precondition))
            || ($postcondition !== null && !is_callable($postcondition))) {
            throw new \InvalidArgumentException('Module migration execution metadata is invalid.');
        }

        if ($this->executableSource === '' || trim($this->executableSource) !== $this->executableSource
            || preg_match('/[\x00-\x1F\x7F]/', $this->executableSource) === 1) {
            throw new \InvalidArgumentException('Module migration executable identity is invalid.');
        }
    }

    public function id(): string
    {
        return $this->id;
    }

    public function sequence(): int
    {
        return $this->sequence;
    }

    public function sourceVersionConstraint(): PackageCompatibility
    {
        return $this->sourceVersionConstraint;
    }

    public function targetPackageVersion(): string
    {
        return $this->targetPackageVersion;
    }

    public function targetSchemaIdentity(): string
    {
        return $this->targetSchemaIdentity;
    }

    public function transactionMode(): string { return $this->transactionMode; }
    public function executableSource(): string { return $this->executableSource; }
    public function checksum(): string
    {
        return hash('sha256', implode("\n", [
            $this->id,
            (string) $this->sequence,
            json_encode($this->sourceVersionConstraint->toArray(), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            $this->targetPackageVersion,
            $this->targetSchemaIdentity,
            $this->transactionMode,
            $this->retryable ? 'retryable' : 'not-retryable',
            $this->executableSource,
            json_encode($this->schemaSurface?->tables() ?? ['undeclared_surface'], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ]));
    }
    public function retryable(): bool { return $this->retryable; }
    public function schemaSurface(): MigrationSchemaSurface { return $this->schemaSurface ?? new MigrationSchemaSurface(['undeclared_surface']); }
    public function appliesTo(string $packageVersion): bool { return $this->sourceVersionConstraint->supports($packageVersion); }
    public function execute(\PDO $connection, ?DatabaseTableNames $tables = null): void
    {
        if ($tables === null) {
            ($this->executor)($connection);
            return;
        }

        ($this->executor)(new ModuleMigrationContext($tables));
    }
    public function executeAuthorized(AuthorizedMigrationContext $context): void { ($this->executor)($context); }
    public function checkPrecondition(\PDO $connection): bool { return $this->precondition === null || (bool) ($this->precondition)($connection); }
    public function checkPostcondition(\PDO $connection): bool { return $this->postcondition === null || (bool) ($this->postcondition)($connection); }
    public function checkPreconditionAuthorized(AuthorizedMigrationContext $context): bool { return $this->precondition === null || (bool) ($this->precondition)($context); }
    public function checkPostconditionAuthorized(AuthorizedMigrationContext $context): bool { return $this->postcondition === null || (bool) ($this->postcondition)($context); }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'sequence' => $this->sequence,
            'source_version_constraint' => [
                'minimum_version' => $this->sourceVersionConstraint->minimumVersion(),
                'maximum_version' => $this->sourceVersionConstraint->maximumVersion(),
            ],
            'target_package_version' => $this->targetPackageVersion,
            'target_schema_identity' => $this->targetSchemaIdentity,
            'transaction_mode' => $this->transactionMode,
            'executable_source' => $this->executableSource,
            'retryable' => $this->retryable,
        ];
    }
}
