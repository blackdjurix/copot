<?php

declare(strict_types=1);

use Copot\Core\EventDispatcher;
use Copot\Core\SynchronousEventDispatcher;

$basePath = dirname(__DIR__);

require $basePath . '/bootstrap/autoload.php';

$assertions = 0;

$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;

    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$dispatcher = new SynchronousEventDispatcher();

$assert($dispatcher instanceof EventDispatcher, 'SynchronousEventDispatcher must implement EventDispatcher.');

$singlePayload = new stdClass();
$singleReceived = null;
$dispatcher->listen('content.published', static function (object $event) use (&$singleReceived): void {
    $singleReceived = $event;
});
$dispatcher->dispatch('content.published', $singlePayload);
$assert($singleReceived === $singlePayload, 'A registered listener did not receive the dispatched payload object.');

$order = [];
$identityPayload = new stdClass();
$receivedPayloads = [];
$dispatcher->listen('core.application.ready', static function (object $event) use (&$order, &$receivedPayloads): void {
    $order[] = 'first';
    $receivedPayloads[] = $event;
});
$dispatcher->listen('core.application.ready', static function (object $event) use (&$order, &$receivedPayloads): void {
    $order[] = 'second';
    $receivedPayloads[] = $event;
});
$dispatcher->listen('core.application.ready', static function (object $event) use (&$order, &$receivedPayloads): void {
    $order[] = 'third';
    $receivedPayloads[] = $event;
});
$dispatcher->dispatch('core.application.ready', $identityPayload);
$assert($order === ['first', 'second', 'third'], 'Listeners did not execute in registration order.');
$assert(
    count($receivedPayloads) === 3
        && $receivedPayloads[0] === $identityPayload
        && $receivedPayloads[1] === $identityPayload
        && $receivedPayloads[2] === $identityPayload,
    'Listeners did not receive the same payload object identity.'
);

$duplicateCalls = 0;
$duplicateListener = static function (object $event) use (&$duplicateCalls): void {
    $duplicateCalls++;
};
$dispatcher->listen('taxonomy.assignments.updated', $duplicateListener);
$dispatcher->listen('taxonomy.assignments.updated', $duplicateListener);
$dispatcher->dispatch('taxonomy.assignments.updated', new stdClass());
$assert($duplicateCalls === 2, 'Duplicate explicit listener registrations did not execute independently.');

$dispatcher->dispatch('core.no.listeners', new stdClass());
$assert(true, 'Dispatch without listeners must complete successfully.');

$validEventNames = [
    'core.application.ready',
    'content.published',
    'taxonomy.assignments.updated',
];

foreach ($validEventNames as $eventName) {
    $dispatcher->dispatch($eventName, new stdClass());
    $assert(true, "Valid event name [{$eventName}] was rejected.");
}

$invalidEventNames = [
    '',
    ' ',
    "\t",
    '.content.published',
    'content.published.',
    'content..published',
    'Content.published',
    'content.*',
    'content/published',
    'content\\published',
    'content:published',
    'content published',
    ' content.published',
    'content.published ',
];

foreach ($invalidEventNames as $eventName) {
    $rejected = false;

    try {
        $dispatcher->dispatch($eventName, new stdClass());
    } catch (InvalidArgumentException) {
        $rejected = true;
    }

    $assert($rejected, 'Invalid event name was not rejected: ' . var_export($eventName, true));
}

$expectedException = new RuntimeException('Listener failure.');
$caughtException = null;
$laterListenerRan = false;
$dispatcher->listen('core.failure.test', static function (object $event) use ($expectedException): void {
    throw $expectedException;
});
$dispatcher->listen('core.failure.test', static function (object $event) use (&$laterListenerRan): void {
    $laterListenerRan = true;
});

try {
    $dispatcher->dispatch('core.failure.test', new stdClass());
} catch (RuntimeException $exception) {
    $caughtException = $exception;
}

$assert($caughtException === $expectedException, 'Listener exception did not propagate unchanged.');
$assert(!$laterListenerRan, 'A later listener ran after a listener exception.');

$firstDispatcher = new SynchronousEventDispatcher();
$secondDispatcher = new SynchronousEventDispatcher();
$isolatedCalls = 0;
$firstDispatcher->listen('core.request.ready', static function (object $event) use (&$isolatedCalls): void {
    $isolatedCalls++;
});
$secondDispatcher->dispatch('core.request.ready', new stdClass());
$assert($isolatedCalls === 0, 'Dispatcher instances shared listener state.');
$firstDispatcher->dispatch('core.request.ready', new stdClass());
$assert($isolatedCalls === 1, 'The dispatcher that owns a listener did not execute it.');

echo "Extensibility Batch 2 smoke tests passed ({$assertions} assertions)." . PHP_EOL;
