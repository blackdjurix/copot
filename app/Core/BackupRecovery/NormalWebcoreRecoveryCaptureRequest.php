<?php
namespace Copot\Core\BackupRecovery;

use Copot\Core\WebcoreApplyPlan;

final class NormalWebcoreRecoveryCaptureRequest
{
    public function __construct(
        private string $operationId,
        private string $installationIdentity,
        private string $namespaceIdentity,
        private string $deploymentIdentity,
        private string $targetPackageIdentity,
        private string $targetReleaseIdentity,
        private string $archiveIdentity,
        private WebcoreApplyPlan $applyPlan,
        private string $preLifecycleIdentity,
        private string $migrationLedgerIdentity,
        private string $databaseIdentity
    ) {}
    public function operationId(): string { return $this->operationId; }
    public function installationIdentity(): string { return $this->installationIdentity; }
    public function namespaceIdentity(): string { return $this->namespaceIdentity; }
    public function deploymentIdentity(): string { return $this->deploymentIdentity; }
    public function targetPackageIdentity(): string { return $this->targetPackageIdentity; }
    public function targetReleaseIdentity(): string { return $this->targetReleaseIdentity; }
    public function archiveIdentity(): string { return $this->archiveIdentity; }
    public function applyPlan(): WebcoreApplyPlan { return $this->applyPlan; }
    public function preLifecycleIdentity(): string { return $this->preLifecycleIdentity; }
    public function migrationLedgerIdentity(): string { return $this->migrationLedgerIdentity; }
    public function databaseIdentity(): string { return $this->databaseIdentity; }
}
