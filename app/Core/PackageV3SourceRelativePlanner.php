<?php

namespace Copot\Core;

/** Pure v3 planner. It verifies evidence and produces immutable intent only. */
final class PackageV3SourceRelativePlanner
{
    public function plan(PackageContract|PackageManifest $input, ?SourceInventoryArtifact $source = null): V3SourceRelativePlan
    {
        $package = $input instanceof PackageManifest ? $input->contract() : $input;
        $packageEvidenceIdentity = $input instanceof PackageManifest ? $input->packageEvidenceIdentity() : null;
        if ($packageEvidenceIdentity !== null) { PackageV3Identity::assertIdentity($packageEvidenceIdentity); }
        $packageEvidenceIdentity ??= self::packageIdentity($package);
        if ($package->manifestContractVersion() !== PackageContract::V3_MANIFEST_CONTRACT_VERSION || !$package->targetInventory() instanceof CanonicalTargetInventory || !in_array($package->coverage(), ['full', 'partial'], true)) {
            throw new \InvalidArgumentException('Source-relative planning requires a v3 package contract.');
        }
        $target = $this->index($package->targetInventory()->entries());
        $delivered = [];
        foreach ($package->inventory() as $entry) {
            if (!$entry instanceof PackageInventoryEntry || $entry->ownership() !== PackageOwnership::PACKAGE_OWNED) { throw new \RuntimeException('V3 delivered inventory ownership is invalid.'); }
            [$root, $path] = self::rootPath($entry->path());
            $key = self::key($root, $path);
            if (isset($delivered[$key])) { throw new \RuntimeException('V3 delivered inventory has a root/path collision.'); }
            $delivered[$key] = [$entry->byteSize(), $entry->sha256(), $entry->path()];
            if (!isset($target[$key]) || $target[$key]->byteSize() !== $entry->byteSize() || $target[$key]->sha256() !== $entry->sha256()) { throw new \RuntimeException('Delivered payload does not match the complete target inventory.'); }
        }

        $sourceIndex = [];
        $sourceFingerprint = '';
        $sourceReference = null;
        if ($source instanceof SourceInventoryArtifact) {
            $sourceReference = $source->reference();
            $sourceFingerprint = $source->inventory()->fingerprint();
            if ($sourceFingerprint !== $sourceReference->fingerprint() || $sourceReference->manifestContractVersion() !== PackageContract::V3_MANIFEST_CONTRACT_VERSION || !in_array($sourceFingerprint, $package->allowedInventoryFingerprints(), true)) { throw new \RuntimeException('Committed source inventory is not compatible with the v3 source requirements.'); }
            if ($package->sourceTreeIdentity() !== null && $package->sourceTreeIdentity() !== $sourceReference->sourceTreeIdentity()) { throw new \RuntimeException('Committed source tree identity does not match the v3 package.'); }
            foreach ($source->inventory()->entries() as $entry) { $key = self::key($entry->root(), $entry->path()); if (isset($sourceIndex[$key])) { throw new \RuntimeException('Committed source inventory has a duplicate root/path.'); } $sourceIndex[$key] = $entry; }
        }
        if ($package->coverage() === 'partial' && !$source instanceof SourceInventoryArtifact) { throw new \RuntimeException('Partial v3 coverage requires a committed source inventory.'); }
        if ($package->removals() !== [] && !$source instanceof SourceInventoryArtifact) { throw new \RuntimeException('Explicit v3 removals require a committed source inventory.'); }

        $actions = [];
        foreach ($target as $key => $entry) {
            if (isset($delivered[$key])) {
                $actions[] = new V3SourceRelativeAction(isset($sourceIndex[$key]) ? V3SourceRelativeAction::REPLACE : V3SourceRelativeAction::DELIVER, $entry->root(), $entry->path(), $entry->byteSize(), $entry->sha256(), isset($sourceIndex[$key]) ? $entry->root() : null, isset($sourceIndex[$key]) ? $entry->path() : null, isset($sourceIndex[$key]) ? 'replaced' : null);
                continue;
            }
            if ($package->coverage() === 'full' || !isset($sourceIndex[$key])) { throw new \RuntimeException('Complete target inventory entry is neither delivered nor provably retained.'); }
            $sourceEntry = $sourceIndex[$key];
            if ($sourceEntry->ownership() !== CanonicalTargetInventoryEntry::PACKAGE_OWNED || $sourceEntry->byteSize() !== $entry->byteSize() || $sourceEntry->sha256() !== $entry->sha256()) { throw new \RuntimeException('Retained source file failed ownership or byte identity verification.'); }
            $actions[] = new V3SourceRelativeAction(V3SourceRelativeAction::RETAIN, $entry->root(), $entry->path(), $entry->byteSize(), $entry->sha256());
        }

        $removalActions = $this->removalActions($package, $sourceIndex, $target, $delivered);
        foreach ($sourceIndex as $key => $entry) {
            if (!isset($target[$key]) && !isset($removalActions[$key])) { throw new \RuntimeException('Source-owned file outside the target requires an explicit removal declaration.'); }
        }
        foreach ($removalActions as $action) {
            $actions[] = new V3SourceRelativeAction($action['kind'] === PackageRemovalSet::RENAME_SOURCE_REMOVAL ? V3SourceRelativeAction::RENAME : V3SourceRelativeAction::REMOVE, $action['source_root'], $action['source_path'], $action['expected_source_byte_size'], $action['expected_source_sha256'], $action['source_root'], $action['source_path'], match ($action['reason']) { PackageRemovalSet::OBSOLETE => 'obsolete', PackageRemovalSet::REPLACED => 'replaced', PackageRemovalSet::RENAMED => 'renamed' });
        }
        usort($actions, static fn (V3SourceRelativeAction $left, V3SourceRelativeAction $right): int => strcmp(json_encode($left->toArray(), JSON_UNESCAPED_SLASHES), json_encode($right->toArray(), JSON_UNESCAPED_SLASHES)));
        return new V3SourceRelativePlan($package, $packageEvidenceIdentity, $sourceFingerprint, $sourceReference, $actions, $package->targetInventory()->fingerprint(), $package->coverage(), array_values($removalActions));
    }

