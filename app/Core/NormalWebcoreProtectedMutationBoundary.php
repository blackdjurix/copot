<?php

namespace Copot\Core;

require_once __DIR__ . DIRECTORY_SEPARATOR . 'ProtectedWebcoreMutation.php';

use Copot\Core\BackupRecovery\InstallationRecoveryMaintenance;
use Copot\Core\BackupRecovery\NormalWebcoreRecoveryCaptureRequest;
use Copot\Core\BackupRecovery\NormalWebcoreRecoveryCaptureService;
use Copot\Core\BackupRecovery\NormalWebcoreRecoverySession;
use Copot\Core\BackupRecovery\ApplicationWriteProtectionEvidence;
use Copot\Core\BackupRecovery\DatabaseScope;
use Copot\Core\BackupRecovery\ScopedDatabaseConsistencyEvidence;
use Copot\Core\BackupRecovery\InstallationScopedMaintenanceGuard;

final class NormalWebcoreProtectedMutationBoundary implements ProtectedWebcoreMutationBoundary
{
    public function __construct(
        private NormalWebcoreRecoveryCaptureService $capture,
        private InstallationRecoveryMaintenance $maintenance,
        private string $installationIdentity,
        private string $namespaceIdentity,
        private string $deploymentIdentity,
        private string $databaseIdentity,
        private ?DatabaseScope $databaseScope = null,
        private ?ScopedDatabaseConsistencyEvidence $databaseConsistencyEvidence = null,
        private ?ApplicationWriteProtectionEvidence $applicationWriteProtectionEvidence = null,
        private bool $filesystemOnly = false,
        private ?InstallationScopedMaintenanceGuard $filesystemGuard = null,
        private ?DatabaseScope $applicationEvidenceScope = null
    ) {}

    public function enter(WebcoreMutationContext $context): ProtectedWebcoreMutationSession
    {
        $lock = $context->installationLock();
        if (!$lock instanceof InstallationLock) {
            throw new \RuntimeException('The lifecycle mutex owner is unavailable to the recovery boundary.');
        }

        $request = $this->request($context);
        $this->maintenance->adopt($lock);
        try {
            $session = $this->capture->capture($request);
        } catch (\Throwable $exception) {
            $this->maintenance->detach();
            throw $exception;
        }
        if (!$session->ready()) {
            $session->close();
            throw new \RuntimeException('Recovery capture did not reach READY.');
        }

        return new NormalWebcoreProtectedMutationSession($session);
    }

    public function requestFor(WebcoreMutationContext $context): NormalWebcoreRecoveryCaptureRequest
    {
        return $this->request($context);
    }

    public function enterExisting(WebcoreMutationContext $context, string $identity, string $manifest): ProtectedWebcoreMutationSession
    {
        $lock = $context->installationLock();
        if (!$lock instanceof InstallationLock) throw new \RuntimeException('The lifecycle mutex owner is unavailable to the recovery boundary.');
        $this->maintenance->adopt($lock);
        try { $session = $this->capture->resume($this->request($context), new \Copot\Core\BackupRecovery\RecoveryIdentity($identity), $manifest); }
        catch (\Throwable $e) { $this->maintenance->detach(); throw $e; }
        if (!$session->ready()) { $session->close(); throw new \RuntimeException('Persisted recovery evidence is not READY.'); }
        return new NormalWebcoreProtectedMutationSession($session);
    }

