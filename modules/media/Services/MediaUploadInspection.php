<?php

final class MediaUploadInspection
{
    public function __construct(private string $mimeType, private string $extension, private int $byteSize, private ?int $width, private ?int $height)
    {
        if ($byteSize <= 0) throw new MediaUploadValidationException('The uploaded file is empty or invalid.');
    }
    public function mimeType(): string { return $this->mimeType; }
    public function extension(): string { return $this->extension; }
    public function byteSize(): int { return $this->byteSize; }
    public function width(): ?int { return $this->width; }
    public function height(): ?int { return $this->height; }
    public function sameFacts(self $other): bool { return $this->mimeType === $other->mimeType && $this->extension === $other->extension && $this->byteSize === $other->byteSize && $this->width === $other->width && $this->height === $other->height; }
}