    public static function packageIdentity(PackageContract $package): string
    {
        return $package->manifestContractVersion() === 3 ? 'v3-package-contract-sha256:' . hash('sha256', json_encode([
            'package_type' => $package->packageType(), 'manifest_contract_version' => 3, 'target_webcore_version' => $package->targetWebcoreVersion(), 'release_identity' => $package->releaseIdentity(), 'source_tree_identity' => $package->sourceTreeIdentity(), 'target_inventory_fingerprint' => $package->targetInventory()?->fingerprint(), 'coverage' => $package->coverage(), 'allowed_inventory_fingerprints' => $package->allowedInventoryFingerprints(), 'removals' => $package->removals(), 'renames' => $package->renames(),
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)) : $package->integrityIdentity();
    }

    private function removalActions(PackageContract $package, array $source, array $target, array $delivered): array
    {
        $renames = []; $actions = [];
        foreach ($package->renames() as $rename) {
            if (!is_array($rename) || array_keys($rename) !== ['source_root', 'source_path', 'target_root', 'target_path']) { throw new \RuntimeException('V3 rename declaration shape is invalid.'); }
            $sourceKey = self::key($rename['source_root'], $rename['source_path']); $targetKey = self::key($rename['target_root'], $rename['target_path']);
            if (isset($renames[$sourceKey]) || !isset($source[$sourceKey]) || !isset($delivered[$targetKey]) || !isset($target[$targetKey]) || ($sourceKey !== $targetKey && isset($source[$targetKey]))) { throw new \RuntimeException('V3 rename source, target, or collision proof is invalid.'); }
            $renames[$sourceKey] = true;
        }
        foreach ($package->removals() as $removal) {
            if (!is_array($removal) || array_keys($removal) !== ['root', 'path', 'ownership', 'expected_source_byte_size', 'expected_source_sha256', 'reason']) { throw new \RuntimeException('V3 removal declaration shape is invalid.'); }
            $key = self::key($removal['root'], $removal['path']); $sourceEntry = $source[$key] ?? null;
            if (!$sourceEntry instanceof CanonicalTargetInventoryEntry || $removal['ownership'] !== CanonicalTargetInventoryEntry::PACKAGE_OWNED || $sourceEntry->byteSize() !== $removal['expected_source_byte_size'] || $sourceEntry->sha256() !== $removal['expected_source_sha256']) { throw new \RuntimeException('V3 removal source evidence is stale or untrusted.'); }
            $matchingRename = null;
            foreach ($package->renames() as $rename) { if (self::key($rename['source_root'], $rename['source_path']) === $key) { $matchingRename = $rename; break; } }
            if (($removal['reason'] === 'renamed') !== ($matchingRename !== null) || ($removal['reason'] !== 'renamed' && $matchingRename !== null)) { throw new \RuntimeException('V3 removal and rename declarations do not agree.'); }
            if (isset($actions[$key])) { throw new \RuntimeException('V3 removal source is declared more than once.'); }
            $actions[$key] = ['kind' => $matchingRename !== null ? PackageRemovalSet::RENAME_SOURCE_REMOVAL : PackageRemovalSet::EXPLICIT_REMOVAL, 'reason' => match ($removal['reason']) { 'obsolete' => PackageRemovalSet::OBSOLETE, 'replaced' => PackageRemovalSet::REPLACED, 'renamed' => PackageRemovalSet::RENAMED }, 'source_root' => $removal['root'], 'source_path' => $removal['path'], 'target_root' => $matchingRename['target_root'] ?? '', 'target_path' => $matchingRename['target_path'] ?? '', 'expected_source_byte_size' => $removal['expected_source_byte_size'], 'expected_source_sha256' => $removal['expected_source_sha256']];
        }
        return $actions;
    }

    private function index(array $entries): array { $result = []; foreach ($entries as $entry) { if (!$entry instanceof CanonicalTargetInventoryEntry) { throw new \RuntimeException('V3 target inventory entry is invalid.'); } $key = self::key($entry->root(), $entry->path()); if (isset($result[$key])) { throw new \RuntimeException('V3 target inventory contains a duplicate root/path.'); } $result[$key] = $entry; } return $result; }
    private static function key(string $root, string $path): string { return strtolower($root . "\0" . $path); }
    private static function rootPath(string $path): array { if (str_starts_with($path, 'public/')) { return [CanonicalTargetInventoryEntry::PUBLIC_ROOT, substr($path, 7)]; } return [CanonicalTargetInventoryEntry::APP_ROOT, $path]; }
}
