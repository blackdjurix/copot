<?php

namespace Copot\Core;

use JsonException;

final class ModulePackageIntakeInspector
{
    public function __construct(
        private ZipIntakeService $intake,
        private ?ModulePackageManifestReader $manifestReader = null,
        private ?PackageInventoryVerifier $inventoryVerifier = null
    ) {
        $this->manifestReader ??= new ModulePackageManifestReader();
        $this->inventoryVerifier ??= new PackageInventoryVerifier();
    }

    public function inspect(string $archivePath): ModulePackageInspection
    {
        $payload = $this->intake->intake($archivePath);
        try {
            $manifest = $this->manifestReader->read($payload);
            $contract = $manifest->contract();
            $root = $contract->ownership()->rootPath();

            $this->assertLiveOwnership($manifest->inventory(), $root);
            $this->inventoryVerifier->verify($manifest->livePayload(), $manifest->inventory());
            $runtimeManifest = $this->readRuntimeManifest($manifest->livePayload(), $root, $contract->moduleIdentity()->value());

            return new ModulePackageInspection(
                $manifest,
                $runtimeManifest,
                [],
                ['Cross-Module schema, permission, and provisioning ownership are not classified without an authoritative ownership registry.']
            );
        } catch (\Throwable $exception) {
            $payload->cleanup();
            throw $exception;
        }
    }

    private function assertLiveOwnership(array $inventory, string $root): void
    {
        $prefix = $root . '/';
        $seen = [];
        foreach ($inventory as $entry) {
            if (!$entry instanceof PackageInventoryEntry) {
                throw new \RuntimeException('Module package inventory entry is invalid.');
            }
            $path = $entry->path();
            if ($path === ModulePackageManifestReader::PATH || !str_starts_with($path, $prefix)) {
                throw new \RuntimeException('Module package inventory escapes its declared Module ownership root.');
            }
            $segments = ArchiveEntryPath::segments($path);
            if (count($segments) < 3 || $segments[0] !== 'modules' || $segments[1] !== substr($root, 8)) {
                throw new \RuntimeException('Module package inventory contains an invalid Module ownership path.');
            }
            $collision = ArchiveEntryPath::collisionKey($path);
            if (isset($seen[$collision])) {
                throw new \RuntimeException('Module package inventory contains a normalized path collision.');
            }
            $seen[$collision] = true;
        }
    }

    private function readRuntimeManifest(StagedPayload $payload, string $root, string $module): array
    {
        $path = $root . '/module.json';
        $staged = null;
        foreach ($payload->files() as $file) {
            if ($file->path() === $path) {
                $staged = $file;
                break;
            }
        }
        if (!$staged instanceof StagedFile) {
            throw new \RuntimeException('Module package runtime module.json is missing.');
        }
        $fullPath = $payload->payloadPath() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
        if (!is_file($fullPath) || is_link($fullPath) || !is_readable($fullPath)) {
            throw new \RuntimeException('Module package runtime module.json is unavailable.');
        }
        try {
            $metadata = json_decode((string) file_get_contents($fullPath), true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new \RuntimeException('Module package runtime module.json is invalid.', 0, $exception);
        }
        if (!is_array($metadata)) {
            throw new \RuntimeException('Module package runtime module.json is invalid.');
        }
        $error = ModuleManifestValidator::validate($module, $metadata);
        if ($error !== null) {
            throw new \RuntimeException($error);
        }
        return $metadata;
    }
}
