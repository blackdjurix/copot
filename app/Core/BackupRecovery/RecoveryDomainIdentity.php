<?php

namespace Copot\Core\BackupRecovery;

final class RecoveryDomainIdentity
{
    public function __construct(
        private string $identifier,
        private string $ownershipKey,
        private string $scopeIdentity,
        private string $artifactIdentity
    ) {
        self::assertTechnical($identifier, 'Recovery domain identifier');
        self::assertTechnical($ownershipKey, 'Recovery domain ownership key');
        self::assertOpaque($scopeIdentity, 'Recovery domain scope identity');
        self::assertHash($artifactIdentity, 'Recovery domain artifact identity');
    }

    public function identifier(): string
    {
        return $this->identifier;
    }

    public function ownershipKey(): string
    {
        return $this->ownershipKey;
    }

    public function scopeIdentity(): string
    {
        return $this->scopeIdentity;
    }

    public function artifactIdentity(): string
    {
        return $this->artifactIdentity;
    }

    public function identity(): string
    {
        return hash('sha256', json_encode([
            'identifier' => $this->identifier,
            'ownership_key' => $this->ownershipKey,
            'scope_identity' => $this->scopeIdentity,
            'artifact_identity' => $this->artifactIdentity,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private static function assertTechnical(string $value, string $label): void
    {
        if (preg_match('/^[a-z][a-z0-9._-]*$/D', $value) !== 1) {
            throw new RecoveryInvariantException($label . ' is invalid.');
        }
    }

    private static function assertOpaque(string $value, string $label): void
    {
        if ($value === '' || trim($value) !== $value || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            throw new RecoveryInvariantException($label . ' is invalid.');
        }
    }

    private static function assertHash(string $value, string $label): void
    {
        if (preg_match('/^[a-f0-9]{64}$/D', strtolower($value)) !== 1) {
            throw new RecoveryInvariantException($label . ' is invalid.');
        }
    }
}
