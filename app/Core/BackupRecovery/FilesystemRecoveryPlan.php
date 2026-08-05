<?php

namespace Copot\Core\BackupRecovery;

use Copot\Core\PackageOwnership;
use Copot\Core\StagedFile;
use Copot\Core\WebcoreApplyPlan;

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
            $entries[] = new FilesystemRecoveryEntry($file->path(), $file->byteSize(), $file->sha256());
        }
        return new self($plan->identity(), $entries);
    }

    /** @param array<int, FilesystemRecoveryEntry> $entries */
    public static function fromCapturedEntries(string $applyPlanIdentity, array $entries): self
    {
        return new self($applyPlanIdentity, $entries);
    }

    public function applyPlanIdentity(): string { return $this->applyPlanIdentity; }

    /** @return array<int, FilesystemRecoveryEntry> */
    public function entries(): array { return $this->entries; }
}
