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
        $contents = json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL;

        if (file_exists($path) || is_link($path)) {
            if (is_link($path) || !is_file($path) || !is_readable($path)) {
                throw new \RuntimeException('Legacy bootstrap artifact is invalid and cannot be reused.');
            }
            $existingContents = @file_get_contents($path);
            if (!is_string($existingContents) || !function_exists('hash_file') || @hash_file('sha256', $path) === false) {
                throw new \RuntimeException('Legacy bootstrap artifact integrity could not be verified.');
            }
            try {
                $existing = json_decode($existingContents, true, 32, JSON_THROW_ON_ERROR);
            } catch (\Throwable $exception) {
                throw new \RuntimeException('Legacy bootstrap artifact is malformed.', 0, $exception);
            }
            if (!is_array($existing)) {
                throw new \RuntimeException('Legacy bootstrap artifact is malformed.');
            }
            $existingComparable = $existing;
            $recordComparable = $record;
            unset($existingComparable['captured_at'], $recordComparable['captured_at']);
            if ($existingComparable !== $recordComparable) {
                throw new \RuntimeException('Legacy bootstrap artifact identity collision detected.');
            }
            return $path;
        }

        $temporary = $path . '.' . bin2hex(random_bytes(8)) . '.tmp';
        $handle = fopen($temporary, 'xb');
        if (!is_resource($handle)) throw new \RuntimeException('Legacy bootstrap artifact could not be prepared.');
        try {
            $offset = 0;
            while ($offset < strlen($contents)) {
                $written = fwrite($handle, substr($contents, $offset));
                if (!is_int($written) || $written < 1) throw new \RuntimeException('Legacy bootstrap artifact could not be written.');
                $offset += $written;
            }
            if (!fflush($handle) || !function_exists('fsync') || !fsync($handle)) throw new \RuntimeException('Legacy bootstrap artifact could not be durably finalized.');
        }
        finally { fclose($handle); }
        chmod($temporary, 0600);
        if (!rename($temporary, $path)) { @unlink($temporary); throw new \RuntimeException('Legacy bootstrap artifact could not be activated.'); }
        if (@filesize($path) !== strlen($contents) || @hash_file('sha256', $path) !== hash('sha256', $contents) || @file_get_contents($path) !== $contents) {
            throw new \RuntimeException('Legacy bootstrap artifact failed read-back verification.');
        }
        return $path;
    }
}
