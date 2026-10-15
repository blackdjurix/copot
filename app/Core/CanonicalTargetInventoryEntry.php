<?php

namespace Copot\Core;

final class CanonicalTargetInventoryEntry
{
    public const APP_ROOT = 'APP_ROOT';
    public const PUBLIC_ROOT = 'PUBLIC_ROOT';
    public const PACKAGE_OWNED = 'package-owned';

    public function __construct(
        private string $root,
        private string $ownership,
        private string $path,
        private int $byteSize,
        private string $sha256
    ) {
        if (!in_array($root, [self::APP_ROOT, self::PUBLIC_ROOT], true) || $ownership !== self::PACKAGE_OWNED) { throw new \InvalidArgumentException('Target inventory root or ownership is invalid.'); }
        $this->path = self::assertPath($path);
        if ($byteSize < 0 || preg_match('/^[a-f0-9]{64}$/D', $sha256) !== 1) { throw new \InvalidArgumentException('Target inventory file identity is invalid.'); }
    }

    public function root(): string { return $this->root; }
    public function ownership(): string { return $this->ownership; }
    public function path(): string { return $this->path; }
    public function byteSize(): int { return $this->byteSize; }
    public function sha256(): string { return $this->sha256; }
    public function toArray(): array { return ['root' => $this->root, 'ownership' => $this->ownership, 'path' => $this->path, 'byte_size' => $this->byteSize, 'sha256' => $this->sha256]; }

    public static function assertPath(string $path): string
    {
        CanonicalText::assert($path, 'Target inventory path');
        if ($path === '' || str_contains($path, '\\') || str_contains($path, ':') || str_starts_with($path, '/') || str_contains($path, '//')) { throw new \InvalidArgumentException('Target inventory path is not canonical.'); }
        $segments = explode('/', $path);
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..' || preg_match('/[. ]$/', $segment) === 1 || preg_match('/^(con|prn|aux|nul|com[1-9]|lpt[1-9])(?:\..*)?$/i', $segment) === 1) { throw new \InvalidArgumentException('Target inventory path is unsafe.'); }
        }
        return $path;
    }
}
