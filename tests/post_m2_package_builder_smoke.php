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

function removeDirectory(string $path): void
{
    if (!is_dir($path)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        if ($item->isDir() && !$item->isLink()) {
            @chmod($item->getPathname(), 0777);
            rmdir($item->getPathname());
            continue;
        }

        @chmod($item->getPathname(), 0666);
        unlink($item->getPathname());
    }

    @chmod($path, 0777);
    rmdir($path);
}

function removeTrackedWorkingFiles(string $targetPath): void
{
    $command = 'git -C ' . escapeshellarg($targetPath) . ' ls-files -z';
    $output = shell_exec($command);

    if (!is_string($output)) {
        throw new RuntimeException('Unable to list tracked files for isolated checkout rematerialization.');
    }

    foreach (explode("\0", $output) as $relativePath) {
        if ($relativePath === '') {
            continue;
        }

        $path = $targetPath . '/' . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

        if (is_file($path) || is_link($path)) {
            @chmod($path, 0666);
            unlink($path);
        }
    }
}

/**
 * @return array{tree: string, hash: string, output: list<string>, eol: list<string>}
 */
function buildFromIsolatedCheckout(string $sourcePath, string $targetPath, string $autocrlf): array
{
    $cloneCommand = 'git clone --quiet --no-hardlinks '
        . escapeshellarg($sourcePath)
        . ' '
        . escapeshellarg($targetPath)
        . ' 2>&1';
    $cloneOutput = [];
    $cloneExitCode = 0;
    exec($cloneCommand, $cloneOutput, $cloneExitCode);

    if ($cloneExitCode !== 0) {
        throw new RuntimeException('Unable to clone isolated checkout: ' . implode("\n", $cloneOutput));
    }

    $resetCommand = 'git -C '
        . escapeshellarg($targetPath)
        . ' -c core.autocrlf='
        . escapeshellarg($autocrlf)
        . ' reset --hard HEAD 2>&1';
    $resetOutput = [];
    $resetExitCode = 0;
    exec($resetCommand, $resetOutput, $resetExitCode);

    if ($resetExitCode !== 0) {
        throw new RuntimeException('Unable to rematerialize isolated checkout: ' . implode("\n", $resetOutput));
    }

    $attributesSource = $sourcePath . '/.gitattributes';
    $attributesTarget = $targetPath . '/.gitattributes';

    if (is_file($attributesSource) && !is_file($attributesTarget)) {
        if (!copy($attributesSource, $attributesTarget)) {
            throw new RuntimeException('Unable to copy working .gitattributes into isolated checkout.');
        }
    }

    if (is_file($attributesTarget)) {
        $attributesAddCommand = 'git -C '
            . escapeshellarg($targetPath)
            . ' add -- '
            . escapeshellarg('.gitattributes')
            . ' 2>&1';
        $attributesAddOutput = [];
        $attributesAddExitCode = 0;
        exec($attributesAddCommand, $attributesAddOutput, $attributesAddExitCode);

        if ($attributesAddExitCode !== 0) {
            throw new RuntimeException('Unable to prepare isolated checkout attributes: ' . implode("\n", $attributesAddOutput));
        }

        $attributesTrackedCommand = 'git -C '
            . escapeshellarg($targetPath)
            . ' cat-file -e '
            . escapeshellarg('HEAD:.gitattributes')
            . ' 2>&1';
        $attributesTrackedOutput = [];
        $attributesTrackedExitCode = 0;
        exec($attributesTrackedCommand, $attributesTrackedOutput, $attributesTrackedExitCode);

        if ($attributesTrackedExitCode !== 0) {
            $attributesCommitCommand = 'git -C '
                . escapeshellarg($targetPath)
                . ' -c user.name='
                . escapeshellarg('Copot Package Smoke')
                . ' -c user.email='
                . escapeshellarg('copot-package-smoke@example.invalid')
                . ' commit --quiet -m '
                . escapeshellarg('Add checkout attributes for reproducibility smoke')
                . ' -- '
                . escapeshellarg('.gitattributes')
                . ' 2>&1';
            $attributesCommitOutput = [];
            $attributesCommitExitCode = 0;
            exec($attributesCommitCommand, $attributesCommitOutput, $attributesCommitExitCode);

            if ($attributesCommitExitCode !== 0) {
                throw new RuntimeException('Unable to commit isolated checkout attributes: ' . implode("\n", $attributesCommitOutput));
            }
        }
    }

    removeTrackedWorkingFiles($targetPath);

    $attributeResetCommand = 'git -C '
        . escapeshellarg($targetPath)
        . ' -c core.autocrlf='
        . escapeshellarg($autocrlf)
        . ' reset --hard HEAD 2>&1';
    $attributeResetOutput = [];
    $attributeResetExitCode = 0;
    exec($attributeResetCommand, $attributeResetOutput, $attributeResetExitCode);

    if ($attributeResetExitCode !== 0) {
        throw new RuntimeException('Unable to rematerialize isolated checkout with attributes: ' . implode("\n", $attributeResetOutput));
    }

    $renormalizeCommand = 'git -C '
        . escapeshellarg($targetPath)
        . ' add --renormalize . 2>&1';
    $renormalizeOutput = [];
    $renormalizeExitCode = 0;
    exec($renormalizeCommand, $renormalizeOutput, $renormalizeExitCode);

    if ($renormalizeExitCode !== 0) {
        throw new RuntimeException('Unable to renormalize isolated checkout index: ' . implode("\n", $renormalizeOutput));
    }

    $checkoutCommand = 'git -C '
        . escapeshellarg($targetPath)
        . ' -c core.autocrlf='
        . escapeshellarg($autocrlf)
        . ' checkout --force -- . 2>&1';
    $checkoutOutput = [];
    $checkoutExitCode = 0;
    exec($checkoutCommand, $checkoutOutput, $checkoutExitCode);

    if ($checkoutExitCode !== 0) {
        throw new RuntimeException('Unable to checkout isolated files after renormalization: ' . implode("\n", $checkoutOutput));
    }

    $treeCommand = 'git -C ' . escapeshellarg($targetPath) . ' rev-parse ' . escapeshellarg('HEAD^{tree}') . ' 2>&1';
    $treeOutput = [];
    $treeExitCode = 0;
    exec($treeCommand, $treeOutput, $treeExitCode);

    if ($treeExitCode !== 0 || ($treeOutput[0] ?? '') === '') {
        throw new RuntimeException('Unable to resolve isolated checkout tree hash: ' . implode("\n", $treeOutput));
    }

    $eolCommand = 'git -C '
        . escapeshellarg($targetPath)
        . ' ls-files --eol .env.example README.md app/Core/Version.php 2>&1';
    $eolOutput = [];
    $eolExitCode = 0;
    exec($eolCommand, $eolOutput, $eolExitCode);

    if ($eolExitCode !== 0) {
        throw new RuntimeException('Unable to inspect isolated checkout EOL state: ' . implode("\n", $eolOutput));
    }

    $buildCommand = escapeshellarg(PHP_BINARY)
        . ' '
        . escapeshellarg($targetPath . '/build/package.php')
        . ' 2>&1';
    $buildOutput = [];
    $buildExitCode = 0;
    exec($buildCommand, $buildOutput, $buildExitCode);

    if ($buildExitCode !== 0) {
        throw new RuntimeException('Isolated package build failed: ' . implode("\n", $buildOutput));
    }

    $zipPath = $targetPath . '/dist/copot-v' . Version::CURRENT . '.zip';

    if (!is_file($zipPath)) {
        throw new RuntimeException('Isolated package build did not create expected ZIP.');
    }

    $hash = hash_file('sha256', $zipPath);

    if (!is_string($hash) || $hash === '') {
        throw new RuntimeException('Unable to hash isolated package ZIP.');
    }

    return [
        'tree' => trim($treeOutput[0]),
        'hash' => strtoupper($hash),
        'output' => $buildOutput,
        'eol' => $eolOutput,
    ];
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
    'modules/module-manager/module.json',
    'modules/module-manager/Services/ModuleInventoryBuilder.php',
    'modules/module-manager/Services/ModuleActionPolicy.php',
    'modules/settings-manager/module.json',
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

$sourceTreeOutput = [];
$sourceTreeExitCode = 0;
exec('git -C ' . escapeshellarg($basePath) . ' rev-parse ' . escapeshellarg('HEAD^{tree}') . ' 2>&1', $sourceTreeOutput, $sourceTreeExitCode);

$assert($sourceTreeExitCode === 0 && ($sourceTreeOutput[0] ?? '') !== '', 'Source Git tree hash must be resolvable.');
$sourceAttributesOutput = [];
$sourceAttributesExitCode = 0;
exec('git -C ' . escapeshellarg($basePath) . ' cat-file -e ' . escapeshellarg('HEAD:.gitattributes') . ' 2>&1', $sourceAttributesOutput, $sourceAttributesExitCode);

if ($sourceTreeExitCode === 0 && ($sourceTreeOutput[0] ?? '') !== '') {
    $tempRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-package-builder-smoke-' . bin2hex(random_bytes(8));
    $checkoutA = $tempRoot . DIRECTORY_SEPARATOR . 'checkout-a';
    $checkoutB = $tempRoot . DIRECTORY_SEPARATOR . 'checkout-b';

    try {
        if (!mkdir($tempRoot, 0775, true) && !is_dir($tempRoot)) {
            throw new RuntimeException('Unable to create isolated checkout root.');
        }

        $isolatedA = buildFromIsolatedCheckout($basePath, $checkoutA, 'true');
        $isolatedB = buildFromIsolatedCheckout($basePath, $checkoutB, 'false');
        $sourceTree = trim($sourceTreeOutput[0]);

        if ($sourceAttributesExitCode === 0) {
            $assert($isolatedA['tree'] === $sourceTree, 'Isolated checkout A must use the same Git tree as source.');
            $assert($isolatedB['tree'] === $sourceTree, 'Isolated checkout B must use the same Git tree as source.');
        }

        $assert($isolatedA['tree'] === $isolatedB['tree'], 'Isolated checkouts must use the same Git tree.');
        $assert($isolatedA['hash'] === $isolatedB['hash'], 'Isolated clean checkouts must produce identical package hashes.');

        foreach ([$isolatedA['eol'], $isolatedB['eol']] as $index => $eolLines) {
            foreach ($eolLines as $line) {
                $assert(str_contains($line, 'w/lf'), 'Isolated checkout ' . ($index + 1) . ' must materialize relevant text files as LF: ' . $line);
            }
        }
    } catch (Throwable $throwable) {
        $assert(false, 'Isolated checkout reproducibility check must pass: ' . $throwable->getMessage());
    } finally {
        removeDirectory($tempRoot);
    }
}

if ($failures !== []) {
    fwrite(STDERR, "Post-M2 package builder smoke tests failed:\n");

    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }

    exit(1);
}

echo 'Post-M2 package builder smoke tests passed (' . $assertions . ' assertions).' . PHP_EOL;
