<?php

namespace Copot\Core;

/** Immutable durable evidence for a PRE_CAPTURE_ABANDONED disposition. */
final class PreCaptureAbandonmentEvidenceStore
{
    public function __construct(private string $storagePath) {}

    private function path(string $operationId): string
    {
        if ($operationId === '' || preg_match('/[^A-Za-z0-9._:-]/', $operationId) === 1) throw new \RuntimeException('Abandonment operation identity is invalid.');
        return rtrim($this->storagePath, '/\\') . DIRECTORY_SEPARATOR . '.copot-lifecycle' . DIRECTORY_SEPARATOR . 'pre-capture-abandonment-' . hash('sha256', $operationId) . '.json';
    }

    public function publish(array $evidence): array
    {
        $path = $this->path((string) ($evidence['operation']['operation_id'] ?? ''));
        $encoded = json_encode($evidence, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
        if (is_link($path) || file_exists($path)) {
            if (!is_file($path) || (string) file_get_contents($path) !== $encoded) throw new \RuntimeException('Abandonment evidence collision detected.');
            return $evidence;
        }
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) throw new \RuntimeException('Abandonment evidence storage is unavailable.');
        $tmp = $path . '.tmp-' . bin2hex(random_bytes(8));
        if (file_put_contents($tmp, $encoded, LOCK_EX) === false || !rename($tmp, $path)) { @unlink($tmp); throw new \RuntimeException('Abandonment evidence could not be published.'); }
        @chmod($path, 0600);
        if ((string) file_get_contents($path) !== $encoded) throw new \RuntimeException('Abandonment evidence read-back failed.');
        return $evidence;
    }

    public function read(string $operationId): ?array
    {
        $path = $this->path($operationId);
        if (!file_exists($path)) return null;
        if (is_link($path) || !is_file($path)) throw new \RuntimeException('Abandonment evidence is invalid.');
        $data = json_decode((string) file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($data) || ($data['operation']['operation_id'] ?? null) !== $operationId) throw new \RuntimeException('Abandonment evidence is malformed.');
        return $data;
    }
}
