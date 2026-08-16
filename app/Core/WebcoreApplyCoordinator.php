<?php

namespace Copot\Core;

final class WebcoreApplyCoordinator
{
    /** @var callable(CoreMigrationPlan):MigrationRunResult */
    private $migrationRunner;

    public function __construct(
        private InstallationMutex $mutex,
        private MaintenanceCoordinator $maintenance,
        private PackageOwnedFileApplier $applier,
        callable $migrationRunner,
        private ?RuntimeRegistry $runtimeRegistry = null,
        private ?ProtectedWebcoreMutationBoundary $recoveryBoundary = null
    ) {
        $this->migrationRunner = $migrationRunner;
    }

    public function execute(
        WebcoreApplyPlan $applyPlan,
        TransitionPlan $transition,
        CoreMigrationPlan $migrationPlan,
        ?LifecycleOperationRecord $existing = null
    ): WebcoreApplyResult {
        $lock = $this->mutex->acquire();
        if (!$lock instanceof InstallationLock) {
            return new WebcoreApplyResult(WebcoreApplyResult::BLOCKED, [], 'Another lifecycle operation is already running.');
        }

        $record = null;
        $session = null;

        try {
            $package = $transition->package();
            $migrationIdentity = hash('sha256', implode("\n", array_map(
                static fn (CoreMigrationDescriptor $migration): string => $migration->id() . ':' . $migration->checksum(),
                array_filter($migrationPlan->migrations(), static fn ($migration): bool => $migration instanceof CoreMigrationDescriptor)
            )));
            $now = gmdate(DATE_ATOM);
            if ($existing instanceof LifecycleOperationRecord) {
                $record = $existing;
            } else {
            $record = new LifecycleOperationRecord(
                bin2hex(random_bytes(16)),
                $transition->classification(),
                $package->targetWebcoreVersion(),
                $package->releaseIdentity(),
                $applyPlan->payload()->archiveSha256(),
                $applyPlan->payload()->stagingPath(),
                hash('sha256', implode(':', array_map(
                    static fn (StagedFile $file): string => $file->path() . ':' . $file->sha256(),
                    $applyPlan->files()
                ))),
                $applyPlan->identity(),
                LifecycleOperationRecord::PREPARING,
                0,
                null,
                $migrationIdentity,
                null,
                $now,
                $now
            );
            }
            if ($existing === null) $this->maintenance->enter($record);

            if (!$transition->accepted() || !$migrationPlan->isAccepted()) {
                $reason = $transition->reason() !== '' ? $transition->reason() : $migrationPlan->reason();
                $record = $record->advance(LifecycleOperationRecord::COMPLETED, 0, null, null, $reason);
                $this->maintenance->clear($record);
                return new WebcoreApplyResult(WebcoreApplyResult::FAILED, [], $reason, $record->operationId());
            }

            $this->runtimeRegistry?->assertTransitionAllowed();

            if ($this->recoveryBoundary instanceof ProtectedWebcoreMutationBoundary) {
                $context = new WebcoreMutationContext($record, $applyPlan, $transition, $migrationPlan, $lock);
                $session = $existing !== null && $record->recoveryIdentity() !== null && $record->recoveryManifestIdentity() !== null && $this->recoveryBoundary instanceof NormalWebcoreProtectedMutationBoundary
                    ? $this->recoveryBoundary->enterExisting($context, $record->recoveryIdentity(), $record->recoveryManifestIdentity())
                    : $this->recoveryBoundary->enter($context);
                $evidence = $session->evidence();
                if (!isset($evidence['identity'], $evidence['manifest'], $evidence['state'])) throw new \RuntimeException('Recovery evidence is incomplete.');
                $record = $record->bindRecovery((string) $evidence['identity'], (string) $evidence['manifest'], (string) $evidence['state']);
                $this->maintenance->update($record);
                $session->authorize();
            }

            $record = $record->advance(LifecycleOperationRecord::APPLYING, 0);
            $this->maintenance->update($record);
            $apply = $this->applier->apply($applyPlan, function (int $cursor, string $path) use (&$record): void {
                $record = $record->advance(LifecycleOperationRecord::APPLYING, $cursor, $path);
                $this->maintenance->update($record);
            });

            if ($apply->status() !== WebcoreApplyResult::COMPLETED) {
                $appliedPaths = $apply->appliedPaths();
                $lastPath = $appliedPaths === [] ? null : $appliedPaths[count($appliedPaths) - 1];
                if ($apply->status() === WebcoreApplyResult::FAILED && $appliedPaths === []) {
                    $reason = $apply->reason();
                    try { $session?->fail($reason); } catch (\Throwable) {}
                    $this->maintenance->clear($record->advance(LifecycleOperationRecord::COMPLETED, 0, null, null, $reason));
                    return new WebcoreApplyResult(WebcoreApplyResult::FAILED, [], $reason, $record->operationId());
                }
                try { $session?->fail($apply->reason()); } catch (\Throwable) {}
                $record = $record->advance(LifecycleOperationRecord::BLOCKED, count($appliedPaths), $lastPath, null, $apply->reason());
                $this->maintenance->update($record);
                return new WebcoreApplyResult(WebcoreApplyResult::BLOCKED, $appliedPaths, $apply->reason(), $record->operationId());
            }

            $appliedPaths = $apply->appliedPaths();
            $lastPath = $appliedPaths === [] ? null : $appliedPaths[count($appliedPaths) - 1];
            $record = $record->advance(LifecycleOperationRecord::MIGRATING, count($appliedPaths), $lastPath);
            $this->maintenance->update($record);
            $migration = ($this->migrationRunner)($migrationPlan, $record->operationId(), $transition->classification());

            if (!$migration instanceof MigrationRunResult || $migration->status() === MigrationRunResult::INDETERMINATE) {
                $reason = $migration instanceof MigrationRunResult ? $migration->reason() : 'Migration outcome is indeterminate.';
                try { $session?->fail($reason); } catch (\Throwable) {}
                $record = $record->advance(LifecycleOperationRecord::INDETERMINATE, count($appliedPaths), $lastPath, MigrationRunResult::INDETERMINATE, $reason);
                $this->maintenance->update($record);
                return new WebcoreApplyResult(WebcoreApplyResult::BLOCKED, $appliedPaths, $reason, $record->operationId());
            }

            if ($migration->status() !== MigrationRunResult::COMPLETED && $migration->status() !== MigrationRunResult::NOOP) {
                try { $session?->fail($migration->reason()); } catch (\Throwable) {}
                $record = $record->advance(LifecycleOperationRecord::BLOCKED, count($appliedPaths), $lastPath, $migration->status(), $migration->reason());
                $this->maintenance->update($record);
                return new WebcoreApplyResult(WebcoreApplyResult::BLOCKED, $appliedPaths, $migration->reason(), $record->operationId());
            }

            $record = $record->advance(LifecycleOperationRecord::AWAITING_WU6, count($appliedPaths), $lastPath, $migration->status());
            $this->maintenance->update($record);
            $session?->complete();

            return new WebcoreApplyResult(WebcoreApplyResult::AWAITING_WU6, $appliedPaths, 'Awaiting WU6 health and installed-state commit.', $record->operationId());
        } catch (\Throwable $exception) {
            try { $session?->fail($exception->getMessage()); } catch (\Throwable) {}
            if ($record instanceof LifecycleOperationRecord) {
                try {
                    $this->maintenance->update($record->advance(LifecycleOperationRecord::BLOCKED, $record->fileCursor(), $record->lastVerifiedPath(), null, $exception->getMessage()));
                } catch (\Throwable) {
                }
            }
            return new WebcoreApplyResult(WebcoreApplyResult::BLOCKED, [], $exception->getMessage(), $record?->operationId());
        } finally {
            $lock->release();
        }
    }

    public function acknowledgeW6(string $operationId): void
    {
        $lock = $this->mutex->acquire();
        if (!$lock instanceof InstallationLock) {
            throw new \RuntimeException('Another lifecycle operation is already running.');
        }

        try {
            $record = $this->maintenance->record();
            if ($record === null || $record->operationId() !== $operationId || $record->phase() !== LifecycleOperationRecord::AWAITING_WU6) {
                throw new \RuntimeException('Lifecycle operation is not ready for WU6 completion.');
            }
            $this->maintenance->clear($record->advance(LifecycleOperationRecord::COMPLETED, $record->fileCursor(), $record->lastVerifiedPath()));
        } finally {
            $lock->release();
        }
    }
}
