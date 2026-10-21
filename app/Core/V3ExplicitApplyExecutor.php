<?php

namespace Copot\Core;

/**
 * Executes an already prepared v3 source-relative plan.
 *
 * Planning, confirmation, and recovery capture remain owned by their existing
 * services. This class is deliberately an execution adapter: it accepts a
 * capture callback so the existing Backup & Recovery boundary can durably
 * capture the original bytes before any removal is attempted.
 */
final class V3ExplicitApplyExecutor
{
    /** Validates mutation evidence without crossing the recovery mutation boundary. */
    public function assertMutationPreconditions(
        V3SourceRelativePlan $plan,
        StagedPayload $payload,
        ?SourceInventoryArtifact $source,
        LifecycleOperationRecord $operation,
        LiveTreePathGuard $guard
    ): void {
        $this->verifyOperation($plan, $operation);
        $this->verifySourceState($plan, $source, $guard, true);
        $this->verifyTargetPreconditions($plan, $guard);
        $this->verifyPayload($plan, $this->deliveredPayload($plan, $payload));
    }

    /**
     * @param callable(V3SourceRelativePlan, LifecycleOperationRecord):bool $captureBeforeMutation
     * @param callable(string, string, int):void|null $journal
     */
    public function execute(
        V3SourceRelativePlan $plan,
        StagedPayload $payload,
        ?SourceInventoryArtifact $source,
        LifecycleOperationRecord $operation,
        LifecycleOperationStore $operations,
        PackageOwnedFileApplier $deliverer,
        LiveTreePathGuard $guard,
        callable $captureBeforeMutation,
        ?DestructivePlanConfirmationAuthority $confirmationAuthority = null,
        ?DestructivePlanConfirmation $confirmation = null,
        ?callable $journal = null,
        ?callable $beforeActivation = null,
        ?LegacyBootstrapV3ExecutionContext $bootstrap = null,
        bool $requireTargetPreconditions = false
    ): WebcoreApplyResult {
        $mutationStarted = false;
        try {
            $this->verifyOperation($plan, $operation, $bootstrap);
            $this->verifySourceState($plan, $source, $guard, $requireTargetPreconditions);
            if ($requireTargetPreconditions) {
                $this->verifyTargetPreconditions($plan, $guard);
            }

            $hasRemovals = $plan->removalActions() !== [];
            if ($hasRemovals && (!$confirmationAuthority instanceof DestructivePlanConfirmationAuthority || !$confirmation instanceof DestructivePlanConfirmation)) {
                return new WebcoreApplyResult(WebcoreApplyResult::BLOCKED, [], 'Typed destructive confirmation is required.');
            }
            if (!$captureBeforeMutation($plan, $operation)) {
                return new WebcoreApplyResult(WebcoreApplyResult::BLOCKED, [], 'Recovery capture was not durably verified.');
            }

            // Consume the typed proof while the operation is still at its
            // pre-mutation boundary. The receipt is single-use even when a
            // later add or verification fails and a new retry is required.
            if ($hasRemovals && !$confirmationAuthority->consume($confirmation, $plan)) {
                return new WebcoreApplyResult(WebcoreApplyResult::BLOCKED, [], 'Destructive confirmation is stale or already consumed.');
            }

            $delivered = $this->deliveredPayload($plan, $payload);
            $this->verifyPayload($plan, $delivered);
            if ($delivered->files() !== []) {
                $mutationStarted = true;
                $delivery = $deliverer->apply(WebcoreApplyPlan::fromPayload($delivered), null, $beforeActivation);
                if ($delivery->status() !== WebcoreApplyResult::COMPLETED) {
                    $status = $delivery->appliedPaths() === [] ? WebcoreApplyResult::BLOCKED : WebcoreApplyResult::INDETERMINATE;
                    return new WebcoreApplyResult($status, $delivery->appliedPaths(), $delivery->reason());
                }
                foreach ($delivery->appliedPaths() as $path) {
                    $ordinal = $this->actionOrdinal($plan, $path);
                    $this->recordProgress($operations, $operation, $ordinal, $path);
                    if ($journal !== null) { $journal('deliver', $path, $ordinal); }
                }
            }

            if ($hasRemovals) {
                $removed = 0;
                foreach ($plan->removalActions() as $action) {
                    $mutationStarted = true;
                    $this->removeOne($action, $guard);
                    $removed++;
                    $path = $this->relativePath($action['source_root'], $action['source_path']);
                    $ordinal = $this->actionOrdinal($plan, $path);
                    $this->recordProgress($operations, $operation, $ordinal, $path);
                    if ($journal !== null) { $journal($action['kind'] === PackageRemovalSet::RENAME_SOURCE_REMOVAL ? 'rename-source-remove' : 'remove', $path, $ordinal); }
                }
            }

            $this->verifyTarget($plan, $guard);
            $latest = $operations->read();
            if ($latest instanceof LifecycleOperationRecord) {
                $operations->save($latest->advance(LifecycleOperationRecord::AWAITING_WU6, count($plan->actions()), $latest->lastVerifiedPath()));
            }
            return new WebcoreApplyResult(WebcoreApplyResult::AWAITING_WU6, $this->appliedPaths($plan), '', $operation->operationId());
        } catch (\Throwable $exception) {
            $latest = $operations->read();
            if ($latest instanceof LifecycleOperationRecord && !$latest->isTerminal()) {
                $phase = $mutationStarted || $latest->fileCursor() > 0 ? LifecycleOperationRecord::INDETERMINATE : LifecycleOperationRecord::BLOCKED;
                try { $operations->save($latest->advance($phase, $latest->fileCursor(), $latest->lastVerifiedPath(), null, $exception->getMessage())); } catch (\Throwable) { }
            }
            return new WebcoreApplyResult($mutationStarted || ($latest instanceof LifecycleOperationRecord && $latest->fileCursor() > 0) ? WebcoreApplyResult::INDETERMINATE : WebcoreApplyResult::BLOCKED, [], $exception->getMessage(), $operation->operationId());
        }
    }