    private function request(WebcoreMutationContext $context): NormalWebcoreRecoveryCaptureRequest
    {
        $operation = $context->operation();
        $package = $context->transition()->package();
        $installed = $context->transition()->installedState();

        $filesystemCycle = $this->filesystemOnly && $context->orderedV3Plan() instanceof V3SourceRelativePlan;
        $applicationEvidence = $this->applicationWriteProtectionEvidence;
        if ($filesystemCycle) {
            if (!$this->filesystemGuard instanceof InstallationScopedMaintenanceGuard) {
                throw new \RuntimeException('Filesystem-only V3 Guard composition is unavailable.');
            }
            $recovery = NormalWebcoreRecoveryCaptureService::forOperation($operation->operationId(), $context->applyPlan()->identity(), $context->recoveryAttemptIdentity());
            $applicationEvidence = $this->filesystemGuard->durableScopedActive($recovery)
                ? $this->filesystemGuard->revalidateFilesystemEvidence($recovery)
                : $this->filesystemGuard->produceFilesystemEvidence($recovery);
        }

        return new NormalWebcoreRecoveryCaptureRequest(
            $operation->operationId(),
            $this->installationIdentity,
            $this->namespaceIdentity,
            $this->deploymentIdentity,
            $this->packageIdentity($package),
            $package->releaseIdentity(),
            $operation->archiveSha256(),
            $context->applyPlan(),
            $this->preLifecycleIdentity($installed),
            $installed?->migrationStateIdentity() ?? hash('sha256', 'copot-empty-migration-ledger'),
            $this->databaseIdentity,
            $context->filesystemRecoveryPlan(),
            $this->databaseScope,
            $this->databaseConsistencyEvidence,
            $applicationEvidence,
            $context->orderedV3Plan(),
            $filesystemCycle,
            $context->recoveryAttemptIdentity()
        );
    }

    private function packageIdentity(PackageContract $package): string
    {
        return hash('sha256', json_encode([
            'package_type' => $package->packageType(),
            'manifest_contract_version' => $package->manifestContractVersion(),
            'target_webcore_version' => $package->targetWebcoreVersion(),
            'release_identity' => $package->releaseIdentity(),
            'source_tree_identity' => $package->sourceTreeIdentity(),
            'inventory_identity' => $package->integrityIdentity(),
            'migration_declaration' => $package->migrationDeclaration()->toArray(),
            'target_requirements' => $package->targetRequirements()->toArray(),
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function preLifecycleIdentity(?InstalledStateSnapshot $installed): string
    {
        return hash('sha256', json_encode([
            'release_identity' => $installed?->releaseIdentity(),
            'source_tree_identity' => $installed?->sourceTreeIdentity(),
            'manifest_contract_version' => $installed?->manifestContractVersion(),
            'schema_state_identity' => $installed?->schemaStateIdentity(),
            'migration_state_identity' => $installed?->migrationStateIdentity(),
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
final class NormalWebcoreProtectedMutationSession implements ProtectedWebcoreMutationSession
{
    private bool $closed = false;

    public function __construct(private NormalWebcoreRecoverySession $session) {}

    public function evidence(): array
    {
        return [
            'identity' => $this->session->recoveryIdentity()->value(),
            'manifest' => $this->session->manifestIdentity(),
            'state' => $this->session->record()->state(),
        ];
    }

    public function authorize(): void
    {
        if ($this->closed || !$this->session->ready()) {
            throw new \RuntimeException('Recovery session is not READY for mutation.');
        }
        $this->session->authorizeMutation();
    }

    public function complete(): void
    {
        if ($this->closed) {
            return;
        }
        try {
            $this->session->completeMutation();
        } catch (\Throwable $exception) {
            try { $this->session->failMutation($exception->getMessage()); } catch (\Throwable) {}
            throw $exception;
        } finally {
            $this->closed = true;
        }
    }

    public function settle(): void
    {
        if ($this->closed) {
            return;
        }
        $this->session->settleMutation();
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }
        try { $this->session->close(); } finally { $this->closed = true; }
    }

    public function fail(string $reason): \Copot\Core\BackupRecovery\RecoveryLifecycleRecord
    {
        if ($this->closed) {
            throw new \RuntimeException('Recovery session is already closed.');
        }
        try {
            $record = $this->session->failMutation($reason);
            $this->closed = $record->state() !== \Copot\Core\BackupRecovery\RecoveryLifecycleState::RESTORE_REQUIRED;
            return $record;
        } finally {
            // RESTORE_REQUIRED deliberately keeps the recovery session open
            // so the caller can execute the verified filesystem restore.
        }
    }

    public function restore(): \Copot\Core\BackupRecovery\RecoveryLifecycleRecord
    {
        if ($this->closed) {
            throw new \RuntimeException('Recovery session is already closed.');
        }
        try {
            return $this->session->restoreMutation();
        } finally {
            $this->closed = true;
        }
    }
}
