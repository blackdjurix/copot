<?php

namespace Copot\Core;

final class ZipArchiveInspector
{
    public function inspect(\ZipArchive $archive, ArchiveLimits $limits): array
    {
        $entryCount = $archive->numFiles;

        if ($entryCount < 1 || $entryCount > $limits->maxEntries()) {
            throw new \RuntimeException('Archive entry count exceeds the WU2 safety limit.');
        }

        $entries = [];
        $collisions = [];
        $totalBytes = 0;
        $totalCompressed = 0;

        for ($index = 0; $index < $entryCount; $index++) {
            $stat = $archive->statIndex($index, \ZipArchive::FL_UNCHANGED);

            if (!is_array($stat) || !isset($stat['name'], $stat['size'], $stat['comp_size'])) {
                throw new \RuntimeException('Archive entry metadata is unavailable.');
            }

            $rawName = $stat['name'];
            $path = ArchiveEntryPath::normalize((string) $rawName);
            $segments = ArchiveEntryPath::segments($path);
            $hasDirectorySyntax = str_ends_with((string) $rawName, '/') || str_ends_with((string) $rawName, '\\');
            $isDirectory = $hasDirectorySyntax;
            $externalType = $this->externalType($archive, $index);

            if ($externalType === 'symlink' || $externalType === 'special') {
                throw new \RuntimeException('Archive contains a symlink or special-file entry.');
            }

            if ($externalType === 'directory') {
                if (!$hasDirectorySyntax) {
                    throw new \RuntimeException('Archive directory metadata contradicts its entry name.');
                }

                $isDirectory = true;
            }

            if ($hasDirectorySyntax && $externalType === 'regular') {
                throw new \RuntimeException('Archive directory name contradicts regular-file metadata.');
            }

            if (($stat['encryption_method'] ?? 0) !== 0) {
                throw new \RuntimeException('Encrypted archive entries are not supported.');
            }

            $size = (int) $stat['size'];
            $compressedSize = (int) $stat['comp_size'];

            if ($size < 0 || $compressedSize < 0 || strlen($path) > $limits->maxPathBytes() || count($segments) > $limits->maxNesting()) {
                throw new \RuntimeException('Archive entry exceeds a WU2 safety limit.');
            }

            if (PackageOwnership::classify($path) !== PackageOwnership::PACKAGE_OWNED) {
                throw new \RuntimeException('Archive entry ownership is not package-owned.');
            }

            $key = ArchiveEntryPath::collisionKey($path);

            if (isset($collisions[$key])) {
                throw new \RuntimeException('Archive contains a duplicate or case-colliding path.');
            }

            $collisions[$key] = ['path' => $path, 'directory' => $isDirectory];

            if ($isDirectory && ($size !== 0 || $compressedSize !== 0)) {
                throw new \RuntimeException('Archive directory metadata is invalid.');
            }

            if (!$isDirectory) {
                if ($size > $limits->maxFileBytes() || ($compressedSize === 0 && $size > 0)) {
                    throw new \RuntimeException('Archive file exceeds a WU2 safety limit.');
                }

                if ($size > 0 && $size / $compressedSize > $limits->maxCompressionRatio()) {
                    throw new \RuntimeException('Archive file compression ratio exceeds the WU2 safety limit.');
                }

                $totalBytes += $size;
                $totalCompressed += $compressedSize;
            }

            $entries[] = [
                'index' => $index,
                'raw_name' => $rawName,
                'path' => $path,
                'directory' => $isDirectory,
                'size' => $size,
                'compressed_size' => $compressedSize,
            ];
        }

        if ($totalBytes > $limits->maxTotalExtractedBytes() || ($totalBytes > 0 && ($totalCompressed === 0 || $totalBytes / $totalCompressed > $limits->maxCompressionRatio()))) {
            throw new \RuntimeException('Archive aggregate size or compression ratio exceeds the WU2 safety limit.');
        }

        foreach ($entries as $entry) {
            if ($entry['directory']) {
                continue;
            }

            $fileKey = ArchiveEntryPath::collisionKey($entry['path']);

            foreach ($entries as $other) {
                if ($other['path'] === $entry['path']) {
                    continue;
                }

                $otherKey = ArchiveEntryPath::collisionKey($other['path']);

                if (str_starts_with($otherKey, $fileKey . '/')) {
                    throw new \RuntimeException('Archive contains a file used as a parent path.');
                }
            }
        }

        return $entries;
    }

    private function externalType(\ZipArchive $archive, int $index): ?string
    {
        if (!$archive->getExternalAttributesIndex($index, $opsys, $attributes, \ZipArchive::FL_UNCHANGED)) {
            return null;
        }

        if ($opsys === \ZipArchive::OPSYS_UNIX) {
            $mode = ($attributes >> 16) & 0xF000;

            return match ($mode) {
                0xA000 => 'symlink',
                0x4000 => 'directory',
                0x8000 => 'regular',
                0 => null,
                default => 'special',
            };
        }

        return (($attributes & 0x10) !== 0) ? 'directory' : null;
    }
}
