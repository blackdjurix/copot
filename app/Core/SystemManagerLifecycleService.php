<?php

namespace Copot\Core;

final class SystemManagerLifecycleService
{
    public function __construct(
        private PackageLifecycleService $lifecycle,
        private SystemManagerRecoveryGate $recovery,
        private SystemManagerPackageUpload $uploads
    ) {}

    public function preflight(string $zip): array
    {
        $result = $this->lifecycle->plan($zip);
        $action = $this->actionFor($result);
        return $this->safeResult($result, $action);
    }

    public function preflightUpload(?array $upload): array
    {
        $path = $this->uploads->stage($upload);
        try { return $this->preflight($path); } finally { $this->uploads->cleanup($path); }
    }

    public function execute(string $zip, string $requestedAction): array
    {
        $plan = $this->lifecycle->plan($zip);
        $action = $this->actionFor($plan);
        if ($action === null || strcasecmp($action, trim($requestedAction)) !== 0) {
            return ['accepted' => false, 'status' => 'rejected', 'reason' => 'The requested lifecycle action is not eligible.', 'action' => $action];
        }
        try {
            $target = (string) ($plan->toArray()['target_webcore_version'] ?? '');
            $recoveryIdentity = $this->recovery->capture($action, $target);
            $result = $this->lifecycle->apply($zip, $action === 'Repair');
            $safe = $this->safeResult($result, $action);
            $safe['recovery_state'] = $result->accepted() ? 'captured' : 'captured_before_failure';
            $safe['recovery_identity'] = $result->accepted() ? $recoveryIdentity : null;
            return $safe;
        } catch (\Throwable) {
            return ['accepted' => false, 'status' => 'blocked', 'reason' => 'Recovery capture or lifecycle execution was unavailable.', 'action' => $action, 'recovery_state' => 'unavailable'];
        }
    }

    public function executeUpload(?array $upload, string $requestedAction): array
    {
        $path = $this->uploads->stage($upload);
        try { return $this->execute($path, $requestedAction); } finally { if (is_file($path)) $this->uploads->cleanup($path); }
    }

    public function status(): array
    {
        $status = $this->lifecycle->status();
        $status['reason'] = 'Lifecycle status is available.';
        $status['next_action'] = match ($status['operation']['classification'] ?? null) {
            TransitionPlan::PATCH, TransitionPlan::UPDATE => 'Update',
            TransitionPlan::UPGRADE => 'Upgrade',
            TransitionPlan::REPAIR => 'Repair',
            default => null,
        };
        return $status;
    }

    public function retry(string $operationId): array
    {
        $status = $this->lifecycle->status();
        $operation = $status['operation'] ?? null;
        if (!is_array($operation) || ($operation['operation_id'] ?? null) !== $operationId || !$this->lifecycle->retryEvidence($operationId)) {
            return ['accepted' => false, 'status' => 'rejected', 'reason' => 'Retry evidence is unavailable or stale.', 'action' => 'Retry'];
        }
        $action = match ($operation['classification'] ?? null) {
            TransitionPlan::PATCH, TransitionPlan::UPDATE => 'Update',
            TransitionPlan::UPGRADE => 'Upgrade',
            TransitionPlan::REPAIR => 'Repair',
            default => null,
        };
        $zip = $this->lifecycle->retrySource($operationId);
        if ($action === null || $zip === null) return ['accepted' => false, 'status' => 'rejected', 'reason' => 'Retry evidence is unavailable or stale.', 'action' => 'Retry'];
        return $this->execute($zip, $action);
    }

    public function reconcile(string $zip, bool $confirmed): array
    {
        if (!$this->lifecycle->reconciliationAvailable()) return ['accepted' => false, 'status' => 'unavailable', 'reason' => 'Reconciliation is unavailable.', 'action' => 'Reconciliation'];
        return $this->safeResult($this->lifecycle->reconcile($zip, $confirmed), 'Reconciliation');
    }

    public function actionFor(PackageLifecycleResult $result): ?string
    {
        if (!$result->accepted()) return null;
        return match ($result->toArray()['classification'] ?? null) {
            TransitionPlan::PATCH, TransitionPlan::UPDATE => 'Update',
            TransitionPlan::UPGRADE => 'Upgrade',
            TransitionPlan::REPAIR => 'Repair',
            default => null,
        };
    }

    private function safeResult(PackageLifecycleResult $result, ?string $action): array
    {
        $data = $result->toArray();
        return [
            'accepted' => $result->accepted(),
            'status' => $result->status(),
            'reason' => $result->accepted() ? '' : 'The package lifecycle request was rejected or is unavailable.',
            'action' => $action,
            'classification' => $data['classification'] ?? null,
            'target_webcore_version' => $data['target_webcore_version'] ?? null,
            'operation_id' => $data['operation_id'] ?? null,
            'migration_outcome' => $data['migrations'] ?? [],
            'recovery_state' => 'not_captured',
        ];
    }
}
