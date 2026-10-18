<?php

namespace Copot\Core;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;

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
        private LegacyBootstrapSourceArtifactStore $artifacts
    ) {
        if (!is_dir($appRoot) || !is_dir($publicRoot) || $appRoot === $publicRoot) throw new \InvalidArgumentException('Legacy bootstrap roots are invalid.');
        $this->appRoot = rtrim(realpath($appRoot) ?: '', DIRECTORY_SEPARATOR);
        $this->publicRoot = rtrim(realpath($publicRoot) ?: '', DIRECTORY_SEPARATOR);
        if ($this->appRoot === '' || $this->publicRoot === '') throw new \InvalidArgumentException('Legacy bootstrap roots are unavailable.');
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
        $source = $this->capture();
        $classification = LegacyClassificationResult::unknown('Classification was not completed.');
        try { $classification = ($this->legacyClassifier)($installed, $this->connection, $this->canonicalSchemaPath, $this->registry); }
        catch (\Throwable $e) { $classification = LegacyClassificationResult::unknown('Legacy baseline classification failed: ' . $e->getMessage()); }

        $collisions = $this->collisions($package->targetInventory(), $source);
        $planIdentity = hash('sha256', json_encode([
            'operation_type' => LegacyBootstrapPreparation::OPERATION_TYPE,
            'installation_identity' => $identity->value(),
            'package_identity' => $manifest->packageEvidenceIdentity() ?? $this->planner::packageIdentity($package),
            'legacy_classification' => $classification->classification(),
            'source_fingerprint' => $source->fingerprint(),
            'collisions' => $collisions,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $operationIdentity = 'legacy-to-v3-bootstrap-sha256:' . hash('sha256', LegacyBootstrapPreparation::OPERATION_TYPE . ':' . $identity->value() . ':' . $planIdentity);
        $blocked = !$classification->isLegacyCandidate() || array_filter($collisions, static fn (array $row): bool => $row['action'] === 'BLOCK') !== [];
        $reason = !$classification->isLegacyCandidate() ? ($classification->reason() !== '' ? $classification->reason() : 'Installation is not an eligible legacy baseline.') : ($blocked ? 'Legacy bootstrap has unverified destination collisions.' : '');
        $status = $blocked ? LegacyBootstrapPreparation::BLOCKED : LegacyBootstrapPreparation::ELIGIBLE;
        $record = [
            'serialization_version' => 1, 'operation_type' => LegacyBootstrapPreparation::OPERATION_TYPE,
            'operation_identity' => $operationIdentity, 'installation_identity' => $identity->value(),
            'plan_identity' => $planIdentity, 'package_identity' => $manifest->packageEvidenceIdentity() ?? $this->planner::packageIdentity($package),
            'recovery_identity' => null, 'captured_at' => gmdate(DATE_ATOM),
            'provenance' => ['kind' => 'legacy-live-tree-scan', 'app_root' => $this->appRoot, 'public_root' => $this->publicRoot],
            'legacy_package_evidence' => $legacyPackageEvidence->toArray(),
            'legacy_classification' => ['classification' => $classification->classification(), 'reason' => $classification->reason()],
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

    private function capture(): LegacyBootstrapSourceInventory
    {
        $entries = [];
        foreach ([[CanonicalTargetInventoryEntry::APP_ROOT, $this->appRoot], [CanonicalTargetInventoryEntry::PUBLIC_ROOT, $this->publicRoot]] as [$root, $base]) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file->isLink()) throw new \RuntimeException('Legacy bootstrap source scan encountered a symlink.');
                if (!$file->isFile()) continue;
                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($base) + 1));
                $entries[] = new LegacyBootstrapSourceEntry($root, $relative, (int) $file->getSize(), (string) hash_file('sha256', $file->getPathname()));
            }
        }
        return new LegacyBootstrapSourceInventory($entries);
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

    private function collisions(CanonicalTargetInventory $target, LegacyBootstrapSourceInventory $source): array
    {
        $live = [];
        foreach ($source->entries() as $entry) $live[strtolower($entry->root()."\0".$entry->path())] = $entry;
        $rows = [];
        foreach ($target->entries() as $entry) {
            $key = strtolower($entry->root()."\0".$entry->path());
            $rows[] = isset($live[$key])
                ? ['action' => 'BLOCK', 'root' => $entry->root(), 'path' => $entry->path(), 'reason' => 'Legacy live bytes do not prove package ownership; explicit collision authority is required.']
                : ['action' => 'ADD', 'root' => $entry->root(), 'path' => $entry->path(), 'reason' => 'Destination is absent and delivered by the full v3 package.'];
        }
        foreach ($live as $key => $entry) {
            if (!isset(array_fill_keys(array_map(static fn (CanonicalTargetInventoryEntry $e): string => strtolower($e->root()."\0".$e->path()), $target->entries()), true)[$key])) {
                $rows[] = ['action' => 'UNVERIFIED', 'root' => $entry->root(), 'path' => $entry->path(), 'reason' => 'Live file is outside the package target inventory and remains untouched.'];
            }
        }
        usort($rows, static fn (array $a, array $b): int => strcmp($a['root'].'\0'.$a['path'], $b['root'].'\0'.$b['path']));
        return $rows;
    }
}
