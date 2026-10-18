<?php

declare(strict_types=1);

use Copot\Core\PackageContract;
use Copot\Core\PackageManifestReader;
use Copot\Core\PackageOwnership;
use Copot\Core\CanonicalTargetInventory;
use Copot\Core\CanonicalTargetInventoryEntry;
use Copot\Core\PackageV3Identity;
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

/** @return array{manifest_version:int, output:?string} */
function parseOptions(array $arguments): array
{
    $manifestVersion = PackageContract::CURRENT_MANIFEST_CONTRACT_VERSION;
    $output = null;

    for ($index = 1, $count = count($arguments); $index < $count; $index++) {
        $argument = $arguments[$index];
        if ($argument === '--manifest-version=3' || ($argument === '--manifest-version' && ($arguments[$index + 1] ?? null) === '3')) {
            $manifestVersion = PackageContract::V3_MANIFEST_CONTRACT_VERSION;
            if ($argument === '--manifest-version') {
                $index++;
            }
            continue;
        }
        if ($argument === '--manifest-version=2' || ($argument === '--manifest-version' && ($arguments[$index + 1] ?? null) === '2')) {
            $manifestVersion = PackageContract::CURRENT_MANIFEST_CONTRACT_VERSION;
            if ($argument === '--manifest-version') {
                $index++;
            }
            continue;
        }
        if (str_starts_with($argument, '--output=')) {
            $output = substr($argument, 9);
            continue;
        }
        if ($argument === '--output' && isset($arguments[$index + 1])) {
            $output = $arguments[++$index];
            continue;
        }
        fail('unsupported option: ' . $argument);
    }

    if ($output === '') {
        fail('output path cannot be empty.');
    }

    return ['manifest_version' => $manifestVersion, 'output' => $output];
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

/** @param list<array{path:string,byte_size:int,sha256:string,ownership:string}> $inventory */
function buildTargetInventory(array $inventory): CanonicalTargetInventory
{
    $runtimeOwned = [
        '.env',
        '.copot/package.json',
        'storage/.install.lock',
        'storage/installed.lock',
        'storage/.copot-lifecycle/committed-state.json',
        'storage/.copot-lifecycle/installation-identity.json',
    ];
    $entries = [];
    foreach ($inventory as $item) {
        $path = $item['path'];
        if (in_array($path, $runtimeOwned, true) || $item['ownership'] !== PackageOwnership::PACKAGE_OWNED) {
            fail('v3 target inventory encountered runtime-owned or non-package-owned path: ' . $path);
        }
        $isPublic = $path === 'public' || str_starts_with($path, 'public/');
        $targetPath = $isPublic ? substr($path, 7) : $path;
        if ($targetPath === '') {
            fail('v3 target inventory contains an invalid public path.');
        }
        $entries[] = new CanonicalTargetInventoryEntry(
            $isPublic ? CanonicalTargetInventoryEntry::PUBLIC_ROOT : CanonicalTargetInventoryEntry::APP_ROOT,
            CanonicalTargetInventoryEntry::PACKAGE_OWNED,
            $targetPath,
            $item['byte_size'],
            $item['sha256']
        );
    }
    return new CanonicalTargetInventory($entries);
}

if (PHP_SAPI !== 'cli') {
    fail('package builder must be run from the CLI.');
}

$options = parseOptions($argv);
$manifestVersion = $options['manifest_version'];

$manifest = loadManifest(__DIR__ . '/package_manifest.php');
$files = collectPackageFiles($basePath, $manifest['include'], $manifest['exclude']);

if ($files === []) {
    fail('manifest did not select any files.');
}

$version = Version::CURRENT;

if (!preg_match('/^\d+\.\d+\.\d+(?:[-+][A-Za-z0-9.-]+)?$/', $version)) {
    fail('invalid Version::CURRENT value: ' . $version);
}

$sourceTreeIdentity = null;

$schemaIdentity = (new Copot\Core\CanonicalSchemaBaselineVerifier())->identity(
    $basePath . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema.sql'
);

$targetInventory = null;

$distPath = $basePath . DIRECTORY_SEPARATOR . 'dist';

$packageName = 'copot-v' . $version . ($manifestVersion === PackageContract::V3_MANIFEST_CONTRACT_VERSION ? '-v3' : '') . '.zip';
$targetPath = $options['output'] ?? ($distPath . DIRECTORY_SEPARATOR . $packageName);
$targetDirectory = dirname($targetPath);
if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0775, true)) {
    fail('unable to create output directory: ' . $targetDirectory);
}
if (!is_dir($targetDirectory) || !is_writable($targetDirectory)) {
    fail('output directory is not writable: ' . $targetDirectory);
}
$temporaryPath = $targetDirectory . DIRECTORY_SEPARATOR . '.' . basename($targetPath) . '.tmp';

/* The manifest is metadata, not package-owned inventory. It is added after
 * the inventory is calculated so WU7 can remove it before live-tree apply. */
$inventory = [];
foreach ($files as $relative => $source) {
    $size = filesize($source);
    $sha256 = hash_file('sha256', $source);
    if (!is_int($size) || !is_string($sha256)) {
        fail('unable to identify package file: ' . $relative);
    }
    $inventory[] = [
        'path' => normalizePath($relative),
        'byte_size' => $size,
        'sha256' => $sha256,
        'ownership' => PackageOwnership::PACKAGE_OWNED,
    ];
}

