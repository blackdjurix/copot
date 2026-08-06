<?php

namespace Copot\Core;

use Copot\Core\BackupRecovery\RecoveryLifecycleState;
use Copot\Core\BackupRecovery\RecoveryIdentity;
use Copot\Core\BackupRecovery\RecoveryLifecycleStore;
use Copot\Core\BackupRecovery\RecoveryManifest;

final class LegacyReconciliationFilesystemConverger
{
    public function __construct(
        private RecoveryLifecycleStore $recoveryStore,
        private PackageOwnedFileApplier $applier,
        private LiveTreePathGuard $liveGuard
    ) {}

    /**
     * Applies only the immutable WU1 package-owned filesystem actions.
     * The optional progress callback is testable downstream observation only;
     * it cannot replace the accepted WU5 apply boundary.
     */
    public function converge(
        LegacyReconciliationPlan $plan,
        RecoveryManifest $manifest,
        ReconciliationMutationEligibility $eligibility,
        StagedPayload $payload,
        ?callable $progress = null
    ): WebcoreApplyResult {
        $this->assertEligibility($plan, $manifest, $eligibility);
        $files = $this->validatePlanAndPayload($plan, $payload);
        $unchanged = [];
        $applicable = [];

        foreach ($plan->filesystemActions() as $action) {
            $destination = $this->liveGuard->destination($action->path());
            $exists = file_exists($destination) || is_link($destination);
            $this->liveGuard->verifyDestination($action->path(), false);

            if ($action->action() === FilesystemReconciliationAction::CREATE) {
                if ($exists) {
                    throw new \RuntimeException('CREATE action no longer matches the live package-owned state.');
                }
                $applicable[] = $action->path();
                continue;
            }

            if ($action->action() === FilesystemReconciliationAction::REPLACE) {
                if (!$exists) {
                    throw new \RuntimeException('REPLACE action no longer matches the live package-owned state.');
                }
                if ($this->matches($destination, $action)) {
                    $unchanged[] = $action->path();
                } elseif ($action->expectedLiveSha256() === null || @hash_file('sha256', $destination) !== $action->expectedLiveSha256()) {
                    throw new \RuntimeException('REPLACE action detected unexpected live drift.');
                } else {
                    $applicable[] = $action->path();
                }
                continue;
            }

            if ($action->action() !== FilesystemReconciliationAction::UNCHANGED || !$exists || !$this->matches($destination, $action)) {
                throw new \RuntimeException('UNCHANGED action no longer matches the live package-owned state.');
            }
            $unchanged[] = $action->path();
        }

        if ($applicable === []) {
            return new WebcoreApplyResult(WebcoreApplyResult::COMPLETED, []);
        }

        $applyPayload = $payload->withoutPaths($unchanged);
        $mutationStarted = false;

        try {
            $result = $this->applier->apply(
                WebcoreApplyPlan::fromPayload($applyPayload),
                $progress,
                function () use ($eligibility, &$mutationStarted): void {
                    $this->recoveryStore->markMutationStarting($eligibility->recoveryIdentity());
                    $mutationStarted = true;
                }
            );
        } catch (\Throwable $exception) {
            $this->markRecoveryRequired($eligibility->recoveryIdentity(), $exception->getMessage());
            throw $exception;
        }

        if ($result->status() !== WebcoreApplyResult::COMPLETED) {
            $this->markRecoveryRequired($eligibility->recoveryIdentity(), $result->reason());
        }

        return $result;
    }

