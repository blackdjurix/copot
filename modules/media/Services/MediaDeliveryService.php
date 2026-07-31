<?php

use Copot\Core\Response;

final class MediaDeliveryService
{
    public function __construct(private MediaRepository $media, private MediaFileInspector $inspector, private MediaFilesystemStorage $storage) {}
    public function inline(MediaId|int $id): Response { return $this->serve($id, false); }
    public function download(MediaId|int $id): Response { return $this->serve($id, true); }
    private function serve(MediaId|int $id, bool $download): Response
    {
        if (is_int($id) && $id <= 0) return $this->notFound(); $media = $this->media->findById($id); if (!$media) return $this->notFound();
        $path = $this->storage->resolve($media->storageKey()); if (!$path) return $this->notFound();
        try { $facts = $this->inspector->inspect($path); } catch (Throwable) { return $this->notFound(); }
        if ($facts->mimeType() !== $media->mimeType() || $facts->extension() !== $media->extension() || $facts->byteSize() !== $media->byteSize() || $facts->width() !== $media->width() || $facts->height() !== $media->height()) return $this->notFound();
        $content = @file_get_contents($path); if (!is_string($content) || strlen($content) !== $facts->byteSize()) return $this->notFound();
        $fallback = $this->fallback($media->originalFilename(), $media->extension()); $disposition = ($download ? 'attachment' : 'inline') . '; filename="' . $fallback . '"; filename*=UTF-8\'\'' . rawurlencode($media->originalFilename());
        $headers = ['Content-Type' => $media->mimeType(), 'Content-Length' => (string) strlen($content), 'Content-Disposition' => $disposition, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'public, max-age=3600']; if (!$download && $media->mimeType() === 'application/pdf') $headers['Content-Security-Policy'] = 'sandbox';
        return Response::content($content, 200, $headers);
    }
    private function fallback(string $name, string $extension): string { $stem = pathinfo($name, PATHINFO_FILENAME); $stem = preg_replace('/[^A-Za-z0-9._-]+/', '_', $stem) ?: 'media'; $stem = trim($stem, '._-'); if ($stem === '' || $stem === '.' || $stem === '..') $stem = 'media'; return substr($stem, 0, 180) . '.' . $extension; }
    private function notFound(): Response { return Response::content('404 Not Found', 404, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff']); }
}
