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
        $decoded = $this->decodeManifestPath($path);
        if ($decoded['manifest']->recoveryIdentity()->value() !== $identity->value()) {
            throw new RecoveryStorageException('Recovery manifest identity does not match the requested recovery set.');
        }
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

    /** Locate and integrity-read one manifest by its canonical operation identity. */
    public function findManifestByOperationIdentity(string $operationIdentity, ?string $executionAttemptIdentity = null): array
    {
        if ($operationIdentity === '' || preg_match('/[\x00-\x1F\x7F]/', $operationIdentity) === 1) {
            throw new RecoveryStorageException('Recovery operation locator is invalid.');
        }
        $root = (new RecoveryStoragePathPolicy($this->root))->recoverySetsRoot();
        if (!is_dir($root)) {
            throw new RecoveryStorageException('Recovery set storage is unavailable.');
        }
        $matches = [];
        foreach (glob($root . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . 'manifest' . DIRECTORY_SEPARATOR . 'recovery-manifest.json') ?: [] as $path) {
            $decoded = $this->decodeManifestPath($path);
            if ($decoded['manifest']->operationIdentity() === $operationIdentity
                && ($executionAttemptIdentity === null
                    ? $decoded['manifest']->executionAttemptIdentity() === null
                    : $decoded['manifest']->executionAttemptIdentity() === $executionAttemptIdentity)) {
                $matches[] = $decoded;
            }
        }
        if (count($matches) !== 1) {
            throw new RecoveryStorageException('Recovery operation locator is missing or ambiguous.');
        }
        $identity = $matches[0]['manifest']->recoveryIdentity();
        return $this->readManifest($identity);
    }

    /**
     * Discover every manifest candidate for one operation without collapsing
     * execution attempts. The physical recovery-set boundary is inspected as
     * well, so an artifact-only or otherwise unclassified residue cannot be
     * mistaken for an empty recovery store.
     *
     * @return array<int, array{manifest: RecoveryManifest, artifacts: array<int, RecoveryArtifactRecord>, complete: bool, identity: string}>
     */
    public function findManifestCandidatesByOperationIdentity(string $operationIdentity): array
    {
        $this->assertOperationIdentity($operationIdentity);
        $policy = new RecoveryStoragePathPolicy($this->root);
        $root = $policy->recoverySetsRoot();
        if (is_link($root)) {
            throw new RecoveryStorageException('Recovery set storage is unsafe.');
        }
        if (!is_dir($root)) {
            if (file_exists($root)) {
                throw new RecoveryStorageException('Recovery set storage is unavailable.');
            }
            return [];
        }

        $entries = @scandir($root);
        if ($entries === false) {
            throw new RecoveryStorageException('Recovery set storage could not be enumerated.');
        }

        $matches = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $setRoot = $root . DIRECTORY_SEPARATOR . $entry;
            if (is_link($setRoot) || !is_dir($setRoot) || preg_match('/^[a-f0-9]{64}$/D', $entry) !== 1) {
                throw new RecoveryStorageException('Recovery set residue is unsafe or ambiguous.');
            }

            $manifestPath = $setRoot . DIRECTORY_SEPARATOR . 'manifest' . DIRECTORY_SEPARATOR . 'recovery-manifest.json';
            $lifecyclePath = $setRoot . DIRECTORY_SEPARATOR . 'state' . DIRECTORY_SEPARATOR . 'recovery-lifecycle.json';
            $manifestPresent = $this->assertOptionalFile($manifestPath, 'Recovery manifest');
            $lifecyclePresent = $this->assertOptionalFile($lifecyclePath, 'Recovery lifecycle record');
            if (!$manifestPresent && !$lifecyclePresent) {
                throw new RecoveryStorageException('Recovery set residue has no authoritative manifest or lifecycle record.');
            }

            $decoded = null;
            if ($manifestPresent) {
                $decoded = $this->decodeManifestPath($manifestPath);
                if ($decoded['complete']) {
                    $this->readManifest($decoded['manifest']->recoveryIdentity());
                }
                if ($decoded['manifest']->operationIdentity() === $operationIdentity) {
                    $matches[] = $decoded;
                }
            }

            $this->assertSetResidue($setRoot, $decoded, $manifestPresent, $lifecyclePresent);
        }

        return $matches;
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

    /** @return array{manifest: RecoveryManifest, artifacts: array<int, RecoveryArtifactRecord>, complete: bool, identity: string} */
    private function decodeManifestPath(string $path): array
    {
        if (is_link($path) || !is_file($path) || !is_readable($path)) {
            throw new RecoveryStorageException('Recovery manifest is unavailable.');
        }
        $decoded = $this->codec->decode((string) file_get_contents($path));
        $expectedPath = (new RecoveryStoragePathPolicy($this->root))->manifestPath($decoded['manifest']->recoveryIdentity());
        if ($this->normalizePath($expectedPath) !== $this->normalizePath($path)) {
            throw new RecoveryStorageException('Recovery manifest identity does not match its storage namespace.');
        }
        return $decoded;
    }

    private function assertOperationIdentity(string $operationIdentity): void
    {
        if ($operationIdentity === '' || preg_match('/[\x00-\x1F\x7F]/', $operationIdentity) === 1) {
            throw new RecoveryStorageException('Recovery operation locator is invalid.');
        }
    }

    private function assertOptionalFile(string $path, string $label): bool
    {
        if (is_link($path) || (file_exists($path) && !is_file($path))) {
            throw new RecoveryStorageException($label . ' is unsafe or ambiguous.');
        }
        if (!file_exists($path)) {
            return false;
        }
        if (!is_readable($path)) {
            throw new RecoveryStorageException($label . ' is unreadable.');
        }
        return true;
    }

    /**
     * @param array{manifest: RecoveryManifest, artifacts: array<int, RecoveryArtifactRecord>, complete: bool, identity: string}|null $decoded
     */
    private function assertSetResidue(string $setRoot, ?array $decoded, bool $manifestPresent, bool $lifecyclePresent): void
    {
        $topLevel = @scandir($setRoot);
        if ($topLevel === false) {
            throw new RecoveryStorageException('Recovery set could not be enumerated.');
        }
        foreach ($topLevel as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $setRoot . DIRECTORY_SEPARATOR . $entry;
            if (is_link($path) || !is_dir($path) || !in_array($entry, ['manifest', 'artifacts', 'state'], true)) {
                throw new RecoveryStorageException('Recovery set contains unclassified physical residue.');
            }
        }

        $allowedDirectories = ['manifest', 'artifacts', 'state'];
        $allowedFiles = [];
        if ($manifestPresent) {
            $allowedFiles['manifest/recovery-manifest.json'] = true;
        }
        if ($lifecyclePresent) {
            $allowedFiles['state/recovery-lifecycle.json'] = true;
        }
        if ($decoded !== null) {
            foreach ($decoded['artifacts'] as $artifact) {
                $domain = $artifact->domainIdentifier();
                $allowedDirectories[] = 'artifacts/' . $domain;
                $allowedFiles['artifacts/' . $domain . '/' . $artifact->artifactIdentity() . '.bin'] = true;
                $allowedFiles['artifacts/' . $domain . '/' . $artifact->artifactIdentity() . '.json'] = true;
            }
        }

        $tree = $this->collectTreeEntries($setRoot);
        foreach ($tree['directories'] as $directory) {
            if (!in_array($directory, $allowedDirectories, true)) {
                throw new RecoveryStorageException('Recovery set contains unclassified directory residue.');
            }
        }
        foreach ($tree['files'] as $file) {
            if (!isset($allowedFiles[$file])) {
                throw new RecoveryStorageException('Recovery set contains unclassified artifact residue.');
            }
        }
    }

    /** @return array{files: array<int, string>, directories: array<int, string>} */
    private function collectTreeEntries(string $directory, string $relative = ''): array
    {
        $entries = @scandir($directory);
        if ($entries === false) {
            throw new RecoveryStorageException('Recovery set residue could not be enumerated.');
        }
        $files = [];
        $directories = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $directory . DIRECTORY_SEPARATOR . $entry;
            $child = $relative === '' ? $entry : $relative . '/' . $entry;
            if (is_link($path)) {
                throw new RecoveryStorageException('Recovery set contains a symlink residue.');
            }
            if (is_dir($path)) {
                $directories[] = $child;
                $nested = $this->collectTreeEntries($path, $child);
                $files = [...$files, ...$nested['files']];
                $directories = [...$directories, ...$nested['directories']];
                continue;
            }
            if (!is_file($path) || !is_readable($path)) {
                throw new RecoveryStorageException('Recovery set contains unreadable physical residue.');
            }
            $files[] = $child;
        }
        return ['files' => $files, 'directories' => $directories];
    }

    private function canonicalMetadata(RecoveryArtifactRecord $record): string
    {
        return json_encode($record->toArray(), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function normalizePath(string $path): string
    {
        $normalized = rtrim(str_replace('/', DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
        return DIRECTORY_SEPARATOR === '\\' ? strtolower($normalized) : $normalized;
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
