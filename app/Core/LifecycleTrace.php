<?php

namespace Copot\Core;

final class LifecycleTrace
{
    public function __construct(private ?string $path, private string $cycleId = 'temporary', private $writer = null)
    {
        if ($this->path !== null) $this->path = trim($this->path);
        if ($this->cycleId === '' || trim($this->cycleId) !== $this->cycleId || preg_match('/[\x00-\x1F\x7F]/', $this->cycleId) === 1) $this->cycleId = 'temporary';
    }

    public function beginRun(): string
    {
        try { $suffix = bin2hex(random_bytes(12)); } catch (\Throwable) { $suffix = str_replace('.', '', uniqid('', true)); }
        $runId = 'trace-' . $suffix;
        $this->record($runId, null, 'operation', 'apply', 'START');
        return $runId;
    }

    public function record(string $runId, ?string $operationIdentity, string $stage, string $action, string $status, ?string $summary = null, array $extra = []): void
    {
        $event = ['run_id' => $runId, 'cycle_id' => $this->cycleId, 'timestamp' => gmdate(DATE_ATOM), 'operation_identity' => $operationIdentity, 'stage' => $stage, 'action' => $action, 'status' => $status];
        if ($summary !== null && $summary !== '') $event['summary'] = self::summary($summary);
        foreach ($extra as $key => $value) if (is_string($key) && preg_match('/^[a-z][a-z0-9_]{0,40}$/D', $key) === 1 && is_scalar($value)) $event[$key] = is_string($value) ? self::summary($value) : $value;
        try {
            $line = json_encode($event, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
            if (is_callable($this->writer)) { ($this->writer)($line); return; }
            $this->append($line);
        } catch (\Throwable) {}
    }

    public static function summary(string $value): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
        $value = preg_replace('~[A-Za-z]:[\\\\/][^\s]*~', '<path>', $value) ?? $value;
        $value = preg_replace('#/(?:[^\s]+)#', '<path>', $value) ?? $value;
        $value = preg_replace('/(?i)(password|secret|token|credential|authorization|dump)\s*[:=]\s*[^\s,;]+/', '$1=<redacted>', $value) ?? $value;
        return substr($value, 0, 240);
    }

    private function append(string $line): void
    {
        if ($this->path === null || $this->path === '' || str_contains($this->path, "\0")) return;
        $parent = dirname($this->path);
        if (!is_dir($parent) || is_link($parent) || is_link($this->path)) return;
        $handle = @fopen($this->path, 'ab');
        if (!is_resource($handle)) return;
        try { @chmod($this->path, 0600); if (!@flock($handle, LOCK_EX)) return; @fwrite($handle, $line); @fflush($handle); @flock($handle, LOCK_UN); } finally { fclose($handle); }
    }
}

