<?php

use Copot\Core\BackupRecovery\RecoveryDomain;
use Copot\Core\BackupRecovery\RecoveryDomainDefinition;
use Copot\Core\BackupRecovery\RecoveryDomainIdentity;
use Copot\Core\BackupRecovery\RecoveryDomainRegistry;
use Copot\Core\BackupRecovery\RecoveryIdentity;
use Copot\Core\BackupRecovery\RecoveryInvariantException;
use Copot\Core\BackupRecovery\RecoveryManifest;

$basePath = dirname(__DIR__);
chdir($basePath);
require $basePath . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$throws = static function (callable $callback, string $message) use ($assert): void {
    try {
        $callback();
        $assert(false, $message);
    } catch (RecoveryInvariantException) {
        $assert(true, $message);
    }
};
$domain = static function (string $identifier, string $ownership, string $scope): RecoveryDomain {
    return new class(new RecoveryDomainDefinition($identifier, $ownership, $scope)) implements RecoveryDomain {
        public function __construct(private RecoveryDomainDefinition $definition) {}
        public function definition(): RecoveryDomainDefinition { return $this->definition; }
    };
};
$hash = static fn (string $value): string => hash('sha256', $value);

$identity = new RecoveryIdentity('recovery-1');
$assert($identity->value() === 'recovery-1', 'Valid recovery identity was not retained.');
$assert($identity->equals(new RecoveryIdentity('recovery-1')), 'Equal recovery identities did not compare equal.');
$throws(static fn (): RecoveryIdentity => new RecoveryIdentity(''), 'Empty recovery identity was accepted.');
$throws(static fn (): RecoveryIdentity => new RecoveryIdentity(" recovery-1"), 'Whitespace-padded recovery identity was accepted.');
$throws(static fn (): RecoveryIdentity => new RecoveryIdentity("recovery\n1"), 'Control-character recovery identity was accepted.');

$databaseDefinition = new RecoveryDomainDefinition('database', 'database.webcore', 'configured-webcore-database');
$assert($databaseDefinition->identity() === (new RecoveryDomainDefinition('database', 'database.webcore', 'configured-webcore-database'))->identity(), 'Domain definition identity was not deterministic.');
$throws(static fn (): RecoveryDomainDefinition => new RecoveryDomainDefinition('Database', 'database.webcore', 'scope'), 'Invalid domain identifier was accepted.');
$throws(static fn (): RecoveryDomainDefinition => new RecoveryDomainDefinition('database', 'database.webcore', ''), 'Empty domain scope was accepted.');

$databaseIdentity = new RecoveryDomainIdentity('database', 'database.webcore', 'configured-webcore-database', $hash('database-artifact'));
$filesystemIdentity = new RecoveryDomainIdentity('filesystem.package-owned', 'filesystem.package-owned', 'reconciliation-apply-plan', $hash('filesystem-artifact'));
$lifecycleIdentity = new RecoveryDomainIdentity('lifecycle.committed', 'filesystem.lifecycle.committed', 'committed-state', $hash('lifecycle-artifact'));
$markerIdentity = new RecoveryDomainIdentity('lifecycle.installed-lock', 'filesystem.lifecycle.installed-lock', 'installed-lock', $hash('marker-artifact'));
$assert($databaseIdentity->identity() === (new RecoveryDomainIdentity('database', 'database.webcore', 'configured-webcore-database', $hash('database-artifact')))->identity(), 'Domain artifact identity was not deterministic.');
$throws(static fn (): RecoveryDomainIdentity => new RecoveryDomainIdentity('database', 'database.webcore', 'scope', 'not-a-hash'), 'Malformed domain artifact identity was accepted.');

