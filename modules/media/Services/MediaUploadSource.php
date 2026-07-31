<?php

final class MediaUploadSource
{
    private function __construct(private string $path, private string $originalFilename, private int $declaredSize, private string $browserMime)
    {
        if ($originalFilename === '' || preg_match('//u', $originalFilename) !== 1 || preg_match('/[\x00-\x1F\x7F]/', $originalFilename)) throw new MediaUploadValidationException('The uploaded filename is invalid.');
        if (strlen($originalFilename) > 255 || $declaredSize <= 0 || $path === '') throw new MediaUploadValidationException('The uploaded file is invalid.');
    }

    public static function fromArray(array $file): self
    {
        if (($file['error'] ?? null) !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null) || !is_string($file['name'] ?? null) || !is_int($file['size'] ?? null)) throw new MediaUploadValidationException('The uploaded file is invalid.');
        $filename = trim($file['name']);
        if (class_exists('Normalizer')) $filename = \Normalizer::normalize($filename, \Normalizer::FORM_C) ?: $filename;
        return new self($file['tmp_name'], $filename, $file['size'], is_string($file['type'] ?? null) ? $file['type'] : '');
    }
    public function path(): string { return $this->path; }
    public function originalFilename(): string { return $this->originalFilename; }
    public function declaredSize(): int { return $this->declaredSize; }
    public function browserMime(): string { return $this->browserMime; }
}
