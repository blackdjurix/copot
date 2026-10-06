<?php

namespace Copot\Core;

final class NetZeroRetirementService
{
    public function __construct(private MaintenanceCoordinator $maintenance, private InstallationMutex $mutex, private NetZeroRetirementVerifier $verifier, private NetZeroRetirementEvidenceStore $evidence, private $clear = null)
    {
    }

    public function retire(string $operationId, WebcoreApplyPlan $plan, NetZeroRetirementContext $context): PackageLifecycleResult
    {
        if ($operationId !== $context->operationId()) return new PackageLifecycleResult(false, 'rejected', 'Operation identity mismatch.', operationId: $operationId);
        $lock = $this->mutex->acquire();
        if (!$lock) return new PackageLifecycleResult(false, 'blocked', 'Lifecycle operation is busy.', operationId: $operationId);
        try {
            $operation = $this->maintenance->record();
            if (!$operation || $operation->operationId() !== $operationId) return new PackageLifecycleResult(false, 'rejected', 'Requested lifecycle operation is not active.', operationId: $operationId);
            $verification = $this->verifier->verify($operation, $plan, $context);
            $this->evidence->publish($operation, $verification, $context->reason(), gmdate(DATE_ATOM));
            $terminal = $operation->advance(LifecycleOperationRecord::RETIRED_NET_ZERO, $operation->fileCursor(), $operation->lastVerifiedPath(), null, $context->reason());
            $clear = $this->clear ?? function (LifecycleOperationRecord $record): void { $this->maintenance->clear($record); };
            $clear($terminal);
            return new PackageLifecycleResult(false, LifecycleOperationRecord::RETIRED_NET_ZERO, 'Operation retired with proven net-zero effective mutation.', operationId: $operationId);
        } catch (\Throwable $exception) {
            return new PackageLifecycleResult(false, 'rejected', $exception->getMessage(), operationId: $operationId);
        } finally { $lock->release(); }
    }
}
