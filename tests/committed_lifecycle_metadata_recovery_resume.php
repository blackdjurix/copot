<?php

declare(strict_types=1);

use Copot\Core\CommittedLifecycleState;
use Copot\Core\CommittedLifecycleStateStore;
use Copot\Core\Config;
use Copot\Core\CoreMigrationLedger;
use Copot\Core\CoreMigrationRegistry;
use Copot\Core\Database;
use Copot\Core\DeploymentContext;
use Copot\Core\Env;
use Copot\Core\InstallationIdentityStore;
use Copot\Core\InstallationState;
use Copot\Core\PackageLifecycleFactory;

$base = dirname(__DIR__);
require $base . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    ++$assertions;
    if (!$condition) throw new RuntimeException($message);
};
$copy = static function (string $source, string $destination) use (&$copy): void {
    if (is_dir($source)) {
        if (!is_dir($destination)) mkdir($destination, 0700, true);
        foreach (scandir($source) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $copy($source . DIRECTORY_SEPARATOR . $entry, $destination . DIRECTORY_SEPARATOR . $entry);
        }
        return;
    }
    if (!is_file($source) || !copy($source, $destination)) throw new RuntimeException('Fixture copy failed: ' . $source);
};
$remove = static function (string $path) use (&$remove): void {
    if (is_dir($path) && !is_link($path)) {
        foreach (scandir($path) ?: [] as $entry) if ($entry !== '.' && $entry !== '..') $remove($path . DIRECTORY_SEPARATOR . $entry);
        @rmdir($path);
    } elseif (is_file($path) || is_link($path)) @unlink($path);
};

