<?php

namespace Copot\Core;

require_once __DIR__ . '/MediaUploadExceptions.php';

final class MediaFilesystemStorage
{
    private const KEY = '/^[a-f0-9]{32}\.(jpg|png|webp|gif|ico|pdf)$/';
    public function __construct(private string $root, private int $maxBytes = MediaFileInspector::MAX_IMAGE_BYTES, private $openSource = null, private $copyOperation = null, private $renameOperation = null, private $deleteOperation = null) {}
    public function stage(string $sourcePath): MediaStagedFile
    {
        if (!is_file($sourcePath) || is_link($sourcePath) || !is_readable($sourcePath)) throw new MediaStorageException('Media storage could not stage the file.');
        $this->ensureDirectories(); $token = '.upload-' . bin2hex(random_bytes(16)); $path = $this->root . DIRECTORY_SEPARATOR . '.tmp' . DIRECTORY_SEPARATOR . $token;
        $in = $this->openSource ? ($this->openSource)($sourcePath) : @fopen($sourcePath, 'rb'); $out = @fopen($path, 'xb');
        if (!$in || !$out) { if ($in) fclose($in); if ($out) fclose($out); @unlink($path); throw new MediaStorageException('Media storage could not stage the file.'); }
        $copied = 0; $ok = false;
        try { $ok = $this->copyOperation ? (bool) ($this->copyOperation)($in, $out, $this->maxBytes) : $this->copyStream($in, $out, $copied); if ($ok) $ok = fflush($out); } catch (Throwable) { $ok = false; }
        fclose($in); fclose($out); if (!$ok) { @unlink($path); throw new MediaStorageException('Media storage could not stage the file.'); }
        return new MediaStagedFile($path, $token);
    }
    public function discard(MediaStagedFile $staged): void { if (is_file($staged->path()) && !is_link($staged->path())) @unlink($staged->path()); }
    public function activate(MediaStagedFile $staged, string $storageKey): void
    {
        if (!preg_match(self::KEY, $storageKey) || !$this->inside($staged->path(), $this->root . DIRECTORY_SEPARATOR . '.tmp')) throw new MediaStorageException('Media storage could not activate the file.');
        $this->ensureDirectories(); $dir = $this->root . DIRECTORY_SEPARATOR . 'originals' . DIRECTORY_SEPARATOR . substr($storageKey, 0, 2) . DIRECTORY_SEPARATOR . substr($storageKey, 2, 2); $this->mkdirSafe($dir);
        $destination = $dir . DIRECTORY_SEPARATOR . $storageKey; if (file_exists($destination) || is_link($destination) || !($this->renameOperation ? ($this->renameOperation)($staged->path(), $destination) : @rename($staged->path(), $destination))) throw new MediaStorageException('Media storage could not activate the file.'); @chmod($destination, 0644);
    }
    public function resolve(string $storageKey): ?string
    {
        if (!preg_match(self::KEY, $storageKey)) return null; $this->ensureDirectories(); $path = $this->root . DIRECTORY_SEPARATOR . 'originals' . DIRECTORY_SEPARATOR . substr($storageKey, 0, 2) . DIRECTORY_SEPARATOR . substr($storageKey, 2, 2) . DIRECTORY_SEPARATOR . $storageKey;
        return is_file($path) && !is_link($path) && $this->inside($path, $this->root . DIRECTORY_SEPARATOR . 'originals') ? $path : null;
    }
    public function delete(string $storageKey): void { $path = $this->resolve($storageKey); if ($path && $this->deleteOperation) { ($this->deleteOperation)($path); return; } if ($path) @unlink($path); }
    public function quarantine(string $storageKey, string $token): array
    {
        $path = $this->resolve($storageKey);
        if (!$path) return ['purge' => static function (): void {}];
        $root = $this->root . DIRECTORY_SEPARATOR . '.quarantine' . DIRECTORY_SEPARATOR . preg_replace('/[^a-z0-9-]/i', '', $token);
        $this->mkdirSafe($root);
        $destination = $root . DIRECTORY_SEPARATOR . basename($storageKey);
        if (file_exists($destination) || !@rename($path, $destination)) throw new MediaStorageException('Media cleanup could not be staged.');
        return ['purge' => static function () use ($destination): void { if (is_file($destination)) @unlink($destination); }, 'restore' => function () use ($path, $destination): void { if (is_file($destination)) @rename($destination, $path); }];
    }
    private function ensureDirectories(): void { $this->mkdirSafe($this->root); $this->mkdirSafe($this->root . DIRECTORY_SEPARATOR . '.tmp'); $this->mkdirSafe($this->root . DIRECTORY_SEPARATOR . 'originals'); }
    private function mkdirSafe(string $path): void { if (is_link($path) || (!is_dir($path) && !@mkdir($path, 0755, true) && !is_dir($path)) || is_link($path)) throw new MediaStorageException('Media storage is unavailable.'); }
    private function inside(string $path, string $base): bool { $realBase = realpath($base); $realPath = realpath($path); return $realBase !== false && $realPath !== false && ($realPath === $realBase || str_starts_with($realPath, $realBase . DIRECTORY_SEPARATOR)); }
    private function copyStream($in, $out, int &$copied): bool { while (!feof($in)) { $chunk = fread($in, min(1048576, $this->maxBytes + 1 - $copied)); if ($chunk === false || $chunk === '') break; $copied += strlen($chunk); if ($copied > $this->maxBytes || fwrite($out, $chunk) !== strlen($chunk)) return false; } return true; }
}
