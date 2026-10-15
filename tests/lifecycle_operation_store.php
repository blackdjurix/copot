<?php

declare(strict_types=1);

use Copot\Core\LifecycleOperationRecord;
use Copot\Core\LifecycleOperationStore;

require dirname(__DIR__) . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $assertions++;
};

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-operation-store-' . bin2hex(random_bytes(6));
mkdir($root . DIRECTORY_SEPARATOR . 'storage', 0700, true);

$now = gmdate(DATE_ATOM);
$record = static fn (string $id, string $phase, int $cursor, string $reason = ''): LifecycleOperationRecord => new LifecycleOperationRecord(
    $id,
    'database_update',
    '0.13.0',
    'copot-v0.13.0',
    hash('sha256', 'archive'),
    $root . DIRECTORY_SEPARATOR . 'staging',
    hash('sha256', 'payload'),
    hash('sha256', 'plan'),
    $phase,
    $cursor,
    $cursor > 0 ? 'app/Core/Example.php' : null,
    hash('sha256', 'migration'),
    null,
    $now,
    $now,
    $reason
);

try {
    $store = new LifecycleOperationStore($root . DIRECTORY_SEPARATOR . 'storage');
    $initial = $record('operation-one', LifecycleOperationRecord::PREPARING, 0);
    $store->create($initial);
    $assert($store->read()?->operationId() === 'operation-one', 'Created operation record was not readable.');

    $advanced = $initial->advance(LifecycleOperationRecord::APPLYING, 1, 'app/Core/Example.php');
    $store->save($advanced);
    $read = $store->read();
    $assert($read?->operationId() === 'operation-one', 'Save changed operation identity.');
    $assert($read?->phase() === LifecycleOperationRecord::APPLYING, 'Saved operation phase was not persisted.');
    $assert($read?->fileCursor() === 1, 'Saved operation cursor was not persisted.');

    $other = $record('operation-two', LifecycleOperationRecord::PREPARING, 0);
    try {
        $store->save($other);
        $assert(false, 'Save accepted a different operation identity.');
    } catch (RuntimeException $exception) {
        $assert(str_contains($exception->getMessage(), 'identity'), 'Wrong-identity save returned the wrong failure.');
    }
    $assert($store->read()?->operationId() === 'operation-one', 'Failed replacement did not preserve the valid record.');

    $terminal = $read->advance(LifecycleOperationRecord::COMPLETED, 1, 'app/Core/Example.php');
    $store->save($terminal);
    $store->clear($terminal);
    $assert($store->read() === null, 'Completed operation record was not cleared.');

    echo "lifecycle_operation_store: {$assertions} assertions passed\n";
} finally {
    if (is_dir($root . DIRECTORY_SEPARATOR . 'storage')) {
        foreach (glob($root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
            if (is_file($path) || is_link($path)) {
                @unlink($path);
            }
        }
        @rmdir($root . DIRECTORY_SEPARATOR . 'storage');
    }
    @rmdir($root);
}
