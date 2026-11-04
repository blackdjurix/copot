<?php

namespace Copot\Core\BackupRecovery;

use Copot\Core\PackageOwnership;
use Copot\Core\ProtectedResourcePolicy;
use Copot\Core\StagedFile;
use Copot\Core\WebcoreApplyPlan;
use Copot\Core\V3SourceRelativeAction;
use Copot\Core\V3SourceRelativePlan;

final class FilesystemRecoveryPlan
{
    /** @var array<int, FilesystemRecoveryEntry> */
    private array $entries;
    /** @var array<string, string> */
    private array $renameDestinations = [];
    /** @var array<string, true> */
    private array $postOperationAbsent = [];

    /** @param array<int, FilesystemRecoveryEntry> $entries */
    /** @param array<string, string> $renameDestinations */
    /** @param array<int, string> $postOperationAbsent */
    private function __construct(private string $applyPlanIdentity, array $entries, array $renameDestinations = [], array $postOperationAbsent = [])
    {
        if (preg_match('/^[a-f0-9]{64}$/D', strtolower($applyPlanIdentity)) !== 1 || $entries === []) {
            throw new FilesystemRecoveryException('Filesystem recovery plan identity or entries are invalid.');
        }
        $indexed = [];
        foreach ($entries as $entry) {
            if (!$entry instanceof FilesystemRecoveryEntry || isset($indexed[$entry->path()])) {
                throw new FilesystemRecoveryException('Filesystem recovery plan contains duplicate or invalid paths.');
            }
            $indexed[$entry->path()] = $entry;
        }
        ksort($indexed, SORT_STRING);
        $this->entries = array_values($indexed);
        foreach ($renameDestinations as $source => $destination) {
            if (!is_string($source) || !is_string($destination) || $source === '' || $destination === '' || isset($this->renameDestinations[$source])) {
                throw new FilesystemRecoveryException('Filesystem recovery rename binding is invalid.');
            }
            $this->renameDestinations[$source] = $destination;
        }
        foreach ($postOperationAbsent as $path) {
            if (!is_string($path) || $path === '' || isset($this->postOperationAbsent[$path]) || !isset($indexed[$path])) {
                throw new FilesystemRecoveryException('Filesystem recovery post-operation absence binding is invalid.');
            }
            ProtectedResourcePolicy::assertMutableRelativePath($path);
            $this->postOperationAbsent[$path] = true;
        }
    }

    public static function fromApplyPlan(WebcoreApplyPlan $plan): self
    {
        $entries = [];
        foreach ($plan->files() as $file) {
            if (!$file instanceof StagedFile || PackageOwnership::classify($file->path()) !== PackageOwnership::PACKAGE_OWNED) {
                throw new FilesystemRecoveryException('Apply plan contains a non-package-owned or invalid filesystem path.');
            }
            ProtectedResourcePolicy::assertMutableRelativePath($file->path());
            $entries[] = new FilesystemRecoveryEntry($file->path(), $file->byteSize(), $file->sha256());
        }
        return new self($plan->identity(), $entries);
    }

    /** @param array<int, FilesystemRecoveryEntry> $entries */
    /** @param array<int, string> $postOperationAbsent */
    public static function fromCapturedEntries(string $applyPlanIdentity, array $entries, array $postOperationAbsent = []): self
    {
        return new self($applyPlanIdentity, $entries, [], $postOperationAbsent);
    }

