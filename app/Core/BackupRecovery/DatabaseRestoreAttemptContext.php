<?php

namespace Copot\Core\BackupRecovery;

final class DatabaseRestoreAttemptContext
{
    public const PREPARED = 'PREPARED';
    public const LOCKED = 'LOCKED';
    public const DROPPING = 'DROPPING';
    public const CREATING = 'CREATING';
    public const LOADING = 'LOADING';
    public const RESTORING_METADATA = 'RESTORING_METADATA';
    public const VERIFYING = 'VERIFYING';
    public const COMPLETED = 'COMPLETED';

    private const STAGES = [
        self::PREPARED,
        self::LOCKED,
        self::DROPPING,
        self::CREATING,
        self::LOADING,
        self::RESTORING_METADATA,
        self::VERIFYING,
        self::COMPLETED,
    ];

    /** @var array<int, string> */
    private array $providerCreatedObjectScope;

    /** @param array<int, string> $providerCreatedObjectScope */
    public function __construct(
        private RecoveryIdentity $recoveryIdentity,
        private string $attemptIdentity,
        private string $expectedDatabaseStateIdentity,
        private string $expectedTableSetIdentity,
        private string $durableStage,
        array $providerCreatedObjectScope,
        private mixed $stageRecorder = null
    ) {
        if ($attemptIdentity === '' || trim($attemptIdentity) !== $attemptIdentity || preg_match('/^[A-Za-z0-9._-]{1,128}$/D', $attemptIdentity) !== 1) {
            throw new DatabaseRecoveryException('Database restore attempt identity is invalid.');
        }
        foreach ([$expectedDatabaseStateIdentity, $expectedTableSetIdentity] as $identity) {
            if (preg_match('/^[a-f0-9]{64}$/D', strtolower($identity)) !== 1) {
                throw new DatabaseRecoveryException('Database restore target identity is invalid.');
            }
        }
        if (!in_array($durableStage, self::STAGES, true)) {
            throw new DatabaseRecoveryException('Database restore stage is invalid.');
        }
        $scope = [];
        foreach ($providerCreatedObjectScope as $name) {
            if (!is_string($name) || preg_match('/^[A-Za-z0-9_]+$/D', $name) !== 1 || isset($scope[$name])) {
                throw new DatabaseRecoveryException('Database restore object scope is invalid.');
            }
            $scope[$name] = true;
        }
        ksort($scope, SORT_STRING);
        $this->providerCreatedObjectScope = array_keys($scope);
        if ($stageRecorder !== null && !is_callable($stageRecorder)) {
            throw new DatabaseRecoveryException('Database restore stage recorder is invalid.');
        }
    }

    public function recoveryIdentity(): RecoveryIdentity { return $this->recoveryIdentity; }
    public function attemptIdentity(): string { return $this->attemptIdentity; }
    public function expectedDatabaseStateIdentity(): string { return $this->expectedDatabaseStateIdentity; }
    public function expectedTableSetIdentity(): string { return $this->expectedTableSetIdentity; }
    public function durableStage(): string { return $this->durableStage; }
    /** @return array<int, string> */
    public function providerCreatedObjectScope(): array { return $this->providerCreatedObjectScope; }

    public function provesDestructiveRestoreBegan(): bool
    {
        return in_array($this->durableStage, [self::DROPPING, self::CREATING, self::LOADING, self::RESTORING_METADATA, self::VERIFYING, self::COMPLETED], true);
    }

    public function recordStage(string $stage): void
    {
        if (!in_array($stage, self::STAGES, true)) {
            throw new DatabaseRecoveryException('Database restore stage is invalid.');
        }
        if ($this->stageRecorder === null || ($this->stageRecorder)($stage) === false) {
            throw new DatabaseRecoveryException('Database restore stage was not durably recorded.');
        }
    }
}
