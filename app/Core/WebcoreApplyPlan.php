<?php

namespace Copot\Core;

final class WebcoreApplyPlan
{
    private array $files;

    private function __construct(
        private StagedPayload $payload,
        private string $identity,
        array $files
    ) {
        $this->files = $files;
    }

    public static function fromPayload(StagedPayload $payload): self
    {
        $files = $payload->files();
        $parts = [$payload->archiveSha256()];

        foreach ($files as $file) {
            if (!$file instanceof StagedFile) {
                throw new \InvalidArgumentException('Apply plan contains an invalid staged file.');
            }
            if (PackageOwnership::classify($file->path()) !== PackageOwnership::PACKAGE_OWNED) {
                throw new \InvalidArgumentException('Apply plan contains a non-package-owned path.');
            }

            $parts[] = implode('|', [$file->path(), (string) $file->byteSize(), $file->sha256()]);
        }

        return new self($payload, hash('sha256', implode("\n", $parts)), $files);
    }

    public function payload(): StagedPayload { return $this->payload; }
    public function identity(): string { return $this->identity; }
    public function files(): array { return $this->files; }
}
