<?php

final class Media
{
    public function __construct(
        private MediaId $id,
        private string $kind,
        private string $originalFilename,
        private string $title,
        private string $storageKey,
        private string $mimeType,
        private string $extension,
        private int $byteSize,
        private ?int $width,
        private ?int $height,
        private string $createdAt,
        private string $updatedAt
    ) {
        self::validateInput(compact('kind', 'originalFilename', 'title', 'storageKey', 'mimeType', 'extension', 'byteSize', 'width', 'height'));
    }

    public static function validateInput(array $data): void
    {
        $kind = (string) ($data['kind'] ?? '');
        if (!in_array($kind, ['image', 'document'], true)) throw new InvalidArgumentException('Media kind must be image or document.');
        foreach (['originalFilename', 'title', 'storageKey', 'mimeType', 'extension'] as $field) if (trim((string) ($data[$field] ?? '')) === '') throw new InvalidArgumentException('Media text fields must be non-empty.');
        if ((int) ($data['byteSize'] ?? 0) <= 0) throw new InvalidArgumentException('Media byte size must be positive.');
        $extension = (string) $data['extension'];
        if ($extension !== strtolower($extension) || str_starts_with($extension, '.')) throw new InvalidArgumentException('Media extension must be canonical lowercase without a leading dot.');
        $width = $data['width'] ?? null; $height = $data['height'] ?? null;
        if ($kind === 'image' && ($width === null || $height === null || (int) $width <= 0 || (int) $height <= 0)) throw new InvalidArgumentException('Image Media requires positive dimensions.');
        if ($kind === 'document' && ($width !== null || $height !== null)) throw new InvalidArgumentException('Document Media cannot have image dimensions.');
        $storageKey = (string) $data['storageKey'];
        if (str_contains($storageKey, '\\') || str_contains($storageKey, '/') || str_contains($storageKey, '://')) throw new InvalidArgumentException('Media storage key must not be a physical path or public URL.');
    }

    public function id(): MediaId { return $this->id; }
    public function kind(): string { return $this->kind; }
    public function originalFilename(): string { return $this->originalFilename; }
    public function title(): string { return $this->title; }
    public function storageKey(): string { return $this->storageKey; }
    public function mimeType(): string { return $this->mimeType; }
    public function extension(): string { return $this->extension; }
    public function byteSize(): int { return $this->byteSize; }
    public function width(): ?int { return $this->width; }
    public function height(): ?int { return $this->height; }
    public function createdAt(): string { return $this->createdAt; }
    public function updatedAt(): string { return $this->updatedAt; }
}
