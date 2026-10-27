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

    /** @param array<int, FilesystemRecoveryEntry> $entries */
    private function __construct(private string $applyPlanIdentity, array $entries)
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
    public static function fromCapturedEntries(string $applyPlanIdentity, array $entries): self
    {
        return new self($applyPlanIdentity, $entries);
    }

    public static function fromV3Plan(V3SourceRelativePlan $plan): self
    {
        $entries = [];
        $seen = [];
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
            $relative = $root === \Copot\Core\CanonicalTargetInventoryEntry::PUBLIC_ROOT ? 'public/' . $path : $path;
            if (isset($seen[$relative])) continue;
            $seen[$relative] = true;
            $entries[] = new FilesystemRecoveryEntry($relative, $action->byteSize(), $action->sha256());
        }
        if ($entries === []) throw new FilesystemRecoveryException('V3 recovery plan contains no package-owned actions.');
        return new self(hash('sha256', 'v3-recovery-plan:' . $plan->identity()), $entries);
    }

    public function applyPlanIdentity(): string { return $this->applyPlanIdentity; }

    /** @return array<int, FilesystemRecoveryEntry> */
    public function entries(): array { return $this->entries; }
}