$host = (string) (getenv('COPOT_TEST_DB_HOST') ?: '127.0.0.1');
$port = (int) (getenv('COPOT_TEST_DB_PORT') ?: 3306);
$username = (string) (getenv('COPOT_TEST_DB_USERNAME') ?: 'root');
$password = (string) (getenv('COPOT_TEST_DB_PASSWORD') ?: '');
$server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$database = 'copot_metadata_resume_' . bin2hex(random_bytes(6));
$server->exec('CREATE DATABASE `' . $database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$sandbox = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-metadata-resume-' . bin2hex(random_bytes(6));
$root = $sandbox . DIRECTORY_SEPARATOR . 'app';
$public = $sandbox . DIRECTORY_SEPARATOR . 'public';
$recovery = $sandbox . DIRECTORY_SEPARATOR . 'recovery';
mkdir($root, 0700, true); mkdir($public, 0700, true); mkdir($recovery, 0700, true);
$pdo = null;
try {
    foreach (['app', 'bootstrap', 'config', 'database', 'modules', 'themes'] as $directory) $copy($base . DIRECTORY_SEPARATOR . $directory, $root . DIRECTORY_SEPARATOR . $directory);
    file_put_contents($root . DIRECTORY_SEPARATOR . '.env', implode(PHP_EOL, [
        'DB_CONNECTION=mysql', 'DB_HOST=' . $host, 'DB_PORT=' . $port, 'DB_DATABASE=' . $database,
        'DB_USERNAME=' . $username, 'DB_PASSWORD=' . $password, 'DB_NAMESPACE=',
        'COPOT_RECOVERY_ROOT=' . $recovery, '',
    ]));
    Env::load($root . DIRECTORY_SEPARATOR . '.env');
    $config = ['host' => $host, 'port' => $port, 'database' => $database, 'username' => $username, 'password' => $password, 'namespace' => ''];
    (new \Copot\Core\InstallerSchemaRunner($base . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema.sql'))->install($config);
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $registry = CoreMigrationRegistry::forProject($root);
    $migration = $registry->migrations()[0];
    $ledger = new CoreMigrationLedger((new Database(new Config($root . DIRECTORY_SEPARATOR . 'config')))->tables());
    $ledger->record($pdo, $migration);
    $storage = $root . DIRECTORY_SEPARATOR . 'storage'; mkdir($storage, 0700, true);
    $identities = new InstallationIdentityStore($storage); $installation = $identities->getOrCreate();
    $installationState = new InstallationState($storage); $installationState->createMarker('0.13.0');
    $committed = new CommittedLifecycleState('0.13.0', 'copot-v0.13.0', null, 1, 'stale-schema', hash('sha256', 'stale-migration'), new DateTimeImmutable('2025-01-01T00:00:00+00:00'));
    (new CommittedLifecycleStateStore($storage))->write($committed);
    $context = new DeploymentContext($root, $public, '/resume-test');
    $service = PackageLifecycleFactory::forProject($context);
    $first = $service->prepareCommittedLifecycleMetadataReconciliation();
    $operation = $first->plan()->operation()->operationId();
    $recovery = $first->recoveryIdentity()->value();
    $manifest = $first->recoveryManifestIdentity();
    $planIdentity = $first->plan()->identity();
    $beforeMigration = (new \Copot\Core\CoreMigrationHealthVerifier($ledger))->identity($pdo);
    unset($first, $service);
    gc_collect_cycles();
    $service = PackageLifecycleFactory::forProject($context);
    $resumed = $service->resumeCommittedLifecycleMetadataReconciliation($operation);
    $assert($resumed->ready(), 'Persisted recovery resume was not READY.');
    $assert($resumed->plan()->operation()->operationId() === $operation, 'Resume changed operation identity.');
    $assert($resumed->recoveryIdentity()->value() === $recovery, 'Resume changed recovery identity.');
    $assert($resumed->recoveryManifestIdentity() === $manifest, 'Resume changed manifest identity.');
    $assert($resumed->plan()->identity() === $planIdentity, 'Resume changed stable preparation identity: ' . $planIdentity . ' -> ' . $resumed->plan()->identity());
    $assert((new \Copot\Core\LifecycleOperationStore($storage))->read() === null, 'Resume created an operation journal before execute.');
    $assert((new \Copot\Core\CoreMigrationHealthVerifier($ledger))->identity($pdo) === $beforeMigration, 'Resume changed the database ledger.');
    $repeatedRejected = false;
    try {
        $repeated = $service->resumeCommittedLifecycleMetadataReconciliation($operation);
        $repeated->close();
    } catch (Throwable $exception) {
        $repeatedRejected = true;
        $assert(str_contains(strtolower($exception->getMessage()), 'mutex'), 'Repeated resume was rejected for an unexpected reason.');
    }
    $assert($repeatedRejected, 'Repeated resume was accepted while the first preparation still held protection.');
    $assert((new \Copot\Core\LifecycleOperationStore($storage))->read() === null, 'Repeated resume created an operation journal.');
    $conflictingStore = new \Copot\Core\LifecycleOperationStore($storage);
    $conflicting = new \Copot\Core\LifecycleOperationRecord(
        'conflicting-operation', 'UPDATE', '0.13.0', 'conflict-release', str_repeat('a', 64),
        $sandbox . DIRECTORY_SEPARATOR . 'conflict-stage', str_repeat('b', 64), str_repeat('c', 64),
        \Copot\Core\LifecycleOperationRecord::BLOCKED, 0, null, null, null,
        gmdate(DATE_ATOM), gmdate(DATE_ATOM), 'conflicting operation fixture'
    );
    $conflictingStore->create($conflicting);
    try {
        $service->resumeCommittedLifecycleMetadataReconciliation($operation);
        throw new RuntimeException('Conflicting lifecycle operation was accepted.');
    } catch (Throwable $exception) {
        $assert(str_contains($exception->getMessage(), 'already active'), 'Conflicting operation did not fail closed.');
    }
    $conflictingTerminal = $conflicting->advance(\Copot\Core\LifecycleOperationRecord::COMPLETED, 0);
    $conflictingStore->save($conflictingTerminal);
    $conflictingStore->clear($conflictingTerminal);
    $assert((new \Copot\Core\LifecycleOperationStore($storage))->read() === null, 'Conflicting operation cleanup left a journal.');
    try {
        $service->resumeCommittedLifecycleMetadataReconciliation('metadata-reconciliation-missing');
        throw new RuntimeException('Missing operation locator was accepted.');
    } catch (Throwable $exception) { $assert(str_contains($exception->getMessage(), 'missing or ambiguous'), 'Missing locator did not fail closed.'); }
    $guardPath = $storage . DIRECTORY_SEPARATOR . '.copot-lifecycle' . DIRECTORY_SEPARATOR . 'application-write-guard.json';
    $resumed->close();
    $inactiveGuard = json_decode((string) file_get_contents($guardPath), true, 16, JSON_THROW_ON_ERROR);
    $assert(($inactiveGuard['active'] ?? true) === false && ($inactiveGuard['draining'] ?? true) === false, 'Preparation close did not release the scoped Guard.');
    $readmitted = $service->resumeCommittedLifecycleMetadataReconciliation($operation);
    $assert($readmitted->ready(), 'Inactive Guard did not support canonical scoped re-admission.');
    $assert($readmitted->plan()->operation()->operationId() === $operation, 'Scoped re-admission changed operation identity.');
    $readmitted->close();

    $guardBytes = file_get_contents($guardPath);
    file_put_contents($guardPath, json_encode([
        'active' => true,
        'draining' => true,
        'identity' => 'conflicting-recovery',
        'evidence_identity' => str_repeat('f', 64),
        'in_flight' => 1,
        'updated_at' => gmdate('c'),
    ], JSON_THROW_ON_ERROR));
    try { $service->resumeCommittedLifecycleMetadataReconciliation($operation); throw new RuntimeException('Conflicting Guard evidence was accepted.'); }
    catch (Throwable $exception) { $assert(preg_match('/writer|protection|mutex/i', $exception->getMessage()) === 1, 'Conflicting Guard evidence failed for an unexpected reason.'); }
    $guardAfterFailure = json_decode((string) file_get_contents($guardPath), true, 16, JSON_THROW_ON_ERROR);
    $assert(($guardAfterFailure['identity'] ?? null) === 'conflicting-recovery', 'Failed Guard re-admission rewrote conflicting protection state.');
    file_put_contents($guardPath, $guardBytes);
    $manifestPath = (glob($sandbox . DIRECTORY_SEPARATOR . 'recovery' . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . 'recovery-sets' . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . 'manifest' . DIRECTORY_SEPARATOR . 'recovery-manifest.json') ?: [])[0] ?? '';
    $assert($manifestPath !== '' && is_file($manifestPath), 'Persisted recovery manifest path was not found.');
    $manifestBytes = file_get_contents($manifestPath);
    file_put_contents($manifestPath, $manifestBytes . 'x');
    try { $service->resumeCommittedLifecycleMetadataReconciliation($operation); throw new RuntimeException('Mismatched manifest was accepted.'); }
    catch (Throwable) { $assert(true, 'Mismatched manifest was rejected.'); }
    file_put_contents($manifestPath, $manifestBytes);
    $stateStore = new CommittedLifecycleStateStore($storage);
    $stateStore->write(new CommittedLifecycleState('0.13.0', 'copot-v0.13.0', null, 1, 'drifted-schema', hash('sha256', 'drifted-migration'), new DateTimeImmutable('2025-01-01T00:00:00+00:00')));
    try { $service->resumeCommittedLifecycleMetadataReconciliation($operation); throw new RuntimeException('Committed metadata drift was accepted.'); }
    catch (Throwable $exception) { $assert(str_contains($exception->getMessage(), 'drifted'), 'Committed metadata drift did not fail closed.'); }
    $stateStore->write($committed);
    $resumed->close();
    echo "committed_lifecycle_metadata_recovery_resume: {$assertions} assertions passed\n";
} finally {
    try { $server->exec('DROP DATABASE IF EXISTS `' . $database . '`'); } catch (Throwable) {}
    $remove($sandbox);
}