    public static function fromV3Plan(V3SourceRelativePlan $plan): self
    {
        $entries = [];
        $seen = [];
        $renameDestinations = [];
        $postOperationAbsent = [];
        $targetActions = [];
        $targetInventory = [];
        $renameDeclarations = [];
        foreach ($plan->package()->targetInventory()?->entries() ?? [] as $targetEntry) {
            $targetInventory[self::key($targetEntry->root(), $targetEntry->path())] = $targetEntry;
        }
        foreach ($plan->package()->renames() as $rename) {
            if (!is_array($rename) || array_keys($rename) !== ['source_root', 'source_path', 'target_root', 'target_path']) {
                throw new FilesystemRecoveryException('V3 rename declaration shape is invalid.');
            }
            $sourceKey = self::key($rename['source_root'], $rename['source_path']);
            if (isset($renameDeclarations[$sourceKey])) {
                throw new FilesystemRecoveryException('V3 rename source is declared more than once.');
            }
            ProtectedResourcePolicy::assertMutable($rename['source_root'], $rename['source_path']);
            ProtectedResourcePolicy::assertMutable($rename['target_root'], $rename['target_path']);
            $renameDeclarations[$sourceKey] = $rename;
        }
        foreach ($plan->actions() as $candidate) {
            if ($candidate instanceof V3SourceRelativeAction && $candidate->action() === V3SourceRelativeAction::DELIVER) {
                $targetActions[self::key($candidate->root(), $candidate->path())] = $candidate;
            }
        }
        $addEntry = static function (string $relative, int $byteSize, string $sha256) use (&$entries, &$seen): void {
            if (isset($seen[$relative])) {
                $existing = $seen[$relative];
                if ($existing['byte_size'] !== $byteSize || $existing['sha256'] !== $sha256) {
                    throw new FilesystemRecoveryException('V3 recovery path identity is inconsistent.');
                }
                return;
            }
            $seen[$relative] = ['byte_size' => $byteSize, 'sha256' => $sha256];
            $entries[] = new FilesystemRecoveryEntry($relative, $byteSize, $sha256);
        };
        foreach ($plan->actions() as $action) {
            if (!$action instanceof V3SourceRelativeAction) throw new FilesystemRecoveryException('V3 recovery action is invalid.');
            $root = in_array($action->action(), [V3SourceRelativeAction::REMOVE, V3SourceRelativeAction::RENAME], true) ? $action->sourceRoot() : $action->root();
            $path = in_array($action->action(), [V3SourceRelativeAction::REMOVE, V3SourceRelativeAction::RENAME], true) ? $action->sourcePath() : $action->path();
            if (!is_string($root) || !is_string($path)) throw new FilesystemRecoveryException('V3 recovery action source identity is unavailable.');
            if (ProtectedResourcePolicy::isProtected($root, $path)) {
                if ($action->action() !== V3SourceRelativeAction::RETAIN) {
                    throw new FilesystemRecoveryException('V3 recovery cannot mutate deployment-owned .htaccess.');
                }
                continue;
            }
            $rename = null;
            $targetAction = null;
            if ($action->action() === V3SourceRelativeAction::RENAME) {
                $rename = $renameDeclarations[self::key($root, $path)] ?? null;
                if (!is_array($rename)) {
                    throw new FilesystemRecoveryException('V3 rename source-to-destination declaration is unavailable.');
                }
                $targetKey = self::key($rename['target_root'], $rename['target_path']);
                $targetAction = $targetActions[$targetKey] ?? null;
                $targetEntry = $targetInventory[$targetKey] ?? null;
                if (!$targetAction instanceof V3SourceRelativeAction || !$targetEntry instanceof \Copot\Core\CanonicalTargetInventoryEntry
                    || $targetAction->byteSize() !== $targetEntry->byteSize() || $targetAction->sha256() !== $targetEntry->sha256()) {
                    throw new FilesystemRecoveryException('V3 rename destination delivery is unavailable.');
                }
            }
            $relative = $root === \Copot\Core\CanonicalTargetInventoryEntry::PUBLIC_ROOT ? 'public/' . $path : $path;
            $addEntry($relative, $action->byteSize(), $action->sha256());
            if (in_array($action->action(), [V3SourceRelativeAction::REMOVE, V3SourceRelativeAction::RENAME], true)) {
                $postOperationAbsent[] = $relative;
            }
            if ($action->action() === V3SourceRelativeAction::RENAME) {
                $destinationRelative = $rename['target_root'] === \Copot\Core\CanonicalTargetInventoryEntry::PUBLIC_ROOT ? 'public/' . $rename['target_path'] : $rename['target_path'];
                if ($relative === $destinationRelative) {
                    throw new FilesystemRecoveryException('V3 rename source and destination are identical.');
                }
                $addEntry($destinationRelative, $targetAction->byteSize(), $targetAction->sha256());
                $renameDestinations[$relative] = $destinationRelative;
            }
        }
        if ($entries === []) throw new FilesystemRecoveryException('V3 recovery plan contains no package-owned actions.');
        return new self(hash('sha256', 'v3-recovery-plan:' . $plan->identity()), $entries, $renameDestinations, $postOperationAbsent);
    }

    public function applyPlanIdentity(): string { return $this->applyPlanIdentity; }

    /** @return array<int, FilesystemRecoveryEntry> */
    public function entries(): array { return $this->entries; }
    public function renameDestination(string $sourcePath): ?string { return $this->renameDestinations[$sourcePath] ?? null; }
    public function isPostOperationAbsent(string $path): bool { return isset($this->postOperationAbsent[$path]); }
    /** @return array<int, string> */
    public function postOperationAbsentPaths(): array { return array_keys($this->postOperationAbsent); }

    private static function key(string $root, string $path): string
    {
        return strtolower($root . "\0" . $path);
    }
}
