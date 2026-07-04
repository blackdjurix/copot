<?php

namespace Copot\Core;

use Throwable;

final class Diagnostics
{
    private const LOG_FILE = 'copot.log';
    private const EVENT_PATTERN = '/^[a-z][a-z0-9]*(?:\.[a-z][a-z0-9]*)+$/';
    private const IDENTIFIER_PATTERN = '/^[a-z0-9][a-z0-9._-]*$/i';
    private const SENSITIVE_VALUE_PATTERN = '/(?:password|passwd|secret|token|authorization|cookie|session(?:_id)?|csrf|dsn|db_(?:host|port|database|username|password))\s*[:=]/i';
    private const CONNECTION_STRING_PATTERN = '/\b(?:mysql|pgsql|postgres|sqlsrv):[^\s]+/i';

    private string $projectRoot;
    private string $storageDirectory;
    private string $logDirectory;
    private string $logPath;

    public function __construct(string $projectRoot)
    {
        $this->projectRoot = rtrim($projectRoot, '/\\');
        $this->storageDirectory = $this->projectRoot . DIRECTORY_SEPARATOR . 'storage';
        $this->logDirectory = $this->storageDirectory . DIRECTORY_SEPARATOR . 'logs';
        $this->logPath = $this->logDirectory . DIRECTORY_SEPARATOR . self::LOG_FILE;
    }

