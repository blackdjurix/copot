<?php

namespace Copot\Core;

/** Independent read-only comparison against current installation evidence. */
final class LegacyBootstrapTargetFreshnessVerifier
{
    public function __construct(
        private InstallationIdentityStore $identityStore,
        private InstallationState $installationState,
        private InstalledStateInspector $installedInspector,
        private mixed $existingEvidence,
        private mixed $legacyClassifier,
        private mixed $connection,
        private string $canonicalSchemaPath,
        private CoreMigrationRegistry $registry,
        private LegacyBootstrapLiveInventoryProvider $liveInventory
    ) {}

    public function verify(LegacyBootstrapIdentityHandoff $handoff): LegacyBootstrapTargetFreshnessResult
    {
        $artifact = $handoff->artifact();
        $stale = []; $unverified = [];
        try {
            $identity = $this->identityStore->read();
            if (!$identity instanceof InstallationIdentity) $unverified[] = 'Current installation identity is unavailable.';
            elseif (!hash_equals($artifact->installationIdentity(), $identity->value())) $stale[] = 'Installation identity changed.';
        } catch (\Throwable $exception) { $unverified[] = 'Installation identity could not be inspected: ' . $exception->getMessage(); }

        try {
            $installed = $this->installedInspector->inspect($this->installationState, ($this->existingEvidence)());
            if ($installed->status() !== $artifact->installedStateStatus()) $stale[] = 'Installed-state status changed.';
            $classification = ($this->legacyClassifier)($installed, $this->connection, $this->canonicalSchemaPath, $this->registry);
            $currentEvidence = $classification->toEvidenceArray($this->registry->identity(), $installed->status());
            if ($currentEvidence !== $artifact->classificationEvidence()) $stale[] = 'Legacy classification or migration-prefix evidence changed.';
        } catch (\Throwable $exception) { $unverified[] = 'Installed-state or database classification could not be verified: ' . $exception->getMessage(); }

        try {
            $currentSource = $this->liveInventory->capture();
            if (!hash_equals($artifact->sourceInventoryFingerprint(), $currentSource->fingerprint())) $stale[] = 'Live source inventory fingerprint changed.';
            $currentCollisions = $this->liveInventory->collisions($handoff->target()->contract()->targetInventory(), $currentSource);
            if ($currentCollisions !== $artifact->collisions()) $stale[] = 'Live collision evidence changed.';
            foreach ($this->liveInventory->targetTypeMismatches($handoff->target()->contract()->targetInventory()) as $mismatch) $stale[] = 'Target path type changed: ' . $mismatch['root'] . '/' . $mismatch['path'] . ' is ' . $mismatch['actual_type'] . '.';
        } catch (\Throwable $exception) { $unverified[] = 'Live source inventory could not be verified: ' . $exception->getMessage(); }

        try {
            $targetFingerprint = $handoff->target()->contract()->targetInventory()->fingerprint();
            if (!hash_equals($artifact->targetInventoryFingerprint(), $targetFingerprint)) $stale[] = 'Candidate target inventory fingerprint changed.';
        } catch (\Throwable $exception) { $targetFingerprint = $artifact->targetInventoryFingerprint(); $unverified[] = 'Candidate target inventory could not be verified: ' . $exception->getMessage(); }

        $diagnostics = array_values(array_unique(array_merge($stale, $unverified)));
        $status = $stale !== [] ? LegacyBootstrapTargetFreshnessResult::STALE : ($unverified !== [] ? LegacyBootstrapTargetFreshnessResult::UNVERIFIED : LegacyBootstrapTargetFreshnessResult::MATCHED);
        return new LegacyBootstrapTargetFreshnessResult($status, $artifact->installationIdentity(), $artifact->operationIdentity(), $artifact->planIdentity(), $artifact->sourceInventoryFingerprint(), $targetFingerprint, $handoff->identity(), $diagnostics);
    }
}
