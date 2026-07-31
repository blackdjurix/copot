<?php

final class MediaVariant
{
    public function __construct(
        private int $id,
        private MediaId $mediaId,
        private string $variantKey,
        private string $storageKey,
        private string $mimeType,
        private string $extension,
        private int $byteSize,
        private ?int $width,
        private ?int $height,
        private string $createdAt,
        private string $updatedAt
    ) {
        self::validateInput(compact('id', 'variantKey', 'storageKey', 'mimeType', 'extension', 'byteSize', 'width', 'height'));
    }

    public static function validateInput(array $data): void
    {
        if ((int) ($data['id'] ?? 1) <= 0 || trim((string) ($data['variantKey'] ?? '')) === '' || trim((string) ($data['storageKey'] ?? '')) === '' || trim((string) ($data['mimeType'] ?? '')) === '' || trim((string) ($data['extension'] ?? '')) === '') throw new InvalidArgumentException('Media variant identity and metadata are required.');
        if ((string) $data['variantKey'] !== trim((string) $data['variantKey']) || (int) ($data['byteSize'] ?? 0) <= 0 || (($data['width'] ?? null) !== null && (int) $data['width'] <= 0) || (($data['height'] ?? null) !== null && (int) $data['height'] <= 0)) throw new InvalidArgumentException('Media variant metadata is invalid.');
    }

    public function id(): int { return $this->id; }
    public function mediaId(): MediaId { return $this->mediaId; }
    public function variantKey(): string { return $this->variantKey; }
    public function storageKey(): string { return $this->storageKey; }
    public function mimeType(): string { return $this->mimeType; }
    public function extension(): string { return $this->extension; }
    public function byteSize(): int { return $this->byteSize; }
    public function width(): ?int { return $this->width; }
    public function height(): ?int { return $this->height; }
    public function createdAt(): string { return $this->createdAt; }
    public function updatedAt(): string { return $this->updatedAt; }
}