    private function assertEligibility(
        LegacyReconciliationPlan $plan,
        RecoveryManifest $manifest,
        ReconciliationMutationEligibility $eligibility
    ): void {
        if (!$eligibility->isValid()
            || !$eligibility->recoveryIdentity()->equals($manifest->recoveryIdentity())
            || $eligibility->operationIdentity() !== $plan->operationIdentity()
            || $eligibility->planIdentity() !== $plan->identity()
            || $eligibility->manifestIdentity() !== $manifest->identity()
            || $eligibility->targetIdentity() !== $plan->target()->packageIdentity()
            || $manifest->operationIdentity() !== $plan->operationIdentity()
            || $manifest->targetPackageIdentity() !== $plan->target()->packageIdentity()
            || $manifest->targetReleaseIdentity() !== $plan->target()->contract()->releaseIdentity()
            || $manifest->archiveIdentity() !== $plan->target()->archiveIdentity()
            || $manifest->applyPlanIdentity() !== $plan->target()->payloadIdentity()) {
            throw new \RuntimeException('Filesystem convergence requires an exact active WU2 eligibility binding.');
        }

        $record = $this->recoveryStore->read($eligibility->recoveryIdentity());
        if ($record->state() !== RecoveryLifecycleState::READY || !$record->captureComplete() || $record->mutationStarted()
            || !$record->confirmationMatches($eligibility->recoveryIdentity(), $manifest->identity(), $eligibility->confirmationIdentity())) {
            throw new \RuntimeException('Filesystem convergence requires an unused READY recovery boundary.');
        }
    }

    /** @return array<string, StagedFile> */
    private function validatePlanAndPayload(LegacyReconciliationPlan $plan, StagedPayload $payload): array
    {
        if ($payload->archiveSha256() !== $plan->target()->archiveIdentity()) {
            throw new \RuntimeException('Staged package archive identity does not match the trusted target.');
        }

        $applyPlan = WebcoreApplyPlan::fromPayload($payload);
        if ($applyPlan->identity() !== $plan->target()->payloadIdentity()) {
            throw new \RuntimeException('Staged package apply-plan identity does not match the trusted target.');
        }

        $inventory = [];
        foreach ($plan->target()->contract()->inventory() as $entry) {
            if (!$entry instanceof PackageInventoryEntry || $entry->ownership() !== PackageOwnership::PACKAGE_OWNED) {
                throw new \RuntimeException('Trusted target inventory contains unsupported ownership.');
            }
            $inventory[$entry->path()] = $entry;
        }

        $files = [];
        foreach ($payload->files() as $file) {
            if (!$file instanceof StagedFile || !isset($inventory[$file->path()])) {
                throw new \RuntimeException('Staged payload contains an unplanned or non-package-owned path.');
            }
            $entry = $inventory[$file->path()];
            if ($file->byteSize() !== $entry->byteSize() || $file->sha256() !== $entry->sha256() || PackageOwnership::classify($file->path()) !== PackageOwnership::PACKAGE_OWNED) {
                throw new \RuntimeException('Staged payload file identity or ownership does not match the trusted inventory.');
            }
            $files[$file->path()] = $file;
        }

        $actions = [];
        foreach ($plan->filesystemActions() as $action) {
            if (isset($actions[$action->path()]) || !isset($inventory[$action->path()])) {
                throw new \RuntimeException('Reconciliation plan contains an ambiguous or untrusted filesystem action.');
            }
            $entry = $inventory[$action->path()];
            if ($action->byteSize() !== $entry->byteSize() || $action->sha256() !== $entry->sha256() || !isset($files[$action->path()])) {
                throw new \RuntimeException('Reconciliation action does not match the trusted package inventory.');
            }
            if ($action->action() === FilesystemReconciliationAction::REPLACE && $action->expectedLiveSha256() === null) {
                throw new \RuntimeException('REPLACE action has no immutable live-file precondition.');
            }
            $actions[$action->path()] = true;
        }

        if (count($actions) !== count($inventory) || count($files) !== count($inventory)) {
            throw new \RuntimeException('Reconciliation plan and trusted inventory are not identical.');
        }

        return $files;
    }

    private function matches(string $path, FilesystemReconciliationAction $action): bool
    {
        return is_file($path) && @filesize($path) === $action->byteSize() && @hash_file('sha256', $path) === $action->sha256();
    }

    private function markRecoveryRequired(RecoveryIdentity $identity, string $reason): void
    {
        try {
            $record = $this->recoveryStore->read($identity);
            if ($record->mutationStarted() && $record->state() === RecoveryLifecycleState::READY) {
                $this->recoveryStore->transition($identity, RecoveryLifecycleState::RESTORE_REQUIRED, $reason);
            }
        } catch (\Throwable) {
            // Preserve the original apply outcome; an unreadable recovery state is not a new IU2 state.
        }
    }
}
