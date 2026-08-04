<?php

namespace Copot\Core;

use PDO;

final class CoreMigrationDescriptor
{
    public const TRANSACTIONAL = 'transactional';
    public const NON_TRANSACTIONAL = 'non_transactional';

    /** @var callable(PDO):void */
    private $executor;
    /** @var callable(PDO):bool|null */
    private $precondition;
    /** @var callable(PDO):bool|null */
    private $postcondition;
    private string $checksum;

    public function __construct(
        private string $id,
        private int $sequence,
        private string $sourceMinimumVersion,
        private ?string $sourceMaximumVersion,
        private string $targetWebcoreVersion,
        private string $targetSchemaIdentity,
        private string $transactionMode,
        string $executableSource,
        callable $executor,
        ?callable $precondition = null,
        ?callable $postcondition = null,
        private bool $retryable = false
    ) {
        if (!preg_match('/^[a-z0-9][a-z0-9._-]*$/', $id) || trim($id) !== $id || $sequence < 1) {
            throw new \InvalidArgumentException('Core migration identity is invalid.');
        }

        PackageVersion::assertValid($sourceMinimumVersion);
        PackageVersion::assertValid($targetWebcoreVersion);

        if ($sourceMaximumVersion !== null) {
            PackageVersion::assertValid($sourceMaximumVersion);

            if (PackageVersion::compare($sourceMaximumVersion, $sourceMinimumVersion) <= 0) {
                throw new \InvalidArgumentException('Core migration source range is invalid.');
            }
        }

        if ($targetSchemaIdentity === '' || trim($targetSchemaIdentity) !== $targetSchemaIdentity || preg_match('/[\x00-\x1F\x7F]/', $targetSchemaIdentity) === 1) {
            throw new \InvalidArgumentException('Core migration schema identity is invalid.');
        }

        if (!in_array($transactionMode, [self::TRANSACTIONAL, self::NON_TRANSACTIONAL], true) || $executableSource === '') {
            throw new \InvalidArgumentException('Core migration execution metadata is invalid.');
        }

        $this->executor = $executor;
        $this->precondition = $precondition;
        $this->postcondition = $postcondition;
        $this->checksum = hash('sha256', implode("\n", [
            $id,
            (string) $sequence,
            $sourceMinimumVersion,
            $sourceMaximumVersion ?? '',
            $targetWebcoreVersion,
            $targetSchemaIdentity,
            $transactionMode,
            $retryable ? 'retryable' : 'not-retryable',
            $executableSource,
        ]));
    }

    public function id(): string { return $this->id; }
    public function sequence(): int { return $this->sequence; }
    public function targetWebcoreVersion(): string { return $this->targetWebcoreVersion; }
    public function targetSchemaIdentity(): string { return $this->targetSchemaIdentity; }
    public function transactionMode(): string { return $this->transactionMode; }
    public function checksum(): string { return $this->checksum; }
    public function retryable(): bool { return $this->retryable; }

    public function appliesTo(string $webcoreVersion): bool
    {
        PackageVersion::assertValid($webcoreVersion);

        return PackageVersion::compare($webcoreVersion, $this->sourceMinimumVersion) >= 0
            && ($this->sourceMaximumVersion === null || PackageVersion::compare($webcoreVersion, $this->sourceMaximumVersion) < 0);
    }

    public function execute(PDO $connection): void { ($this->executor)($connection); }
    public function checkPrecondition(PDO $connection): bool { return $this->precondition === null || (bool) ($this->precondition)($connection); }
    public function checkPostcondition(PDO $connection): bool { return $this->postcondition === null || (bool) ($this->postcondition)($connection); }
}