    private function verifyOperation(V3SourceRelativePlan $plan, LifecycleOperationRecord $operation, ?LegacyBootstrapV3ExecutionContext $bootstrap = null): void
    {
        if (!in_array($operation->phase(), [LifecycleOperationRecord::PREPARING, LifecycleOperationRecord::BLOCKED], true)
            || $operation->fileCursor() !== 0 || $operation->lastVerifiedPath() !== null) {
            throw new \RuntimeException('V3 operation is stale or already progressed.');
        }
        if ($bootstrap instanceof LegacyBootstrapV3ExecutionContext) {
            if ($bootstrap->operation()->operationId() !== $operation->operationId()
                || $bootstrap->plan()->identity() !== $plan->identity()
                || !$bootstrap->recoveryBinding()->bound()) {
                throw new \RuntimeException('Bootstrap operation, V3 plan, or recovery binding identity does not match.');
            }
            return;
        }
        if ($operation->operationId() !== $plan->operationIdentity()) {
            throw new \RuntimeException('V3 operation identity does not match the prepared plan.');
        }
    }

    private function verifyPayload(V3SourceRelativePlan $plan, StagedPayload $payload): void
    {
        $expected = [];
        foreach ($plan->actions() as $action) {
            if (in_array($action->action(), [V3SourceRelativeAction::DELIVER, V3SourceRelativeAction::REPLACE], true)) {
                $expected[$this->relativePath($action->root(), $action->path())] = [$action->byteSize(), $action->sha256()];
            }
        }
        $actual = [];
        foreach ($payload->files() as $file) {
            $actual[$file->path()] = [$file->byteSize(), $file->sha256()];
        }
        if ($actual !== $expected) { throw new \RuntimeException('Delivered payload does not exactly match the v3 plan.'); }
    }

    private function deliveredPayload(V3SourceRelativePlan $plan, StagedPayload $payload): StagedPayload
    {
        $retained = array_map(
            static fn (V3SourceRelativeAction $action): string => $this->relativePath($action->root(), $action->path()),
            array_filter($plan->actions(), static fn (V3SourceRelativeAction $action): bool => $action->action() === V3SourceRelativeAction::RETAIN)
        );
        return $payload->withoutPaths($retained);
    }

    private function verifyTargetPreconditions(V3SourceRelativePlan $plan, LiveTreePathGuard $guard): void
    {
        foreach ($plan->actions() as $action) {
            if (!in_array($action->action(), [V3SourceRelativeAction::DELIVER, V3SourceRelativeAction::REPLACE, V3SourceRelativeAction::RETAIN], true)) {
                continue;
            }
            $state = $action->expectedTargetState();
            if ($state === null) {
                throw new \RuntimeException('Mutation action is missing expected target state evidence.');
            }
            $path = $this->relativePath($action->root(), $action->path());
            $destination = $guard->verifyDestination($path, $state === 'PRESENT');
            $exists = is_file($destination) && !is_link($destination);
            if ($state === 'ABSENT' && $exists) {
                throw new \RuntimeException('Mutation CREATE target is no longer absent.');
            }
            if ($state === 'PRESENT' && (!$exists || @filesize($destination) !== $action->expectedTargetByteSize() || @hash_file('sha256', $destination) !== $action->expectedTargetSha256())) {
                throw new \RuntimeException('Mutation target original identity changed before activation.');
            }
        }
    }

