<?php

namespace Copot\Core;

/** WU2 preparation boundary. It never applies a package or commits v3 state. */
final class LegacyToV3BootstrapEntry
{
    public function __construct(
        private string $appRoot,
        private string $publicRoot,
        private InstallationIdentityStore $identityStore,
        private InstallationState $installationState,
        private InstalledStateInspector $installedInspector,
        private $evidence,
        private $legacyClassifier,
        private $connection,
        private string $canonicalSchemaPath,
        private CoreMigrationRegistry $registry,
        private PackageV3SourceRelativePlanner $planner,
        private LegacyBootstrapSourceArtifactStore $artifacts,
        private ?LegacyBootstrapLiveInventoryProvider $liveInventory = null
    ) {
        if (!is_dir($appRoot) || !is_dir($publicRoot) || $appRoot === $publicRoot) throw new \InvalidArgumentException('Legacy bootstrap roots are invalid.');
        $this->appRoot = rtrim(realpath($appRoot) ?: '', DIRECTORY_SEPARATOR);
        $this->publicRoot = rtrim(realpath($publicRoot) ?: '', DIRECTORY_SEPARATOR);
        if ($this->appRoot === '' || $this->publicRoot === '') throw new \InvalidArgumentException('Legacy bootstrap roots are unavailable.');
        $this->liveInventory ??= new LegacyBootstrapLiveInventoryProvider($this->appRoot, $this->publicRoot);
    }

    public function prepare(PackageManifest $manifest): LegacyBootstrapPreparation
    {
        $package = $manifest->contract();
        if ($package->manifestContractVersion() !== PackageContract::V3_MANIFEST_CONTRACT_VERSION || $package->coverage() !== 'full') {
            return $this->blocked('A full manifest-v3 package is required for legacy bootstrap.', 'invalid-package');
        }
        $this->planner->plan($manifest);

        $identity = $this->identityStore->read();
        if (!$identity instanceof InstallationIdentity) return $this->blocked('Installation identity is unavailable.', 'identity');
        $installed = $this->installedInspector->inspect($this->installationState, ($this->evidence)());
        $legacyPackageEvidence = $this->legacyPackageEvidence();
        $source = $this->liveInventory->capture();
        $classification = LegacyClassificationResult::unknown('Classification was not completed.');
        try { $classification = ($this->legacyClassifier)($installed, $this->connection, $this->canonicalSchemaPath, $this->registry); }
        catch (\Throwable $e) { $classification = LegacyClassificationResult::unknown('Legacy baseline classification failed: ' . $e->getMessage()); }

        $collisions = $this->liveInventory->collisions($package->targetInventory(), $source);
        $targetInventoryFingerprint = $package->targetInventory()->fingerprint();
        $planIdentity = hash('sha256', json_encode([
            'operation_type' => LegacyBootstrapPreparation::OPERATION_TYPE,
            'installation_identity' => $identity->value(),
            'package_identity' => $manifest->packageEvidenceIdentity() ?? $this->planner::packageIdentity($package),
            'legacy_classification' => $classification->classification(),
            'source_fingerprint' => $source->fingerprint(),
            'target_inventory_fingerprint' => $targetInventoryFingerprint,
            'collisions' => $collisions,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $operationIdentity = 'legacy-to-v3-bootstrap-sha256:' . hash('sha256', LegacyBootstrapPreparation::OPERATION_TYPE . ':' . $identity->value() . ':' . $planIdentity);
        $blocked = !$classification->isLegacyCandidate() || array_filter($collisions, static fn (array $row): bool => $row['action'] === 'BLOCK') !== [];
        $reason = !$classification->isLegacyCandidate() ? ($classification->reason() !== '' ? $classification->reason() : 'Installation is not an eligible legacy baseline.') : ($blocked ? 'Legacy bootstrap has unverified destination collisions.' : '');
        $status = $blocked ? LegacyBootstrapPreparation::BLOCKED : LegacyBootstrapPreparation::ELIGIBLE;
        $record = [
            'serialization_version' => 3, 'operation_type' => LegacyBootstrapPreparation::OPERATION_TYPE,
            'operation_identity' => $operationIdentity, 'installation_identity' => $identity->value(),
            'plan_identity' => $planIdentity, 'package_identity' => $manifest->packageEvidenceIdentity() ?? $this->planner::packageIdentity($package),
            'target_inventory_fingerprint' => $targetInventoryFingerprint,
            'archive_identity' => $manifest->payload()->archiveSha256(),
            'payload_identity' => WebcoreApplyPlan::fromPayload($manifest->payload())->identity(),
            'apply_plan_identity' => WebcoreApplyPlan::fromPayload($manifest->payload())->identity(),
            'recovery_identity' => null, 'captured_at' => gmdate(DATE_ATOM),
            'provenance' => ['kind' => 'legacy-live-tree-scan', 'app_root' => $this->appRoot, 'public_root' => $this->publicRoot],
            'legacy_package_evidence' => $legacyPackageEvidence->toArray(),
            'legacy_classification' => $classification->toEvidenceArray($this->registry->identity(), $installed->status()),
            'source_inventory' => ['fingerprint' => $source->fingerprint(), 'entries' => array_map(static fn (LegacyBootstrapSourceEntry $e): array => $e->toArray(), $source->entries())],
            'collisions' => $collisions, 'status' => $status,
        ];
        $path = $this->artifacts->publish($record);
        return new LegacyBootstrapPreparation($status, $reason, $operationIdentity, $planIdentity, $identity->value(), $classification, $source, $collisions, $path);
    }

    private function blocked(string $reason, string $tag): LegacyBootstrapPreparation
    {
        $empty = LegacyClassificationResult::unknown($reason);
        $identity = 'legacy-to-v3-bootstrap-blocked:' . hash('sha256', $tag . ':' . $reason);
        return new LegacyBootstrapPreparation(LegacyBootstrapPreparation::BLOCKED, $reason, $identity, hash('sha256', $reason), '', $empty, null, [['action' => 'BLOCK', 'reason' => $reason]], null);
    }

    private function legacyPackageEvidence(): LegacyBootstrapPackageEvidence
    {
        $path = $this->appRoot . DIRECTORY_SEPARATOR . '.copot' . DIRECTORY_SEPARATOR . 'package.json';
        if (!file_exists($path)) return new LegacyBootstrapPackageEvidence(false, false, null, null, 'Legacy package metadata is absent; installation identity and baseline evidence remain independently required.');
        if (is_link($path) || !is_file($path) || !is_readable($path)) return new LegacyBootstrapPackageEvidence(true, false, null, null, 'Legacy package metadata is not a safe readable regular file.');
        $raw = (string) file_get_contents($path);
        try { $data = json_decode($raw, true, 32, JSON_THROW_ON_ERROR); }
        catch (\Throwable $e) { return new LegacyBootstrapPackageEvidence(true, false, null, hash('sha256', $raw), 'Legacy package metadata is invalid JSON.'); }
        $version = $data['manifest_contract_version'] ?? null;
        $valid = is_array($data) && is_int($version) && in_array($version, [1, 2], true);
        return new LegacyBootstrapPackageEvidence(true, $valid, $valid ? $version : null, hash('sha256', $raw), $valid ? '' : 'Legacy package metadata is not a recognized v1/v2 manifest.');
    }

}
