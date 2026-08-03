<?php

declare(strict_types=1);

use Copot\Core\ArchiveEntryPath;
use Copot\Core\ArchiveLimits;
use Copot\Core\PackageInventoryEntry;
use Copot\Core\PackageInventoryVerifier;
use Copot\Core\PackageOwnership;
use Copot\Core\StagedArchiveExtractor;
use Copot\Core\ZipArchiveInspector;
use Copot\Core\StagingSession;
use Copot\Core\ZipIntakeService;

$basePath = dirname(__DIR__);
chdir($basePath);
require $basePath . '/bootstrap/autoload.php';

$assertions = 0;
$skips = [];
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;

    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$throws = static function (callable $callback, string $message) use (&$assertions): void {
    $assertions++;

    try {
        $callback();
    } catch (Throwable) {
        return;
    }

    throw new RuntimeException($message . ' did not throw.');
};
$temporaryPaths = [];
$temporaryDirectory = static function (string $label) use (&$temporaryPaths): string {
    $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-wu2-' . $label . '-' . bin2hex(random_bytes(6));

    if (!mkdir($path, 0700, true)) {
        throw new RuntimeException('Temporary WU2 directory could not be created.');
    }

    $temporaryPaths[] = $path;

    return $path;
};
$removeDirectory = static function (string $path) use (&$removeDirectory): void {
    if (is_link($path) || is_file($path)) {
        @unlink($path);

        return;
    }

    if (!is_dir($path)) {
        return;
    }

    foreach (new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS) as $entry) {
        $removeDirectory($entry->getPathname());
    }

    @rmdir($path);
};
$makeZip = static function (string $path, array $files = [], array $directories = [], ?callable $attributes = null): string {
    $zip = new ZipArchive();

    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Fixture ZIP could not be opened.');
    }

    foreach ($directories as $directory) {
        $zip->addEmptyDir($directory);
    }

    foreach ($files as $name => $contents) {
        if (!$zip->addFromString($name, $contents)) {
            throw new RuntimeException('Fixture ZIP entry could not be added.');
        }

        $zip->setCompressionName($name, ZipArchive::CM_STORE);

        if ($attributes !== null) {
            $attributes($zip, $name);
        }
    }

    if (!$zip->close()) {
        throw new RuntimeException('Fixture ZIP could not be closed.');
    }

    return $path;
};
$stagingChildren = static function (string $root): array {
    if (!is_dir($root)) {
        return [];
    }

    return array_values(array_map(
        static fn (SplFileInfo $entry): string => $entry->getFilename(),
        iterator_to_array(new FilesystemIterator($root, FilesystemIterator::SKIP_DOTS))
    ));
};

