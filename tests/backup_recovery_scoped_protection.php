<?php

use Copot\Core\BackupRecovery\ApplicationWriteProtectionEvidence;
use Copot\Core\BackupRecovery\DatabaseScope;
use Copot\Core\BackupRecovery\InstallationScopedMaintenanceGuard;
use Copot\Core\BackupRecovery\RecoveryIdentity;
use Copot\Core\BackupRecovery\ScopedDatabaseConsistencyEvidence;
use Copot\Core\InstallationMutex;

$base = dirname(__DIR__);
require $base . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) { throw new RuntimeException($message); }
};
$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-scoped-protection-' . bin2hex(random_bytes(4));
$other = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-scoped-protection-other-' . bin2hex(random_bytes(4));
mkdir($root . DIRECTORY_SEPARATOR . 'storage', 0770, true);
mkdir($other . DIRECTORY_SEPARATOR . 'storage', 0770, true);

$scope = new DatabaseScope('installation-a', 'shared_db', 'site_a', ['site_a_users', 'site_a_core_migration_history']);
$identity = new RecoveryIdentity('scoped-protection-a');
$now = time();
$application = new ApplicationWriteProtectionEvidence($scope, $identity, str_repeat('a', 64), str_repeat('b', 64), ['http', 'cli', 'scheduled', 'background'], true, true, $now, $now + 300);
$database = new ScopedDatabaseConsistencyEvidence($scope, $identity, str_repeat('c', 64), str_repeat('d', 64), $now, $now + 300);
$guard = new InstallationScopedMaintenanceGuard(new InstallationMutex($root . DIRECTORY_SEPARATOR . 'storage'), $root . DIRECTORY_SEPARATOR . 'storage');

$writer = $guard->admit('http');
$assert($writer !== null, 'HTTP writer admission was not granted while unprotected.');
$assert(!$guard->enterScoped($identity, $application), 'In-flight HTTP writer was not drained fail-closed.');
$writer->release();
$assert($guard->enterScoped($identity, $application), 'Scoped maintenance did not enter after drain.');
$assert($guard->scopedActive($identity, $application), 'Scoped maintenance state was not durable/active.');
$assert($guard->admit('http') === null, 'HTTP writer was admitted during scoped maintenance.');
$assert($guard->admit('unknown-external') === null, 'Unknown external writer was admitted.');
$assert($guard->leave($identity), 'Scoped maintenance did not release.');
$after = $guard->admit('http');
$assert($after !== null, 'HTTP writer remained blocked after guard release.');
$after->release();

$otherGuard = new InstallationScopedMaintenanceGuard(new InstallationMutex($other . DIRECTORY_SEPARATOR . 'storage'), $other . DIRECTORY_SEPARATOR . 'storage');
$otherIdentity = new RecoveryIdentity('scoped-protection-b');
$otherScope = new DatabaseScope('installation-b', 'shared_db', 'site_b', ['site_b_users', 'site_b_core_migration_history']);
$otherApplication = new ApplicationWriteProtectionEvidence($otherScope, $otherIdentity, str_repeat('e', 64), str_repeat('f', 64), ['http', 'cli', 'scheduled', 'background'], true, true, $now, $now + 300);
$assert($otherGuard->enterScoped($otherIdentity, $otherApplication), 'Independent installation was blocked by another installation guard.');
$assert($otherGuard->leave($otherIdentity), 'Independent installation guard did not release.');

try { new ApplicationWriteProtectionEvidence($scope, $identity, str_repeat('e', 64), str_repeat('f', 64), ['http'], true, true, $now, $now + 300); $assert(false, 'Partial writer evidence was accepted.'); } catch (Throwable) { $assert(true, 'Partial writer evidence failed closed.'); }
$expired = new ApplicationWriteProtectionEvidence($scope, $identity, str_repeat('1', 64), str_repeat('2', 64), ['http', 'cli', 'scheduled', 'background'], true, true, $now - 10, $now - 1);
$assert(!$expired->isValidAt($now), 'Expired writer evidence remained valid.');
$mismatchedIdentity = new RecoveryIdentity('scoped-protection-mismatch');
$assert(!$guard->enterScoped($mismatchedIdentity, $application), 'Evidence for another operation was accepted.');
$assert(!str_contains((string) file_get_contents($base . '/app/Core/BackupRecovery/ApplicationWriteProtectionEvidence.php'), 'externalWritersExcluded'), 'Application evidence still requires universal external-writer exclusion.');
$expiredDatabase = new ScopedDatabaseConsistencyEvidence($scope, $identity, str_repeat('3', 64), str_repeat('4', 64), $now - 10, $now - 1);
$assert(!$expiredDatabase->isValidAt($now), 'Expired database consistency evidence remained valid.');
$assert($database->scope()->identity() === $scope->identity(), 'Database evidence lost namespace scope linkage.');

$remove = static function (string $path) use (&$remove): void { if (!is_dir($path)) { @unlink($path); return; } foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) { $remove($path . DIRECTORY_SEPARATOR . $entry); } @rmdir($path); };
$remove($root); $remove($other);
echo "Scoped protection assertions: {$assertions}\n";
