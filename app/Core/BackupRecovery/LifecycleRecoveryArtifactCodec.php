<?php

namespace Copot\Core\BackupRecovery;

use Copot\Core\CommittedLifecycleState;

final class LifecycleRecoveryArtifactCodec
{
    private const DOMAIN = 'filesystem.lifecycle.committed';
    private const MAX_BYTES = 16384;

    public function encodePresent(CommittedLifecycleState $state): string
    {
        return $this->encodeBody(['kind' => 'PRESENT_COMMITTED_STATE', 'value' => $state->toArray()]);
    }

    public function encodeAbsent(): string
    {
        return $this->encodeBody(['kind' => 'ABSENT_BEFORE_OPERATION']);
    }

    public function decode(string $bytes): LifecycleRecoveryArtifact
    {
        if ($bytes === '' || strlen($bytes) > self::MAX_BYTES) { throw new LifecycleRecoveryException('Lifecycle recovery artifact size is invalid.'); }
        try { $record = json_decode($bytes, true, 16, JSON_THROW_ON_ERROR); } catch (\Throwable $exception) { throw new LifecycleRecoveryException('Lifecycle recovery artifact is malformed.', 0, $exception); }
        if (!is_array($record)) { throw new LifecycleRecoveryException('Lifecycle recovery artifact is invalid.'); }
        $this->keys($record, ['format_version', 'domain_identifier', 'state', 'identity']);
        if ($record['format_version'] !== 1 || $record['domain_identifier'] !== self::DOMAIN || !is_array($record['state']) || !is_string($record['identity']) || preg_match('/^[a-f0-9]{64}$/D', $record['identity']) !== 1) { throw new LifecycleRecoveryException('Lifecycle recovery artifact fields are invalid.'); }
        $body = $record; unset($body['identity']);
        $expected = hash('sha256', $this->json($body));
        if (!hash_equals($expected, $record['identity']) || $this->json($record) !== $bytes) { throw new LifecycleRecoveryException('Lifecycle recovery artifact integrity is invalid.'); }
        $this->keys($record['state'], ['kind', ...($record['state']['kind'] ?? '') === 'PRESENT_COMMITTED_STATE' ? ['value'] : []]);
        $kind = $record['state']['kind'];
        if ($kind === 'PRESENT_COMMITTED_STATE') {
            if (!is_array($record['state']['value'])) { throw new LifecycleRecoveryException('Committed lifecycle state is invalid.'); }
            try { $state = CommittedLifecycleState::fromArray($record['state']['value']); } catch (\Throwable $exception) { throw new LifecycleRecoveryException('Committed lifecycle state is invalid.', 0, $exception); }
            return new LifecycleRecoveryArtifact(new RecoveryArtifactRecord(self::DOMAIN, hash('sha256', $bytes), strlen($bytes)), $bytes, $kind, $state);
        }
        if ($kind !== 'ABSENT_BEFORE_OPERATION') { throw new LifecycleRecoveryException('Lifecycle recovery state kind is invalid.'); }
        return new LifecycleRecoveryArtifact(new RecoveryArtifactRecord(self::DOMAIN, hash('sha256', $bytes), strlen($bytes)), $bytes, $kind, null);
    }

    public function artifactFromState(?CommittedLifecycleState $state): LifecycleRecoveryArtifact
    {
        $kind = $state instanceof CommittedLifecycleState ? 'PRESENT_COMMITTED_STATE' : 'ABSENT_BEFORE_OPERATION';
        $bytes = $state instanceof CommittedLifecycleState ? $this->encodePresent($state) : $this->encodeAbsent();
        return new LifecycleRecoveryArtifact(new RecoveryArtifactRecord(self::DOMAIN, hash('sha256', $bytes), strlen($bytes)), $bytes, $kind, $state);
    }

    /** @param array<string, mixed> $state */
    private function encodeBody(array $state): string
    {
        $body = ['format_version' => 1, 'domain_identifier' => self::DOMAIN, 'state' => $state];
        $record = [...$body, 'identity' => hash('sha256', $this->json($body))];
        $bytes = $this->json($record);
        if (strlen($bytes) > self::MAX_BYTES) { throw new LifecycleRecoveryException('Lifecycle recovery artifact exceeds the bounded size.'); }
        return $bytes;
    }

    private function json(array $value): string { return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR); }

    private function keys(array $value, array $required): void { if (array_keys($value) !== $required) { throw new LifecycleRecoveryException('Lifecycle recovery artifact has unknown or missing fields.'); } }
}
