<?php
namespace Copot\Core;

use Copot\Core\BackupRecovery\FilesystemRecoveryPlan;
use Copot\Core\BackupRecovery\RecoveryLifecycleRecord;

final class WebcoreMutationContext
{
    public function __construct(private LifecycleOperationRecord $operation, private WebcoreApplyPlan $applyPlan, private TransitionPlan $transition, private CoreMigrationPlan $migration, private ?InstallationLock $installationLock = null, private ?FilesystemRecoveryPlan $filesystemRecoveryPlan = null, private ?V3SourceRelativePlan $orderedV3Plan = null) {}
    public function operation(): LifecycleOperationRecord { return $this->operation; }
    public function applyPlan(): WebcoreApplyPlan { return $this->applyPlan; }
    public function transition(): TransitionPlan { return $this->transition; }
    public function migration(): CoreMigrationPlan { return $this->migration; }
    public function installationLock(): ?InstallationLock { return $this->installationLock; }
    public function filesystemRecoveryPlan(): ?FilesystemRecoveryPlan { return $this->filesystemRecoveryPlan; }
    public function orderedV3Plan(): ?V3SourceRelativePlan { return $this->orderedV3Plan; }
}
interface ProtectedWebcoreMutationSession
{
    public function evidence(): array;
    public function authorize(): void;
    public function complete(): void;
    public function fail(string $reason): RecoveryLifecycleRecord;
    public function restore(): RecoveryLifecycleRecord;
}
interface ProtectedWebcoreMutationBoundary
{
    public function enter(WebcoreMutationContext $context): ProtectedWebcoreMutationSession;
}
