<?php

final class MediaFeaturedProfile
{
    public static function request(MediaId|int $id, array $crop): MediaProcessingRequest
    {
        return MediaProcessingRequest::fromArray($id, [
            'profile' => 'content.featured',
            'crop' => $crop,
            'crop_aspect_ratio' => ['width' => 16, 'height' => 9],
            'allow_upscale' => true,
            'fit' => 'cover',
            'responsive_widths' => [640, 960, 1280],
        ]);
    }
}
