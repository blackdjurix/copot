<?php

namespace Copot\Core;

final class StagedFile
{
    public function __construct(
        private string $path,
        private int $byteSize,
        private string $sha256
    ) {
        $this->path = ArchiveEntryPath::normalize($path);

        if ($byteSize < 0 || preg_match('/^[a-f0-9]{64}$/', strtolower($sha256)) !== 1) {
            throw new \InvalidArgumentException('Staged file identity is invalid.');
        }

        $this->sha256 = strtolower($sha256);
    }

    public function path(): string { return $this->path; }
    public function byteSize(): int { return $this->byteSize; }
    public function sha256(): string { return $this->sha256; }
}
