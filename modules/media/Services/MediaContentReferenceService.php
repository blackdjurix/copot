<?php

use Copot\Core\Database;

final class MediaContentReferenceService
{
    public const USAGE_KEY = 'featured_media';
    private const ALLOWED = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(
        private Database $database,
        private MediaRepository $media,
        private MediaUsageRepository $usages
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
        return [
            'id' => $media->id()->value(),
            'title' => $media->title(),
            'original_filename' => $media->originalFilename(),
            'mime_type' => $media->mimeType(),
            'url' => '/media/' . $media->id()->value(),
        ];
    }
}
