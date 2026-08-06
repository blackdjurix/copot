<?php

namespace Copot\Core\BackupRecovery;

use DateTimeImmutable;

final class RecoveryLifecycleRecord
{
    public function __construct(
        private RecoveryIdentity $recoveryIdentity,
        private string $manifestIdentity,
        private string $operationIdentity,
        private string $state,
        private bool $mutationStarted = false,
        private bool $captureComplete = false,
        private ?string $confirmationRecoveryIdentity = null,
        private ?string $confirmationManifestIdentity = null,
        private ?string $confirmationTargetIdentity = null,
        private ?string $restoreAttemptIdentity = null,
        private ?string $restoreStage = null,
        private string $failureClass = '',
        private string $failureReason = '',
        private ?string $createdAt = null,
        private ?string $updatedAt = null
    ) {
        self::hash($manifestIdentity, 'Manifest identity');
        self::opaque($operationIdentity, 'Operation identity');
        RecoveryLifecycleState::assert($state);
        foreach ([$confirmationRecoveryIdentity, $confirmationManifestIdentity, $confirmationTargetIdentity] as $identity) {
            if ($identity !== null && $identity === '') { throw new RecoveryLifecycleException('Recovery confirmation is invalid.'); }
        }
        if ($confirmationRecoveryIdentity !== null) { self::opaque($confirmationRecoveryIdentity, 'Confirmation recovery identity'); }
        if ($confirmationManifestIdentity !== null) { self::hash($confirmationManifestIdentity, 'Confirmation manifest identity'); }
        if ($restoreAttemptIdentity !== null) { self::opaque($restoreAttemptIdentity, 'Restore attempt identity'); }
        if ($restoreStage !== null) { self::opaque($restoreStage, 'Restore stage'); }
        if (strlen($failureClass) > 128 || strlen($failureReason) > 1024 || preg_match('/[\x00-\x1F\x7F]/', $failureClass . $failureReason) === 1) {
            throw new RecoveryLifecycleException('Recovery failure information is invalid.');
        }
        $this->createdAt ??= gmdate(DATE_ATOM);
        $this->updatedAt ??= $this->createdAt;
        foreach ([$this->createdAt, $this->updatedAt] as $timestamp) {
            if (DateTimeImmutable::createFromFormat(DATE_ATOM, $timestamp) === false) { throw new RecoveryLifecycleException('Recovery lifecycle timestamp is invalid.'); }
        }
    }

    public static function fromArray(array $data): self
    {
        $required = ['recovery_identity','manifest_identity','operation_identity','state','mutation_started','capture_complete','confirmation_recovery_identity','confirmation_manifest_identity','confirmation_target_identity','restore_attempt_identity','restore_stage','failure_class','failure_reason','created_at','updated_at'];
        if (array_keys($data) !== $required || !is_bool($data['mutation_started']) || !is_bool($data['capture_complete'])) {
            throw new RecoveryLifecycleException('Recovery lifecycle record format is invalid.');
        }
        return new self(new RecoveryIdentity($data['recovery_identity']), ...array_values(array_slice($data, 1)));
    }

    public function toArray(): array
    {
        return ['recovery_identity'=>$this->recoveryIdentity->value(),'manifest_identity'=>$this->manifestIdentity,'operation_identity'=>$this->operationIdentity,'state'=>$this->state,'mutation_started'=>$this->mutationStarted,'capture_complete'=>$this->captureComplete,'confirmation_recovery_identity'=>$this->confirmationRecoveryIdentity,'confirmation_manifest_identity'=>$this->confirmationManifestIdentity,'confirmation_target_identity'=>$this->confirmationTargetIdentity,'restore_attempt_identity'=>$this->restoreAttemptIdentity,'restore_stage'=>$this->restoreStage,'failure_class'=>$this->failureClass,'failure_reason'=>$this->failureReason,'created_at'=>$this->createdAt,'updated_at'=>$this->updatedAt];
    }

    public function recoveryIdentity(): RecoveryIdentity { return $this->recoveryIdentity; }
    public function manifestIdentity(): string { return $this->manifestIdentity; }
    public function operationIdentity(): string { return $this->operationIdentity; }
    public function state(): string { return $this->state; }
    public function mutationStarted(): bool { return $this->mutationStarted; }
    public function captureComplete(): bool { return $this->captureComplete; }
    public function restoreAttemptIdentity(): ?string { return $this->restoreAttemptIdentity; }
    public function restoreStage(): ?string { return $this->restoreStage; }
    public function failureClass(): string { return $this->failureClass; }
    public function confirmationMatches(RecoveryIdentity $recovery, string $manifest, string $target): bool
    { return $this->confirmationRecoveryIdentity === $recovery->value() && $this->confirmationManifestIdentity === $manifest && $this->confirmationTargetIdentity === $target; }

    public function transition(string $state, string $reason = ''): self
    {
        if (!RecoveryLifecycleState::canTransition($this->state, $state)) { throw new RecoveryLifecycleException('Illegal recovery lifecycle transition.'); }
        return $this->copy($state, $this->mutationStarted, $this->captureComplete, $this->confirmationRecoveryIdentity, $this->confirmationManifestIdentity, $this->confirmationTargetIdentity, $this->restoreAttemptIdentity, $this->restoreStage, $this->failureClass, $reason);
    }

    public function withCaptureComplete(): self { return $this->copy($this->state, $this->mutationStarted, true, $this->confirmationRecoveryIdentity, $this->confirmationManifestIdentity, $this->confirmationTargetIdentity, $this->restoreAttemptIdentity, $this->restoreStage, $this->failureClass, $this->failureReason); }
    public function withConfirmation(RecoveryIdentity $recovery, string $manifest, string $target): self
    { self::hash($manifest, 'Confirmation manifest identity'); return $this->copy($this->state, $this->mutationStarted, $this->captureComplete, $recovery->value(), $manifest, $target, $this->restoreAttemptIdentity, $this->restoreStage, $this->failureClass, $this->failureReason); }
    public function withMutationStarted(): self { return $this->copy($this->state, true, $this->captureComplete, $this->confirmationRecoveryIdentity, $this->confirmationManifestIdentity, $this->confirmationTargetIdentity, $this->restoreAttemptIdentity, $this->restoreStage, $this->failureClass, $this->failureReason); }
    public function withRestoreStage(string $attempt, string $stage): self { return $this->copy($this->state, $this->mutationStarted, $this->captureComplete, $this->confirmationRecoveryIdentity, $this->confirmationManifestIdentity, $this->confirmationTargetIdentity, $attempt, $stage, $this->failureClass, $this->failureReason); }

    private function copy(string $state, bool $mutation, bool $capture, ?string $confirmRecovery, ?string $confirmManifest, ?string $confirmTarget, ?string $attempt, ?string $stage, string $failureClass, string $reason): self
    { return new self($this->recoveryIdentity, $this->manifestIdentity, $this->operationIdentity, $state, $mutation, $capture, $confirmRecovery, $confirmManifest, $confirmTarget, $attempt, $stage, $failureClass, $reason, $this->createdAt, gmdate(DATE_ATOM)); }
    private static function opaque(string $value, string $label): void { if ($value === '' || trim($value) !== $value || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) throw new RecoveryLifecycleException($label . ' is invalid.'); }
    private static function hash(string $value, string $label): void { if (preg_match('/^[a-f0-9]{64}$/D', strtolower($value)) !== 1) throw new RecoveryLifecycleException($label . ' is invalid.'); }
}
