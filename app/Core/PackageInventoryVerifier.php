<?php

namespace Copot\Core;

final class PackageInventoryVerifier
{
    public function verify(StagedPayload $payload, array $inventory, array $metadataPaths = []): void
    {
        $expected = [];

        foreach ($inventory as $entry) {
            if (!$entry instanceof PackageInventoryEntry || $entry->ownership() !== PackageOwnership::PACKAGE_OWNED) {
                throw new \RuntimeException('Package inventory contains unsupported ownership.');
            }

            if (isset($expected[$entry->path()])) {
                throw new \RuntimeException('Package inventory contains a duplicate path.');
            }

            $expected[$entry->path()] = $entry;
        }

        $excluded = [];

        foreach ($metadataPaths as $path) {
            $normalized = ArchiveEntryPath::normalize((string) $path);
            $excluded[$normalized] = true;
        }

        $actual = [];

        foreach ($payload->files() as $file) {
            if (isset($excluded[$file->path()])) {
                continue;
            }

            $actual[$file->path()] = $file;

            if (!isset($expected[$file->path()])) {
                throw new \RuntimeException('Staged payload contains an unexpected file.');
            }

            $entry = $expected[$file->path()];

            if ($entry->byteSize() !== $file->byteSize() || $entry->sha256() !== $file->sha256()) {
                throw new \RuntimeException('Staged payload integrity does not match package inventory.');
            }
        }

        foreach ($expected as $path => $entry) {
            if (!isset($actual[$path])) {
                throw new \RuntimeException('Package inventory declares a missing staged file.');
            }
        }
    }
}
