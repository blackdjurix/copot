<?php

namespace Copot\Core;

use Copot\Core\BackupRecovery\RecoveryIdentityCrossReference;

/** Read-only typed result; it grants no recovery or mutation authority. */
final class LegacyBootstrapRecoveryIdentityCrossReferenceResult
{
    public const VALID = 'VALID';
    public const INCOMPLETE = 'INCOMPLETE';
    public const STALE = 'STALE';
    public const UNVERIFIED = 'UNVERIFIED';
    private string $identity;

    /** @param list<string> $diagnostics */
    public function __construct(
        private string $status,
        private string $handoffIdentity,
        private string $operationIdentity,
        private ?RecoveryIdentityCrossReference $crossReference,
        private ?string $manifestIdentity,
        private ?string $applicationEvidenceIdentity,
        private ?string $databaseEvidenceIdentity,
        private array $diagnostics
    ) {
        if (!in_array($status, [self::VALID, self::INCOMPLETE, self::STALE, self::UNVERIFIED], true)) throw new \InvalidArgumentException('Bootstrap recovery cross-reference status is invalid.');
        foreach ($diagnostics as $diagnostic) if (!is_string($diagnostic) || $diagnostic === '') throw new \InvalidArgumentException('Bootstrap recovery cross-reference diagnostic is invalid.');
        $this->identity = hash('sha256', json_encode([
            'status' => $status, 'handoff_identity' => $handoffIdentity, 'operation_identity' => $operationIdentity,
            'cross_reference_identity' => $crossReference?->identity(), 'manifest_identity' => $manifestIdentity,
            'application_evidence_identity' => $applicationEvidenceIdentity, 'database_evidence_identity' => $databaseEvidenceIdentity,
            'diagnostics' => $diagnostics,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    public function status(): string { return $this->status; }
    public function valid(): bool { return $this->status === self::VALID; }
    public function identity(): string { return $this->identity; }
    public function crossReference(): ?RecoveryIdentityCrossReference { return $this->crossReference; }
    public function diagnostics(): array { return $this->diagnostics; }
    public function operationalReady(): bool { return false; }
    public function mutationAuthorized(): bool { return false; }

    public function toArray(): array
    {
        return [
            'result_identity' => $this->identity, 'status' => $this->status,
            'handoff_identity' => $this->handoffIdentity, 'operation_identity' => $this->operationIdentity,
            'cross_reference' => $this->crossReference?->toArray(), 'manifest_identity' => $this->manifestIdentity,
            'application_evidence_identity' => $this->applicationEvidenceIdentity,
            'database_evidence_identity' => $this->databaseEvidenceIdentity,
            'diagnostics' => $this->diagnostics, 'operational_ready' => false, 'mutation_authorized' => false,
        ];
    }
}
