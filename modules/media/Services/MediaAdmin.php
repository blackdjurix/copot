<?php

final class MediaAdmin
{
    public const PAGE_SIZE = 24;

    public function __construct(
        private MediaRepository $media,
        private MediaUploadService $upload,
        private MediaLifecycleService $lifecycle,
        private MediaProcessingService $processing
    ) {
    }

    public function workspace(array $filters, int $page): array
    {
        $page = max(1, $page);

        return $this->media->workspace(
            $filters,
            self::PAGE_SIZE,
            ($page - 1) * self::PAGE_SIZE
        );
    }

    public function upload(array $file, string $title): MediaId
    {
        return $this->upload->upload(MediaUploadSource::fromArray($file), $title);
    }

    public function updateTitle(MediaId|int $id, string $title): void
    {
        $this->lifecycle->updateTitle($id, $title);
    }

    public function process(MediaId|int $id, string $preset): array
    {
        $request = $this->preset($id, $preset);

        return $this->processing->process($id, $request);
    }

    public function preset(MediaId|int $id, string $preset): MediaProcessingRequest
    {
        $data = match ($preset) {
            'square' => [
                'resize' => ['width' => 640, 'height' => 640],
                'fit' => 'cover',
                'output_format' => 'webp',
                'quality' => 82,
            ],
            'landscape' => [
                'resize' => ['width' => 1280, 'height' => 720],
                'fit' => 'cover',
                'output_format' => 'webp',
                'quality' => 82,
            ],
            'contain' => [
                'resize' => ['width' => 1280, 'height' => 1280],
                'fit' => 'contain',
            ],
            default => throw new MediaProcessingValidationException('The selected processing preset is invalid.'),
        };

        return MediaProcessingRequest::fromArray($id, $data);
    }

    public function isEditable(Media $media): bool
    {
        return $media->kind() === 'image'
            && in_array($media->mimeType(), ['image/jpeg', 'image/png', 'image/webp'], true);
    }
}