if ($manifestVersion === PackageContract::V3_MANIFEST_CONTRACT_VERSION) {
    foreach (['public/.htaccess', 'routes/site_settings.php'] as $requiredPolicyPath) {
        if (!isset($files[$requiredPolicyPath])) {
            fail('v3 packaging policy cannot determine ownership for required path: ' . $requiredPolicyPath);
        }
    }
    $targetInventory = buildTargetInventory($inventory);
}

$manifestPath = tempnam(sys_get_temp_dir(), 'copot-package-manifest-');
if ($manifestPath === false) {
    fail('unable to prepare package metadata.');
}

$manifestData = [
    'package_type' => PackageContract::WEBCORE_PACKAGE_TYPE,
    'manifest_contract_version' => $manifestVersion,
    'target_webcore_version' => $version,
    'release_identity' => 'copot-v' . $version,
    'source_tree_identity' => $sourceTreeIdentity !== '' ? $sourceTreeIdentity : null,
    'source_compatibility' => ['minimum_source_version' => '0.0.0', 'maximum_source_version' => null],
    'runtime_compatibility' => [
        'minimum_php_version' => '8.2.0',
        'minimum_database_versions' => ['mysql' => '8.0.0'],
        'required_extensions' => ['json', 'pdo', 'pdo_mysql', 'session', 'filter', 'zip'],
    ],
    'inventory' => $inventory,
    'migration_declaration' => ['declares_core_migrations' => true, 'declaration_identity' => Copot\Core\CoreMigrationRegistry::IDENTITY],
    'target_requirements' => (new Copot\Core\PackageTargetRequirements([
        new Copot\Core\PackageTargetRequirement(
            Copot\Core\PackageTargetRequirement::SCHEMA,
            'webcore',
            'core-schema',
            Copot\Core\PackageTargetRequirement::EXACT_IDENTITY,
            $schemaIdentity
        ),
    ]))->toArray(),
];

if ($manifestVersion === PackageContract::V3_MANIFEST_CONTRACT_VERSION) {
    $targetEntries = $targetInventory->entries();
    usort($targetEntries, static fn (CanonicalTargetInventoryEntry $a, CanonicalTargetInventoryEntry $b): int => strcmp(
        $a->root() . "\0" . $a->ownership() . "\0" . $a->path(),
        $b->root() . "\0" . $b->ownership() . "\0" . $b->path()
    ));
    $manifestData += [
        'coverage' => 'full',
        'target_inventory' => [
            'serialization_version' => 1,
            'fingerprint' => $targetInventory->fingerprint(),
            'entries' => array_map(static fn (CanonicalTargetInventoryEntry $entry): array => $entry->toArray(), $targetEntries),
        ],
        'source_state_requirements' => ['allowed_inventory_fingerprints' => []],
        'removals' => [],
        'renames' => [],
    ];
}

try {
    $encodedManifest = json_encode(
        $manifestData,
        ($manifestVersion === PackageContract::V3_MANIFEST_CONTRACT_VERSION ? JSON_UNESCAPED_UNICODE : JSON_PRETTY_PRINT) | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    ) . PHP_EOL;
    if (file_put_contents($manifestPath, $encodedManifest) !== strlen($encodedManifest)) {
        fail('unable to write package metadata.');
    }
    $files[PackageManifestReader::PATH] = $manifestPath;
    ksort($files, SORT_STRING);

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
} finally {
    @unlink($manifestPath);
}

if ($manifestVersion === PackageContract::CURRENT_MANIFEST_CONTRACT_VERSION && $options['output'] === null) {
    echo 'Built dist/' . $packageName . ' with ' . count($files) . ' files.' . PHP_EOL;
} else {
    echo 'Built ' . $targetPath . ' with ' . count($files) . ' files.' . PHP_EOL;
}
if ($manifestVersion === PackageContract::V3_MANIFEST_CONTRACT_VERSION) {
    $manifestSha256 = PackageV3Identity::manifestDigest($encodedManifest);
    $payloadIntegrityIdentity = (new PackageContract(
        PackageContract::WEBCORE_PACKAGE_TYPE,
        PackageContract::V3_MANIFEST_CONTRACT_VERSION,
        $version,
        'copot-v' . $version,
        $sourceTreeIdentity !== '' ? $sourceTreeIdentity : null,
        new Copot\Core\PackageCompatibility('0.0.0'),
        new Copot\Core\PackageRuntimeCompatibility('8.2.0', ['mysql' => '8.0.0'], ['json', 'pdo', 'pdo_mysql', 'session', 'filter', 'zip']),
        array_map(static fn (array $entry): Copot\Core\PackageInventoryEntry => new Copot\Core\PackageInventoryEntry($entry['path'], $entry['byte_size'], $entry['sha256'], $entry['ownership']),
            $inventory),
        new Copot\Core\PackageMigrationDeclaration(true, Copot\Core\CoreMigrationRegistry::IDENTITY),
        Copot\Core\PackageTargetRequirements::fromArray($manifestData['target_requirements']),
        $targetInventory,
        'full', [], [], []
    ))->integrityIdentity();
    echo 'Manifest SHA-256: ' . $manifestSha256 . PHP_EOL;
    echo 'Delivered payload integrity identity: ' . $payloadIntegrityIdentity . PHP_EOL;
    echo 'Package identity: ' . PackageV3Identity::identity($manifestSha256, $payloadIntegrityIdentity) . PHP_EOL;
}
