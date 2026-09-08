<?php

namespace Copot\Core;

/**
 * Keeps the Core Content featured-media reference aligned with Core Media's
 * authoritative usage ledger. This is deliberately limited to the one Core
 * Content slot; richer preparation and variant behavior remains extension
 * territory.
 */
final class ContentFeaturedMediaReferenceService
{
    public const CONSUMER_TYPE = 'content';
    public const USAGE_KEY = 'featured_media';

    public function __construct(
        private MediaRepository $media,
        private MediaUsageRepository $usages
    ) {
    }

    public function validate(?int $mediaId): void
    {
        if ($mediaId === null) {
            return;
        }

        $media = $this->media->findById($mediaId, true);
        if ($media === null || $media->kind() !== 'image') {
            throw new \InvalidArgumentException('Selected Media is unavailable for featured use.');
        }
    }

    /**
     * @return list<mixed>
     */
    public function sync(int $contentId, ?int $previousMediaId, ?int $nextMediaId, ?string $pendingToken = null, ?int $userId = null): array
    {
        unset($pendingToken, $userId);

        $this->validate($nextMediaId);

        if ($previousMediaId !== null && $previousMediaId !== $nextMediaId) {
            $this->usages->remove($previousMediaId, self::CONSUMER_TYPE, $contentId, self::USAGE_KEY);
        }

        if ($nextMediaId !== null) {
            // INSERT IGNORE keeps unchanged and historical references singular.
            $this->usages->register($nextMediaId, self::CONSUMER_TYPE, $contentId, self::USAGE_KEY);
        }

        return [];
    }

    /** @param list<mixed> $cleanup */
    public function finalize(array $cleanup): void
    {
        unset($cleanup);
    }
}
