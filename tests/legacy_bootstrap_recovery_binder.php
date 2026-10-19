<?php

use Copot\Core\LegacyBootstrapRecoveryBinder;
use Copot\Core\LegacyBootstrapRecoveryBindingResult;

$base = dirname(__DIR__);
chdir($base);
require $base . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};

$source = file_get_contents($base . '/app/Core/LegacyBootstrapRecoveryBinder.php');
$assert(is_string($source), 'Bootstrap recovery binder source could not be read.');
$assert(str_contains($source, 'NormalWebcoreRecoveryCaptureService'), 'Binder does not use the canonical recovery capture service.');
$assert(str_contains($source, 'readManifest'), 'Binder does not independently reread the recovery manifest.');
$assert(str_contains($source, 'readArtifact'), 'Binder does not independently reread the action-linkage artifact.');
$assert(str_contains($source, 'bindRecovery'), 'Binder does not bind the canonical lifecycle operation record.');
$assert(str_contains($source, '->resume('), 'Binder does not use the canonical retry/resume path.');
$assert(!str_contains($source, 'authorizeMutation'), 'Binder must not authorize mutation.');

$result = new LegacyBootstrapRecoveryBindingResult(
    LegacyBootstrapRecoveryBindingResult::BOUND,
    'handoff',
    'operation',
    hash('sha256', 'recovery'),
    hash('sha256', 'manifest'),
    hash('sha256', 'cross-reference'),
    hash('sha256', 'action-linkage'),
    []
);
$array = $result->toArray();
$assert($result->bound(), 'BOUND result was not typed as bound.');
$assert($result->mutationAuthorized() === false && $result->operationalReady() === false, 'Recovery binding granted prohibited authority.');
$assert($array['mutation_authorized'] === false && $array['operational_ready'] === false, 'Serialized recovery binding granted prohibited authority.');
$assert((new ReflectionMethod(LegacyBootstrapRecoveryBinder::class, 'bind'))->getReturnType()?->getName() === LegacyBootstrapRecoveryBindingResult::class, 'Binder return type is not explicit.');

echo "Bootstrap recovery binder focused assertions: {$assertions}" . PHP_EOL;
