<?php

namespace Copot\Core;

/** The single shared authority for typed v3 destructive confirmation. */
final class DestructivePlanConfirmationAuthority
{
    /** @param callable(DestructiveActorAuthorizationEvidence, DestructivePlanConfirmation, V3SourceRelativePlan):bool $actorVerifier
     *  @param callable(V3SourceRelativePlan):bool|null $planVerifier */
    public function __construct(
        private LifecycleOperationStore $operations,
        private $actorVerifier,
        private $planVerifier = null,
        private $clock = null
    ) {
        if (!is_callable($actorVerifier) || ($planVerifier !== null && !is_callable($planVerifier))) { throw new \InvalidArgumentException('Confirmation authority verifier is invalid.'); }
    }

    public function accept(DestructivePlanConfirmation $confirmation, V3SourceRelativePlan $plan): DestructivePlanConfirmationReceipt
    {
        $record = $this->eligibleRecord($confirmation, $plan);
        if ($record->destructiveConfirmationIdentity() !== null) { throw new \RuntimeException('Destructive plan confirmation has already been accepted.'); }
        $this->verifyActor($confirmation, $plan);
        $acceptedAt = $this->now();
        $updated = $record->withDestructiveConfirmation($confirmation->bindingIdentity(), $confirmation->actorAuthorizationEvidence()->actorIdentity(), $confirmation->removalSetDigest(), $acceptedAt);
        $this->operations->save($updated);
        $saved = $this->operations->read();
        if (!$saved instanceof LifecycleOperationRecord || $saved->destructiveConfirmationIdentity() !== $confirmation->bindingIdentity() || $saved->destructiveConfirmationRemovalSetDigest() !== $confirmation->removalSetDigest()) { throw new \RuntimeException('Destructive confirmation persistence verification failed.'); }
        return new DestructivePlanConfirmationReceipt($confirmation, $acceptedAt);
    }

    public function verify(DestructivePlanConfirmation $confirmation, V3SourceRelativePlan $plan): bool
    {
        $record = $this->eligibleRecord($confirmation, $plan);
        if ($record->destructiveConfirmationIdentity() !== $confirmation->bindingIdentity() || $record->destructiveConfirmationConsumedAt() !== null) { return false; }
        try { $this->verifyActor($confirmation, $plan); return true; } catch (\Throwable) { return false; }
    }

    /** Atomically marks accepted proof consumed; it performs no destructive mutation. */
    public function consume(DestructivePlanConfirmation $confirmation, V3SourceRelativePlan $plan): bool
    {
        if (!$this->verify($confirmation, $plan)) { return false; }
        $record = $this->operations->read();
        if (!$record instanceof LifecycleOperationRecord) { return false; }
        $updated = $record->withDestructiveConfirmationConsumed($this->now());
        $this->operations->save($updated);
        $saved = $this->operations->read();
        return $saved instanceof LifecycleOperationRecord && $saved->destructiveConfirmationConsumedAt() !== null;
    }

    private function eligibleRecord(DestructivePlanConfirmation $confirmation, V3SourceRelativePlan $plan): LifecycleOperationRecord
    {
        if ($plan->removalActions() === []) { throw new \RuntimeException('Destructive confirmation requires a non-empty removal set.'); }
        if ($confirmation->operationIdentity() !== $plan->operationIdentity() || $confirmation->planIdentity() !== $plan->identity() || $confirmation->removalSetDigest() !== $plan->removalSetDigest()) { throw new \RuntimeException('Destructive confirmation does not match the prepared plan.'); }
        if ($this->planVerifier !== null && !(($this->planVerifier)($plan))) { throw new \RuntimeException('Prepared plan or source state is stale.'); }
        $record = $this->operations->read();
        if (!$record instanceof LifecycleOperationRecord || $record->operationId() !== $confirmation->operationIdentity() || !in_array($record->phase(), [LifecycleOperationRecord::PREPARING, LifecycleOperationRecord::BLOCKED], true) || $record->fileCursor() !== 0 || $record->lastVerifiedPath() !== null) { throw new \RuntimeException('Lifecycle operation is not eligible for destructive confirmation.'); }
        return $record;
    }

    private function verifyActor(DestructivePlanConfirmation $confirmation, V3SourceRelativePlan $plan): void
    { if (!(($this->actorVerifier)($confirmation->actorAuthorizationEvidence(), $confirmation, $plan))) { throw new \RuntimeException('Actor is not currently authorized for this destructive plan.'); } }
    private function now(): string { $value = is_callable($this->clock) ? ($this->clock)() : null; return $value instanceof \DateTimeImmutable ? $value->format(DATE_ATOM) : gmdate(DATE_ATOM); }
}
