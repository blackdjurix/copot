<?php

namespace Copot\Core\BackupRecovery;

final class FilesystemRecoveryActionLink
{
    public function __construct(
        private string $operationIdentity,
        private string $applyPlanIdentity,
        private RecoveryIdentity $recoveryIdentity,
        private int $ordinal,
        private string $action,
        private string $root,
        private string $path,
        private string $actionIdentity,
        private ?string $originalArtifactIdentity,
        private string $originalState
    ) {
        if ($ordinal < 0 || !in_array($action, ['deliver', 'replace', 'remove', 'rename'], true) || !in_array($root, ['APP_ROOT', 'PUBLIC_ROOT'], true)
            || $path === '' || preg_match('/^[a-f0-9]{64}$/D', strtolower($actionIdentity)) !== 1
            || !in_array($originalState, [FilesystemRecoveryEntry::EXISTING_FILE, FilesystemRecoveryEntry::ABSENT_BEFORE_OPERATION], true)) {
            throw new FilesystemRecoveryException('Filesystem recovery action linkage is invalid.');
        }
        if ($action === 'deliver') {
            if ($originalState !== FilesystemRecoveryEntry::ABSENT_BEFORE_OPERATION || $originalArtifactIdentity !== null) {
                throw new FilesystemRecoveryException('ADD action must bind an absent original state without an original artifact.');
            }
        } elseif ($originalState !== FilesystemRecoveryEntry::EXISTING_FILE
            || !is_string($originalArtifactIdentity)
            || preg_match('/^[a-f0-9]{64}$/D', strtolower($originalArtifactIdentity)) !== 1) {
            throw new FilesystemRecoveryException('Destructive action must bind existing original-byte evidence.');
        }
        \Copot\Core\CanonicalTargetInventoryEntry::assertPath($path);
        if (trim($operationIdentity) !== $operationIdentity || $operationIdentity === '' || trim($applyPlanIdentity) !== $applyPlanIdentity) {
            throw new FilesystemRecoveryException('Filesystem recovery action identity is invalid.');
        }
    }

    public function operationIdentity(): string { return $this->operationIdentity; }
    public function applyPlanIdentity(): string { return $this->applyPlanIdentity; }
    public function recoveryIdentity(): RecoveryIdentity { return $this->recoveryIdentity; }
    public function ordinal(): int { return $this->ordinal; }
    public function action(): string { return $this->action; }
    public function root(): string { return $this->root; }
    public function path(): string { return $this->path; }
    public function actionIdentity(): string { return $this->actionIdentity; }
    public function originalArtifactIdentity(): ?string { return $this->originalArtifactIdentity; }
    public function originalState(): string { return $this->originalState; }

    public function identity(): string
    {
        return hash('sha256', json_encode($this->toArray(), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    public function toArray(): array
    {
        return [
            'operation_identity' => $this->operationIdentity,
            'apply_plan_identity' => $this->applyPlanIdentity,
            'recovery_identity' => $this->recoveryIdentity->value(),
            'ordinal' => $this->ordinal,
            'action' => $this->action,
            'root' => $this->root,
            'path' => $this->path,
            'action_identity' => $this->actionIdentity,
            'original_state' => $this->originalState,
            'original_artifact_identity' => $this->originalArtifactIdentity,
        ];
    }
}
