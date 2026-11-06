<?php

declare(strict_types=1);

$base = dirname(__DIR__);
chdir($base);
require $base . '/bootstrap/autoload.php';

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-v3-fresh-inventory-' . bin2hex(random_bytes(6));
$app = $root . DIRECTORY_SEPARATOR . 'app-root';
$public = $root . DIRECTORY_SEPARATOR . 'public-root';
$storage = $app . DIRECTORY_SEPARATOR . 'storage';
$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$remove = static function (string $path) use (&$remove): void {
    if (is_file($path) || is_link($path)) { @unlink($path); return; }
    if (!is_dir($path)) { return; }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry !== '.' && $entry !== '..') { $remove($path . DIRECTORY_SEPARATOR . $entry); }
    }
    @rmdir($path);
};

mkdir($app . DIRECTORY_SEPARATOR . 'config', 0700, true);
mkdir($app . DIRECTORY_SEPARATOR . '.copot', 0700, true);
mkdir($storage . DIRECTORY_SEPARATOR . '.copot-lifecycle', 0700, true);
mkdir($public, 0700, true);
file_put_contents($app . DIRECTORY_SEPARATOR . '.copot' . DIRECTORY_SEPARATOR . 'package.json', 'metadata');
file_put_contents($app . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php', 'config');
file_put_contents($app . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'installed.lock', 'runtime');
file_put_contents($app . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . '.copot-lifecycle' . DIRECTORY_SEPARATOR . 'committed-state.json', 'runtime');
file_put_contents($app . DIRECTORY_SEPARATOR . 'app.php', 'app');
file_put_contents($public . DIRECTORY_SEPARATOR . 'index.php', 'public');
file_put_contents($public . DIRECTORY_SEPARATOR . '.htaccess', 'deployment-owned');

try {
    $inventory = (new Copot\Core\CanonicalSourceInventoryBuilder($app, $public))->capture();
    $keys = array_map(static fn (Copot\Core\CanonicalTargetInventoryEntry $entry): string => $entry->root() . ':' . $entry->path(), $inventory->entries());
    sort($keys, SORT_STRING);
    $assert(Copot\Core\PackageOwnership::isPackageMetadata('.copot/package.json'), 'Package manifest metadata was not recognized.');
    $assert(Copot\Core\PackageOwnership::classify('.copot/package.json') === Copot\Core\PackageOwnership::PACKAGE_OWNED, 'Package manifest transport ownership changed unexpectedly.');
    $assert(!in_array('APP_ROOT:.copot/package.json', $keys, true), 'Package manifest metadata leaked into committed source inventory.');
    $assert($keys === ['APP_ROOT:app.php', 'APP_ROOT:config/database.php', 'PUBLIC_ROOT:index.php'], 'Fresh inventory did not reflect actual package-owned roots: ' . implode(',', $keys));

    @unlink($app . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'installed.lock');
    @unlink($app . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . '.copot-lifecycle' . DIRECTORY_SEPARATOR . 'committed-state.json');
    $storageState = new Copot\Core\InstallationState($storage);
    $storageState->createMarker('0.13.0');
    $artifacts = new Copot\Core\SourceInventoryArtifactStore($storage);
    $artifact = $artifacts->publish($inventory, 'copot-v0.13.0', Copot\Core\PackageContract::V3_MANIFEST_CONTRACT_VERSION);
    $state = new Copot\Core\CommittedLifecycleState(
        '0.13.0',
        'copot-v0.13.0',
        null,
        Copot\Core\PackageContract::V3_MANIFEST_CONTRACT_VERSION,
        'canonical-current',
        Copot\Core\CoreMigrationStateIdentity::fromRecords([]),
        new DateTimeImmutable('now'),
        null,
        $artifact->reference()
    );
    (new Copot\Core\CommittedLifecycleStateStore($storage))->commitWithSourceInventory($storageState, $state, $artifacts, $artifact);
    $read = (new Copot\Core\CommittedLifecycleStateStore($storage))->read();
    $assert($read instanceof Copot\Core\CommittedLifecycleState
        && $read->manifestContractVersion() === Copot\Core\PackageContract::V3_MANIFEST_CONTRACT_VERSION
        && $read->sourceInventoryReference()?->fingerprint() === $inventory->fingerprint()
        && $artifacts->read($read->sourceInventoryReference())->inventory()->fingerprint() === $inventory->fingerprint(), 'Fresh V3 committed source inventory did not round-trip exactly.');

    echo 'Fresh V3 source inventory assertions passed: ' . $assertions . " assertions.\n";
} finally {
    $remove($root);
}
