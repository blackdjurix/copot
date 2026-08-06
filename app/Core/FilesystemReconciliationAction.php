<?php

namespace Copot\Core;

final class FilesystemReconciliationAction
{
    public const CREATE = 'create';
    public const REPLACE = 'replace';
    public const UNCHANGED = 'unchanged';

    public function __construct(
        private string $action,
        private string $path,
        private int $byteSize,
        private string $sha256,
        private ?string $expectedLiveSha256 = null
    ) {
        if (!in_array($action, [self::CREATE, self::REPLACE, self::UNCHANGED], true)) {
            throw new \InvalidArgumentException('Filesystem reconciliation action is invalid.');
        }
        if (preg_match('/^[a-f0-9]{64}$/', $sha256) !== 1 || $byteSize < 0) {
            throw new \InvalidArgumentException('Filesystem reconciliation identity is invalid.');
        }
        if ($expectedLiveSha256 !== null && preg_match('/^[a-f0-9]{64}$/', $expectedLiveSha256) !== 1) {
            throw new \InvalidArgumentException('Filesystem reconciliation precondition identity is invalid.');
        }
        PackageInventoryEntry::normalizePath($path);
    }

    public function action(): string { return $this->action; }
    public function path(): string { return $this->path; }
    public function byteSize(): int { return $this->byteSize; }
    public function sha256(): string { return $this->sha256; }
    public function expectedLiveSha256(): ?string { return $this->expectedLiveSha256; }

    public function toArray(): array
    {
        return [
            'action' => $this->action,
            'path' => $this->path,
            'byte_size' => $this->byteSize,
            'sha256' => $this->sha256,
            'expected_live_sha256' => $this->expectedLiveSha256,
        ];
    }
}
