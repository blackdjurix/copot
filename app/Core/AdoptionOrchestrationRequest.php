<?php

namespace Copot\Core;

/** Immutable target-bound request for bounded Adoption orchestration. */
final class AdoptionOrchestrationRequest
{
    public function __construct(
        private PackageTargetRequirements $target,
        private AdoptionEvaluationSnapshot $initial
    ) {
        if ($target->identity() === '') {
            throw new \InvalidArgumentException('Adoption target identity is invalid.');
        }
        if ($initial->targetIdentity() === '') {
            throw new \InvalidArgumentException('Adoption target evidence identity is invalid.');
        }
    }

    public function target(): PackageTargetRequirements { return $this->target; }
    public function initial(): AdoptionEvaluationSnapshot { return $this->initial; }
    public function identity(): string
    {
        return hash('sha256', json_encode([
            'target_requirement_identity' => $this->target->identity(),
            'target_identity' => $this->initial->targetIdentity(),
            'installation_identity' => $this->initial->installationIdentity(),
            'namespace_identity' => $this->initial->namespaceIdentity(),
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
