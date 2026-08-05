<?php

namespace Copot\Core;

final class ModuleLifecycleStateStore
{
    private string $root;

    public function __construct(string $storagePath)
    {
        if (is_link($storagePath) || !is_dir($storagePath) || !is_writable($storagePath)) throw new \RuntimeException('Module lifecycle storage is unavailable.');
        $storage = realpath($storagePath);
        if ($storage === false || is_link($storage)) throw new \RuntimeException('Module lifecycle storage is unavailable.');
        $root = $storage . DIRECTORY_SEPARATOR . '.copot-lifecycle' . DIRECTORY_SEPARATOR . 'modules';
        if (!is_dir($root) && !mkdir($root, 0700, true)) throw new \RuntimeException('Module lifecycle storage could not be created.');
        if (is_link($root) || !is_dir($root) || !is_writable($root)) throw new \RuntimeException('Module lifecycle storage is invalid.');
        $resolved = realpath($root);
        if ($resolved === false || strcasecmp($resolved, $root) !== 0) throw new \RuntimeException('Module lifecycle storage escaped its runtime namespace.');
        $this->root = $resolved;
    }

    public function read(ModuleIdentity|string $module): ?ModuleLifecycleState
    {
        $identity = $module instanceof ModuleIdentity ? $module : new ModuleIdentity($module);
        $path = $this->path($identity);
        if (!file_exists($path)) return null;
        if (is_link($path) || !is_file($path) || !is_readable($path)) throw new \RuntimeException('Module lifecycle state is invalid.');
        $size = @filesize($path);
        if (!is_int($size) || $size < 1 || $size > 8192) throw new \RuntimeException('Module lifecycle state is invalid.');
        try {
            $data = json_decode((string) file_get_contents($path), true, 16, JSON_THROW_ON_ERROR);
            if (!is_array($data)) throw new \InvalidArgumentException('Invalid state payload.');
            return ModuleLifecycleState::fromArray($data);
        } catch (\Throwable $exception) {
            throw new \RuntimeException('Module lifecycle state is invalid.', 0, $exception);
        }
    }

    public function write(ModuleLifecycleState $state): void
    {
        $path = $this->path($state->moduleIdentity());
        $temporary = $this->root . DIRECTORY_SEPARATOR . '.' . $state->moduleIdentity()->value() . '-' . bin2hex(random_bytes(12)) . '.tmp';
        $handle = @fopen($temporary, 'xb');
        if (!is_resource($handle)) throw new \RuntimeException('Module lifecycle state could not be prepared.');
        try {
            $contents = json_encode($state->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL;
            if (@fwrite($handle, $contents) !== strlen($contents) || !@fflush($handle) || (function_exists('fsync') && !@fsync($handle))) throw new \RuntimeException('Module lifecycle state could not be finalized.');
        } finally { fclose($handle); }
        @chmod($temporary, 0600);
        if (!@rename($temporary, $path)) { @unlink($temporary); throw new \RuntimeException('Module lifecycle state could not be activated.'); }
        if ($this->read($state->moduleIdentity())?->toArray() !== $state->toArray()) throw new \RuntimeException('Module lifecycle state could not be verified.');
    }

    /** @return list<ModuleLifecycleState> */
    public function all(): array
    {
        $states = [];
        foreach (glob($this->root . DIRECTORY_SEPARATOR . '*.json') ?: [] as $path) {
            $name = basename($path, '.json');
            $states[] = $this->read(new ModuleIdentity($name));
        }
        usort($states, static fn (ModuleLifecycleState $left, ModuleLifecycleState $right): int => strcmp($left->moduleIdentity()->value(), $right->moduleIdentity()->value()));
        return array_values(array_filter($states, static fn (?ModuleLifecycleState $state): bool => $state instanceof ModuleLifecycleState));
    }

    private function path(ModuleIdentity $module): string { return $this->root . DIRECTORY_SEPARATOR . $module->value() . '.json'; }
}
