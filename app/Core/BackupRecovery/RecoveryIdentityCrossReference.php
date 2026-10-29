<?php

namespace Copot\Core\BackupRecovery;

use Copot\Core\LifecycleExecutionAttemptIdentity;

/** Immutable bridge between lifecycle-operation and recovery-set identities. */
final class RecoveryIdentityCrossReference
{
    private string $identity;

    public function __construct(
        private string $operationIdentity,
        private RecoveryIdentity $recoveryIdentity,
        private string $installationIdentity,
        private string $packageIdentity,
        private string $applyPlanIdentity,
        private DatabaseScope $databaseScope,
        private string $targetIdentity,
        private ?string $executionAttemptIdentity = null
    ) {
        self::opaque($operationIdentity, 'Lifecycle operation identity');
        self::opaque($installationIdentity, 'Installation identity');
        self::opaque($packageIdentity, 'Package identity');
        self::hash($applyPlanIdentity, 'Apply-plan identity');
        self::opaque($targetIdentity, 'Target identity');
        if ($executionAttemptIdentity !== null) { LifecycleExecutionAttemptIdentity::assert($executionAttemptIdentity); }
        if ($databaseScope->installationIdentity() !== $installationIdentity) {
            throw new RecoveryLifecycleException('Recovery identity cross-reference scope is bound to another installation.');
        }
        $data = [
            'operation_identity' => $operationIdentity,
            'recovery_identity' => $recoveryIdentity->value(),
            'installation_identity' => $installationIdentity,
            'package_identity' => $packageIdentity,
            'apply_plan_identity' => $applyPlanIdentity,
            'database_scope_identity' => $databaseScope->identity(),
            'target_identity' => $targetIdentity,
        ];
        if ($executionAttemptIdentity !== null) { $data['execution_attempt_identity'] = $executionAttemptIdentity; }
        $this->identity = hash('sha256', json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    public function identity(): string { return $this->identity; }
    public function operationIdentity(): string { return $this->operationIdentity; }
    public function recoveryIdentity(): RecoveryIdentity { return $this->recoveryIdentity; }
    public function installationIdentity(): string { return $this->installationIdentity; }
    public function packageIdentity(): string { return $this->packageIdentity; }
    public function applyPlanIdentity(): string { return $this->applyPlanIdentity; }
    public function databaseScope(): DatabaseScope { return $this->databaseScope; }
    public function targetIdentity(): string { return $this->targetIdentity; }
    public function executionAttemptIdentity(): ?string { return $this->executionAttemptIdentity; }

    public function matchesEvidence(ApplicationWriteProtectionEvidence $application, ScopedDatabaseConsistencyEvidence $database): bool
    {
        return $application->operationIdentity()->equals($this->recoveryIdentity)
            && $database->operationIdentity()->equals($this->recoveryIdentity)
            && $application->scope()->identity() === $this->databaseScope->identity()
            && $database->scope()->identity() === $this->databaseScope->identity()
            && $application->isValidAt()
            && $database->isValidAt();
    }

    public function toArray(): array
    {
        return [
            'cross_reference_identity' => $this->identity,
            'operation_identity' => $this->operationIdentity,
            'recovery_identity' => $this->recoveryIdentity->value(),
            'installation_identity' => $this->installationIdentity,
            'package_identity' => $this->packageIdentity,
            'apply_plan_identity' => $this->applyPlanIdentity,
            'database_scope_identity' => $this->databaseScope->identity(),
            'database_identity' => $this->databaseScope->databaseIdentity(),
            'namespace_identity' => $this->databaseScope->namespaceIdentity(),
            'target_identity' => $this->targetIdentity,
            ...($this->executionAttemptIdentity === null ? [] : ['execution_attempt_identity' => $this->executionAttemptIdentity]),
        ];
    }

    private static function opaque(string $value, string $label): void
    {
        if ($value === '' || trim($value) !== $value || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) throw new RecoveryLifecycleException($label . ' is invalid.');
    }

    private static function hash(string $value, string $label): void
    {
        if (preg_match('/^[a-f0-9]{64}$/D', strtolower($value)) !== 1) throw new RecoveryLifecycleException($label . ' is invalid.');
    }
}
