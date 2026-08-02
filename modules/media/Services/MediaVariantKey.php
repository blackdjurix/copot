<?php

final class MediaVariantKey
{
    public static function contentSlot(MediaId|int $mediaId, int $contentId, int $width): string
    {
        $id = $mediaId instanceof MediaId ? $mediaId->value() : (new MediaId($mediaId))->value();
        if ($contentId < 1 || $width < 1) throw new MediaProcessingValidationException('Variant slot is invalid.');
        return 'content-slot-' . substr(hash('sha256', $id . '|content|' . $contentId . '|featured_media|' . $width), 0, 32);
    }

    public static function pendingSlot(string $token, int $width): string
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token) || $width < 1) throw new MediaProcessingValidationException('Pending preparation is invalid.');
        return 'pending-' . substr(hash('sha256', $token . '|' . $width), 0, 32);
    }

    public static function physical(MediaId|int $mediaId, string $originalStorageKey, string $semanticKey, string $processorVersion, string $extension, ?string $scope = null): string
    {
        if (!preg_match('/^(?:r1|content-featured)-[a-f0-9]{32}$/', $semanticKey) || !in_array($extension, ['jpg','png','webp'], true)) throw new MediaProcessingValidationException('Variant identity is invalid.');
        if ($scope !== null && !preg_match('/^(?:pending|content-slot)-[a-f0-9]{32}$/', $scope)) throw new MediaProcessingValidationException('Variant identity is invalid.');
        $id = $mediaId instanceof MediaId ? $mediaId->value() : (new MediaId($mediaId))->value(); return substr(hash('sha256', $id . '|' . $originalStorageKey . '|' . $semanticKey . '|' . ($scope ?? '') . '|' . $processorVersion . '|' . $extension), 0, 32) . '.' . $extension;
    }
}
