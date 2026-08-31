<?php

namespace Copot\Core;

final class MediaAdmin
{
    public const PAGE_SIZE = 50;

    public function __construct(
        private MediaRepository $media,
        private MediaUploadService $upload,
        private MediaLifecycleService $lifecycle,
        private MediaUsageRepository $usages
    ) {
    }

    public function inventory(int $page = 1): array
    {
        $page = max(1, $page);
        return $this->media->paginate('', self::PAGE_SIZE, ($page - 1) * self::PAGE_SIZE);
    }

    public function upload(array $file, string $title): MediaId
    {
        return $this->upload->upload(MediaUploadSource::fromArray($file), $title);
    }

    public function updateTitle(MediaId|int $id, string $title): void
    {
        $this->lifecycle->updateTitle($id, $title);
    }

    public function usages(MediaId|int $id): array
    {
        return $this->usages->forMedia($id);
    }

    public function delete(MediaId|int $id): void
    {
        $this->lifecycle->delete($id);
    }
}