$manifest = new RecoveryManifest(
    $identity,
    'operation-1',
    'copot-webcore',
    'release-1',
    $hash('archive'),
    $hash('apply-plan'),
    [$markerIdentity, $databaseIdentity, $filesystemIdentity, $lifecycleIdentity],
    'pre-lifecycle-1',
    'pre-ledger-1'
);
$assert(count($manifest->domainIdentities()) === 4, 'Manifest did not retain all domain identities.');
$assert(array_map(static fn (RecoveryDomainIdentity $domain): string => $domain->identifier(), $manifest->domainIdentities()) === [
    'database', 'filesystem.package-owned', 'lifecycle.committed', 'lifecycle.installed-lock',
], 'Manifest domain identities were not deterministically ordered.');
$assert($manifest->identity() === (new RecoveryManifest(
    new RecoveryIdentity('recovery-1'), 'operation-1', 'copot-webcore', 'release-1',
    $hash('archive'), $hash('apply-plan'), [$databaseIdentity, $filesystemIdentity, $lifecycleIdentity, $markerIdentity],
    'pre-lifecycle-1', 'pre-ledger-1'
))->identity(), 'Manifest identity was not deterministic across input order.');
$assert($manifest->recoveryIdentity()->value() === 'recovery-1', 'Manifest recovery identity was not bound.');
$assert($manifest->preOperationMigrationLedgerIdentity() === 'pre-ledger-1', 'Manifest migration identity was not bound.');
$throws(static fn (): RecoveryManifest => new RecoveryManifest(
    $identity, 'operation-1', 'copot-webcore', 'release-1', $hash('archive'), $hash('apply-plan'), [],
    'pre-lifecycle-1', 'pre-ledger-1'
), 'Manifest without domain identities was accepted.');
$throws(static fn (): RecoveryManifest => new RecoveryManifest(
    $identity, 'operation-1', 'copot-webcore', 'release-1', $hash('archive'), $hash('apply-plan'),
    [$databaseIdentity, new RecoveryDomainIdentity('database', 'database.other', 'other-scope', $hash('other'))],
    'pre-lifecycle-1', 'pre-ledger-1'
), 'Manifest with duplicate domain identifier was accepted.');
$throws(static fn (): RecoveryManifest => new RecoveryManifest(
    $identity, 'operation-1', 'copot-webcore', 'release-1', $hash('archive'), $hash('apply-plan'),
    [$databaseIdentity, new RecoveryDomainIdentity('database.other', 'database.webcore', 'other-scope', $hash('other'))],
    'pre-lifecycle-1', 'pre-ledger-1'
), 'Manifest with ambiguous domain ownership was accepted.');

$registry = new RecoveryDomainRegistry([
    $domain('lifecycle.installed-lock', 'filesystem.lifecycle.installed-lock', 'installed-lock'),
    $domain('database', 'database.webcore', 'configured-webcore-database'),
]);
$assert(array_map(static fn (RecoveryDomain $domain): string => $domain->definition()->identifier(), $registry->all()) === ['database', 'lifecycle.installed-lock'], 'Registry order was not deterministic.');
$assert($registry->has('database'), 'Registered domain was not found.');
$assert($registry->get('database')->definition()->ownershipKey() === 'database.webcore', 'Registry lookup returned the wrong domain.');
$throws(static fn (): RecoveryDomainRegistry => new RecoveryDomainRegistry([
    $domain('database', 'database.webcore', 'one'),
    $domain('database', 'database.other', 'two'),
]), 'Registry duplicate domain identifier was accepted.');
$throws(static fn (): RecoveryDomainRegistry => new RecoveryDomainRegistry([
    $domain('database', 'database.webcore', 'one'),
    $domain('database.copy', 'database.webcore', 'two'),
]), 'Registry ambiguous ownership was accepted.');

$assert(!is_dir($basePath . DIRECTORY_SEPARATOR . '.copot-recovery'), 'WU1 created a recovery directory.');
$assert(!file_exists($basePath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . '.copot-lifecycle' . DIRECTORY_SEPARATOR . 'recovery.json'), 'WU1 created a recovery persistence artifact.');

echo "Backup & Recovery WU1 focused tests passed ({$assertions} assertions)." . PHP_EOL;
