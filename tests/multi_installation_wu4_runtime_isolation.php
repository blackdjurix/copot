<?php

declare(strict_types=1);

use Copot\Core\Config;
use Copot\Core\DatabaseTableNames;
use Copot\Core\InstallationIdentity;
use Copot\Core\InstallationIdentityStore;
use Copot\Core\InstallationMutex;
use Copot\Core\InstallationRuntimePaths;
use Copot\Core\PackageApplyTemporaryRoot;
use Copot\Core\RuntimeParticipant;
use Copot\Core\RuntimeRegistry;
use Copot\Core\RuntimeTransitionCoordinator;
use Copot\Core\Session;

$basePath = dirname(__DIR__);
chdir($basePath);
require $basePath . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-wu4-' . bin2hex(random_bytes(6));
$storageA = $root . DIRECTORY_SEPARATOR . 'a';
$storageB = $root . DIRECTORY_SEPARATOR . 'b';
mkdir($storageA, 0700, true);
mkdir($storageB, 0700, true);

$identityA = (new InstallationIdentityStore($storageA))->getOrCreate();
$identityB = (new InstallationIdentityStore($storageB))->getOrCreate();
$assert($identityA->value() !== $identityB->value(), 'Independent installations received the same installation identity.');
$assert((new InstallationIdentityStore($storageA))->getOrCreate()->value() === $identityA->value(), 'Installation identity was not stable.');

$runtimeIdA = RuntimeRegistry::runtimeId();
$runtimeIdB = RuntimeRegistry::runtimeId();
$assert($runtimeIdA !== (string) getmypid(), 'Runtime identity was derived from PID.');
$registryA = new RuntimeRegistry($storageA, $identityA, new InstallationMutex($storageA));
$registryB = new RuntimeRegistry($storageB, $identityB, new InstallationMutex($storageB));
$registryA->register($runtimeIdA, 'web', ['http', 'worker'], '0.13.0', 'package-a', 'modules-a', 'deploy-a');
$registryA->register($runtimeIdB, 'worker', ['worker'], '0.13.0', 'package-a', 'modules-a', 'deploy-a');
$registryB->register($runtimeIdA, 'web', ['http'], '0.13.0', 'package-b', 'modules-b', 'deploy-b');
$assert(count($registryA->all()) === 2 && count($registryB->all()) === 1, 'Runtime Registry participants crossed installation ownership.');
$assert($registryA->heartbeat($runtimeIdA)->state() === RuntimeParticipant::ACTIVE, 'Runtime heartbeat did not activate the participant.');

$registryA->evaluateCompatibility($runtimeIdA, ['package_identity' => 'package-a', 'deployment_identity' => 'deploy-a', 'capabilities' => ['http']]);
$registryA->evaluateCompatibility($runtimeIdB, ['package_identity' => 'wrong-package']);
$assert($registryA->all()[1]->state() === RuntimeParticipant::INCOMPATIBLE, 'Incompatible runtime evidence was not recorded.');

$registryFile = (new ReflectionClass($registryA))->getProperty('path');
$registryFile->setAccessible(true);
$path = $registryFile->getValue($registryA);
$payload = json_decode((string) file_get_contents($path), true, 16, JSON_THROW_ON_ERROR);
$payload['runtimes'][$runtimeIdA]['last_seen_at'] = gmdate(DATE_ATOM, time() - 3600);
file_put_contents($path, json_encode($payload, JSON_THROW_ON_ERROR) . PHP_EOL);
$registryA->markStale(60);
$assert($registryA->all()[0]->state() === RuntimeParticipant::STALE, 'Stale runtime evidence was not retained.');
$blocked = false;
try { (new RuntimeTransitionCoordinator(new InstallationMutex($storageA), $registryA))->execute(static fn (): bool => true); } catch (RuntimeException) { $blocked = true; }
$assert($blocked, 'Unsafe shared-state transition ignored stale/incompatible runtime evidence.');
$registryA->detach($runtimeIdA);
$registryA->detach($runtimeIdB);
$assert($registryA->all()[0]->state() === RuntimeParticipant::DETACHED, 'Runtime detach semantics were not explicit.');
$runtimeIdC = RuntimeRegistry::runtimeId();
$registryA->register($runtimeIdC, 'web', ['http'], '0.13.0', 'package-a', 'modules-a', 'deploy-a');
$registryA->heartbeat($runtimeIdC);
$assert((new RuntimeTransitionCoordinator(new InstallationMutex($storageA), $registryA))->execute(static fn (): string => 'mutated', ['package_identity' => 'package-a', 'deployment_identity' => 'deploy-a', 'capabilities' => ['http']]) === 'mutated', 'Compatible transition could not revalidate at the mutex boundary.');

$config = new Config($basePath . '/config');
$sessionA = new Session($config, $identityA);
$sessionB = new Session($config, $identityB);
$defaultSession = new Session($config);
$assert($sessionA->cookieName() !== $sessionB->cookieName(), 'Independent installations shared a session cookie identity.');
$assert($sessionA->cookiePath() === $sessionB->cookiePath(), 'Cookie path lost the configured deployment scope.');
$assert($defaultSession->cookieName() === 'COPOTSESSID', 'Empty/default session behavior changed.');

$pathsA = InstallationRuntimePaths::forInstallation($identityA->value(), $root . DIRECTORY_SEPARATOR . 'runtime');
$pathsB = InstallationRuntimePaths::forInstallation($identityB->value(), $root . DIRECTORY_SEPARATOR . 'runtime');
$assert($pathsA->root() !== $pathsB->root() && $pathsA->packageStaging() !== $pathsB->packageStaging(), 'Runtime filesystem namespaces collided.');
$temporaryA = PackageApplyTemporaryRoot::forProject($basePath, $identityA->value());
$temporaryB = PackageApplyTemporaryRoot::forProject($basePath, $identityB->value());
$assert($temporaryA !== $temporaryB, 'Package-apply temporary roots were not installation-scoped.');
$lockA = (new InstallationMutex($storageA))->acquire();
$lockB = (new InstallationMutex($storageB))->acquire();
$assert($lockA !== null && $lockB !== null, 'Independent installation mutexes incorrectly blocked each other.');
$lockA?->release(); $lockB?->release();

$tables = new DatabaseTableNames('alpha');
$assert($tables->table('users') === 'alpha_users' && $tables->moduleTable('content') === 'alpha_content', 'WU2/WU3 namespace ownership was not preserved.');

echo "WU4 runtime isolation focused tests passed ({$assertions} assertions).\n";
