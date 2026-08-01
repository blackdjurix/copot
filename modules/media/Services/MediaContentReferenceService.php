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
        private ?MediaVariantRepository $variants = null
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

    public function sync(int $contentId, ?int $previous, ?int $next): void
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
    }

    public function descriptor(?int $mediaId): ?array
    {
        $media = $this->validate($mediaId);
        if (!$media) return null;
        $variants = $this->variants?->forMedia($media->id()) ?? [];
        $prepared = array_values(array_filter($variants, static fn (MediaVariant $variant): bool => str_starts_with($variant->variantKey(), 'content-featured-')));
        usort($prepared, static fn (MediaVariant $a, MediaVariant $b): int => ($b->width() ?? 0) <=> ($a->width() ?? 0));
        if ($prepared === []) return null;
        $srcset = array_map(static fn (MediaVariant $variant): string => '/media/' . $media->id()->value() . '/variant/' . rawurlencode($variant->variantKey()) . ' ' . (int) $variant->width() . 'w', $prepared);
        $primary = $prepared[0];
        return [
            'id' => $media->id()->value(),
            'title' => $media->title(),
            'original_filename' => $media->originalFilename(),
            'mime_type' => $media->mimeType(),
            'url' => '/media/' . $media->id()->value() . '/variant/' . rawurlencode($primary->variantKey()),
            'srcset' => implode(', ', $srcset),
            'width' => $primary->width(),
            'height' => $primary->height(),
            'alt' => $media->title() !== '' ? $media->title() : $media->originalFilename(),
        ];
    }
}
