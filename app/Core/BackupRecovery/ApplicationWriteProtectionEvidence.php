<?php

namespace Copot\Core\BackupRecovery;

final class ApplicationWriteProtectionEvidence
{
    /** @var list<string> */
    private array $coveredWriterClasses;

    /** @param list<string> $coveredWriterClasses */
    public function __construct(
        private DatabaseScope $scope,
        private RecoveryIdentity $operationIdentity,
        private string $writerExclusionIdentity,
        private string $drainIdentity,
        array $coveredWriterClasses,
        private bool $admissionEstablished,
        private bool $drainVerified,
        private int $capturedAt,
        private int $validUntil
    ) {
        $allowed = ['background', 'cli', 'http', 'scheduled'];
        $classes = array_values(array_unique(array_map('strval', $coveredWriterClasses)));
        sort($classes, SORT_STRING);
        if ($classes !== $allowed
            || !$admissionEstablished
            || !$drainVerified
            || preg_match('/^[a-f0-9]{64}$/D', strtolower($writerExclusionIdentity)) !== 1
            || preg_match('/^[a-f0-9]{64}$/D', strtolower($drainIdentity)) !== 1
            || $capturedAt < 1
            || $validUntil < $capturedAt) {
            throw new RecoveryLifecycleException('Application write protection evidence is incomplete.');
        }
        $this->coveredWriterClasses = $classes;
    }

    public function scope(): DatabaseScope { return $this->scope; }
    public function operationIdentity(): RecoveryIdentity { return $this->operationIdentity; }
    public function writerExclusionIdentity(): string { return $this->writerExclusionIdentity; }
    public function drainIdentity(): string { return $this->drainIdentity; }
    /** @return list<string> */
    public function coveredWriterClasses(): array { return $this->coveredWriterClasses; }
    public function admissionEstablished(): bool { return $this->admissionEstablished; }
    public function drainVerified(): bool { return $this->drainVerified; }
    public function capturedAt(): int { return $this->capturedAt; }
    public function validUntil(): int { return $this->validUntil; }
    public function isValidAt(?int $at = null): bool
    {
        $at ??= time();
        return $this->admissionEstablished && $this->drainVerified && $at >= $this->capturedAt && $at <= $this->validUntil;
    }
    public function identity(): string
    {
        return hash('sha256', json_encode([
            'scope_identity' => $this->scope->identity(),
            'operation_identity' => $this->operationIdentity->value(),
            'writer_exclusion_identity' => $this->writerExclusionIdentity,
            'drain_identity' => $this->drainIdentity,
            'covered_writer_classes' => $this->coveredWriterClasses,
            'admission_established' => $this->admissionEstablished,
            'drain_verified' => $this->drainVerified,
            'captured_at' => $this->capturedAt,
            'valid_until' => $this->validUntil,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
