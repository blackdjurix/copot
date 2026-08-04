<?php

namespace Copot\Core;

final class LifecycleOperationStore
{
    private string $root;
    private string $path;

    public function __construct(string $storagePath)
    {
        if (is_link($storagePath) || !is_dir($storagePath)) { throw new \RuntimeException('Lifecycle storage is unavailable.'); }
        $storage = realpath($storagePath);
        if ($storage === false || is_link($storage)) { throw new \RuntimeException('Lifecycle storage is unavailable.'); }
        $this->root = $storage . DIRECTORY_SEPARATOR . '.copot-lifecycle';
        if (!file_exists($this->root) && !mkdir($this->root, 0700)) { throw new \RuntimeException('Lifecycle storage could not be created.'); }
        if (is_link($this->root) || !is_dir($this->root) || !is_writable($this->root)) { throw new \RuntimeException('Lifecycle storage is invalid.'); }
        $resolved = realpath($this->root);
        if ($resolved === false || !str_starts_with(strtolower($resolved), strtolower($storage . DIRECTORY_SEPARATOR))) { throw new \RuntimeException('Lifecycle storage escaped its runtime namespace.'); }
        $this->root = $resolved;
        $this->path = $this->root . DIRECTORY_SEPARATOR . 'active-operation.json';
    }

    public function read(): ?LifecycleOperationRecord
    {
        if (!file_exists($this->path)) { return null; }
        if (is_link($this->path) || !is_file($this->path) || !is_readable($this->path)) { throw new \RuntimeException('Lifecycle operation record is invalid.'); }
        $size = filesize($this->path);
        if (!is_int($size) || $size < 1 || $size > 16384) { throw new \RuntimeException('Lifecycle operation record is invalid.'); }
        $data = json_decode((string) file_get_contents($this->path), true);
        if (!is_array($data)) { throw new \RuntimeException('Lifecycle operation record is invalid.'); }
        try { return LifecycleOperationRecord::fromArray($data); } catch (\Throwable $e) { throw new \RuntimeException('Lifecycle operation record is invalid.', 0, $e); }
    }

    public function create(LifecycleOperationRecord $record): void
    {
        if ($this->read() !== null || file_exists($this->path)) { throw new \RuntimeException('A lifecycle operation is already active.'); }
        $this->activate($record, false);
    }

    public function save(LifecycleOperationRecord $record): void
    {
        $current = $this->read();
        if ($current === null || $current->operationId() !== $record->operationId()) { throw new \RuntimeException('Lifecycle operation identity changed.'); }
        $this->activate($record, true);
    }

    public function clear(LifecycleOperationRecord $record): void
    {
        $current = $this->read();
        if ($current === null || $current->operationId() !== $record->operationId() || !$record->isTerminal()) { throw new \RuntimeException('Lifecycle operation cannot be cleared.'); }
        if (is_link($this->path) || !@unlink($this->path)) { throw new \RuntimeException('Lifecycle operation record could not be cleared.'); }
    }

    public function classify(bool $currentExecutorOwnsMutex): string
    {
        $record = $this->read();
        if ($record === null) { return 'inactive'; }
        return $currentExecutorOwnsMutex ? 'active' : 'interrupted';
    }

    private function activate(LifecycleOperationRecord $record, bool $replace): void
    {
        $temporary = $this->root . DIRECTORY_SEPARATOR . '.operation-' . bin2hex(random_bytes(16)) . '.tmp';
        $handle = @fopen($temporary, 'xb');
        if (!is_resource($handle)) { throw new \RuntimeException('Lifecycle operation record could not be prepared.'); }
        try {
            $contents = json_encode($record->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL;
            $offset = 0;
            while ($offset < strlen($contents)) { $written = fwrite($handle, substr($contents, $offset)); if (!is_int($written) || $written < 1) { throw new \RuntimeException('Lifecycle operation record could not be written.'); } $offset += $written; }
            if (!fflush($handle) || (function_exists('fsync') && !fsync($handle))) { throw new \RuntimeException('Lifecycle operation record could not be finalized.'); }
        } finally { fclose($handle); }
        @chmod($temporary, 0600);
        if (!$replace && file_exists($this->path)) { @unlink($temporary); throw new \RuntimeException('A lifecycle operation is already active.'); }
        if (!@rename($temporary, $this->path)) { @unlink($temporary); throw new \RuntimeException('Lifecycle operation record could not be activated.'); }
    }
}
