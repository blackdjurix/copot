<?php

namespace Copot\Core;

final class LegacyBootstrapSourceArtifactStore
{
    private string $root;
    public function __construct(string $storagePath)
    {
        if (is_link($storagePath) || !is_dir($storagePath) || !is_writable($storagePath)) throw new \RuntimeException('Legacy bootstrap storage is unavailable.');
        $base = realpath($storagePath);
        if ($base === false) throw new \RuntimeException('Legacy bootstrap storage is unavailable.');
        $this->root = $base . DIRECTORY_SEPARATOR . '.copot-lifecycle' . DIRECTORY_SEPARATOR . 'legacy-bootstrap-source';
        if (!is_dir($this->root) && !mkdir($this->root, 0700, true) && !is_dir($this->root)) throw new \RuntimeException('Legacy bootstrap storage could not be created.');
        if (is_link($this->root) || !is_dir($this->root) || !is_writable($this->root)) throw new \RuntimeException('Legacy bootstrap storage is invalid.');
    }

    /** @param array<string,mixed> $record */
    public function publish(array $record): string
    {
        $required = ['serialization_version','operation_type','operation_identity','installation_identity','plan_identity','package_identity','recovery_identity','captured_at','provenance','legacy_package_evidence','legacy_classification','source_inventory','collisions','status'];
        if (array_keys($record) !== $required || $record['operation_type'] !== LegacyBootstrapPreparation::OPERATION_TYPE) throw new \InvalidArgumentException('Legacy bootstrap artifact shape is invalid.');
        $path = $this->root . DIRECTORY_SEPARATOR . hash('sha256', $record['operation_identity']) . '.json';
        if (file_exists($path)) throw new \RuntimeException('Legacy bootstrap artifact is immutable and already exists.');
        $contents = json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        $temporary = $path . '.' . bin2hex(random_bytes(8)) . '.tmp';
        $handle = fopen($temporary, 'xb');
        if (!is_resource($handle)) throw new \RuntimeException('Legacy bootstrap artifact could not be prepared.');
        try { if (fwrite($handle, $contents) !== strlen($contents) || !fflush($handle)) throw new \RuntimeException('Legacy bootstrap artifact could not be finalized.'); }
        finally { fclose($handle); }
        chmod($temporary, 0600);
        if (!rename($temporary, $path)) { @unlink($temporary); throw new \RuntimeException('Legacy bootstrap artifact could not be activated.'); }
        return $path;
    }
}