try {
    if (!class_exists(ZipArchive::class) || !extension_loaded('zip')) {
        throw new RuntimeException('WU2 test executor lacks ZipArchive/ext-zip.');
    }

    foreach (['foo\\bar.txt', './foo//bar.txt', 'foo/./bar.txt'] as $raw) {
        $assert(ArchiveEntryPath::normalize($raw) === 'foo/bar.txt', 'Archive path normalization failed.');
    }

    foreach (['../x', 'a/../x', '/x', '\\x', 'C:/x', 'C:x', '//server/share/x', 'x/'] as $raw) {
        if ($raw === 'x/') {
            $assert(ArchiveEntryPath::normalize($raw) === 'x', 'Trailing separator normalization failed.');
            continue;
        }

        $throws(static fn () => ArchiveEntryPath::normalize($raw), 'Unsafe archive path was accepted: ' . $raw);
    }

    foreach (["bad\0name", "bad\nname", 'café.txt', 'bad:name.txt'] as $raw) {
        $throws(static fn () => ArchiveEntryPath::normalize($raw), 'Invalid archive path was accepted.');
    }

    $fixtureRoot = $temporaryDirectory('fixtures');
    $stagingRoot = $temporaryDirectory('staging');
    $source = $fixtureRoot . DIRECTORY_SEPARATOR . 'valid.zip';
    $makeZip($source, [
        'app/Core/Version.php' => "<?php return '0.12.0';\n",
        'storage/cache/.gitkeep' => '',
        'public/index.php' => '<?php echo "ok";',
    ], ['app', 'app/Core', 'storage', 'storage/cache', 'public']);

    $service = new ZipIntakeService($basePath, $stagingRoot);
    $payload = $service->intake($source);
    $assert(count($payload->files()) === 3, 'Valid current-style archive intake did not stage all regular files.');
    $assert(is_file($payload->payloadPath() . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'Version.php'), 'Staged payload file is missing.');
    $assert(is_file($payload->archivePath()), 'Immutable staged source copy is missing.');
    $assert(hash_file('sha256', $payload->archivePath()) === $payload->archiveSha256(), 'Staged archive SHA-256 identity is incorrect.');
    $assert((int) filesize($payload->payloadPath() . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'index.php') === 16, 'Staged file size identity is incorrect.');
    unlink($source);
    $assert(is_file($payload->payloadPath() . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'index.php'), 'Staged payload depended on the source ZIP after intake.');

    $payload->cleanup();
    $assert($stagingChildren($stagingRoot) === [], 'Successful staging cleanup left a session behind.');

    $badCases = [
        'duplicate' => ['a.txt' => 'a', 'a/../b.txt' => 'b'],
        'case' => ['A.txt' => 'a', 'a.txt' => 'b'],
        'file-parent' => ['a' => 'a', 'a/b.txt' => 'b'],
        'ownership' => ['.env' => 'secret'],
        'non-ascii' => ['café.txt' => 'x'],
    ];

    foreach ($badCases as $label => $files) {
        $path = $fixtureRoot . DIRECTORY_SEPARATOR . $label . '.zip';
        $makeZip($path, $files);
        $throws(static fn () => $service->intake($path), 'Unsafe archive case was accepted: ' . $label);
        $assert($stagingChildren($stagingRoot) === [], 'Failed intake left staging behind: ' . $label);
    }

    $conflictPath = $fixtureRoot . DIRECTORY_SEPARATOR . 'conflict.zip';
    $makeZip($conflictPath, ['a/' => 'not-a-directory']);
    $throws(static fn () => $service->intake($conflictPath), 'File/directory conflict was accepted.');

    $symlinkPath = $fixtureRoot . DIRECTORY_SEPARATOR . 'symlink.zip';
    $makeZip($symlinkPath, ['link' => 'outside'], [], static function (ZipArchive $zip, string $name): void {
        $zip->setExternalAttributesName($name, ZipArchive::OPSYS_UNIX, 0120777 << 16);
    });
    $throws(static fn () => $service->intake($symlinkPath), 'Symlink archive entry was accepted.');

    $specialPath = $fixtureRoot . DIRECTORY_SEPARATOR . 'special.zip';
    $makeZip($specialPath, ['fifo' => 'special'], [], static function (ZipArchive $zip, string $name): void {
        $zip->setExternalAttributesName($name, ZipArchive::OPSYS_UNIX, 0010666 << 16);
    });
    $throws(static fn () => $service->intake($specialPath), 'Special-file archive entry was accepted.');

    $encryptedPath = $fixtureRoot . DIRECTORY_SEPARATOR . 'encrypted.zip';
    $encrypted = new ZipArchive();
    $encryptedSupported = method_exists($encrypted, 'setEncryptionName') && defined('ZipArchive::EM_TRAD_PKWARE');

    if ($encryptedSupported && $encrypted->open($encryptedPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
        $encrypted->setPassword('wu2-test-password');
        $encrypted->addFromString('secret.txt', 'secret');
        $encryptedSupported = $encrypted->setEncryptionName('secret.txt', ZipArchive::EM_TRAD_PKWARE);
        $encrypted->close();
    }

    if ($encryptedSupported) {
        $throws(static fn () => $service->intake($encryptedPath), 'Encrypted archive entry was accepted.');
    } else {
        $skips[] = 'Encrypted ZIP fixture generation is unavailable in this ZipArchive build.';
    }

    $skips[] = 'ZipArchive capability-failure branch requires a separate executor with ext-zip disabled.';

    $smallArchiveLimits = static fn (int $archive = 67108864, int $entries = 5000, int $total = 268435456, int $file = 67108864, int $ratio = 100, int $path = 240, int $nesting = 32): ArchiveLimits => new ArchiveLimits($archive, $entries, $total, $file, $ratio, $path, $nesting);
    $limitedService = static fn (ArchiveLimits $limits) => new ZipIntakeService($basePath, $stagingRoot, $limits);

    $limitPath = $fixtureRoot . DIRECTORY_SEPARATOR . 'limits.zip';
    $makeZip($limitPath, ['large.txt' => str_repeat('x', 32)]);
    $throws(static fn () => $limitedService($smallArchiveLimits(10))->intake($limitPath), 'Archive byte limit was not enforced.');

    $entriesPath = $fixtureRoot . DIRECTORY_SEPARATOR . 'entries.zip';
    $makeZip($entriesPath, ['a.txt' => 'a', 'b.txt' => 'b']);
    $throws(static fn () => $limitedService($smallArchiveLimits(67108864, 1))->intake($entriesPath), 'Entry-count limit was not enforced.');

    $totalPath = $fixtureRoot . DIRECTORY_SEPARATOR . 'total.zip';
    $makeZip($totalPath, ['a.txt' => str_repeat('a', 8), 'b.txt' => str_repeat('b', 8)]);
    $throws(static fn () => $limitedService($smallArchiveLimits(67108864, 5000, 10))->intake($totalPath), 'Total extracted limit was not enforced.');
    $throws(static fn () => $limitedService($smallArchiveLimits(67108864, 5000, 256, 5))->intake($totalPath), 'Individual file limit was not enforced.');

    $ratioPath = $fixtureRoot . DIRECTORY_SEPARATOR . 'ratio.zip';
    $ratioZip = new ZipArchive();
    $ratioZip->open($ratioPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $ratioZip->addFromString('ratio.txt', str_repeat('a', 1000));
    $ratioZip->setCompressionName('ratio.txt', ZipArchive::CM_DEFLATE, 9);
    $ratioZip->close();
    $throws(static fn () => $limitedService($smallArchiveLimits(67108864, 5000, 256, 67108864, 2))->intake($ratioPath), 'Per-entry compression ratio limit was not enforced.');
    $throws(static fn () => $limitedService($smallArchiveLimits(67108864, 5000, 256, 67108864, 2))->intake($ratioPath), 'Aggregate compression ratio limit was not enforced.');

    $longPath = $fixtureRoot . DIRECTORY_SEPARATOR . 'long.zip';
    $makeZip($longPath, [str_repeat('a', 241) . '.txt' => 'x']);
    $throws(static fn () => $service->intake($longPath), 'Canonical path length limit was not enforced.');

    $deepPath = $fixtureRoot . DIRECTORY_SEPARATOR . 'deep.zip';
    $makeZip($deepPath, [implode('/', array_fill(0, 33, 'x')) . '.txt' => 'x']);
    $throws(static fn () => $service->intake($deepPath), 'Nesting limit was not enforced.');

    $overwritePath = $fixtureRoot . DIRECTORY_SEPARATOR . 'overwrite.zip';
    $makeZip($overwritePath, ['a.txt' => 'new']);
    $overwriteSession = StagingSession::create($basePath, $stagingRoot);
    mkdir($overwriteSession->payloadPath(), 0700);
    file_put_contents($overwriteSession->payloadPath() . DIRECTORY_SEPARATOR . 'a.txt', 'old');
    $overwriteArchive = new ZipArchive();
    $overwriteArchive->open($overwritePath, ZipArchive::RDONLY);
    $overwriteEntries = (new ZipArchiveInspector())->inspect($overwriteArchive, new ArchiveLimits());
    $throws(static fn () => (new StagedArchiveExtractor())->extract($overwriteArchive, $overwriteEntries, $overwriteSession, str_repeat('0', 64), new ArchiveLimits()), 'Existing destination was overwritten.');
    $overwriteArchive->close();
    $overwriteSession->cleanup();

    $inventoryPath = $fixtureRoot . DIRECTORY_SEPARATOR . 'inventory.zip';
    $makeZip($inventoryPath, ['app/a.txt' => 'alpha', 'app/b.txt' => 'beta']);
    $inventoryPayload = $service->intake($inventoryPath);
    $filesByPath = [];
    foreach ($inventoryPayload->files() as $file) {
        $filesByPath[$file->path()] = $file;
    }
    $verifier = new PackageInventoryVerifier();
    $verifier->verify($inventoryPayload, [
        new PackageInventoryEntry('app/a.txt', 5, $filesByPath['app/a.txt']->sha256()),
        new PackageInventoryEntry('app/b.txt', 4, $filesByPath['app/b.txt']->sha256()),
    ]);
    $assertions++;
    $throws(static fn () => $verifier->verify($inventoryPayload, [new PackageInventoryEntry('app/a.txt', 5, $filesByPath['app/a.txt']->sha256())]), 'Unexpected staged inventory file was not rejected.');
    $missingPath = $fixtureRoot . DIRECTORY_SEPARATOR . 'missing.zip';
    $makeZip($missingPath, ['app/a.txt' => 'alpha']);
    $missingPayload = $service->intake($missingPath);
    $throws(static fn () => $verifier->verify($missingPayload, [new PackageInventoryEntry('app/a.txt', 5, $filesByPath['app/a.txt']->sha256()), new PackageInventoryEntry('app/missing.txt', 1, str_repeat('0', 64))]), 'Declared-but-missing inventory file was not rejected.');
    $missingPayload->cleanup();
    $throws(static fn () => $verifier->verify($inventoryPayload, [new PackageInventoryEntry('app/a.txt', 4, $filesByPath['app/a.txt']->sha256()), new PackageInventoryEntry('app/b.txt', 4, $filesByPath['app/b.txt']->sha256())]), 'Size mismatch was not rejected.');
    $throws(static fn () => $verifier->verify($inventoryPayload, [new PackageInventoryEntry('app/a.txt', 5, str_repeat('0', 64)), new PackageInventoryEntry('app/b.txt', 4, $filesByPath['app/b.txt']->sha256())]), 'Hash mismatch was not rejected.');
    $inventoryPayload->cleanup();

    $cleanupSession = StagingSession::create($basePath, $stagingRoot);
    $outside = $temporaryDirectory('outside');
    file_put_contents($outside . DIRECTORY_SEPARATOR . 'keep.txt', 'keep');
    $link = $cleanupSession->path() . DIRECTORY_SEPARATOR . 'outside-link';

    if (@symlink($outside, $link)) {
        $cleanupSession->cleanup();
        $assert(is_file($outside . DIRECTORY_SEPARATOR . 'keep.txt'), 'Symlink-safe cleanup followed a symlink.');
    } else {
        $skips[] = 'Symlink-safe cleanup fixture unavailable on this Windows executor.';
        $cleanupSession->cleanup();
    }

    $stale = StagingSession::create($basePath, $stagingRoot);
    touch($stale->path(), time() - 900);
    $stalePath = $stale->path();
    $assert(StagingSession::reconcileStale($stagingRoot, 60) === 1 && !file_exists($stalePath), 'Stale staging reconciliation failed.');

    $zipIntakeSource = new ZipIntakeService($basePath, $stagingRoot);
    $assert(method_exists($zipIntakeSource, 'intake'), 'WU2 intake service boundary is missing.');
    $assert(PackageOwnership::classify('storage/cache/.gitkeep') === PackageOwnership::PACKAGE_OWNED, 'WU1 package ownership contract changed unexpectedly.');

    echo 'WU2 package lifecycle assertions: ' . $assertions . PHP_EOL;
    foreach ($skips as $skip) {
        echo 'SKIP: ' . $skip . PHP_EOL;
    }
} finally {
    foreach (array_reverse($temporaryPaths) as $path) {
        $removeDirectory($path);
    }
}
