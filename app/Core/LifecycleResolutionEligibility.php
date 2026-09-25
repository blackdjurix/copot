<?php

namespace Copot\Core;

final class LifecycleResolutionEligibility
{
    public const UPDATE = 'update';
    public const UPGRADE = 'upgrade';
    public const REPAIR = 'repair';
    public const RETRY = 'retry';
    public const RECONCILIATION = 'reconciliation';
    public const SAME_VERSION_SCHEMA_FORWARD = 'same_version_schema_forward';
    public const OWNER_AUTHORIZED_MIGRATION = 'owner_authorized_migration';

    private const CLASSES = [self::UPDATE, self::UPGRADE, self::REPAIR, self::RETRY, self::RECONCILIATION, self::SAME_VERSION_SCHEMA_FORWARD, self::OWNER_AUTHORIZED_MIGRATION];

    public function __construct(
        private string $requirementGapIdentity,
        private string $lifecycleClass,
        private bool $eligible,
        private string $evidenceIdentity,
        private string $detail
    ) {
        if ($requirementGapIdentity === '' || trim($requirementGapIdentity) !== $requirementGapIdentity || preg_match('/[\x00-\x1F\x7F]/', $requirementGapIdentity) === 1) {
            throw new \InvalidArgumentException('Requirement gap identity is invalid.');
        }
        if (!in_array($lifecycleClass, self::CLASSES, true)) {
            throw new \InvalidArgumentException('Lifecycle resolution class is unsupported.');
        }
        foreach ([$evidenceIdentity, $detail] as $value) {
            if ($value === '' || trim($value) !== $value || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
                throw new \InvalidArgumentException('Lifecycle eligibility evidence is invalid.');
            }
        }
    }

    public function requirementGapIdentity(): string { return $this->requirementGapIdentity; }
    public function lifecycleClass(): string { return $this->lifecycleClass; }
    public function eligible(): bool { return $this->eligible; }
    public function evidenceIdentity(): string { return $this->evidenceIdentity; }
    public function detail(): string { return $this->detail; }
    public function toArray(): array { return ['requirement_gap_identity' => $this->requirementGapIdentity, 'lifecycle_class' => $this->lifecycleClass, 'eligible' => $this->eligible, 'evidence_identity' => $this->evidenceIdentity, 'detail' => $this->detail]; }
}
