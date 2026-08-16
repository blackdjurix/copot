<?php

namespace Copot\Core;

require_once __DIR__ . DIRECTORY_SEPARATOR . 'ProtectedWebcoreMutation.php';

use Copot\Core\BackupRecovery\InstallationRecoveryMaintenance;
use Copot\Core\BackupRecovery\NormalWebcoreRecoveryCaptureRequest;
use Copot\Core\BackupRecovery\NormalWebcoreRecoveryCaptureService;
use Copot\Core\BackupRecovery\NormalWebcoreRecoverySession;

final class NormalWebcoreProtectedMutationBoundary implements ProtectedWebcoreMutationBoundary
{
    public function __construct(
        private NormalWebcoreRecoveryCaptureService $capture,
        private InstallationRecoveryMaintenance $maintenance,
        private string $installationIdentity,
        private string $namespaceIdentity,
        private string $deploymentIdentity,
        private string $databaseIdentity
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
            $this->databaseIdentity
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

    public function fail(string $reason): void
    {
        if ($this->closed) {
            return;
        }
        try {
            $this->session->failMutation($reason);
        } finally {
            $this->closed = true;
        }
    }
}
