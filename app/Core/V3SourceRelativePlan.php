<?php

namespace Copot\Core;

final class V3SourceRelativePlan
{
    private string $identity;
    private string $operationIdentity;

    /** @param list<V3SourceRelativeAction> $actions */
    public function __construct(
        private PackageContract $package,
        private string $packageEvidenceIdentity,
        private string $sourceFingerprint,
        private ?SourceInventoryReference $sourceReference,
        private array $actions,
        private string $targetFingerprint,
        private string $coverage,
        private array $removalActions
    ) {
        if (!in_array($coverage, ['full', 'partial'], true) || preg_match('/^[a-f0-9]{64}$/D', $targetFingerprint) !== 1 || ($sourceFingerprint !== '' && preg_match('/^[a-f0-9]{64}$/D', $sourceFingerprint) !== 1)) { throw new \InvalidArgumentException('V3 source-relative plan identity context is invalid.'); }
        foreach ($actions as $action) { if (!$action instanceof V3SourceRelativeAction) { throw new \InvalidArgumentException('V3 source-relative plan action is invalid.'); } }
        foreach ($removalActions as $action) { if (!is_array($action)) { throw new \InvalidArgumentException('V3 removal action evidence is invalid.'); } }
        $body = [
            'plan_scheme' => 'v3-source-relative-plan-v1',
            'package_identity' => $packageEvidenceIdentity,
            'target_inventory_fingerprint' => $targetFingerprint,
            'coverage' => $coverage,
            'source_inventory_fingerprint' => $sourceFingerprint === '' ? null : $sourceFingerprint,
            'source_inventory_reference' => $sourceReference?->toArray(),
            'actions' => array_map(static fn (V3SourceRelativeAction $action): array => $action->toArray(), $actions),
        ];
        $this->identity = 'v3-plan-sha256:' . hash('sha256', json_encode($body, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $this->operationIdentity = 'v3-operation-sha256:' . hash('sha256', json_encode(['operation_scheme' => 'v3-source-relative-operation-v1', 'plan_identity' => $this->identity], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $this->removalSetDigest = PackageRemovalSet::digest($this->identity, $removalActions);
    }

    private string $removalSetDigest;
    public function identity(): string { return $this->identity; }
    public function operationIdentity(): string { return $this->operationIdentity; }
    public function package(): PackageContract { return $this->package; }
    public function packageEvidenceIdentity(): string { return $this->packageEvidenceIdentity; }
    public function sourceFingerprint(): string { return $this->sourceFingerprint; }
    public function sourceReference(): ?SourceInventoryReference { return $this->sourceReference; }
    public function actions(): array { return $this->actions; }
    public function targetFingerprint(): string { return $this->targetFingerprint; }
    public function coverage(): string { return $this->coverage; }
    public function removalActions(): array { return $this->removalActions; }
    public function removalSetDigest(): string { return $this->removalSetDigest; }

    /** Stable operator-facing evidence; planning only, with no executable mutation instruction. */
    public function preparedEvidence(): array
    {
        return [
            'plan_identity' => $this->identity,
            'operation_identity' => $this->operationIdentity,
            'package_identity' => $this->packageEvidenceIdentity,
            'target_inventory_fingerprint' => $this->targetFingerprint,
            'source_inventory_fingerprint' => $this->sourceFingerprint === '' ? null : $this->sourceFingerprint,
            'coverage' => $this->coverage,
            'action_count' => count($this->actions),
            'removal_action_count' => count($this->removalActions),
            'removal_set_digest' => $this->removalSetDigest,
            'mutation_executed' => false,
        ];
    }

    public function toArray(): array
    {
        return [
            ...$this->preparedEvidence(),
            'source_inventory_reference' => $this->sourceReference?->toArray(),
            'actions' => array_map(static fn (V3SourceRelativeAction $action): array => $action->toArray(), $this->actions),
            'removal_actions' => $this->removalActions,
        ];
    }
}
