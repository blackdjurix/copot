<?php

declare(strict_types=1);

use Copot\Core\Version;

$basePath = dirname(__DIR__);

require $basePath . '/bootstrap/autoload.php';

/**
 * @return never
 */
function fail(string $message): void
{
    fwrite(STDERR, 'Package build failed: ' . $message . PHP_EOL);
    exit(1);
}

/**
 * @return array{include: list<string>, exclude: list<string>}
 */
function loadManifest(string $path): array
{
    if (!is_file($path)) {
        fail('manifest not found at ' . $path);
    }

    $manifest = require $path;

    if (!is_array($manifest) || !isset($manifest['include'], $manifest['exclude'])) {
        fail('manifest must return include and exclude lists.');
    }

    foreach (['include', 'exclude'] as $key) {
        if (!is_array($manifest[$key])) {
            fail('manifest ' . $key . ' value must be a list.');
        }

        foreach ($manifest[$key] as $entry) {
            if (!is_string($entry) || $entry === '') {
                fail('manifest ' . $key . ' entries must be non-empty strings.');
            }
        }
    }

    return [
        'include' => array_values($manifest['include']),
        'exclude' => array_values($manifest['exclude']),
    ];
}

function normalizePath(string $path): string
{
    return str_replace('\\', '/', $path);
}

function relativePath(string $basePath, string $path): string
{
    $base = rtrim(normalizePath(realpath($basePath) ?: $basePath), '/') . '/';
    $real = normalizePath(realpath($path) ?: $path);

    if (!str_starts_with($real, $base)) {
        fail('path escapes repository root: ' . $path);
    }

    return ltrim(substr($real, strlen($base)), '/');
}

function matchesPattern(string $relativePath, string $pattern): bool
{
    $path = trim(normalizePath($relativePath), '/');
    $pattern = trim(normalizePath($pattern), '/');

    if ($pattern === '') {
        return false;
    }

    if (str_ends_with($pattern, '/*')) {
        $base = substr($pattern, 0, -2);

        return $path !== $base && str_starts_with($path, $base . '/');
    }

    return $path === $pattern || str_starts_with($path, $pattern . '/');
}

/**
 * @param list<string> $exclude
 */
function isExcluded(string $relativePath, array $exclude): bool
{
    foreach ($exclude as $pattern) {
        if (matchesPattern($relativePath, $pattern)) {
            return true;
        }
    }

    return false;
}

/**
 * @param list<string> $include
 * @param list<string> $exclude
 * @return array<string, string>
 */
function collectPackageFiles(string $basePath, array $include, array $exclude): array
{
    $files = [];

    foreach ($include as $entry) {
        $absolute = $basePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $entry);

        if (!file_exists($absolute)) {
            fail('included path does not exist: ' . $entry);
        }

        $relative = relativePath($basePath, $absolute);

        if (is_link($absolute)) {
            fail('symlinked package paths are not supported: ' . $entry);
        }

        if (is_file($absolute)) {
            $files[$relative] = $absolute;
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($absolute, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $fileInfo) {
            $filePath = $fileInfo->getPathname();
            $fileRelativePath = relativePath($basePath, $filePath);

            if ($fileInfo->isLink()) {
                fail('symlinked package paths are not supported: ' . $fileRelativePath);
            }

            if (!$fileInfo->isFile() || isExcluded($fileRelativePath, $exclude)) {
                continue;
            }

            $files[$fileRelativePath] = $filePath;
        }
    }

    ksort($files, SORT_STRING);

    return $files;
}

/**
 * @param array<string, string> $files
 */
function writeStoredZip(string $zipPath, array $files): void
{
    $handle = fopen($zipPath, 'wb');

    if ($handle === false) {
        fail('unable to open output file: ' . $zipPath);
    }

    $centralDirectory = '';
    $offset = 0;
    $dosTime = 0;
    $dosDate = ((2026 - 1980) << 9) | (1 << 5) | 1;

    foreach ($files as $archiveName => $sourcePath) {
        $contents = file_get_contents($sourcePath);

        if ($contents === false) {
            fclose($handle);
            fail('unable to read package file: ' . $archiveName);
        }

        $name = normalizePath($archiveName);
        $nameLength = strlen($name);
        $size = strlen($contents);
        $crc = crc32($contents);
        $localHeader = pack(
            'VvvvvvVVVvv',
            0x04034b50,
            10,
            0,
            0,
            $dosTime,
            $dosDate,
            $crc,
            $size,
            $size,
            $nameLength,
            0
        );

        if (fwrite($handle, $localHeader . $name . $contents) === false) {
            fclose($handle);
            fail('unable to write package entry: ' . $name);
        }

        $centralDirectory .= pack(
            'VvvvvvvVVVvvvvvVV',
            0x02014b50,
            20,
            10,
            0,
            0,
            $dosTime,
            $dosDate,
            $crc,
            $size,
            $size,
            $nameLength,
            0,
            0,
            0,
            0,
            0,
            $offset
        ) . $name;

        $offset += strlen($localHeader) + $nameLength + $size;
    }

    $centralDirectorySize = strlen($centralDirectory);

    if (fwrite($handle, $centralDirectory) === false) {
        fclose($handle);
        fail('unable to write central directory.');
    }

    $endOfCentralDirectory = pack(
        'VvvvvVVv',
        0x06054b50,
        0,
        0,
        count($files),
        count($files),
        $centralDirectorySize,
        $offset,
        0
    );

    if (fwrite($handle, $endOfCentralDirectory) === false) {
        fclose($handle);
        fail('unable to finalize package.');
    }

    if (!fclose($handle)) {
        fail('unable to close output file.');
    }
}

if (PHP_SAPI !== 'cli') {
    fail('package builder must be run from the CLI.');
}

$manifest = loadManifest(__DIR__ . '/package_manifest.php');
$files = collectPackageFiles($basePath, $manifest['include'], $manifest['exclude']);

if ($files === []) {
    fail('manifest did not select any files.');
}

$version = Version::CURRENT;

if (!preg_match('/^\d+\.\d+\.\d+(?:[-+][A-Za-z0-9.-]+)?$/', $version)) {
    fail('invalid Version::CURRENT value: ' . $version);
}

$distPath = $basePath . DIRECTORY_SEPARATOR . 'dist';

if (!is_dir($distPath) && !mkdir($distPath, 0775, true)) {
    fail('unable to create dist directory.');
}

if (!is_dir($distPath) || !is_writable($distPath)) {
    fail('dist directory is not writable.');
}

$packageName = 'copot-v' . $version . '.zip';
$targetPath = $distPath . DIRECTORY_SEPARATOR . $packageName;
$temporaryPath = $distPath . DIRECTORY_SEPARATOR . '.' . $packageName . '.tmp';

if (file_exists($temporaryPath) && !unlink($temporaryPath)) {
    fail('unable to remove stale temporary package.');
}

writeStoredZip($temporaryPath, $files);

if (file_exists($targetPath) && !unlink($targetPath)) {
    @unlink($temporaryPath);
    fail('unable to replace existing package.');
}

if (!rename($temporaryPath, $targetPath)) {
    @unlink($temporaryPath);
    fail('unable to move package into place.');
}

echo 'Built dist/' . $packageName . ' with ' . count($files) . ' files.' . PHP_EOL;