    public function report(string $event, Throwable $exception, array $context = []): ?string
    {
        try {
            if (!$this->validEvent($event)) {
                return null;
            }

            $reference = 'ERR-' . strtoupper(bin2hex(random_bytes(12)));
            $record = $this->baseRecord('error', $event);
            $record['reference'] = $reference;
            $record['exception'] = $exception::class;
            $record['summary'] = 'Unexpected application failure.';

            $source = $this->projectRelativeSource($exception);

            if ($source !== null) {
                $record['source'] = $source;
            }

            $safeContext = $this->sanitizeContext($context);

            if ($safeContext !== []) {
                $record['context'] = $safeContext;
            }

            return $this->append($record) ? $reference : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function warning(string $event, string $summary, array $context = []): bool
    {
        try {
            if (!$this->validEvent($event)) {
                return false;
            }

            $summary = $this->sanitizeText($summary, 240);

            if ($summary === '') {
                return false;
            }

            $record = $this->baseRecord('warning', $event);
            $record['summary'] = $summary;
            $safeContext = $this->sanitizeContext($context);

            if ($safeContext !== []) {
                $record['context'] = $safeContext;
            }

            return $this->append($record);
        } catch (Throwable) {
            return false;
        }
    }

    private function baseRecord(string $level, string $event): array
    {
        return [
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'level' => $level,
            'event' => $event,
        ];
    }

    private function validEvent(string $event): bool
    {
        return strlen($event) <= 100 && preg_match(self::EVENT_PATTERN, $event) === 1;
    }

    private function sanitizeContext(array $context): array
    {
        $safe = [];

        foreach (['component', 'operation', 'method', 'path', 'status', 'slot'] as $key) {
            if (!array_key_exists($key, $context)) {
                continue;
            }

            $value = $context[$key];

            if ($key === 'status') {
                if (is_int($value) && $value >= 100 && $value <= 599) {
                    $safe[$key] = $value;
                }

                continue;
            }

            if (!is_string($value)) {
                continue;
            }

            if ($key === 'method') {
                $value = strtoupper(trim($value));

                if (preg_match('/^[A-Z]{3,10}$/', $value) === 1) {
                    $safe[$key] = $value;
                }

                continue;
            }

            if ($key === 'path') {
                $path = parse_url($value, PHP_URL_PATH);

                if (!is_string($path) || $path === '') {
                    continue;
                }

                $path = $this->sanitizeText($path, 512, false);

                if ($path !== '') {
                    $safe[$key] = $path;
                }

                continue;
            }

            $value = trim($value);

            if (
                $value !== ''
                && strlen($value) <= 80
                && preg_match(self::IDENTIFIER_PATTERN, $value) === 1
            ) {
                $safe[$key] = $value;
            }
        }

        return $safe;
    }

    private function sanitizeText(string $value, int $maximumLength, bool $redactAbsolutePaths = true): string
    {
        $value = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $value) ?? '';
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');

        if (
            preg_match(self::SENSITIVE_VALUE_PATTERN, $value) === 1
            || preg_match(self::CONNECTION_STRING_PATTERN, $value) === 1
            || ($redactAbsolutePaths && preg_match('/(?:^|\s)[A-Za-z]:[\\\\\/]/', $value) === 1)
            || ($redactAbsolutePaths && preg_match('#(?:^|\s)/(?:[^/\s]+/)+[^\s]*#', $value) === 1)
        ) {
            return '[redacted]';
        }

        if (strlen($value) > $maximumLength) {
            $value = substr($value, 0, $maximumLength);
        }

        return $value;
    }

    private function projectRelativeSource(Throwable $exception): ?string
    {
        $root = realpath($this->projectRoot);
        $file = realpath($exception->getFile());

        if ($root === false || $file === false) {
            return null;
        }

        $root = rtrim(str_replace('\\', '/', $root), '/') . '/';
        $file = str_replace('\\', '/', $file);
        $inside = DIRECTORY_SEPARATOR === '\\'
            ? strncasecmp($file, $root, strlen($root)) === 0
            : str_starts_with($file, $root);

        if (!$inside) {
            return null;
        }

        $relative = substr($file, strlen($root));

        if ($relative === '' || str_contains($relative, '..')) {
            return null;
        }

        return $relative . ':' . max(1, $exception->getLine());
    }

    private function append(array $record): bool
    {
        if (!$this->safeLogDestination()) {
            return false;
        }

        $encoded = json_encode(
            $record,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );

        if (!is_string($encoded)) {
            return false;
        }

        $line = $encoded . "\n";
        $created = !file_exists($this->logPath);
        $handle = null;
        $locked = false;

        try {
            $handle = @fopen($this->logPath, 'ab');

            if (!is_resource($handle) || !@flock($handle, LOCK_EX)) {
                return false;
            }

            $locked = true;
            $length = strlen($line);
            $offset = 0;

            while ($offset < $length) {
                $written = @fwrite($handle, substr($line, $offset));

                if (!is_int($written) || $written < 1) {
                    return false;
                }

                $offset += $written;
            }

            if (!@fflush($handle)) {
                return false;
            }

            if ($created) {
                @chmod($this->logPath, 0640);
            }

            return true;
        } catch (Throwable) {
            return false;
        } finally {
            if (is_resource($handle)) {
                if ($locked) {
                    try {
                        @flock($handle, LOCK_UN);
                    } catch (Throwable) {
                    }
                }

                try {
                    @fclose($handle);
                } catch (Throwable) {
                }
            }
        }
    }

    private function safeLogDestination(): bool
    {
        if (
            $this->projectRoot === ''
            || !is_dir($this->storageDirectory)
            || is_link($this->storageDirectory)
            || !is_dir($this->logDirectory)
            || is_link($this->logDirectory)
            || !is_writable($this->logDirectory)
        ) {
            return false;
        }

        $root = realpath($this->projectRoot);
        $directory = realpath($this->logDirectory);

        if ($root === false || $directory === false) {
            return false;
        }

        $root = rtrim(str_replace('\\', '/', $root), '/') . '/';
        $directory = rtrim(str_replace('\\', '/', $directory), '/') . '/';
        $inside = DIRECTORY_SEPARATOR === '\\'
            ? strncasecmp($directory, $root, strlen($root)) === 0
            : str_starts_with($directory, $root);

        if (!$inside) {
            return false;
        }

        if (!file_exists($this->logPath)) {
            return true;
        }

        return is_file($this->logPath)
            && !is_link($this->logPath)
            && is_writable($this->logPath);
    }
}
