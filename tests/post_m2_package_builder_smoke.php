<?php

declare(strict_types=1);

$basePath = dirname(__DIR__);
require $basePath . '/bootstrap/autoload.php';

use Copot\Core\Version;

$assertions = 0;
$failures = [];

$assert = static function (bool $condition, string $message) use (&$assertions, &$failures): void {
    $assertions++;

    if (!$condition) {
        $failures[] = $message;
    }
};

/**
 * @return list<string>
 */
function readZipEntries(string $zipPath): array
{
    $contents = file_get_contents($zipPath);

    if ($contents === false) {
        throw new RuntimeException('Unable to read ZIP file.');
    }

    $endSignature = "PK\x05\x06";
    $endOffset = strrpos($contents, $endSignature);

    if ($endOffset === false) {
        throw new RuntimeException('ZIP end of central directory not found.');
    }

    $end = unpack('vdisk/vstartDisk/ventriesDisk/ventries/Vsize/Voffset/vcommentLength', substr($contents, $endOffset + 4, 18));

    if (!is_array($end)) {
        throw new RuntimeException('ZIP end of central directory is invalid.');
    }

    $entries = [];
    $offset = (int) $end['offset'];
    $count = (int) $end['entries'];

    for ($index = 0; $index < $count; $index++) {
        if (substr($contents, $offset, 4) !== "PK\x01\x02") {
            throw new RuntimeException('ZIP central directory entry is invalid.');
        }

        $header = unpack(
            'vversionMade/vversionNeeded/vflags/vcompression/vtime/vdate/Vcrc/VcompressedSize/VuncompressedSize/vnameLength/vextraLength/vcommentLength/vdiskStart/vinternalAttributes/VexternalAttributes/VlocalOffset',
            substr($contents, $offset + 4, 42)
        );

        if (!is_array($header)) {
            throw new RuntimeException('ZIP central directory header is invalid.');
        }

        if ((int) $header['compression'] !== 0) {
            throw new RuntimeException('ZIP entry uses unsupported compression.');
        }

        $nameLength = (int) $header['nameLength'];
        $extraLength = (int) $header['extraLength'];
        $commentLength = (int) $header['commentLength'];
        $name = substr($contents, $offset + 46, $nameLength);

        if ($name === '') {
            throw new RuntimeException('ZIP entry name is empty.');
        }

        $entries[] = str_replace('\\', '/', $name);
        $offset += 46 + $nameLength + $extraLength + $commentLength;
    }

    sort($entries, SORT_STRING);

    return $entries;
}

$packagePath = $basePath . '/dist/copot-v' . Version::CURRENT . '.zip';

if (is_file($packagePath) && !unlink($packagePath)) {
    $failures[] = 'Unable to remove existing package before smoke test.';
}

$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($basePath . '/build/package.php') . ' 2>&1';
$output = [];
$exitCode = 0;
exec($command, $output, $exitCode);

$assert($exitCode === 0, 'Package builder must exit successfully. Output: ' . implode("\n", $output));
$assert(is_file($packagePath), 'Package ZIP must be created.');
$assert(basename($packagePath) === 'copot-v' . Version::CURRENT . '.zip', 'Package filename must use Version::CURRENT.');

$entries = [];

if (is_file($packagePath)) {
    try {
        $entries = readZipEntries($packagePath);
        $assert($entries !== [], 'Package ZIP must be valid and contain entries.');
    } catch (Throwable $throwable) {
        $assert(false, 'Package ZIP must be valid: ' . $throwable->getMessage());
    }
} else {
    $assert(false, 'Package ZIP must be valid and contain entries.');
}

$contains = static fn (string $entry): bool => in_array($entry, $entries, true);
$containsPrefix = static function (string $prefix) use (&$entries): bool {
    foreach ($entries as $entry) {
        if (str_starts_with($entry, $prefix)) {
            return true;
        }
    }

    return false;
};

foreach ([
    'app/Core/Version.php',
    'bootstrap/autoload.php',
    'bootstrap/app.php',
    'bootstrap/installer.php',
    'config/app.php',
    'database/schema.sql',
    'modules/content/module.json',
    'modules/taxonomy/module.json',
    'public/.htaccess',
    'public/index.php',
    'public/admin-assets/css/admin.css',
    'resources/views/installer/index.php',
    'routes/web.php',
    'storage/cache/.gitkeep',
    'storage/logs/.gitkeep',
    'themes/default/theme.json',
    '.env.example',
    'CHANGELOG.md',
    'INSTALL.md',
    'LICENSE',
    'README.md',
] as $requiredEntry) {
    $assert($contains($requiredEntry), 'Package must contain required file: ' . $requiredEntry);
}

foreach ([
    '.env',
    'AGENTS.md',
    'build/package.php',
    'build/package_manifest.php',
    'docs/15_distribution_and_packaging.md',
    'tests/post_m2_package_builder_smoke.php',
    'modules/example/module.json',
    'storage/installed.lock',
    'storage/.install.lock',
    'storage/logs/copot.log',
    'storage/site-assets/',
    'dist/copot-v' . Version::CURRENT . '.zip',
] as $forbiddenEntry) {
    $assert(!$contains($forbiddenEntry), 'Package must not contain forbidden file: ' . $forbiddenEntry);
}

foreach ([
    '.git/',
    '.github/',
    'build/',
    'dist/',
    'docs/',
    'modules/example/',
    'storage/site-assets/',
    'tests/',
] as $forbiddenPrefix) {
    $assert(!$containsPrefix($forbiddenPrefix), 'Package must not contain forbidden path prefix: ' . $forbiddenPrefix);
}

foreach ($entries as $entry) {
    $assert(!str_contains($entry, '\\'), 'Package entry paths must use forward slashes: ' . $entry);
    $assert(!str_starts_with($entry, '/'), 'Package entry paths must be relative: ' . $entry);
    $assert(!str_contains($entry, '/../') && !str_starts_with($entry, '../'), 'Package entry paths must not escape root: ' . $entry);
}

if ($failures !== []) {
    fwrite(STDERR, "Post-M2 package builder smoke tests failed:\n");

    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }

    exit(1);
}

echo 'Post-M2 package builder smoke tests passed (' . $assertions . ' assertions).' . PHP_EOL;
