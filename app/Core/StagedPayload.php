<?php

namespace Copot\Core;

final class StagedPayload
{
    private array $files;

    public function __construct(
        private StagingSession $session,
        private string $archiveSha256,
        array $files
    ) {
        $indexed = [];

        foreach ($files as $file) {
            if (!$file instanceof StagedFile || isset($indexed[$file->path()])) {
                throw new \InvalidArgumentException('Staged payload files are invalid.');
            }

            $indexed[$file->path()] = $file;
        }

        ksort($indexed, SORT_STRING);
        $this->files = $indexed;
        $this->archiveSha256 = strtolower($archiveSha256);
    }

    public function stagingPath(): string { return $this->session->path(); }
    public function archivePath(): string { return $this->session->archivePath(); }
    public function payloadPath(): string { return $this->session->payloadPath(); }
    public function archiveSha256(): string { return $this->archiveSha256; }
    public function files(): array { return array_values($this->files); }

    /** @param list<string> $excludedPaths */
    public function withoutPaths(array $excludedPaths): self
    {
        $excluded = [];
        foreach ($excludedPaths as $path) {
            $excluded[ArchiveEntryPath::normalize($path)] = true;
        }

        $files = array_filter($this->files, static fn (StagedFile $file): bool => !isset($excluded[$file->path()]));

        return new self($this->session, $this->archiveSha256, $files);
    }

    public function cleanup(): void
    {
        $this->session->cleanup();
    }
}
