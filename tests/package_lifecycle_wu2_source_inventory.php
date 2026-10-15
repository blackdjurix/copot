<?php

declare(strict_types=1);

$base = dirname(__DIR__);
chdir($base);
require $base . '/bootstrap/autoload.php';

$assert = static function (bool $condition, string $message): void { if (!$condition) { throw new RuntimeException($message); } };
$throws = static function (callable $callback, string $message) use ($assert): void {
    try { $callback(); } catch (Throwable) { return; }
    $assert(false, $message);
};
$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-wu2-source-' . bin2hex(random_bytes(6));
mkdir($root, 0700, true);
$remove = static function (string $path) use (&$remove): void {
    if (is_file($path) || is_link($path)) { @unlink($path); return; }
    if (!is_dir($path)) { return; }
    foreach (scandir($path) ?: [] as $entry) { if ($entry !== '.' && $entry !== '..') { $remove($path . DIRECTORY_SEPARATOR . $entry); } }
    @rmdir($path);
};

try {
    $inventory = new Copot\Core\CanonicalTargetInventory([
        new Copot\Core\CanonicalTargetInventoryEntry('APP_ROOT', 'package-owned', 'app/a.txt', 3, hash('sha256', 'abc')),
    ]);
    $store = new Copot\Core\SourceInventoryArtifactStore($root);
    $artifact = $store->publish($inventory, 'release-1', 3, 'tree-1');
    $reference = $artifact->reference();
    $assert($artifact->inventory()->fingerprint() === $inventory->fingerprint(), 'Published source inventory fingerprint changed.');
    $assert($store->read($reference)->inventory()->fingerprint() === $inventory->fingerprint(), 'Durable source inventory reread failed.');

    $stateStore = new Copot\Core\CommittedLifecycleStateStore($root);
    $state = new Copot\Core\CommittedLifecycleState('0.13.0', 'release-1', 'tree-1', 3, 'schema', 'migration', new DateTimeImmutable('now'), 'package', $reference);
    $stateStore->commitWithSourceInventory(new Copot\Core\InstallationState($root), $state, $store, $artifact);
    $linked = $stateStore->read();
    $assert($linked instanceof Copot\Core\CommittedLifecycleState && $linked->sourceInventoryReference()?->toArray() === $reference->toArray(), 'Committed state did not retain exact source reference.');

    $orphanPath = $store->pathFor($reference);
    $stateStore->remove();
    $assert(is_file($orphanPath), 'Orphan artifact was cleaned automatically.');
    $throws(static fn (): Copot\Core\SourceInventoryArtifact => $store->read(new Copot\Core\SourceInventoryReference(str_repeat('0', 64), $reference->byteSize(), $reference->fingerprint(), 'release-1', 3, 'tree-1')), 'Unlinked source inventory was accepted as authority.');

    $contents = file_get_contents($orphanPath);
    file_put_contents($orphanPath, str_replace('"fingerprint":"' . $reference->fingerprint() . '"', '"fingerprint":"' . str_repeat('a', 64) . '"', (string) $contents));
    $throws(static fn (): Copot\Core\SourceInventoryArtifact => $store->read($reference), 'Corrupt source inventory was accepted.');

    $durabilityFailure = new Copot\Core\SourceInventoryArtifactStore($root, static fn ($handle): bool => false);
    $throws(static fn (): Copot\Core\SourceInventoryArtifact => $durabilityFailure->publish(new Copot\Core\CanonicalTargetInventory([
        new Copot\Core\CanonicalTargetInventoryEntry('PUBLIC_ROOT', 'package-owned', 'index.php', 3, hash('sha256', 'php')),
    ]), 'release-2', 3), 'Missing positive durability was not fail-closed.');

    echo "WU2 source inventory assertions passed.\n";
} finally {
    $remove($root);
}
