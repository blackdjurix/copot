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
    /**
     * Read-only pre-capture proof for a continuation. This deliberately runs
     * before recovery capture so live-tree drift cannot create recovery state
     * for a package that is already ineligible to mutate.
     */
    public function verifyPreMutationState(V3SourceRelativePlan $plan, StagedPayload $payload, ?SourceInventoryArtifact $source, LiveTreePathGuard $guard): void
    {
        $this->assertProtectedMutationFree($plan);
        $this->verifyPayload($plan, $payload);
        $this->verifySourceState($plan, $source, $guard);

        $sourceIndex = [];
        if ($source instanceof SourceInventoryArtifact) {
            foreach ($source->inventory()->entries() as $entry) {
                $sourceIndex[strtolower($entry->root() . "\0" . $entry->path())] = $entry;
            }
        }
        foreach ($plan->actions() as $action) {
            $path = $this->relativePath($action->root(), $action->path());
            $destination = $guard->destination($path);
            if ($action->action() === V3SourceRelativeAction::DELIVER) {
                if (file_exists($destination) || is_link($destination)) {
                    throw new \RuntimeException('V3 add target already exists or is a link.');
                }
                continue;
            }
            if ($action->action() !== V3SourceRelativeAction::REPLACE) {
                continue;
            }
            $sourceRoot = $action->sourceRoot() ?? $action->root();
            $sourcePath = $action->sourcePath() ?? $action->path();
            $entry = $sourceIndex[strtolower($sourceRoot . "\0" . $sourcePath)] ?? null;
            $sourcePath = $this->relativePath($sourceRoot, $sourcePath);
            $sourceDestination = $guard->destination($sourcePath);
            if (!$entry instanceof CanonicalTargetInventoryEntry
                || !is_file($sourceDestination)
                || is_link($sourceDestination)
                || @filesize($sourceDestination) !== $entry->byteSize()
                || @hash_file('sha256', $sourceDestination) !== $entry->sha256()) {
                throw new \RuntimeException('V3 replacement target does not match the committed source state.');
            }
            if ($sourceDestination !== $destination) {
                throw new \RuntimeException('V3 replacement source and target paths are inconsistent.');
            }
        }
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
        ?callable $afterActivation = null,
        bool $allowConsumedContinuation = false
    ): WebcoreApplyResult {
        $mutationStarted = false;
        try {
            $this->assertProtectedMutationFree($plan);
            $this->verifyOperation($plan, $operation);
            $this->verifyPayload($plan, $payload);
            $this->verifySourceState($plan, $source, $guard);

            $hasRemovals = $plan->removalActions() !== [];
            if ($hasRemovals && !$confirmationAuthority instanceof DestructivePlanConfirmationAuthority) {
                return new WebcoreApplyResult(WebcoreApplyResult::BLOCKED, [], 'Typed destructive confirmation is required.');
            }
            if (!$captureBeforeMutation($plan, $operation)) {
                return new WebcoreApplyResult(WebcoreApplyResult::BLOCKED, [], 'Recovery capture was not durably verified.');
            }

            // Consume the typed proof while the operation is still at its
            // pre-mutation boundary. The receipt is single-use even when a
            // later add or verification fails and a new retry is required.
            if ($hasRemovals) {
                if ($confirmation instanceof DestructivePlanConfirmation) {
                    if (!$confirmationAuthority->consume($confirmation, $plan)) {
                        return new WebcoreApplyResult(WebcoreApplyResult::BLOCKED, [], 'Destructive confirmation is stale or already consumed.');
                    }
                } elseif (!$allowConsumedContinuation || !$confirmationAuthority->verifyConsumedContinuation($operation, $plan)) {
                    return new WebcoreApplyResult(WebcoreApplyResult::BLOCKED, [], 'Destructive confirmation is required.');
                }
            }

            $delivered = $this->deliveredPayload($plan, $payload);
            if ($delivered->files() !== []) {
                $mutationStarted = true;
                $delivery = $deliverer->apply(
                    WebcoreApplyPlan::fromPayload($delivered),
                    function (int $cursor, string $path) use ($operations, $operation): void {
                        $this->recordProgress($operations, $operation, $cursor, $path);
                    },
                    $beforeActivation,
                    $afterActivation
                );
                if ($delivery->status() !== WebcoreApplyResult::COMPLETED) {
                    $status = $delivery->appliedPaths() === [] ? WebcoreApplyResult::BLOCKED : WebcoreApplyResult::INDETERMINATE;
                    return new WebcoreApplyResult($status, $delivery->appliedPaths(), $delivery->reason());
                }
                foreach ($delivery->appliedPaths() as $path) {
                    $this->recordProgress($operations, $operation, count($delivery->appliedPaths()), $path);
                    if ($journal !== null) { $journal('deliver', $path, count($delivery->appliedPaths())); }
                }
            }

            if ($hasRemovals) {
                $removed = 0;
                foreach ($plan->removalActions() as $action) {
                    $mutationStarted = true;
                    $this->removeOne($action, $guard);
                    $removed++;
                    $path = $this->relativePath($action['source_root'], $action['source_path']);
                    $this->recordProgress($operations, $operation, count($plan->actions()) + $removed, $path);
                    if ($journal !== null) { $journal($action['kind'] === PackageRemovalSet::RENAME_SOURCE_REMOVAL ? 'rename-source-remove' : 'remove', $path, $removed); }
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

    /** Validate the immutable deployment-owned boundary before any mutation. */
    private function assertProtectedMutationFree(V3SourceRelativePlan $plan): void
    {
        foreach ($plan->actions() as $action) {
            if (!$action instanceof V3SourceRelativeAction) {
                throw new \RuntimeException('V3 action is invalid.');
            }
            if ($action->action() === V3SourceRelativeAction::RETAIN) {
                continue;
            }
            $sourceAction = in_array($action->action(), [V3SourceRelativeAction::REMOVE, V3SourceRelativeAction::RENAME], true);
            ProtectedResourcePolicy::assertMutable(
                $sourceAction ? $action->sourceRoot() : $action->root(),
                $sourceAction ? $action->sourcePath() : $action->path()
            );
            if (in_array($action->action(), [V3SourceRelativeAction::RENAME, V3SourceRelativeAction::REPLACE], true)) {
                ProtectedResourcePolicy::assertMutable($action->root(), $action->path());
            }
        }
        foreach ($plan->removalActions() as $action) {
            if (!is_array($action) || !isset($action['source_root'], $action['source_path'])) {
                throw new \RuntimeException('V3 removal action identity is unavailable.');
            }
            ProtectedResourcePolicy::assertMutable($action['source_root'], $action['source_path']);
            if (($action['target_root'] ?? '') !== '' || ($action['target_path'] ?? '') !== '') {
                ProtectedResourcePolicy::assertMutable($action['target_root'], $action['target_path']);
            }
        }
    }

    private function verifyOperation(V3SourceRelativePlan $plan, LifecycleOperationRecord $operation): void
    {
        if (!in_array($operation->phase(), [LifecycleOperationRecord::PREPARING, LifecycleOperationRecord::BLOCKED], true)
            || $operation->fileCursor() !== 0 || $operation->lastVerifiedPath() !== null) {
            throw new \RuntimeException('V3 operation is stale or already progressed.');
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
        ksort($expected, SORT_STRING);
        ksort($actual, SORT_STRING);
        if ($actual !== $expected) { throw new \RuntimeException('Delivered payload does not exactly match the v3 plan.'); }
    }

    private function deliveredPayload(V3SourceRelativePlan $plan, StagedPayload $payload): StagedPayload
    {
        return $payload;
    }

    private function verifySourceState(V3SourceRelativePlan $plan, ?SourceInventoryArtifact $source, LiveTreePathGuard $guard): void
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
            $key = strtolower($action->root() . "\0" . $action->path());
            $entry = $sourceIndex[$key] ?? null;
            if (!$entry instanceof CanonicalTargetInventoryEntry || $entry->byteSize() !== $action->byteSize() || $entry->sha256() !== $action->sha256()) { throw new \RuntimeException('Committed source identity is unavailable or stale at ' . $action->root() . '/' . $action->path() . '.'); }
            $path = $this->relativePath($action->root(), $action->path());
            $destination = $guard->destination($path);
            if (!is_file($destination) || is_link($destination) || @filesize($destination) !== $action->byteSize() || @hash_file('sha256', $destination) !== $action->sha256()) { throw new \RuntimeException('Live source identity is unavailable or stale at ' . $action->root() . '/' . $action->path() . ' (' . $destination . ').'); }
        }
    }

    private function removeOne(array $action, LiveTreePathGuard $guard): void
    {
        if (array_keys($action) !== ['kind', 'reason', 'source_root', 'source_path', 'target_root', 'target_path', 'expected_source_byte_size', 'expected_source_sha256']) { throw new \RuntimeException('Removal action shape is invalid.'); }
        ProtectedResourcePolicy::assertMutable($action['source_root'], $action['source_path']);
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

    private function appliedPaths(V3SourceRelativePlan $plan): array
    { return array_values(array_map(static fn (V3SourceRelativeAction $action): string => $action->path(), array_filter($plan->actions(), static fn (V3SourceRelativeAction $action): bool => in_array($action->action(), [V3SourceRelativeAction::DELIVER, V3SourceRelativeAction::REPLACE], true)))); }
}
