<?php

namespace Copot\Core\BackupRecovery;

final class FilesystemRecoveryActionLinkage
{
    /** @param list<FilesystemRecoveryActionLink> $links */
    public function __construct(private string $operationIdentity, private string $applyPlanIdentity, private RecoveryIdentity $recoveryIdentity, array $links)
    {
        if ($links === []) throw new FilesystemRecoveryException('Filesystem recovery action linkage is empty.');
        $seenOrdinal = []; $seenPath = [];
        foreach ($links as $link) {
            if (!$link instanceof FilesystemRecoveryActionLink || $link->operationIdentity() !== $operationIdentity || $link->applyPlanIdentity() !== $applyPlanIdentity || !$link->recoveryIdentity()->equals($recoveryIdentity) || isset($seenOrdinal[$link->ordinal()]) || isset($seenPath[$link->root() . "\0" . $link->path()])) {
                throw new FilesystemRecoveryException('Filesystem recovery action linkage contains duplicate or mismatched actions.');
            }
            $seenOrdinal[$link->ordinal()] = true;
            $seenPath[$link->root() . "\0" . $link->path()] = true;
        }
        usort($links, static fn (FilesystemRecoveryActionLink $a, FilesystemRecoveryActionLink $b): int => $a->ordinal() <=> $b->ordinal());
        $this->links = array_values($links);
    }

    /** @var list<FilesystemRecoveryActionLink> */
    private array $links;
    public function operationIdentity(): string { return $this->operationIdentity; }
    public function applyPlanIdentity(): string { return $this->applyPlanIdentity; }
    public function recoveryIdentity(): RecoveryIdentity { return $this->recoveryIdentity; }
    /** @return list<FilesystemRecoveryActionLink> */
    public function links(): array { return $this->links; }
    public function identity(): string { return hash('sha256', json_encode($this->toArray(), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)); }
    public function artifactBytes(): string { return (new FilesystemRecoveryActionLinkageCodec())->encode($this); }
    public function artifact(): RecoveryArtifactRecord
    {
        $bytes = $this->artifactBytes();
        return new RecoveryArtifactRecord('filesystem.action-linkage', hash('sha256', $bytes), strlen($bytes));
    }
    public function toArray(): array { return ['operation_identity' => $this->operationIdentity, 'apply_plan_identity' => $this->applyPlanIdentity, 'recovery_identity' => $this->recoveryIdentity->value(), 'links' => array_map(static fn (FilesystemRecoveryActionLink $link): array => $link->toArray(), $this->links)]; }
}
