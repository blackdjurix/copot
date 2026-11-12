<?php

namespace Copot\Core\BackupRecovery;

use Copot\Core\SourceInventoryReference;

final class ReusableRecoveryPointManifestCodec
{
    private const MAX_BYTES = 1048576;

    /** @param array<int, RecoveryArtifactRecord> $artifacts */
    public function encode(ReusableRecoveryPointManifest $manifest): string
    {
        $body = $this->body($manifest);
        $record = [...$body, 'manifest_identity' => hash('sha256', $this->json($body))];
        $encoded = $this->json($record);
        if (strlen($encoded) > self::MAX_BYTES) throw new RecoveryStorageException('Reusable recovery-point manifest exceeds the bounded size.');
        return $encoded;
    }

    /** @return array{manifest: ReusableRecoveryPointManifest, identity: string} */
    public function decode(string $encoded): array
    {
        if ($encoded === '' || strlen($encoded) > self::MAX_BYTES) throw new RecoveryStorageException('Reusable recovery-point manifest size is invalid.');
        try { $record = json_decode($encoded, true, 32, JSON_THROW_ON_ERROR); } catch (\Throwable $exception) { throw new RecoveryStorageException('Reusable recovery-point manifest is malformed.', 0, $exception); }
        if (!is_array($record)) throw new RecoveryStorageException('Reusable recovery-point manifest is invalid.');
        $required = ['format_version','kind','point_identity','project_identity','installation_identity','originating_operation_identity','creation_identity','created_at','source_inventory_reference','captured_lifecycle_identity','captured_installed_lock_identity','domain_identities','artifacts','manifest_identity'];
        $this->keys($record, $required);
        if (!is_string($record['manifest_identity']) || preg_match('/^[a-f0-9]{64}$/D', $record['manifest_identity']) !== 1) throw new RecoveryStorageException('Reusable recovery-point manifest identity is invalid.');
        $body = $record; unset($body['manifest_identity']);
        if (!hash_equals($record['manifest_identity'], hash('sha256', $this->json($body))) || $this->json($record) !== $encoded) throw new RecoveryStorageException('Reusable recovery-point manifest integrity is invalid.');
        if ($record['format_version'] !== ReusableRecoveryPointManifest::FORMAT_VERSION || $record['kind'] !== ReusableRecoveryPointManifest::KIND || !is_array($record['source_inventory_reference']) || !is_array($record['domain_identities']) || !is_array($record['artifacts'])) throw new RecoveryStorageException('Reusable recovery-point manifest structure is invalid.');
        try {
            $domains = [];
            foreach ($record['domain_identities'] as $domain) {
                if (!is_array($domain)) throw new RecoveryInvariantException('Reusable recovery-point domain is invalid.');
                $this->keys($domain, ['identifier','ownership_key','scope_identity','artifact_identity']);
                $domains[] = new RecoveryDomainIdentity($domain['identifier'], $domain['ownership_key'], $domain['scope_identity'], $domain['artifact_identity']);
            }
            $artifacts = [];
            foreach ($record['artifacts'] as $artifact) {
                if (!is_array($artifact)) throw new RecoveryInvariantException('Reusable recovery-point artifact is invalid.');
                $this->keys($artifact, ['domain_identifier','artifact_identity','byte_size','metadata_identity']);
                $artifacts[] = new RecoveryArtifactRecord($artifact['domain_identifier'], $artifact['artifact_identity'], $artifact['byte_size'], $artifact['metadata_identity']);
            }
            $manifest = new ReusableRecoveryPointManifest(
                new ReusableRecoveryPointIdentity($record['point_identity']),
                $record['project_identity'],
                $record['installation_identity'],
                $record['originating_operation_identity'],
                $record['creation_identity'],
                $record['created_at'],
                SourceInventoryReference::fromArray($record['source_inventory_reference']),
                $record['captured_lifecycle_identity'],
                $record['captured_installed_lock_identity'],
                $domains,
                $artifacts
            );
        } catch (\Throwable $exception) { throw new RecoveryStorageException('Reusable recovery-point manifest state is invalid.', 0, $exception); }
        if ($manifest->identity() !== $record['manifest_identity']) throw new RecoveryStorageException('Reusable recovery-point manifest identity changed.');
        return ['manifest' => $manifest, 'identity' => $record['manifest_identity']];
    }

    private function body(ReusableRecoveryPointManifest $manifest): array
    {
        $domains = array_map(static fn (RecoveryDomainIdentity $domain): array => ['identifier' => $domain->identifier(), 'ownership_key' => $domain->ownershipKey(), 'scope_identity' => $domain->scopeIdentity(), 'artifact_identity' => $domain->artifactIdentity()], $manifest->domainIdentities());
        $artifacts = array_map(static fn (RecoveryArtifactRecord $artifact): array => $artifact->toArray(), $manifest->artifactRecords());
        return ['format_version' => ReusableRecoveryPointManifest::FORMAT_VERSION, 'kind' => ReusableRecoveryPointManifest::KIND, 'point_identity' => $manifest->pointIdentity()->value(), 'project_identity' => $manifest->projectIdentity(), 'installation_identity' => $manifest->installationIdentity(), 'originating_operation_identity' => $manifest->originatingOperationIdentity(), 'creation_identity' => $manifest->creationIdentity(), 'created_at' => $manifest->createdAt(), 'source_inventory_reference' => $manifest->sourceInventoryReference()->toArray(), 'captured_lifecycle_identity' => $manifest->capturedLifecycleIdentity(), 'captured_installed_lock_identity' => $manifest->capturedInstalledLockIdentity(), 'domain_identities' => $domains, 'artifacts' => $artifacts];
    }
    private function json(array $value): string { return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR); }
    private function keys(array $value, array $required): void { if (array_keys($value) !== $required) throw new RecoveryStorageException('Reusable recovery-point manifest has unknown or missing fields.'); }
}

