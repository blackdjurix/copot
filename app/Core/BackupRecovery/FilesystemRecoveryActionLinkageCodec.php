<?php

namespace Copot\Core\BackupRecovery;

final class FilesystemRecoveryActionLinkageCodec
{
    private const DOMAIN = 'filesystem.action-linkage';
    public function encode(FilesystemRecoveryActionLinkage $linkage): string
    {
        return json_encode(['domain_identifier' => self::DOMAIN, 'version' => 1, ...$linkage->toArray(), 'linkage_identity' => $linkage->identity()], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    public function decode(string $bytes): FilesystemRecoveryActionLinkage
    {
        $data = json_decode($bytes, true, 32, JSON_THROW_ON_ERROR);
        $required = ['domain_identifier', 'version', 'operation_identity', 'apply_plan_identity', 'recovery_identity', 'links', 'linkage_identity'];
        if (!is_array($data) || array_keys($data) !== $required || $data['domain_identifier'] !== self::DOMAIN || $data['version'] !== 1 || !is_array($data['links']) || !is_string($data['linkage_identity'])) throw new FilesystemRecoveryException('Filesystem recovery action linkage structure is invalid.');
        if (preg_match('/^[a-f0-9]{64}$/D', $data['linkage_identity']) !== 1) throw new FilesystemRecoveryException('Filesystem recovery action linkage integrity failed.');
        $links = [];
        foreach ($data['links'] as $row) {
            if (!is_array($row) || array_keys($row) !== ['operation_identity', 'apply_plan_identity', 'recovery_identity', 'ordinal', 'action', 'root', 'path', 'action_identity', 'original_artifact_identity'] || !is_int($row['ordinal'])) throw new FilesystemRecoveryException('Filesystem recovery action link is malformed.');
            $links[] = new FilesystemRecoveryActionLink($row['operation_identity'], $row['apply_plan_identity'], new RecoveryIdentity($row['recovery_identity']), $row['ordinal'], $row['action'], $row['root'], $row['path'], $row['action_identity'], $row['original_artifact_identity']);
        }
        $linkage = new FilesystemRecoveryActionLinkage($data['operation_identity'], $data['apply_plan_identity'], new RecoveryIdentity($data['recovery_identity']), $links);
        if (!hash_equals($linkage->identity(), $data['linkage_identity'])) throw new FilesystemRecoveryException('Filesystem recovery action linkage identity changed.');
        return $linkage;
    }
}
