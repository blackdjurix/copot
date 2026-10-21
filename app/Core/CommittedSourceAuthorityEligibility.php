<?php

namespace Copot\Core;

use Copot\Core\BackupRecovery\DatabaseScope;

/** Pure eligibility validation; it neither persists state nor authorizes mutation. */
final class CommittedSourceAuthorityEligibility
{
    public function evaluate(
        CommittedLifecycleState $committed,
        InstallationIdentity $installation,
        PackageManifest $manifest,
        CanonicalTargetInventory $actualTarget,
        DatabaseScope $databaseScope,
        InstallerOwnershipProof $ownershipProof,
        bool $allowTargetDrift = false
    ): CommittedSourceAuthorityEligibilityResult {
        if ($committed->sourceInventoryReference() !== null) {
            return $this->blocked('Committed source authority already exists.');
        }

        $package = $manifest->contract();
        if ($package->manifestContractVersion() !== PackageContract::V3_MANIFEST_CONTRACT_VERSION
            || $package->coverage() !== 'full'
            || !$package->targetInventory() instanceof CanonicalTargetInventory) {
            return $this->blocked('A complete manifest-v3 target inventory is required.');
        }
        if ($package->removals() !== [] || $package->renames() !== []) {
            return $this->blocked('Authority-only reconciliation cannot contain removal or rename declarations.');
        }
        if ($manifest->rawManifestSha256() === null || $manifest->packageEvidenceIdentity() === null) {
            return $this->blocked('Trusted manifest and provenance identities are incomplete.');
        }
        try {
            PackageV3Identity::assertIdentity($manifest->packageEvidenceIdentity());
            if (preg_match('/^[a-f0-9]{64}$/D', $manifest->rawManifestSha256()) !== 1) {
                return $this->blocked('Manifest provenance identity is not a raw manifest digest.');
            }
            $expectedEvidence = PackageV3Identity::identity($manifest->rawManifestSha256(), $package->integrityIdentity());
            if (!hash_equals($expectedEvidence, $manifest->packageEvidenceIdentity())) {
                return $this->blocked('Manifest provenance identity does not match the verified manifest and payload evidence.');
            }
        } catch (\Throwable $exception) {
            return $this->blocked($exception->getMessage());
        }
        if (!$allowTargetDrift && $package->targetInventory()->fingerprint() !== $actualTarget->fingerprint()) {
            return $this->blocked('Target inventory fingerprint does not match the trusted manifest.');
        }
        if ($package->targetWebcoreVersion() !== $committed->webcoreVersion()
            || $package->releaseIdentity() !== $committed->releaseIdentity()) {
            return $this->blocked('Committed version or release identity does not match the trusted package.');
        }
        if ($databaseScope->installationIdentity() !== $installation->value()
            || $ownershipProof->installationId() !== $installation->value()
            || $ownershipProof->namespace() !== $databaseScope->namespaceIdentity()
            || !$ownershipProof->isVerified()
            || $ownershipProof->migrationIdentity() !== $committed->migrationStateIdentity()) {
            return $this->blocked('Database scope or ownership proof is missing, stale, or mismatched.');
        }
        return new CommittedSourceAuthorityEligibilityResult(
            CommittedSourceAuthorityEligibilityResult::ELIGIBLE,
            'Committed source authority may be planned as authority-only; execution, recovery, and WU6 remain required.'
        );
    }

    private function blocked(string $reason): CommittedSourceAuthorityEligibilityResult
    {
        return new CommittedSourceAuthorityEligibilityResult(CommittedSourceAuthorityEligibilityResult::BLOCKED, $reason);
    }
}
