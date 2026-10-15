<?php

namespace Copot\Core;

/** Private immutable store for committed complete source inventories. */
final class SourceInventoryArtifactStore
{
    private string $root;
    private string $directory;

    public function __construct(string $storagePath, private mixed $sync = null)
    {
        if (is_link($storagePath) || !is_dir($storagePath) || !is_writable($storagePath)) { throw new \RuntimeException('Source inventory storage is unavailable.'); }
        $storage = realpath($storagePath);
        if ($storage === false || is_link($storage)) { throw new \RuntimeException('Source inventory storage is unavailable.'); }
        $this->root = $storage . DIRECTORY_SEPARATOR . '.copot-lifecycle';
        if (!file_exists($this->root) && !mkdir($this->root, 0700)) { throw new \RuntimeException('Source inventory namespace could not be created.'); }
        if (is_link($this->root) || !is_dir($this->root) || !is_writable($this->root)) { throw new \RuntimeException('Source inventory namespace is unsafe.'); }
        $this->directory = $this->root . DIRECTORY_SEPARATOR . 'source-inventory';
        if (!file_exists($this->directory) && !mkdir($this->directory, 0700)) { throw new \RuntimeException('Source inventory store could not be created.'); }
        if (is_link($this->directory) || !is_dir($this->directory) || !is_writable($this->directory)) { throw new \RuntimeException('Source inventory store is unsafe.'); }
    }

    public function publish(CanonicalTargetInventory $inventory, string $releaseIdentity, int $manifestContractVersion, ?string $sourceTreeIdentity = null): SourceInventoryArtifact
    {
        $entries = $inventory->entries();
        usort($entries, static fn (CanonicalTargetInventoryEntry $left, CanonicalTargetInventoryEntry $right): int => strcmp($left->root() . "\0" . $left->ownership() . "\0" . $left->path(), $right->root() . "\0" . $right->ownership() . "\0" . $right->path()));
        $payload = ['serialization_version' => 1, 'owner_policy' => SourceInventoryReference::OWNER_POLICY, 'root_mapping' => ['APP_ROOT' => 'APP_ROOT', 'PUBLIC_ROOT' => 'PUBLIC_ROOT'], 'encoding' => SourceInventoryReference::ENCODING, 'release_identity' => $releaseIdentity, 'manifest_contract_version' => $manifestContractVersion, 'source_tree_identity' => $sourceTreeIdentity, 'fingerprint' => $inventory->fingerprint(), 'entries' => array_map(static fn (CanonicalTargetInventoryEntry $entry): array => $entry->toArray(), $entries)];
        $contents = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        $artifactIdentity = hash('sha256', $contents);
        $reference = new SourceInventoryReference($artifactIdentity, strlen($contents), $inventory->fingerprint(), $releaseIdentity, $manifestContractVersion, $sourceTreeIdentity);
        $path = $this->path($artifactIdentity);
        if (file_exists($path) || is_link($path)) {
            if (is_link($path) || !is_file($path) || @filesize($path) !== strlen($contents) || @hash_file('sha256', $path) !== $artifactIdentity || @file_get_contents($path) !== $contents) { throw new \RuntimeException('Source inventory artifact collision detected.'); }
        } else {
            $this->atomicWrite($path, $contents);
        }
        $read = $this->read($reference);
        if ($read->reference()->toArray() !== $reference->toArray()) { throw new \RuntimeException('Source inventory publication linkage failed.'); }
        return $read;
    }

