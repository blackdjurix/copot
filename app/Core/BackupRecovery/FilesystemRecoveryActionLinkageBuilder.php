<?php

namespace Copot\Core\BackupRecovery;

use Copot\Core\CanonicalTargetInventoryEntry;
use Copot\Core\V3SourceRelativeAction;
use Copot\Core\V3SourceRelativePlan;

final class FilesystemRecoveryActionLinkageBuilder
{
    /** @param list<FilesystemRecoveryEntry> $entries */
    public static function build(V3SourceRelativePlan $plan, array $entries, string $operationIdentity, RecoveryIdentity $recoveryIdentity, string $originalArtifactIdentity): ?FilesystemRecoveryActionLinkage
    {
        $entryIndex = [];
        foreach ($entries as $entry) {
            if (!$entry instanceof FilesystemRecoveryEntry || $entry->preOperationState() === null) throw new FilesystemRecoveryException('Original-byte evidence is incomplete.');
            $entryIndex[$entry->path()] = $entry;
        }
        $links = [];
        foreach ($plan->actions() as $ordinal => $action) {
            if (!$action instanceof V3SourceRelativeAction || $action->action() === V3SourceRelativeAction::RETAIN) continue;
            $destructive = in_array($action->action(), [V3SourceRelativeAction::REPLACE, V3SourceRelativeAction::REMOVE, V3SourceRelativeAction::RENAME], true);
            $root = $destructive ? ($action->sourceRoot() ?? $action->root()) : $action->root();
            $path = $destructive ? ($action->sourcePath() ?? $action->path()) : $action->path();
            $relative = $root === CanonicalTargetInventoryEntry::PUBLIC_ROOT ? 'public/' . $path : $path;
            if (!isset($entryIndex[$relative])) throw new FilesystemRecoveryException('Mutation action has no recovery entry.');
            $entry = $entryIndex[$relative];
            if ($entry->targetSize() !== $action->byteSize() || $entry->targetHash() !== $action->sha256()) throw new FilesystemRecoveryException('Destructive action target identity does not match its recovery entry.');
            if ($destructive && $entry->preOperationState() !== FilesystemRecoveryEntry::EXISTING_FILE) throw new FilesystemRecoveryException('Destructive action has no original-byte recovery entry.');
            if (!$destructive && $entry->preOperationState() !== FilesystemRecoveryEntry::ABSENT_BEFORE_OPERATION) throw new FilesystemRecoveryException('ADD action requires an absent original state.');
            if ($action->action() === V3SourceRelativeAction::RENAME) {
                $targetRoot = $action->root();
                $targetPath = $action->path();
                $targetRelative = $targetRoot === CanonicalTargetInventoryEntry::PUBLIC_ROOT ? 'public/' . $targetPath : $targetPath;
                $targetEntry = $entryIndex[$targetRelative] ?? null;
                if (!$targetEntry instanceof FilesystemRecoveryEntry || $targetEntry->preOperationState() !== FilesystemRecoveryEntry::ABSENT_BEFORE_OPERATION) {
                    throw new FilesystemRecoveryException('RENAME requires an absent destination recovery entry.');
                }
            }
            $links[] = new FilesystemRecoveryActionLink(
                $operationIdentity,
                hash('sha256', 'v3-recovery-plan:' . $plan->identity()),
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
        return new FilesystemRecoveryActionLinkage($operationIdentity, hash('sha256', 'v3-recovery-plan:' . $plan->identity()), $recoveryIdentity, $links);
    }
}
