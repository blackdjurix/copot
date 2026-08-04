<?php

namespace Copot\Core;

use PDO;

final class PackageLifecycleService
{
    /** @var callable(): ExistingInstallEvidence */
    private $evidence;
    /** @var callable(): RuntimeCompatibilityContext */
    private $runtime;
    /** @var callable(): PDO */
    private $connection;
    /** @var callable(): array<string, callable> */
    private $runtimeChecks;

    public function __construct(
        private ZipIntakeService $intake,
        private PackageManifestReader $manifestReader,
        private PackageInventoryVerifier $inventoryVerifier,
        private InstalledStateInspector $installedInspector,
        private InstallationState $installationState,
        callable $evidence,
        private TransitionPlanner $transitionPlanner,
        private CoreMigrationPlanner $migrationPlanner,
        private CoreMigrationRegistry $migrationRegistry,
        private CoreMigrationLedger $ledger,
        callable $connection,
        private WebcoreApplyCoordinator $applyCoordinator,
        private HealthIntegrityCommitCoordinator $healthCoordinator,
        private LiveTreePathGuard $liveGuard,
        callable $runtime,
        callable $runtimeChecks
    ) {
        $this->evidence = $evidence;
        $this->connection = $connection;
        $this->runtime = $runtime;
        $this->runtimeChecks = $runtimeChecks;
    }

    public function plan(string $zip): PackageLifecycleResult
    {
        try {
            [$payload, $manifest, $transition, $migration] = $this->prepare($zip);
            $result = $transition->accepted() && $migration->isAccepted()
                ? new PackageLifecycleResult(true, 'planned', '', $transition, $migration)
                : new PackageLifecycleResult(false, 'rejected', $transition->reason() !== '' ? $transition->reason() : $migration->reason(), $transition, $migration);
            $payload->cleanup();
            return $result;
        } catch (\Throwable $exception) {
            return new PackageLifecycleResult(false, $this->isCapabilityFailure($exception) ? 'unavailable' : 'invalid_package', $exception->getMessage());
        }
    }

    public function apply(string $zip, bool $repairOnly = false): PackageLifecycleResult
    {
        try {
            [$payload, $manifest, $transition, $migration] = $this->prepare($zip);
            if ($repairOnly && $transition->classification() !== TransitionPlan::REPAIR) {
                $payload->cleanup();
                return new PackageLifecycleResult(false, 'rejected', 'Repair requires a same-version target.', $transition, $migration);
            }
            if (!$transition->accepted() || !$migration->isAccepted()) {
                $reason = $transition->reason() !== '' ? $transition->reason() : $migration->reason();
                $payload->cleanup();
                return new PackageLifecycleResult(false, 'rejected', $reason, $transition, $migration);
            }

            $applyPlan = WebcoreApplyPlan::fromPayload($manifest->payload());
            $apply = $this->applyCoordinator->execute($applyPlan, $transition, $migration);
            if ($apply->status() !== WebcoreApplyResult::AWAITING_WU6) {
                if ($apply->status() === WebcoreApplyResult::FAILED) { $payload->cleanup(); }
                return new PackageLifecycleResult(false, strtolower($apply->status()), $apply->reason(), $transition, $migration, $apply->operationId());
            }
            $connection = ($this->connection)();
            $final = $this->healthCoordinator->finalize($apply->operationId(), $manifest->contract(), $applyPlan, $migration, $this->liveGuard, $connection, ($this->runtimeChecks)());
            if ($final->status() === HealthIntegrityCommitResult::COMPLETED) {
                $payload->cleanup();
                return new PackageLifecycleResult(true, 'completed', '', $transition, $migration, $apply->operationId());
            }
            return new PackageLifecycleResult(false, $final->status(), $final->reason(), $transition, $migration, $apply->operationId());
        } catch (\Throwable $exception) {
            return new PackageLifecycleResult(false, $this->isCapabilityFailure($exception) ? 'unavailable' : 'invalid_package', $exception->getMessage());
        }
    }

    public function status(): array
    {
        try {
            $inspection = $this->installedInspector->inspect($this->installationState, ($this->evidence)());
            return ['accepted' => true, 'status' => $inspection->status(), 'reason' => $inspection->reason()];
        } catch (\Throwable $exception) {
            return ['accepted' => false, 'status' => 'invalid', 'reason' => $exception->getMessage()];
        }
    }

    private function prepare(string $zip): array
    {
        $payload = $this->intake->intake($zip);
        try {
            $manifest = $this->manifestReader->read($payload);
            $this->inventoryVerifier->verify($manifest->payload(), $manifest->contract()->inventory());
            $installed = $this->installedInspector->inspect($this->installationState, ($this->evidence)());
            $runtime = ($this->runtime)();
            $transition = $this->transitionPlanner->plan($installed, $manifest->contract(), $runtime);
            $migration = $this->migrationPlanner->plan($installed, $manifest->contract(), $this->migrationRegistry, $this->ledger, ($this->connection)());
            return [$payload, $manifest, $transition, $migration];
        } catch (\Throwable $exception) {
            $payload->cleanup();
            throw $exception;
        }
    }

    private function isCapabilityFailure(\Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());
        return $exception instanceof \PDOException
            || str_contains($message, 'ziparchive')
            || str_contains($message, 'ext-zip')
            || str_contains($message, 'could not find driver')
            || str_contains($message, 'database connection');
    }
}
