<?php

namespace Copot\Core;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/** Shared read-only live inventory and collision evidence provider. */
final class LegacyBootstrapLiveInventoryProvider
{
    private string $appRoot;
    private string $publicRoot;

    public function __construct(string $appRoot, string $publicRoot)
    {
        $app = realpath($appRoot); $public = realpath($publicRoot);
        if ($app === false || $public === false || !is_dir($app) || !is_dir($public) || $app === $public) throw new \InvalidArgumentException('Legacy bootstrap roots are invalid.');
        $this->appRoot = rtrim($app, DIRECTORY_SEPARATOR);
        $this->publicRoot = rtrim($public, DIRECTORY_SEPARATOR);
    }

    public function capture(): LegacyBootstrapSourceInventory
    {
        $entries = [];
        foreach ([[CanonicalTargetInventoryEntry::APP_ROOT, $this->appRoot], [CanonicalTargetInventoryEntry::PUBLIC_ROOT, $this->publicRoot]] as [$root, $base]) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file->isLink()) throw new \RuntimeException('Legacy bootstrap source scan encountered a symlink.');
                if (!$file->isFile()) continue;
                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($base) + 1));
                if ($root === CanonicalTargetInventoryEntry::APP_ROOT && str_starts_with(strtolower($relative), 'storage/.copot-lifecycle/legacy-bootstrap-source/')) continue;
                $entries[] = new LegacyBootstrapSourceEntry($root, $relative, (int) $file->getSize(), (string) hash_file('sha256', $file->getPathname()));
            }
        }
        return new LegacyBootstrapSourceInventory($entries);
    }

    public function collisions(CanonicalTargetInventory $target, LegacyBootstrapSourceInventory $source): array
    {
        $live = [];
        foreach ($source->entries() as $entry) $live[strtolower($entry->root()."\0".$entry->path())] = $entry;
        $targetKeys = array_fill_keys(array_map(static fn (CanonicalTargetInventoryEntry $entry): string => strtolower($entry->root()."\0".$entry->path()), $target->entries()), true);
        $rows = [];
        foreach ($target->entries() as $entry) {
            $key = strtolower($entry->root()."\0".$entry->path());
            $rows[] = isset($live[$key])
                ? ['action' => 'BLOCK', 'root' => $entry->root(), 'path' => $entry->path(), 'reason' => 'Legacy live bytes do not prove package ownership; explicit collision authority is required.']
                : ['action' => 'ADD', 'root' => $entry->root(), 'path' => $entry->path(), 'reason' => 'Destination is absent and delivered by the full v3 package.'];
        }
        foreach ($live as $key => $entry) if (!isset($targetKeys[$key])) $rows[] = ['action' => 'UNVERIFIED', 'root' => $entry->root(), 'path' => $entry->path(), 'reason' => 'Live file is outside the package target inventory and remains untouched.'];
        usort($rows, static fn (array $a, array $b): int => strcmp($a['root'].'\0'.$a['path'], $b['root'].'\0'.$b['path']));
        return $rows;
    }

    /** @return list<array{root:string,path:string,actual_type:string}> */
    public function targetTypeMismatches(CanonicalTargetInventory $target): array
    {
        $mismatches = [];
        foreach ($target->entries() as $entry) {
            $base = $entry->root() === CanonicalTargetInventoryEntry::APP_ROOT ? $this->appRoot : $this->publicRoot;
            $path = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $entry->path());
            if (!file_exists($path) && !is_link($path)) continue;
            if (is_link($path) || !is_file($path)) $mismatches[] = ['root' => $entry->root(), 'path' => $entry->path(), 'actual_type' => is_link($path) ? 'symlink' : (is_dir($path) ? 'directory' : 'non-regular')];
        }
        return $mismatches;
    }
}
