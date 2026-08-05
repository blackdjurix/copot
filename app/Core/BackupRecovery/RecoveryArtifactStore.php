<?php

namespace Copot\Core\BackupRecovery;

final class RecoveryArtifactStore
{
    public function __construct(private RecoveryStorageRoot $root, private ?RecoveryAtomicFileWriter $writer = null, private ?RecoveryManifestCodec $codec = null)
    {
        $this->writer ??= new RecoveryAtomicFileWriter();
        $this->codec ??= new RecoveryManifestCodec();
    }

    /** @param array<int, array{record: RecoveryArtifactRecord, bytes: string}> $artifacts */
    public function publish(RecoveryManifest $manifest, array $artifacts): void
    {
        $policy = new RecoveryStoragePathPolicy($this->root);
        $setRoot = $policy->recoverySetRoot($manifest->recoveryIdentity());
        $manifestPath = $policy->manifestPath($manifest->recoveryIdentity());
        $this->makeDirectory($policy->recoverySetsRoot());
        $this->makeDirectory($setRoot . DIRECTORY_SEPARATOR . 'manifest');
        $this->makeDirectory($setRoot . DIRECTORY_SEPARATOR . 'artifacts');
        try {
            $records = [];
            foreach ($artifacts as $entry) {
                if (!is_array($entry) || !isset($entry['record'], $entry['bytes']) || !$entry['record'] instanceof RecoveryArtifactRecord || !is_string($entry['bytes'])) {
                    throw new RecoveryStorageException('Recovery artifact input is invalid.');
                }
                $record = $entry['record'];
                $bytes = $entry['bytes'];
                if (strlen($bytes) !== $record->byteSize() || hash('sha256', $bytes) !== $record->artifactIdentity()) {
                    throw new RecoveryStorageException('Recovery artifact identity does not match its bytes.');
                }
                $records[] = $record;
                $directory = dirname($policy->artifactPath($manifest->recoveryIdentity(), $record));
                $this->makeDirectory($directory);
                $this->writeOrReuse($policy->artifactPath($manifest->recoveryIdentity(), $record), $bytes);
                $metadata = $this->canonicalMetadata($record);
                $this->writeOrReuse($policy->artifactMetadataPath($manifest->recoveryIdentity(), $record), $metadata);
            }
            $encoded = $this->codec->encode($manifest, $records, true);
            $this->writeOrReuse($manifestPath, $encoded);
            $decoded = $this->codec->decode((string) file_get_contents($manifestPath));
            if (!$decoded['complete']) { throw new RecoveryStorageException('Recovery manifest was not published complete.'); }
        } catch (\Throwable $exception) {
            if (!file_exists($manifestPath)) { $this->removeTree($setRoot); }
            if ($exception instanceof RecoveryStorageException) { throw $exception; }
            throw new RecoveryStorageException('Recovery set publication failed.', 0, $exception);
        }
    }

    public function readManifest(RecoveryIdentity $identity): array
    {
        $path = (new RecoveryStoragePathPolicy($this->root))->manifestPath($identity);
        if (is_link($path) || !is_file($path) || !is_readable($path)) { throw new RecoveryStorageException('Recovery manifest is unavailable.'); }
        $decoded = $this->codec->decode((string) file_get_contents($path));
        if (!$decoded['complete']) { throw new RecoveryStorageException('Recovery manifest is incomplete.'); }
        $policy = new RecoveryStoragePathPolicy($this->root);
        foreach ($decoded['artifacts'] as $artifact) {
            $bytesPath = $policy->artifactPath($identity, $artifact);
            $metadataPath = $policy->artifactMetadataPath($identity, $artifact);
            if (is_link($bytesPath) || !is_file($bytesPath) || !is_readable($bytesPath) || @filesize($bytesPath) !== $artifact->byteSize() || @hash_file('sha256', $bytesPath) !== $artifact->artifactIdentity()) {
                throw new RecoveryStorageException('Recovery artifact failed integrity verification.');
            }
            $metadata = $this->canonicalMetadata($artifact);
            if (is_link($metadataPath) || !is_file($metadataPath) || !is_readable($metadataPath) || @filesize($metadataPath) !== strlen($metadata) || @hash_file('sha256', $metadataPath) !== hash('sha256', $metadata)) {
                throw new RecoveryStorageException('Recovery artifact metadata failed integrity verification.');
            }
        }
        return $decoded;
    }

    public function readArtifact(RecoveryIdentity $identity, RecoveryArtifactRecord $artifact): string
    {
        $decoded = $this->readManifest($identity);
        $referenced = false;
        foreach ($decoded['artifacts'] as $candidate) {
            if ($candidate->domainIdentifier() === $artifact->domainIdentifier()
                && $candidate->artifactIdentity() === $artifact->artifactIdentity()
                && $candidate->byteSize() === $artifact->byteSize()) {
                $referenced = true;
                break;
            }
        }
        if (!$referenced) {
            throw new RecoveryStorageException('Requested recovery artifact is not referenced by the immutable manifest.');
        }

        $path = (new RecoveryStoragePathPolicy($this->root))->artifactPath($identity, $artifact);
        if (is_link($path) || !is_file($path) || !is_readable($path)) {
            throw new RecoveryStorageException('Recovery artifact is unavailable.');
        }
        $contents = @file_get_contents($path);
        if (!is_string($contents) || strlen($contents) !== $artifact->byteSize() || hash('sha256', $contents) !== $artifact->artifactIdentity()) {
            throw new RecoveryStorageException('Recovery artifact failed integrity verification.');
        }
        return $contents;
    }

    private function writeOrReuse(string $path, string $contents): void
    {
        if (file_exists($path) || is_link($path)) {
            if (!is_file($path) || @filesize($path) !== strlen($contents) || @hash_file('sha256', $path) !== hash('sha256', $contents)) {
                throw new RecoveryStorageException('Immutable recovery artifact collision detected.');
            }
            return;
        }
        $this->writer?->write($path, $contents);
    }

    private function canonicalMetadata(RecoveryArtifactRecord $record): string
    {
        return json_encode($record->toArray(), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function makeDirectory(string $path): void
    {
        if (file_exists($path)) {
            if (is_link($path) || !is_dir($path) || !is_writable($path)) { throw new RecoveryStorageException('Recovery storage directory is unsafe.'); }
            return;
        }
        if (!mkdir($path, 0700, true) && !is_dir($path)) { throw new RecoveryStorageException('Recovery storage directory could not be created.'); }
        @chmod($path, 0700);
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path) || is_link($path)) { return; }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') { continue; }
            $child = $path . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($child) && !is_link($child)) { $this->removeTree($child); } else { @unlink($child); }
        }
        @rmdir($path);
    }
}
