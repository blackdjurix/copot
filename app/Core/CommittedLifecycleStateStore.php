<?php

namespace Copot\Core;

final class CommittedLifecycleStateStore
{
    private string $root;
    private string $path;

    public function __construct(string $storagePath)
    {
        if (is_link($storagePath) || !is_dir($storagePath) || !is_writable($storagePath)) {
            throw new \RuntimeException('Committed lifecycle storage is unavailable.');
        }
        $storage = realpath($storagePath);
        if ($storage === false || is_link($storage)) {
            throw new \RuntimeException('Committed lifecycle storage is unavailable.');
        }
        $this->root = $storage . DIRECTORY_SEPARATOR . '.copot-lifecycle';
        if (!file_exists($this->root) && !mkdir($this->root, 0700)) {
            throw new \RuntimeException('Committed lifecycle storage could not be created.');
        }
        if (is_link($this->root) || !is_dir($this->root) || !is_writable($this->root)) {
            throw new \RuntimeException('Committed lifecycle storage is invalid.');
        }
        $resolved = realpath($this->root);
        if ($resolved === false || strcasecmp($resolved, $storage . DIRECTORY_SEPARATOR . '.copot-lifecycle') !== 0) {
            throw new \RuntimeException('Committed lifecycle storage escaped its runtime namespace.');
        }
        $this->root = $resolved;
        $this->path = $this->root . DIRECTORY_SEPARATOR . 'committed-state.json';
    }

    public function read(): ?CommittedLifecycleState
    {
        if (!file_exists($this->path)) { return null; }
        if (is_link($this->path) || !is_file($this->path) || !is_readable($this->path)) {
            throw new \RuntimeException('Committed lifecycle state is invalid.');
        }
        $size = @filesize($this->path);
        if (!is_int($size) || $size < 1 || $size > 8192) {
            throw new \RuntimeException('Committed lifecycle state is invalid.');
        }
        try {
            $data = json_decode((string) file_get_contents($this->path), true, 16, JSON_THROW_ON_ERROR);
            if (!is_array($data)) { throw new \InvalidArgumentException('Invalid state payload.'); }
            return CommittedLifecycleState::fromArray($data);
        } catch (\Throwable $exception) {
            throw new \RuntimeException('Committed lifecycle state is invalid.', 0, $exception);
        }
    }

    public function write(CommittedLifecycleState $state): void
    {
        $temporary = $this->root . DIRECTORY_SEPARATOR . '.committed-' . bin2hex(random_bytes(16)) . '.tmp';
        $handle = @fopen($temporary, 'xb');
        if (!is_resource($handle)) { throw new \RuntimeException('Committed lifecycle state could not be prepared.'); }
        try {
            $contents = json_encode($state->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL;
            $offset = 0;
            while ($offset < strlen($contents)) {
                $written = @fwrite($handle, substr($contents, $offset));
                if (!is_int($written) || $written < 1) { throw new \RuntimeException('Committed lifecycle state could not be written.'); }
                $offset += $written;
            }
            if (!@fflush($handle) || (function_exists('fsync') && !@fsync($handle))) {
                throw new \RuntimeException('Committed lifecycle state could not be finalized.');
            }
        } finally {
            fclose($handle);
        }
        @chmod($temporary, 0600);
        if (!@rename($temporary, $this->path)) {
            @unlink($temporary);
            throw new \RuntimeException('Committed lifecycle state could not be activated.');
        }
        if ($this->read()?->toArray() !== $state->toArray()) {
            throw new \RuntimeException('Committed lifecycle state could not be verified.');
        }
    }

    public function commit(InstallationState $installationState, CommittedLifecycleState $state): void
    {
        $previous = $this->read();
        $previousMarker = $installationState->readMarker();

        try {
            $this->write($state);
            $installationState->replaceMarker($state->webcoreVersion(), $state->committedAt()->format(DATE_ATOM));
        } catch (\Throwable $exception) {
            try {
                if ($previous instanceof CommittedLifecycleState) {
                    $this->write($previous);
                } else {
                    $this->remove();
                }
                if (is_array($previousMarker)) {
                    $installationState->replaceMarker($previousMarker['version'], $previousMarker['installed_at']);
                }
            } catch (\Throwable) {
                // Preserve the original failure; inspection will fail closed if recovery was incomplete.
            }
            throw $exception;
        }
    }

    /** Publish the immutable source artifact, then durably link it from committed state. */
    public function commitWithSourceInventory(
        InstallationState $installationState,
        CommittedLifecycleState $state,
        SourceInventoryArtifactStore $artifacts,
        SourceInventoryArtifact $artifact
    ): void {
        if (!function_exists('fsync')) {
            throw new \RuntimeException('Committed source inventory linkage requires durable synchronization.');
        }
        $reference = $state->sourceInventoryReference();
        if ($reference === null || $artifact->reference()->toArray() !== $reference->toArray()) {
            throw new \RuntimeException('Committed source inventory reference is not exact.');
        }
        if ($artifact->inventory()->fingerprint() !== $reference->fingerprint()) {
            throw new \RuntimeException('Committed source inventory fingerprint is not linked.');
        }
        $published = $artifacts->publish(
            $artifact->inventory(),
            $reference->releaseIdentity(),
            $reference->manifestContractVersion(),
            $reference->sourceTreeIdentity()
        );
        if ($published->reference()->toArray() !== $reference->toArray()) {
            throw new \RuntimeException('Published source inventory reference changed.');
        }
        $this->commit($installationState, $state);
        $linked = $this->read();
        if (!$linked instanceof CommittedLifecycleState
            || $linked->sourceInventoryReference()?->toArray() !== $reference->toArray()
            || $artifacts->read($reference)->inventory()->fingerprint() !== $reference->fingerprint()) {
            throw new \RuntimeException('Committed source inventory linkage verification failed.');
        }
    }

    public function remove(): void
    {
        if (!file_exists($this->path)) {
            return;
        }
        if (is_link($this->path) || !@unlink($this->path)) {
            throw new \RuntimeException('Committed lifecycle state could not be removed.');
        }
        if ($this->read() !== null) {
            throw new \RuntimeException('Committed lifecycle state remained after removal.');
        }
    }

}
