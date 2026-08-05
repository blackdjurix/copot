<?php

namespace Copot\Core;

final class ModulePackageLibrary
{
    private string $root;

    public function __construct(string $storagePath)
    {
        $storage = realpath($storagePath);
        if ($storage === false || is_link($storagePath) || !is_dir($storage) || !is_writable($storage)) throw new \RuntimeException('Module package library storage is unavailable.');
        $root = $storage . DIRECTORY_SEPARATOR . '.copot-lifecycle' . DIRECTORY_SEPARATOR . 'module-packages';
        if (!is_dir($root) && !mkdir($root, 0700, true)) throw new \RuntimeException('Module package library could not be created.');
        $resolved = realpath($root);
        if ($resolved === false || is_link($root) || strcasecmp($resolved, $root) !== 0 || !is_writable($root)) throw new \RuntimeException('Module package library is invalid.');
        $this->root = $resolved;
    }

    public function register(ModulePackageInspection $inspection): array
    {
        if (!$inspection->accepted()) throw new \InvalidArgumentException('Only accepted Module packages may be registered.');
        $contract = $inspection->contract();
        $key = hash('sha256', $contract->packageIdentity()->value() . "\n" . $contract->moduleIdentity()->value() . "\n" . $contract->releaseIdentity());
        $archive = $inspection->livePayload()->archivePath();
        if (!is_file($archive) || is_link($archive) || !is_readable($archive)) throw new \RuntimeException('Accepted package archive is unavailable.');
        $zip = $this->root . DIRECTORY_SEPARATOR . $key . '.zip';
        $json = $this->root . DIRECTORY_SEPARATOR . $key . '.json';
        $this->assertInside($zip); $this->assertInside($json);
        if (!is_file($zip)) $this->copy($archive, $zip);
        $record = ['library_version' => 1, 'candidate_key' => $key, 'package_identity' => $contract->packageIdentity()->value(), 'technical_module_identity' => $contract->moduleIdentity()->value(), 'title' => $contract->title(), 'package_version' => $contract->packageVersion(), 'release_identity' => $contract->releaseIdentity(), 'archive_sha256' => hash_file('sha256', $zip), 'archive_size' => filesize($zip), 'contract' => $contract->toArray(), 'registered_at' => gmdate(DATE_ATOM)];
        $temporary = $json . '.' . bin2hex(random_bytes(8)) . '.tmp';
        file_put_contents($temporary, json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL, LOCK_EX); chmod($temporary, 0600);
        if (!rename($temporary, $json)) { @unlink($temporary); throw new \RuntimeException('Module package library record could not be finalized.'); }
        return $record;
    }

    public function all(): array
    {
        $records = [];
        foreach (glob($this->root . DIRECTORY_SEPARATOR . '*.json') ?: [] as $path) {
            if (is_link($path) || !is_file($path) || !$this->inside($path)) continue;
            try { $record = json_decode((string) file_get_contents($path), true, 16, JSON_THROW_ON_ERROR); if (is_array($record)) $records[] = $record; } catch (\Throwable) { }
        }
        usort($records, static fn (array $a, array $b): int => strcmp((string) ($a['technical_module_identity'] ?? ''), (string) ($b['technical_module_identity'] ?? '')) ?: -PackageVersion::compare((string) ($a['package_version'] ?? '0.0.0'), (string) ($b['package_version'] ?? '0.0.0')));
        return $records;
    }

    public function find(string $key): ?array { foreach ($this->all() as $record) if (($record['candidate_key'] ?? null) === $key) return $record; return null; }
    public function archive(array $record): string
    {
        $key = (string) ($record['candidate_key'] ?? '');
        if (preg_match('/^[a-f0-9]{64}$/', $key) !== 1) throw new \RuntimeException('Module package candidate identity is invalid.');
        $path = $this->root . DIRECTORY_SEPARATOR . $key . '.zip'; $this->assertInside($path);
        if (!is_file($path) || is_link($path) || hash_file('sha256', $path) !== ($record['archive_sha256'] ?? null)) throw new \RuntimeException('Stored Module package candidate integrity is invalid.');
        return $path;
    }

    private function copy(string $source, string $destination): void
    {
        $temporary = $destination . '.' . bin2hex(random_bytes(8)) . '.tmp'; $in = @fopen($source, 'rb'); $out = @fopen($temporary, 'xb');
        if (!is_resource($in) || !is_resource($out)) { if (is_resource($in)) fclose($in); if (is_resource($out)) fclose($out); throw new \RuntimeException('Module package candidate could not be stored.'); }
        try { if (stream_copy_to_stream($in, $out) === false || !fflush($out)) throw new \RuntimeException('Module package candidate could not be stored.'); } finally { fclose($in); fclose($out); }
        chmod($temporary, 0600); if (!rename($temporary, $destination)) { @unlink($temporary); throw new \RuntimeException('Module package candidate could not be finalized.'); }
    }

    private function assertInside(string $path): void { if (!$this->inside($path)) throw new \RuntimeException('Module package library path escaped its storage boundary.'); }
    private function inside(string $path): bool { $candidate = realpath($path) ?: dirname($path) . DIRECTORY_SEPARATOR . basename($path); return strcasecmp($candidate, $this->root) === 0 || str_starts_with(strtolower($candidate), strtolower($this->root . DIRECTORY_SEPARATOR)); }
}
