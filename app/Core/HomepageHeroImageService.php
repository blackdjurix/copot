<?php

namespace Copot\Core;

final class HomepageHeroImageService
{
    public const CONSUMER_TYPE = 'homepage';
    public const CONSUMER_ID = 1;
    public const USAGE_KEY = 'hero_image';

    public function __construct(
        private SettingsService $settings,
        private Database $database,
        private MediaRepository $media,
        private MediaUsageRepository $usages,
        private MediaLifecycleService $lifecycle
    ) {}

    public function selected(): ?Media
    {
        $value = $this->settings->get('site', 'homepage_hero_media');
        if (!is_int($value) || $value < 1) return null;
        $media = $this->media->findById($value);
        return $media instanceof Media && $media->kind() === 'image' ? $media : null;
    }

    public function selectedId(): ?int
    {
        return $this->selected()?->id()->value();
    }

    public function set(?int $mediaId): void
    {
        $old = $this->settings->get('site', 'homepage_hero_media');
        $old = is_int($old) && $old > 0 ? $old : null;
        if ($mediaId !== null) {
            $media = $this->media->findById($mediaId, true);
            if (!$media instanceof Media || $media->kind() !== 'image') {
                throw new SettingsException('The selected Hero Image is unavailable.');
            }
        }
        $connection = $this->database->connection();
        $ownsTransaction = !$connection->inTransaction();
        if ($ownsTransaction) $connection->beginTransaction();
        try {
            $this->settings->set('site', 'homepage_hero_media', $mediaId, 'json');
            if ($old !== null && $old !== $mediaId) $this->lifecycle->removeUsage($old, self::CONSUMER_TYPE, self::CONSUMER_ID, self::USAGE_KEY);
            if ($mediaId !== null) $this->lifecycle->registerUsage($mediaId, self::CONSUMER_TYPE, self::CONSUMER_ID, self::USAGE_KEY);
            if ($ownsTransaction) $connection->commit();
        } catch (\Throwable $failure) {
            if ($ownsTransaction && $connection->inTransaction()) $connection->rollBack();
            throw $failure;
        }
    }

}
