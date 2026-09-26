<?php

namespace Copot\Core;

/**
 * WU5-only consumption boundary. It decides what Installer may present or
 * continue; it never executes lifecycle work or mutates installation state.
 */
final class InstallerAdoptionIntegration
{
    /** @param list<InstallerOwnershipProof> $proofs */
    public function decide(InstallerRoutingPlan $routing, AdoptionOrchestrationResult $orchestration, array $proofs): InstallerAdoptionDecision
    {
        if ($routing->route() !== InstallerRoutingPlanner::ADOPT) {
            return $this->blocked($routing, 'Installer Adoption requires the existing-installation route.');
        }

        $proof = $this->proofForNamespace($routing->namespace(), $proofs);
        if (!$proof instanceof InstallerOwnershipProof) {
            return new InstallerAdoptionDecision(InstallerAdoptionDecision::STALE, 'reinspect_adoption', $routing->namespace(), null, true, false, 'Installation ownership or namespace proof is unavailable.');
        }

        if ($orchestration->state() === AdoptionOrchestrationResult::READY) {
            if (!in_array($orchestration->classification(), [
                AdoptionBoundaryClassification::EXACT_MATCH_ADOPTION,
                AdoptionBoundaryClassification::GENERALIZED_ADOPTION_COMPATIBLE,
            ], true)) {
                return $this->blocked($routing, 'Adoption readiness did not produce a terminal-compatible classification.');
            }
            if ($orchestration->targetIdentity() === null
                || $orchestration->installationIdentity() === null
                || $orchestration->namespaceIdentity() === null
                || $orchestration->installationIdentity() !== $proof->installationId()
                || $orchestration->namespaceIdentity() !== $proof->namespace()
                || $orchestration->namespaceIdentity() !== $routing->namespace()) {
                return new InstallerAdoptionDecision(InstallerAdoptionDecision::STALE, 'reinspect_adoption', $routing->namespace(), $proof->installationId(), true, false, 'Adoption readiness is not bound to the current installation and namespace identity.');
            }

            return new InstallerAdoptionDecision(
                InstallerAdoptionDecision::TERMINAL_ADOPT,
                'complete_adoption',
                $routing->namespace(),
                $proof->installationId(),
                true,
                false,
                'Fresh Adoption Readiness is proven; preserve the existing installation state.'
            );
        }

        if ($orchestration->state() === AdoptionOrchestrationResult::STALE) {
            return new InstallerAdoptionDecision(InstallerAdoptionDecision::STALE, 'reinspect_adoption', $routing->namespace(), $proof->installationId(), true, false, 'Adoption evidence is stale and must be freshly evaluated.');
        }

        if ($orchestration->state() === AdoptionOrchestrationResult::SUSPENDED) {
            return new InstallerAdoptionDecision(InstallerAdoptionDecision::SUSPENDED, 'inspect_recovery_or_retry', $routing->namespace(), $proof->installationId(), true, false, 'Adoption is suspended by underlying lifecycle or recovery state.');
        }

        return new InstallerAdoptionDecision(InstallerAdoptionDecision::BLOCKED, 'resolve_lifecycle_or_reconcile', $routing->namespace(), $proof->installationId(), true, false, 'Adoption is not ready for terminal completion.');
    }

    /** @param list<InstallerOwnershipProof> $proofs */
    private function proofForNamespace(string $namespace, array $proofs): ?InstallerOwnershipProof
    {
        $matches = array_values(array_filter($proofs, static fn (mixed $proof): bool => $proof instanceof InstallerOwnershipProof && $proof->namespace() === $namespace && $proof->isVerified()));
        return count($matches) === 1 ? $matches[0] : null;
    }

    private function blocked(InstallerRoutingPlan $routing, string $detail): InstallerAdoptionDecision
    {
        return new InstallerAdoptionDecision(InstallerAdoptionDecision::BLOCKED, 'resolve_lifecycle_or_reconcile', $routing->namespace(), null, true, false, $detail);
    }
}
