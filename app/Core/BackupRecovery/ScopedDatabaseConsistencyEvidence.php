<?php

namespace Copot\Core\BackupRecovery;

final class ScopedDatabaseConsistencyEvidence
{
    public function __construct(
        private DatabaseScope $scope,
        private RecoveryIdentity $operationIdentity,
        private string $writerExclusionIdentity,
        private string $schemaStabilityIdentity,
        private int $capturedAt,
        private int $validUntil
    ) {
        foreach ([$writerExclusionIdentity, $schemaStabilityIdentity] as $identity) {
            if (preg_match('/^[a-f0-9]{64}$/D', strtolower($identity)) !== 1) {
                throw new DatabaseRecoveryException('Scoped database consistency evidence is invalid.');
            }
        }
        if ($capturedAt < 1 || $validUntil < $capturedAt) {
            throw new DatabaseRecoveryException('Scoped database consistency validity is invalid.');
        }
    }

    public function scope(): DatabaseScope { return $this->scope; }
    public function operationIdentity(): RecoveryIdentity { return $this->operationIdentity; }
    public function writerExclusionIdentity(): string { return $this->writerExclusionIdentity; }
    public function schemaStabilityIdentity(): string { return $this->schemaStabilityIdentity; }
    public function capturedAt(): int { return $this->capturedAt; }
    public function validUntil(): int { return $this->validUntil; }
    public function isValidAt(?int $at = null): bool
    {
        $at ??= time();
        return $at >= $this->capturedAt && $at <= $this->validUntil;
    }
    public function identity(): string
    {
        return hash('sha256', json_encode([
            'scope_identity' => $this->scope->identity(),
            'operation_identity' => $this->operationIdentity->value(),
            'writer_exclusion_identity' => $this->writerExclusionIdentity,
            'schema_stability_identity' => $this->schemaStabilityIdentity,
            'captured_at' => $this->capturedAt,
            'valid_until' => $this->validUntil,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