    private function verifySourceState(V3SourceRelativePlan $plan, ?SourceInventoryArtifact $source, LiveTreePathGuard $guard, bool $allowTargetEvidence = false): void
    {
        if ($plan->sourceReference() !== null && (!$source instanceof SourceInventoryArtifact || $source->reference()->toArray() !== $plan->sourceReference()->toArray())) {
            throw new \RuntimeException('Committed source inventory reference does not match the plan.');
        }
        $sourceIndex = [];
        if ($source instanceof SourceInventoryArtifact) {
            foreach ($source->inventory()->entries() as $entry) { $sourceIndex[strtolower($entry->root() . "\0" . $entry->path())] = $entry; }
        }
        foreach ($plan->actions() as $action) {
            if (!in_array($action->action(), [V3SourceRelativeAction::RETAIN, V3SourceRelativeAction::REMOVE, V3SourceRelativeAction::RENAME], true)) { continue; }
            if ($allowTargetEvidence && $source === null && $action->expectedTargetState() === 'PRESENT') { continue; }
            $key = strtolower($action->root() . "\0" . $action->path());
            $entry = $sourceIndex[$key] ?? null;
            if (!$entry instanceof CanonicalTargetInventoryEntry || $entry->byteSize() !== $action->byteSize() || $entry->sha256() !== $action->sha256()) { throw new \RuntimeException('Committed source identity is unavailable or stale.'); }
            $path = $this->relativePath($action->root(), $action->path());
            $destination = $guard->destination($path);
            if (!is_file($destination) || is_link($destination) || @filesize($destination) !== $action->byteSize() || @hash_file('sha256', $destination) !== $action->sha256()) { throw new \RuntimeException('Live source identity is unavailable or stale.'); }
        }
    }

    private function removeOne(array $action, LiveTreePathGuard $guard): void
    {
        if (array_keys($action) !== ['kind', 'reason', 'source_root', 'source_path', 'target_root', 'target_path', 'expected_source_byte_size', 'expected_source_sha256']) { throw new \RuntimeException('Removal action shape is invalid.'); }
        $path = $this->relativePath($action['source_root'], $action['source_path']);
        $destination = $guard->destination($path);
        if (!is_file($destination) || is_link($destination) || @filesize($destination) !== $action['expected_source_byte_size'] || @hash_file('sha256', $destination) !== $action['expected_source_sha256']) { throw new \RuntimeException('Removal source changed before mutation.'); }
        if (!@unlink($destination) || file_exists($destination) || is_link($destination)) { throw new \RuntimeException('Explicit removal could not be verified.'); }
    }

    private function verifyTarget(V3SourceRelativePlan $plan, LiveTreePathGuard $guard): void
    {
        foreach ($plan->package()->targetInventory()->entries() as $entry) {
            $path = $this->relativePath($entry->root(), $entry->path());
            $destination = $guard->destination($path);
            if (!is_file($destination) || is_link($destination) || @filesize($destination) !== $entry->byteSize() || @hash_file('sha256', $destination) !== $entry->sha256()) { throw new \RuntimeException('Target inventory verification failed after mutation.'); }
        }
    }

    private function relativePath(string $root, string $path): string
    { return $root === CanonicalTargetInventoryEntry::PUBLIC_ROOT ? 'public/' . $path : $path; }

    private function recordProgress(LifecycleOperationStore $operations, LifecycleOperationRecord $operation, int $cursor, string $path): void
    {
        $latest = $operations->read();
        if ($latest instanceof LifecycleOperationRecord) { $operations->save($latest->advance(LifecycleOperationRecord::APPLYING, $cursor, $path)); }
    }

    private function actionOrdinal(V3SourceRelativePlan $plan, string $relativePath): int
    {
        foreach (array_values($plan->actions()) as $index => $action) {
            if ($this->relativePath($action->root(), $action->path()) === $relativePath) {
                return $index + 1;
            }
        }
        throw new \RuntimeException('Mutation progress path is not present in the immutable action plan.');
    }

    private function appliedPaths(V3SourceRelativePlan $plan): array
    { return array_values(array_map(static fn (V3SourceRelativeAction $action): string => $action->path(), array_filter($plan->actions(), static fn (V3SourceRelativeAction $action): bool => in_array($action->action(), [V3SourceRelativeAction::DELIVER, V3SourceRelativeAction::REPLACE], true)))); }
}
