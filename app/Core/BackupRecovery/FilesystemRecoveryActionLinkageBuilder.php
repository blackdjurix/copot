<?php

namespace Copot\Core\BackupRecovery;

use Copot\Core\CanonicalTargetInventoryEntry;
use Copot\Core\ProtectedResourcePolicy;
use Copot\Core\V3SourceRelativeAction;
use Copot\Core\V3SourceRelativePlan;

final class FilesystemRecoveryActionLinkageBuilder
{
    /** @param list<FilesystemRecoveryEntry> $entries */
    public static function build(V3SourceRelativePlan $plan, array $entries, string $operationIdentity, RecoveryIdentity $recoveryIdentity, string $originalArtifactIdentity, ?string $applyPlanIdentity = null): ?FilesystemRecoveryActionLinkage
    {
        $applyPlanIdentity ??= hash('sha256', 'v3-recovery-plan:' . $plan->identity());
        $entryIndex = [];
        foreach ($entries as $entry) {
            if (!$entry instanceof FilesystemRecoveryEntry || $entry->preOperationState() === null) throw new FilesystemRecoveryException('Original-byte evidence is incomplete.');
            $entryIndex[$entry->path()] = $entry;
        }
        $targetActions = [];
        $targetInventory = [];
        foreach ($plan->package()->targetInventory()?->entries() ?? [] as $targetEntry) {
            $targetInventory[self::key($targetEntry->root(), $targetEntry->path())] = $targetEntry;
        }
        foreach ($plan->actions() as $candidate) {
            if ($candidate instanceof V3SourceRelativeAction && $candidate->action() === V3SourceRelativeAction::DELIVER) {
                $targetActions[self::key($candidate->root(), $candidate->path())] = $candidate;
            }
        }
        $renameDeclarations = [];
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
        $links = [];
        foreach ($plan->actions() as $ordinal => $action) {
            if (!$action instanceof V3SourceRelativeAction || $action->action() === V3SourceRelativeAction::RETAIN) continue;
            $destructive = in_array($action->action(), [V3SourceRelativeAction::REPLACE, V3SourceRelativeAction::REMOVE, V3SourceRelativeAction::RENAME], true);
            $root = $destructive ? ($action->sourceRoot() ?? $action->root()) : $action->root();
            $path = $destructive ? ($action->sourcePath() ?? $action->path()) : $action->path();
            ProtectedResourcePolicy::assertMutable($root, $path);
            $relative = $root === CanonicalTargetInventoryEntry::PUBLIC_ROOT ? 'public/' . $path : $path;
            if (!isset($entryIndex[$relative])) throw new FilesystemRecoveryException('Mutation action has no recovery entry.');
            $entry = $entryIndex[$relative];
            if ($entry->targetSize() !== $action->byteSize() || $entry->targetHash() !== $action->sha256()) throw new FilesystemRecoveryException('Destructive action target identity does not match its recovery entry.');
            if ($destructive && $entry->preOperationState() !== FilesystemRecoveryEntry::EXISTING_FILE) throw new FilesystemRecoveryException('Destructive action has no original-byte recovery entry.');
            if (!$destructive && $entry->preOperationState() !== FilesystemRecoveryEntry::ABSENT_BEFORE_OPERATION) throw new FilesystemRecoveryException('ADD action requires an absent original state.');
            if ($action->action() === V3SourceRelativeAction::RENAME) {
                $rename = $renameDeclarations[self::key($root, $path)] ?? null;
                if (!is_array($rename)) {
                    throw new FilesystemRecoveryException('RENAME source-to-destination declaration is unavailable.');
                }
                $targetKey = self::key($rename['target_root'], $rename['target_path']);
                $targetAction = $targetActions[$targetKey] ?? null;
                $targetInventoryEntry = $targetInventory[$targetKey] ?? null;
                if (!$targetAction instanceof V3SourceRelativeAction || !$targetInventoryEntry instanceof CanonicalTargetInventoryEntry
                    || $targetAction->byteSize() !== $targetInventoryEntry->byteSize() || $targetAction->sha256() !== $targetInventoryEntry->sha256()) {
                    throw new FilesystemRecoveryException('RENAME destination delivery is unavailable.');
                }
                $targetRelative = $rename['target_root'] === CanonicalTargetInventoryEntry::PUBLIC_ROOT ? 'public/' . $rename['target_path'] : $rename['target_path'];
                $targetEntry = $entryIndex[$targetRelative] ?? null;
                if (!$targetEntry instanceof FilesystemRecoveryEntry || $targetEntry->preOperationState() !== FilesystemRecoveryEntry::ABSENT_BEFORE_OPERATION) {
                    throw new FilesystemRecoveryException('RENAME requires an absent destination recovery entry.');
                }
                if ($targetEntry->targetSize() !== $targetAction->byteSize() || $targetEntry->targetHash() !== $targetAction->sha256()) {
                    throw new FilesystemRecoveryException('RENAME destination identity does not match delivered target.');
                }
            }
            $links[] = new FilesystemRecoveryActionLink(
                $operationIdentity,
                $applyPlanIdentity,
                $recoveryIdentity,
                $ordinal,
                $action->action(),
                $root,
                $path,
                hash('sha256', json_encode($action->toArray(), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
                $destructive ? $originalArtifactIdentity : null,
                $entry->preOperationState()
            );
        }
        if ($links === []) return null;
        return new FilesystemRecoveryActionLinkage($operationIdentity, $applyPlanIdentity, $recoveryIdentity, $links);
    }

    private static function key(string $root, string $path): string
    {
        return strtolower($root . "\0" . $path);
    }
}


