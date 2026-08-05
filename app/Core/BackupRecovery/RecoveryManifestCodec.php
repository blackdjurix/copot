<?php

namespace Copot\Core\BackupRecovery;

final class RecoveryManifestCodec
{
    private const MAX_BYTES = 1048576;

    /** @param array<int, RecoveryArtifactRecord> $artifacts */
    public function encode(RecoveryManifest $manifest, array $artifacts, bool $complete = true): string
    {
        $body = $this->body($manifest, $artifacts, $complete);
        $bodyBytes = $this->canonicalJson($body);
        $record = $body;
        $record['manifest_identity'] = hash('sha256', $bodyBytes);
        $encoded = $this->canonicalJson($record);
        if (strlen($encoded) > self::MAX_BYTES) {
            throw new RecoveryStorageException('Recovery manifest exceeds the bounded size.');
        }
        return $encoded;
    }

    /** @return array{manifest: RecoveryManifest, artifacts: array<int, RecoveryArtifactRecord>, complete: bool, identity: string} */
    public function decode(string $encoded): array
    {
        if ($encoded === '' || strlen($encoded) > self::MAX_BYTES) {
            throw new RecoveryStorageException('Recovery manifest size is invalid.');
        }
        try {
            $record = json_decode($encoded, true, 32, JSON_THROW_ON_ERROR);
        } catch (\Throwable $exception) {
            throw new RecoveryStorageException('Recovery manifest is malformed.', 0, $exception);
        }
        if (!is_array($record)) {
            throw new RecoveryStorageException('Recovery manifest is invalid.');
        }
        $required = ['recovery_identity', 'operation_identity', 'target_package_identity', 'target_release_identity', 'archive_identity', 'apply_plan_identity', 'domain_identities', 'pre_operation_lifecycle_identity', 'pre_operation_migration_ledger_identity', 'artifacts', 'capture_publication_complete', 'manifest_identity'];
        $this->assertKeys($record, $required);
        if (!is_string($record['manifest_identity']) || preg_match('/^[a-f0-9]{64}$/D', $record['manifest_identity']) !== 1) {
            throw new RecoveryStorageException('Recovery manifest identity is invalid.');
        }
        $body = $record;
        unset($body['manifest_identity']);
        $expected = hash('sha256', $this->canonicalJson($body));
        if (!hash_equals($expected, $record['manifest_identity'])) {
            throw new RecoveryStorageException('Recovery manifest identity does not match.');
        }
        if (!is_bool($record['capture_publication_complete']) || !is_array($record['domain_identities']) || !is_array($record['artifacts'])) {
            throw new RecoveryStorageException('Recovery manifest structure is invalid.');
        }
        try {
            $domains = [];
            foreach ($record['domain_identities'] as $domain) {
                if (!is_array($domain)) { throw new RecoveryInvariantException('Invalid recovery domain identity.'); }
                $this->assertKeys($domain, ['identifier', 'ownership_key', 'scope_identity', 'artifact_identity']);
                $domains[] = new RecoveryDomainIdentity($domain['identifier'], $domain['ownership_key'], $domain['scope_identity'], $domain['artifact_identity']);
            }
            $artifacts = [];
            foreach ($record['artifacts'] as $artifact) {
                if (!is_array($artifact)) { throw new RecoveryInvariantException('Invalid recovery artifact.'); }
                $this->assertKeys($artifact, ['domain_identifier', 'artifact_identity', 'byte_size', 'metadata_identity']);
                $artifacts[] = new RecoveryArtifactRecord($artifact['domain_identifier'], $artifact['artifact_identity'], $artifact['byte_size'], $artifact['metadata_identity']);
            }
            $manifest = new RecoveryManifest(new RecoveryIdentity($record['recovery_identity']), $record['operation_identity'], $record['target_package_identity'], $record['target_release_identity'], $record['archive_identity'], $record['apply_plan_identity'], $domains, $record['pre_operation_lifecycle_identity'], $record['pre_operation_migration_ledger_identity']);
        } catch (\Throwable $exception) {
            throw new RecoveryStorageException('Recovery manifest contains invalid required state.', 0, $exception);
        }
        $this->assertArtifactBindings($manifest, $artifacts);
        return ['manifest' => $manifest, 'artifacts' => $artifacts, 'complete' => $record['capture_publication_complete'], 'identity' => $record['manifest_identity']];
    }

    /** @param array<int, RecoveryArtifactRecord> $artifacts */
    private function body(RecoveryManifest $manifest, array $artifacts, bool $complete): array
    {
        $artifactRows = [];
        foreach ($artifacts as $artifact) {
            if (!$artifact instanceof RecoveryArtifactRecord) { throw new RecoveryStorageException('Recovery manifest contains an invalid artifact.'); }
            $artifactRows[] = $artifact->toArray();
        }
        usort($artifactRows, static fn (array $a, array $b): int => [$a['domain_identifier'], $a['artifact_identity']] <=> [$b['domain_identifier'], $b['artifact_identity']]);
        $domainRows = array_map(static fn (RecoveryDomainIdentity $domain): array => ['identifier' => $domain->identifier(), 'ownership_key' => $domain->ownershipKey(), 'scope_identity' => $domain->scopeIdentity(), 'artifact_identity' => $domain->artifactIdentity()], $manifest->domainIdentities());
        return ['recovery_identity' => $manifest->recoveryIdentity()->value(), 'operation_identity' => $manifest->operationIdentity(), 'target_package_identity' => $manifest->targetPackageIdentity(), 'target_release_identity' => $manifest->targetReleaseIdentity(), 'archive_identity' => $manifest->archiveIdentity(), 'apply_plan_identity' => $manifest->applyPlanIdentity(), 'domain_identities' => $domainRows, 'pre_operation_lifecycle_identity' => $manifest->preOperationLifecycleIdentity(), 'pre_operation_migration_ledger_identity' => $manifest->preOperationMigrationLedgerIdentity(), 'artifacts' => $artifactRows, 'capture_publication_complete' => $complete];
    }

    private function canonicalJson(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function assertKeys(array $value, array $required): void
    {
        if (array_diff($required, array_keys($value)) !== [] || array_diff(array_keys($value), $required) !== []) {
            throw new RecoveryStorageException('Recovery manifest contains unknown or missing fields.');
        }
    }

    /** @param array<int, RecoveryArtifactRecord> $artifacts */
    private function assertArtifactBindings(RecoveryManifest $manifest, array $artifacts): void
    {
        $expected = [];
        foreach ($manifest->domainIdentities() as $domain) {
            $expected[$domain->identifier()] = $domain->artifactIdentity();
        }
        $seen = [];
        foreach ($artifacts as $artifact) {
            if (!array_key_exists($artifact->domainIdentifier(), $expected) || $expected[$artifact->domainIdentifier()] !== $artifact->artifactIdentity() || isset($seen[$artifact->domainIdentifier()])) {
                throw new RecoveryStorageException('Recovery artifact does not match its manifest domain binding.');
            }
            $seen[$artifact->domainIdentifier()] = true;
        }
        if (count($seen) !== count($expected)) {
            throw new RecoveryStorageException('Recovery manifest references missing domain artifacts.');
        }
    }
}
