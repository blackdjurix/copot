<?php

namespace Copot\Core;

final class NetZeroRetirementEvidenceStore
{
    private string $root;

    public function __construct(string $root)
    {
        $this->root = rtrim($root, '/\\') . DIRECTORY_SEPARATOR . 'retired-net-zero';
        if (!is_dir($this->root) && !mkdir($this->root, 0700, true) && !is_dir($this->root)) throw new \RuntimeException('Net-zero evidence directory is unavailable.');
        if (is_link($this->root) || !is_writable($this->root)) throw new \RuntimeException('Net-zero evidence directory is unsafe.');
    }

    public function publish(LifecycleOperationRecord $operation, NetZeroRetirementVerification $verification, string $reason, string $timestamp): void
    {
        $body = $verification->evidence() + [
            'classification' => $operation->classification(),
            'target_webcore_version' => $operation->targetWebcoreVersion(),
            'release_identity' => $operation->releaseIdentity(),
            'retirement_reason' => $reason,
            'retired_at' => $timestamp,
            'disposition' => LifecycleOperationRecord::RETIRED_NET_ZERO,
        ];
        $bytes = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        $path = $this->path($operation->operationId());
        if (is_file($path)) {
            if (hash('sha256', (string) file_get_contents($path)) !== hash('sha256', $bytes)) throw new \RuntimeException('Conflicting net-zero evidence already exists.');
            return;
        }
        $tmp = $this->root . DIRECTORY_SEPARATOR . '.retired-' . bin2hex(random_bytes(8)) . '.tmp';
        if (@file_put_contents($tmp, $bytes, LOCK_EX) !== strlen($bytes) || !@rename($tmp, $path)) { @unlink($tmp); throw new \RuntimeException('Net-zero evidence could not be durably published.'); }
        $read = @file_get_contents($path);
        if ($read !== $bytes) throw new \RuntimeException('Net-zero evidence read-back failed.');
    }

    public function path(string $operationId): string { return $this->root . DIRECTORY_SEPARATOR . $operationId . '.json'; }
}
