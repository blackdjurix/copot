<?php
namespace Copot\Core;

final class WebcoreMutationContext
{
    public function __construct(private LifecycleOperationRecord $operation, private WebcoreApplyPlan $applyPlan, private TransitionPlan $transition, private CoreMigrationPlan $migration) {}
    public function operation(): LifecycleOperationRecord { return $this->operation; }
    public function applyPlan(): WebcoreApplyPlan { return $this->applyPlan; }
    public function transition(): TransitionPlan { return $this->transition; }
    public function migration(): CoreMigrationPlan { return $this->migration; }
}
interface ProtectedWebcoreMutationSession
{
    public function evidence(): array;
    public function authorize(): void;
    public function complete(): void;
    public function fail(string $reason): void;
}
interface ProtectedWebcoreMutationBoundary
{
    public function enter(WebcoreMutationContext $context): ProtectedWebcoreMutationSession;
}
