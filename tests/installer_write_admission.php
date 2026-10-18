<?php

use Copot\Core\BackupRecovery\ApplicationWriteProtectionEvidence;
use Copot\Core\BackupRecovery\DatabaseScope;
use Copot\Core\BackupRecovery\InstallationScopedMaintenanceGuard;
use Copot\Core\BackupRecovery\RecoveryIdentity;
use Copot\Core\InstallationMutex;

$base = dirname(__DIR__);
require $base . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) { throw new RuntimeException($message); }
};

$installer = (string) file_get_contents($base . '/bootstrap/installer.php');
$admissionOffset = strpos($installer, "->admit('http')");
$committerOffset = strpos($installer, 'new InstallerInstallationCommitter');
$assert($admissionOffset !== false, 'Installer does not request HTTP writer admission.');
$assert($committerOffset !== false && $admissionOffset < $committerOffset, 'Installer admission is not before installation mutation.');
$assert(strpos($installer, 'Installer writes are temporarily blocked') !== false, 'Installer guard rejection has no fail-closed response.');
$assert(strpos($installer, '$installerWriteLease?->release();') !== false, 'Installer admission lease has no release path.');

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-installer-admission-' . bin2hex(random_bytes(4));
mkdir($root . DIRECTORY_SEPARATOR . 'storage', 0770, true);
$guard = new InstallationScopedMaintenanceGuard(new InstallationMutex($root . DIRECTORY_SEPARATOR . 'storage'), $root . DIRECTORY_SEPARATOR . 'storage');
$identity = new RecoveryIdentity('installer-admission-test');
$scope = new DatabaseScope('installer-installation', 'installer_database', 'installer', ['installer_users']);
$now = time();
$evidence = new ApplicationWriteProtectionEvidence($scope, $identity, str_repeat('a', 64), str_repeat('b', 64), ['http', 'cli', 'scheduled', 'background'], true, true, $now, $now + 300);

$inFlight = $guard->admit('http');
$assert($inFlight !== null, 'HTTP installer writer was not admitted before maintenance.');
$assert(!$guard->enterScoped($identity, $evidence), 'Maintenance entered while installer writer was in flight.');
$inFlight->release();
$assert($guard->enterScoped($identity, $evidence), 'Maintenance did not enter after installer writer drained.');
$assert($guard->admit('http') === null, 'Installer writer was admitted during active maintenance.');
$assert($guard->leave($identity), 'Installer guard did not release after active rejection test.');

$after = $guard->admit('http');
$assert($after !== null, 'Installer writer remained blocked after guard release.');
$after->release();

$remove = static function (string $path) use (&$remove): void {
    if (!is_dir($path)) { @unlink($path); return; }
    foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) { $remove($path . DIRECTORY_SEPARATOR . $entry); }
    @rmdir($path);
};
$remove($root);
echo "Installer write admission assertions: {$assertions}\n";