    public function read(SourceInventoryReference $reference): SourceInventoryArtifact
    {
        $path = $this->path($reference->artifactIdentity());
        if (is_link($path) || !is_file($path) || !is_readable($path) || @filesize($path) !== $reference->byteSize() || @hash_file('sha256', $path) !== $reference->artifactIdentity()) { throw new \RuntimeException('Source inventory artifact is unavailable or corrupt.'); }
        $contents = @file_get_contents($path);
        if (!is_string($contents) || hash('sha256', $contents) !== $reference->artifactIdentity()) { throw new \RuntimeException('Source inventory artifact failed integrity verification.'); }
        try { StrictJsonDuplicateKeyGuard::validate($contents); $data = json_decode($contents, true, 32, JSON_THROW_ON_ERROR); } catch (\Throwable $exception) { throw new \RuntimeException('Source inventory artifact is malformed.', 0, $exception); }
        $keys = ['serialization_version', 'owner_policy', 'root_mapping', 'encoding', 'release_identity', 'manifest_contract_version', 'source_tree_identity', 'fingerprint', 'entries'];
        if (!is_array($data) || array_keys($data) !== $keys || $data['serialization_version'] !== 1 || $data['owner_policy'] !== SourceInventoryReference::OWNER_POLICY || $data['root_mapping'] !== ['APP_ROOT' => 'APP_ROOT', 'PUBLIC_ROOT' => 'PUBLIC_ROOT'] || $data['encoding'] !== SourceInventoryReference::ENCODING || !is_string($data['release_identity']) || !is_int($data['manifest_contract_version']) || ($data['source_tree_identity'] !== null && !is_string($data['source_tree_identity'])) || !is_string($data['fingerprint']) || !is_array($data['entries']) || $data['entries'] === []) { throw new \RuntimeException('Source inventory artifact context is invalid.'); }
        $entries = [];
        foreach ($data['entries'] as $entry) {
            if (!is_array($entry) || array_keys($entry) !== ['root', 'ownership', 'path', 'byte_size', 'sha256']) { throw new \RuntimeException('Source inventory entry is invalid.'); }
            $entries[] = new CanonicalTargetInventoryEntry($entry['root'], $entry['ownership'], $entry['path'], $entry['byte_size'], $entry['sha256']);
        }
        $inventory = new CanonicalTargetInventory($entries);
        if ($inventory->fingerprint() !== $data['fingerprint'] || $inventory->fingerprint() !== $reference->fingerprint() || $data['release_identity'] !== $reference->releaseIdentity() || $data['manifest_contract_version'] !== $reference->manifestContractVersion() || $data['source_tree_identity'] !== $reference->sourceTreeIdentity()) { throw new \RuntimeException('Source inventory linkage context is invalid.'); }
        return new SourceInventoryArtifact($inventory, $reference);
    }

    public function pathFor(SourceInventoryReference $reference): string { return $this->path($reference->artifactIdentity()); }

    private function path(string $identity): string { if (preg_match('/^[a-f0-9]{64}$/D', $identity) !== 1) { throw new \InvalidArgumentException('Source inventory artifact identity is invalid.'); } return $this->directory . DIRECTORY_SEPARATOR . $identity . '.json'; }

    private function atomicWrite(string $path, string $contents): void
    {
        if (!function_exists('fsync') && $this->sync === null) { throw new \RuntimeException('Source inventory durability capability is unavailable.'); }
        $temporary = dirname($path) . DIRECTORY_SEPARATOR . '.source-inventory-' . bin2hex(random_bytes(16)) . '.tmp';
        $handle = @fopen($temporary, 'xb'); if (!is_resource($handle)) { throw new \RuntimeException('Source inventory temporary file could not be created.'); }
        try {
            $offset = 0; while ($offset < strlen($contents)) { $written = @fwrite($handle, substr($contents, $offset)); if (!is_int($written) || $written < 1) { throw new \RuntimeException('Source inventory could not be written.'); } $offset += $written; }
            if (!@fflush($handle)) { throw new \RuntimeException('Source inventory could not be flushed.'); }
            $sync = $this->sync ?? static fn ($resource): bool => @fsync($resource);
            if (!$sync($handle)) { throw new \RuntimeException('Source inventory could not be durably synchronized.'); }
        } catch (\Throwable $exception) { @unlink($temporary); throw $exception; } finally { fclose($handle); }
        @chmod($temporary, 0600);
        if (!@rename($temporary, $path)) { @unlink($temporary); throw new \RuntimeException('Source inventory could not be published.'); }
        if (@filesize($path) !== strlen($contents) || @hash_file('sha256', $path) !== hash('sha256', $contents)) { throw new \RuntimeException('Source inventory failed reread verification.'); }
    }
}
