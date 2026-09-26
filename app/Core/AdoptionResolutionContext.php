<?php

namespace Copot\Core;

/** Non-authorizing context passed to an existing lifecycle authority. */
final class AdoptionResolutionContext
{
    public function __construct(
        private string $orchestrationIdentity,
        private string $targetIdentity,
        private string $installationIdentity,
        private string $namespaceIdentity,
        private string $requirementGapIdentity,
        private string $lifecycleClass,
        private int $step
    ) {
        foreach ([$orchestrationIdentity, $targetIdentity, $installationIdentity, $namespaceIdentity, $requirementGapIdentity, $lifecycleClass] as $value) {
            if ($value === '' || trim($value) !== $value || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
                throw new \InvalidArgumentException('Adoption resolution context is invalid.');
            }
        }
        if ($step < 1) {
            throw new \InvalidArgumentException('Adoption resolution step is invalid.');
        }
    }

    public function orchestrationIdentity(): string { return $this->orchestrationIdentity; }
    public function targetIdentity(): string { return $this->targetIdentity; }
    public function installationIdentity(): string { return $this->installationIdentity; }
    public function namespaceIdentity(): string { return $this->namespaceIdentity; }
    public function requirementGapIdentity(): string { return $this->requirementGapIdentity; }
    public function lifecycleClass(): string { return $this->lifecycleClass; }
    public function step(): int { return $this->step; }
}
