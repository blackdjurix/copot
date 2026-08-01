<?php

final class MediaVariantKey
{
    public static function physical(MediaId|int $mediaId, string $originalStorageKey, string $semanticKey, string $processorVersion, string $extension): string
    {
        if (!preg_match('/^(?:r1|content-featured)-[a-f0-9]{32}$/', $semanticKey) || !in_array($extension, ['jpg','png','webp'], true)) throw new MediaProcessingValidationException('Variant identity is invalid.');
        $id = $mediaId instanceof MediaId ? $mediaId->value() : (new MediaId($mediaId))->value(); return substr(hash('sha256', $id . '|' . $originalStorageKey . '|' . $semanticKey . '|' . $processorVersion . '|' . $extension), 0, 32) . '.' . $extension;
    }
}
