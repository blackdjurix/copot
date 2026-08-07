<?php

use Copot\Core\Database;

final class MediaContentReferenceService
{
    public const USAGE_KEY = 'featured_media';
    private const ALLOWED = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(
        private Database $database,
        private MediaRepository $media,
        private MediaUsageRepository $usages,
        private ?MediaVariantRepository $variants = null,
        private ?MediaPendingPreparationService $pending = null,
        private ?MediaVariantFilesystemStorage $storage = null,
        private ?Closure $url = null
    ) {
    }

    public function validate(?int $mediaId): ?Media
    {
        if ($mediaId === null) return null;
        if ($mediaId < 1) throw new InvalidArgumentException('Featured Media reference is invalid.');
        $media = $this->media->findById($mediaId, true);
        if (!$media || !in_array($media->mimeType(), self::ALLOWED, true)) {
            throw new InvalidArgumentException('Selected Media is unavailable for featured use.');
        }
        return $media;
    }

    public function sync(int $contentId, ?int $previous, ?int $next, ?string $pendingToken = null, ?int $userId = null): array
    {
        if ($contentId < 1) throw new InvalidArgumentException('Content identity is invalid.');
        $connection = $this->database->connection();
        $this->validate($next);
        if ($previous !== null && $previous !== $next) {
            $this->usages->remove($previous, 'content', $contentId, self::USAGE_KEY);
        }
        if ($next !== null) {
            $this->usages->register($next, 'content', $contentId, self::USAGE_KEY);
        }
        if ($pendingToken !== null && $pendingToken !== '') {
            if ($next === null || $userId === null || !$this->pending) throw new InvalidArgumentException('Featured Media preparation is unavailable.');
            return $this->pending->promote($pendingToken, $userId, $contentId, $next);
        }
        return [];
    }

    public function descriptor(?int $mediaId, ?int $contentId = null): ?array
    {
        $media = $this->validate($mediaId);
        if (!$media) return null;
        if ($contentId === null || $contentId < 1) return null;
        $variants = $this->variants?->forMedia($media->id()) ?? [];
        $prepared = array_values(array_filter($variants, static fn (MediaVariant $variant): bool => $variant->variantKey() === MediaVariantKey::contentSlot($media->id(), $contentId, (int) $variant->width())));
        $currentByWidth = [];
        foreach ($prepared as $variant) {
            $width = $variant->width();
            if ($width === null) continue;
            $current = $currentByWidth[$width] ?? null;
            if (!$current || strcmp($variant->updatedAt(), $current->updatedAt()) > 0 || (strcmp($variant->updatedAt(), $current->updatedAt()) === 0 && $variant->id() > $current->id())) {
                $currentByWidth[$width] = $variant;
            }
        }
        $prepared = array_values($currentByWidth);
        usort($prepared, static fn (MediaVariant $a, MediaVariant $b): int => ($b->width() ?? 0) <=> ($a->width() ?? 0));
        if ($prepared === []) return null;
        $url = $this->url ?? static fn (string $path): string => $path;
        $srcset = array_map(fn (MediaVariant $variant): string => $url('/media/' . $media->id()->value() . '/variant/' . rawurlencode($variant->variantKey())) . ' ' . (int) $variant->width() . 'w', $prepared);
        $primary = $prepared[0];
        return [
            'id' => $media->id()->value(),
            'title' => $media->title(),
            'original_filename' => $media->originalFilename(),
            'mime_type' => $media->mimeType(),
            'url' => $url('/media/' . $media->id()->value() . '/variant/' . rawurlencode($primary->variantKey())),
            'srcset' => implode(', ', $srcset),
            'width' => $primary->width(),
            'height' => $primary->height(),
            'alt' => $media->title() !== '' ? $media->title() : $media->originalFilename(),
        ];
    }

    public function finalize(array $storageKeys): void { if(!$this->storage)return; foreach(array_unique($storageKeys) as $key)try{$this->storage->delete($key);}catch(Throwable){} }
}
