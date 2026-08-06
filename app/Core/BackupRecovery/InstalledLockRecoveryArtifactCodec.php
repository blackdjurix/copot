<?php

namespace Copot\Core\BackupRecovery;

final class InstalledLockRecoveryArtifactCodec
{
    private const DOMAIN = 'filesystem.lifecycle.installed-lock';
    private const MAX_BYTES = 8192;

    /** @param array{version: string, installed_at: string} $marker */
    public function encode(array $marker): string
    {
        $this->assertMarker($marker);
        $body = ['format_version' => 1, 'domain_identifier' => self::DOMAIN, 'marker' => $marker];
        return $this->json([...$body, 'identity' => hash('sha256', $this->json($body))]);
    }

    public function decode(string $bytes): InstalledLockRecoveryArtifact
    {
        if ($bytes === '' || strlen($bytes) > self::MAX_BYTES) { throw new LifecycleRecoveryException('Installed-lock recovery artifact size is invalid.'); }
        try { $record = json_decode($bytes, true, 16, JSON_THROW_ON_ERROR); } catch (\Throwable $exception) { throw new LifecycleRecoveryException('Installed-lock recovery artifact is malformed.', 0, $exception); }
        if (!is_array($record) || array_keys($record) !== ['format_version', 'domain_identifier', 'marker', 'identity'] || $record['format_version'] !== 1 || $record['domain_identifier'] !== self::DOMAIN || !is_array($record['marker']) || !is_string($record['identity'])) { throw new LifecycleRecoveryException('Installed-lock recovery artifact is invalid.'); }
        $body = $record; unset($body['identity']);
        if (preg_match('/^[a-f0-9]{64}$/D', $record['identity']) !== 1 || !hash_equals($record['identity'], hash('sha256', $this->json($body))) || $this->json($record) !== $bytes) { throw new LifecycleRecoveryException('Installed-lock recovery artifact integrity is invalid.'); }
        $this->assertMarker($record['marker']);
        return new InstalledLockRecoveryArtifact(new RecoveryArtifactRecord(self::DOMAIN, hash('sha256', $bytes), strlen($bytes)), $bytes, $record['marker']);
    }

    /** @param array{version: string, installed_at: string} $marker */
    public function artifactFromMarker(array $marker): InstalledLockRecoveryArtifact
    {
        $bytes = $this->encode($marker);
        return new InstalledLockRecoveryArtifact(new RecoveryArtifactRecord(self::DOMAIN, hash('sha256', $bytes), strlen($bytes)), $bytes, $marker);
    }

    private function assertMarker(array $marker): void
    {
        if (array_keys($marker) !== ['version', 'installed_at'] || !is_string($marker['version']) || !is_string($marker['installed_at'])) { throw new LifecycleRecoveryException('Installed-lock marker is invalid.'); }
        if (!preg_match('/^[0-9]+\.[0-9]+\.[0-9]+(?:[-+][A-Za-z0-9.-]+)?$/D', $marker['version']) || \DateTimeImmutable::createFromFormat(\DATE_ATOM, $marker['installed_at']) === false) { throw new LifecycleRecoveryException('Installed-lock marker is invalid.'); }
    }

    private function json(array $value): string { return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR); }
}
