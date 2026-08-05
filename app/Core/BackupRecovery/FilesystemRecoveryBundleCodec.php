<?php

namespace Copot\Core\BackupRecovery;

final class FilesystemRecoveryBundleCodec
{
    private const DOMAIN = 'filesystem.package-owned';
    private const MAX_BYTES = 268435456;
    private const MAX_ENTRIES = 4096;

    /** @param array<int, FilesystemRecoveryEntry> $entries */
    public function encode(FilesystemRecoveryPlan $plan, array $entries): string
    {
        if (count($entries) < 1 || count($entries) > self::MAX_ENTRIES) {
            throw new FilesystemRecoveryException('Filesystem recovery bundle entry count is invalid.');
        }
        $rows = [];
        $seen = [];
        foreach ($entries as $entry) {
            if (!$entry instanceof FilesystemRecoveryEntry || $entry->preOperationState() === null || isset($seen[$entry->path()])) {
                throw new FilesystemRecoveryException('Filesystem recovery bundle contains invalid entries.');
            }
            $seen[$entry->path()] = true;
            $rows[] = $entry->toArray();
        }
        usort($rows, static fn (array $a, array $b): int => $a['path'] <=> $b['path']);
        $payload = [
            'domain_identifier' => self::DOMAIN,
            'apply_plan_identity' => $plan->applyPlanIdentity(),
            'entries' => $rows,
            'version' => 1,
        ];
        try {
            $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (\Throwable $exception) {
            throw new FilesystemRecoveryException('Filesystem recovery bundle could not be encoded.', 0, $exception);
        }
        if (strlen($encoded) > self::MAX_BYTES) {
            throw new FilesystemRecoveryException('Filesystem recovery bundle exceeds its bounded size.');
        }
        return $encoded;
    }

    /** @return array{plan_identity: string, entries: array<int, FilesystemRecoveryEntry>} */
    public function decode(string $bytes): array
    {
        if ($bytes === '' || strlen($bytes) > self::MAX_BYTES) {
            throw new FilesystemRecoveryException('Filesystem recovery bundle size is invalid.');
        }
        try {
            $payload = json_decode($bytes, true, 32, JSON_THROW_ON_ERROR);
        } catch (\Throwable $exception) {
            throw new FilesystemRecoveryException('Filesystem recovery bundle is malformed.', 0, $exception);
        }
        if (!is_array($payload) || array_keys($payload) !== ['domain_identifier', 'apply_plan_identity', 'entries', 'version'] || $payload['domain_identifier'] !== self::DOMAIN || $payload['version'] !== 1 || !is_string($payload['apply_plan_identity']) || !is_array($payload['entries']) || count($payload['entries']) > self::MAX_ENTRIES) {
            throw new FilesystemRecoveryException('Filesystem recovery bundle structure is invalid.');
        }
        $entries = [];
        $seen = [];
        foreach ($payload['entries'] as $row) {
            if (!is_array($row) || array_keys($row) !== ['path', 'pre_operation_state', 'pre_operation_size', 'pre_operation_hash', 'pre_operation_mode', 'target_size', 'target_hash', 'pre_image_base64']) {
                throw new FilesystemRecoveryException('Filesystem recovery bundle contains unknown or missing fields.');
            }
            try {
                $image = $row['pre_image_base64'] === null ? null : base64_decode((string) $row['pre_image_base64'], true);
                if ($row['pre_image_base64'] !== null && $image === false) { throw new FilesystemRecoveryException('Filesystem recovery bundle image encoding is invalid.'); }
                $entry = new FilesystemRecoveryEntry($row['path'], $row['target_size'], $row['target_hash'], $row['pre_operation_state'], $row['pre_operation_size'], $row['pre_operation_hash'], $row['pre_operation_mode'], $image);
            } catch (\Throwable $exception) {
                if ($exception instanceof FilesystemRecoveryException) { throw $exception; }
                throw new FilesystemRecoveryException('Filesystem recovery bundle entry is invalid.', 0, $exception);
            }
            if (isset($seen[$entry->path()])) { throw new FilesystemRecoveryException('Filesystem recovery bundle contains duplicate paths.'); }
            $seen[$entry->path()] = true;
            $entries[] = $entry;
        }
        if ($entries === []) { throw new FilesystemRecoveryException('Filesystem recovery bundle contains no entries.'); }
        return ['plan_identity' => $payload['apply_plan_identity'], 'entries' => $entries];
    }
}
