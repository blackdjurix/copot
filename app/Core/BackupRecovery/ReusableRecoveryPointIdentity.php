<?php

namespace Copot\Core\BackupRecovery;

final class ReusableRecoveryPointIdentity
{
    public function __construct(private string $value)
    {
        if (preg_match('/^rp_[a-f0-9]{64}$/D', $value) !== 1) {
            throw new RecoveryInvariantException('Reusable recovery-point identity is invalid.');
        }
    }

    /** @param array<int, RecoveryDomainIdentity> $domains */
    public static function forCapture(
        string $projectIdentity,
        string $installationIdentity,
        string $originatingOperationIdentity,
        string $creationIdentity,
        string $createdAt,
        array $domains
    ): self {
        $rows = array_map(static fn (RecoveryDomainIdentity $domain): array => [
            'identifier' => $domain->identifier(),
            'ownership_key' => $domain->ownershipKey(),
            'scope_identity' => $domain->scopeIdentity(),
            'artifact_identity' => $domain->artifactIdentity(),
        ], $domains);
        usort($rows, static fn (array $left, array $right): int => $left['identifier'] <=> $right['identifier']);
        $body = [
            'kind' => 'reusable-filesystem-recovery-point',
            'project_identity' => $projectIdentity,
            'installation_identity' => $installationIdentity,
            'originating_operation_identity' => $originatingOperationIdentity,
            'creation_identity' => $creationIdentity,
            'created_at' => $createdAt,
            'domain_identities' => $rows,
        ];
        return new self('rp_' . hash('sha256', json_encode($body, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)));
    }

    public function value(): string { return $this->value; }
    public function equals(self $other): bool { return $this->value === $other->value; }
}

