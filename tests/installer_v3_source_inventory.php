<?php

declare(strict_types=1);

$base = dirname(__DIR__);
chdir($base);
require $base . '/bootstrap/autoload.php';

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-v3-fresh-inventory-' . bin2hex(random_bytes(6));
$app = $root . DIRECTORY_SEPARATOR . 'app-root';
$public = $root . DIRECTORY_SEPARATOR . 'public-root';
$storage = $app . DIRECTORY_SEPARATOR . 'storage';
$remove = static function (string $path) use (&$remove): void {
    if (is_file($path) || is_link($path)) { @unlink($path); return; }
    if (!is_dir($path)) { return; }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry !== '.' && $entry !== '..') { $remove($path . DIRECTORY_SEPARATOR . $entry); }
    }
    @rmdir($path);
};

mkdir($app . DIRECTORY_SEPARATOR . 'config', 0700, true);
mkdir($storage . DIRECTORY_SEPARATOR . '.copot-lifecycle', 0700, true);
mkdir($public, 0700, true);
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
    if ($keys !== ['APP_ROOT:app.php', 'APP_ROOT:config/database.php', 'PUBLIC_ROOT:index.php']) {
        throw new RuntimeException('Fresh inventory did not reflect actual package-owned roots: ' . implode(',', $keys));
    }

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
    if (!$read instanceof Copot\Core\CommittedLifecycleState
        || $read->manifestContractVersion() !== Copot\Core\PackageContract::V3_MANIFEST_CONTRACT_VERSION
        || $read->sourceInventoryReference()?->fingerprint() !== $inventory->fingerprint()
        || $artifacts->read($read->sourceInventoryReference())->inventory()->fingerprint() !== $inventory->fingerprint()) {
        throw new RuntimeException('Fresh V3 committed source inventory did not round-trip exactly.');
    }

    echo "Fresh V3 source inventory assertions passed.\n";
} finally {
    $remove($root);
}
