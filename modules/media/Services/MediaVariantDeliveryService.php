<?php

use Copot\Core\Response;

final class MediaVariantDeliveryService
{
    public function __construct(private MediaRepository $media, private MediaVariantRepository $variants, private MediaVariantFilesystemStorage $storage, private MediaFileInspector $inspector) {}
    public function inline(int $id, string $key): Response
    {
        if ($id < 1 || !preg_match('/^content-featured-[a-f0-9]{32}$/', $key)) return $this->notFound();
        $media = $this->media->findById($id); $variant = $media ? $this->variants->find($id, $key) : null; $path = $variant ? $this->storage->resolve($variant->storageKey()) : null;
        if (!$media || !$variant || !$path) return $this->notFound();
        try { $facts = $this->inspector->inspect($path); } catch (Throwable) { return $this->notFound(); }
        if ($facts->mimeType() !== $variant->mimeType() || $facts->byteSize() !== $variant->byteSize() || $facts->width() !== $variant->width() || $facts->height() !== $variant->height()) return $this->notFound();
        $body = @file_get_contents($path); return is_string($body) ? Response::content($body, 200, ['Content-Type' => $variant->mimeType(), 'Content-Length' => (string) strlen($body), 'Cache-Control' => 'public, max-age=3600', 'X-Content-Type-Options' => 'nosniff']) : $this->notFound();
    }
    private function notFound(): Response { return Response::content('404 Not Found', 404, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff']); }
}
