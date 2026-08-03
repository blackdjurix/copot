<?php

namespace Copot\Core;

final class PackageInventoryEntry
{
    private string $path;
    private int $byteSize;
    private string $sha256;
    private string $ownership;

    public function __construct(string $path, int $byteSize, string $sha256, string $ownership = PackageOwnership::PACKAGE_OWNED)
    {
        $this->path = self::normalizePath($path);

        if ($byteSize < 0) {
            throw new \InvalidArgumentException('Package inventory file size cannot be negative.');
        }

        $sha256 = strtolower($sha256);

        if (preg_match('/^[a-f0-9]{64}$/', $sha256) !== 1) {
            throw new \InvalidArgumentException('Package inventory SHA-256 identity is invalid.');
        }

        PackageOwnership::assertCompatible($this->path, $ownership);

        $this->byteSize = $byteSize;
        $this->sha256 = $sha256;
        $this->ownership = $ownership;
    }

    public static function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);

        if ($path === '' || preg_match('/[\x00-\x1F\x7F]/', $path) === 1) {
            throw new \InvalidArgumentException('Package inventory path is invalid.');
        }

        if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:\//', $path) === 1) {
            throw new \InvalidArgumentException('Package inventory path must be relative.');
        }

        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                throw new \InvalidArgumentException('Package inventory path cannot escape its root.');
            }

            $segments[] = $segment;
        }

        if ($segments === []) {
            throw new \InvalidArgumentException('Package inventory path is invalid.');
        }

        return implode('/', $segments);
    }

    public function path(): string
    {
        return $this->path;
    }

    public function byteSize(): int
    {
        return $this->byteSize;
    }

    public function sha256(): string
    {
        return $this->sha256;
    }

    public function ownership(): string
    {
        return $this->ownership;
    }

    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'byte_size' => $this->byteSize,
            'sha256' => $this->sha256,
            'ownership' => $this->ownership,
        ];
    }
}
