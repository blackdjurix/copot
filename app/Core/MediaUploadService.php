<?php

namespace Copot\Core;

require_once __DIR__ . '/MediaUploadExceptions.php';

use Copot\Core\Database;

final class MediaUploadService
{
    public function __construct(private Database $database, private MediaLifecycleService $lifecycle, private MediaFileInspector $inspector, private MediaFilesystemStorage $storage, private $diagnostics = null, private $keyGenerator = null) {}
    public function upload(MediaUploadSource $source, string $title): MediaId
    {
        $title = $this->resolveTitle($source->originalFilename(), $title);
        $staged = null; $activated = null;
        try {
            $facts = $this->inspector->inspect($source->path()); $staged = $this->storage->stage($source->path()); $stagedFacts = $this->inspector->inspect($staged->path());
            if (!$facts->sameFacts($stagedFacts)) throw new MediaStorageException('The staged media did not verify.');
            $this->lastExtension = $facts->extension();
            for ($attempt = 0; $attempt < 5; $attempt++) { $key = $this->key(); if (!$this->storage->resolve($key)) break; $key = null; }
            if (!isset($key)) throw new MediaStorageException('Media storage could not allocate an identity.');
            $this->storage->activate($staged, $key); $activated = $key; $staged = null;
            $id = $this->lifecycle->create(['kind' => $facts->mimeType() === 'application/pdf' ? 'document' : 'image', 'original_filename' => $source->originalFilename(), 'title' => trim($title), 'storage_key' => $key, 'mime_type' => $facts->mimeType(), 'extension' => $facts->extension(), 'byte_size' => $facts->byteSize(), 'width' => $facts->width(), 'height' => $facts->height()]);
            return $id;
        } catch (Throwable $exception) {
            if ($staged) $this->storage->discard($staged); if ($activated) { try { $this->storage->delete($activated); } catch (Throwable) { if (is_object($this->diagnostics) && method_exists($this->diagnostics, 'warning')) $this->diagnostics->warning('media.cleanup_failed', 'Media cleanup failed after an upload error.', ['component' => 'media']); } }
            if ($exception instanceof MediaUploadException) throw $exception; throw new MediaStorageException('The media could not be stored.', 0, $exception);
        }
    }
    private function resolveTitle(string $originalFilename, string $title): string
    {
        $title = trim($title);
        if ($title !== '') return $title;
        $dot = strrpos($originalFilename, '.');
        $base = $dot === false ? $originalFilename : substr($originalFilename, 0, $dot);
        $derived = preg_replace('/\s+/u', ' ', str_replace('_', ' ', $base ?? ''));
        $derived = is_string($derived) ? trim($derived) : '';
        if ($derived === '') throw new MediaUploadValidationException('The media title could not be derived from the filename.');
        return $derived;
    }
    private function key(): string { $raw = $this->keyGenerator ? ($this->keyGenerator)() : bin2hex(random_bytes(16)); if (!is_string($raw) || !preg_match('/^[a-f0-9]{32}$/', $raw)) throw new MediaStorageException('Media storage could not allocate an identity.'); return $raw . '.' . ($this->lastExtension ?? ''); }
    private ?string $lastExtension = null;
}
