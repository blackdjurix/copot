<?php

namespace Copot\Core;

/** Pure identity composition over immutable preparation and staged package evidence. */
final class LegacyBootstrapIdentityComposer
{
    public function __construct(
        private LegacyBootstrapSourceArtifactStore $artifacts,
        private PackageInventoryVerifier $inventoryVerifier,
        private CoreMigrationRegistry $registry
    ) {}

    public function compose(string $artifactPath, PackageManifest $manifest, string $installationIdentity): LegacyBootstrapIdentityHandoff
    {
        $package = $manifest->contract();
        if ($package->manifestContractVersion() !== PackageContract::V3_MANIFEST_CONTRACT_VERSION || $package->coverage() !== 'full' || !$package->targetInventory() instanceof CanonicalTargetInventory) {
            throw new \RuntimeException('Bootstrap identity composition requires a full manifest-v3 target.');
        }
        $this->inventoryVerifier->verify($manifest->payload(), $package->inventory());
        $applyPlan = WebcoreApplyPlan::fromPayload($manifest->payload());
        $target = TrustedWebcorePackageTarget::fromManifest($manifest, $this->inventoryVerifier);
        $packageIdentity = $manifest->packageEvidenceIdentity() ?? PackageV3SourceRelativePlanner::packageIdentity($package);
        $artifact = $this->artifacts->rehydrate($artifactPath, [
            'installation_identity' => $installationIdentity,
            'package_identity' => $packageIdentity,
            'target_inventory_fingerprint' => $package->targetInventory()->fingerprint(),
            'archive_identity' => $manifest->payload()->archiveSha256(),
            'payload_identity' => $applyPlan->identity(),
            'apply_plan_identity' => $applyPlan->identity(),
        ]);
        if ($artifact->status() !== LegacyBootstrapPreparation::ELIGIBLE || !$artifact->classification()->isLegacyCandidate() || $artifact->recoveryIdentity() !== null) {
            throw new \RuntimeException('Bootstrap preparation evidence is not eligible for identity composition.');
        }
        foreach ($artifact->collisions() as $collision) {
            if (($collision['action'] ?? null) === 'BLOCK') throw new \RuntimeException('Bootstrap preparation contains a blocked collision.');
        }
        $planIdentity = hash('sha256', json_encode([
            'operation_type' => LegacyBootstrapPreparation::OPERATION_TYPE,
            'installation_identity' => $artifact->installationIdentity(),
            'package_identity' => $artifact->packageIdentity(),
            'legacy_classification' => $artifact->classification()->classification(),
            'source_fingerprint' => $artifact->sourceInventoryFingerprint(),
            'target_inventory_fingerprint' => $artifact->targetInventoryFingerprint(),
            'collisions' => $artifact->collisions(),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        if (!hash_equals($artifact->planIdentity(), $planIdentity)) throw new \RuntimeException('Bootstrap preparation plan identity changed.');
        $operationIdentity = 'legacy-to-v3-bootstrap-sha256:' . hash('sha256', LegacyBootstrapPreparation::OPERATION_TYPE . ':' . $artifact->installationIdentity() . ':' . $planIdentity);
        if (!hash_equals($artifact->operationIdentity(), $operationIdentity)) throw new \RuntimeException('Bootstrap operation identity changed.');
        if ($artifact->migrationRegistryIdentity() !== $this->registry->identity()) throw new \RuntimeException('Bootstrap migration registry identity changed.');
        $classification = $artifact->classification();
        $descriptors = $this->registry->migrations();
        foreach ($classification->records() as $index => $record) {
            $descriptor = $descriptors[$index] ?? null;
            if (!$descriptor instanceof CoreMigrationDescriptor || $record->migrationId() !== $descriptor->id() || $record->sequence() !== $descriptor->sequence() || $record->targetWebcoreVersion() !== $descriptor->targetWebcoreVersion() || $record->targetSchemaIdentity() !== $descriptor->targetSchemaIdentity() || $record->checksum() !== $descriptor->checksum()) {
                throw new \RuntimeException('Bootstrap migration-prefix evidence changed.');
            }
        }
        if (!$package->sourceCompatibility()->supports($classification->sourceWebcoreVersion())) throw new \RuntimeException('Bootstrap package does not support the classified source version.');
        $this->registry->resolve($package->migrationDeclaration());
        $migrationInput = new LegacyBootstrapMigrationInput($classification, $this->registry->identity(), $package->targetWebcoreVersion(), $package->migrationDeclaration());
        return new LegacyBootstrapIdentityHandoff($artifact, $target, $applyPlan, $migrationInput);
    }
}
