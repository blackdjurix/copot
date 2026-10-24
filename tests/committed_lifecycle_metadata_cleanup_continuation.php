<?php

declare(strict_types=1);

use Copot\Core\BackupRecovery\ApplicationWriteProtectionEvidence;
use Copot\Core\BackupRecovery\DatabaseScope;
use Copot\Core\BackupRecovery\RecoveryIdentity;
use Copot\Core\BackupRecovery\RecoveryLifecycleCoordinator;
use Copot\Core\BackupRecovery\RecoveryLifecycleState;
use Copot\Core\BackupRecovery\RecoveryLifecycleStore;
use Copot\Core\BackupRecovery\RecoveryRootResolver;
use Copot\Core\BackupRecovery\ScopedDatabaseConsistencyEvidence;
use Copot\Core\BackupRecovery\UnavailableDatabaseQuiescence;
use Copot\Core\CommittedLifecycleState;
use Copot\Core\CommittedLifecycleStateStore;
use Copot\Core\Config;
use Copot\Core\CoreMigrationLedger;
use Copot\Core\CoreMigrationRegistry;
use Copot\Core\Database;
use Copot\Core\DatabaseTableOwnershipCatalog;
use Copot\Core\DeploymentContext;
use Copot\Core\Env;
use Copot\Core\InstallationIdentityStore;
use Copot\Core\InstallationMutex;
use Copot\Core\BackupRecovery\InstallationScopedMaintenanceGuard;
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
        if (!is_dir($destination) && !mkdir($destination, 0700, true)) throw new RuntimeException('Fixture directory copy failed.');
        foreach (scandir($source) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $copy($source . DIRECTORY_SEPARATOR . $entry, $destination . DIRECTORY_SEPARATOR . $entry);
        }
        return;
    }
    if (!is_file($source) || !copy($source, $destination)) throw new RuntimeException('Fixture file copy failed: ' . $source);
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

$buildFixture = static function () use ($base, $copy, $remove, $server, $host, $port, $username, $password): array {
    $database = 'copot_metadata_cleanup_' . bin2hex(random_bytes(6));
    $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-metadata-cleanup-' . bin2hex(random_bytes(6));
    $public = $root . DIRECTORY_SEPARATOR . 'public';
    $recoveryRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-metadata-recovery-' . bin2hex(random_bytes(6));
    mkdir($root, 0700, true); mkdir($public, 0700, true); mkdir($recoveryRoot, 0700, true);
    $pdo = null;
    try {
        $server->exec('CREATE DATABASE `' . $database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        foreach (['app', 'bootstrap', 'config', 'database', 'modules', 'themes'] as $directory) {
            $copy($base . DIRECTORY_SEPARATOR . $directory, $root . DIRECTORY_SEPARATOR . $directory);
        }
        file_put_contents($root . DIRECTORY_SEPARATOR . '.env', implode(PHP_EOL, [
            'DB_CONNECTION=mysql', 'DB_HOST=' . $host, 'DB_PORT=' . $port, 'DB_DATABASE=' . $database,
            'DB_USERNAME=' . $username, 'DB_PASSWORD=' . $password, 'DB_NAMESPACE=',
            'COPOT_RECOVERY_ROOT=' . $recoveryRoot, '',
        ]));
        Env::load($root . DIRECTORY_SEPARATOR . '.env');
        $config = ['host' => $host, 'port' => $port, 'database' => $database, 'username' => $username, 'password' => $password, 'namespace' => ''];
        (new \Copot\Core\InstallerSchemaRunner($base . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema.sql'))->install($config);
        $pdo = new PDO("mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $registry = CoreMigrationRegistry::forProject($root);
        $migration = $registry->migrations()[0];
        $databaseService = new Database(new Config($root . DIRECTORY_SEPARATOR . 'config'));
        $ledger = new CoreMigrationLedger($databaseService->tables());
        $ledger->record($pdo, $migration);
        $storage = $root . DIRECTORY_SEPARATOR . 'storage';
        mkdir($storage, 0700, true);
        $identities = new InstallationIdentityStore($storage);
        $installation = $identities->getOrCreate();
        $installationState = new InstallationState($storage);
        $committedAt = new DateTimeImmutable('2025-01-01T00:00:00+00:00');
        $installationState->createMarker('0.13.0');
        $installationState->replaceMarker('0.13.0', $committedAt->format(DATE_ATOM));
        $committedStore = new CommittedLifecycleStateStore($storage);
        $before = new CommittedLifecycleState('0.13.0', 'copot-v0.13.0', null, 1, 'stale-schema', hash('sha256', 'stale-migration'), $committedAt);
        $committedStore->write($before);

        $context = new DeploymentContext($root, $public, '/cleanup-test');
        $service = PackageLifecycleFactory::forProject($context);
        $preparation = $service->prepareCommittedLifecycleMetadataReconciliation();
        $operation = $preparation->plan()->operation()->operationId();
        $recovery = $preparation->recoveryIdentity();
        $manifest = $preparation->recoveryManifestIdentity();
        $permit = $preparation->recoverySession()->authorizeMutation();
        $committedStore->commit($installationState, $preparation->plan()->after());

        $recoveryStorage = (new RecoveryRootResolver($root, $recoveryRoot, [$storage, $root . DIRECTORY_SEPARATOR . 'storage'], [$public]))->resolve();
        $recoveryStore = new RecoveryLifecycleStore($recoveryStorage);
        $coordinator = new RecoveryLifecycleCoordinator(
            $recoveryStore,
            new InstallationMutex($storage),
            new UnavailableDatabaseQuiescence(),
            new InstallationScopedMaintenanceGuard(new InstallationMutex($storage), $storage)
        );
        $coordinator->recordPostReconciliationVerification($recovery, $manifest, $manifest);
        $coordinator->markReconciliationCleanupPending($recovery, $manifest, $manifest);
        $permit->release();
        $preparation->close();

        $scope = DatabaseScope::fromOwnershipCatalog($installation->value(), $database, '', DatabaseTableOwnershipCatalog::current());
        $now = time();
        $application = new ApplicationWriteProtectionEvidence($scope, $recovery, hash('sha256', 'cleanup-writers'), hash('sha256', 'cleanup-drain'), ['background', 'cli', 'http', 'scheduled'], true, true, $now - 1, $now + 600);
        $databaseEvidence = new ScopedDatabaseConsistencyEvidence($scope, $recovery, hash('sha256', 'cleanup-writers'), hash('sha256', 'cleanup-schema'), $now - 1, $now + 600);
        $guard = new InstallationScopedMaintenanceGuard(new InstallationMutex($storage), $storage);
        $guardCoordinator = new RecoveryLifecycleCoordinator(
            $recoveryStore,
            new InstallationMutex($storage),
            new UnavailableDatabaseQuiescence(),
            $guard
        );
        $guardCoordinator->enterScopedMaintenance($recovery, $application, $databaseEvidence);

        return compact('database', 'root', 'recoveryRoot', 'storage', 'service', 'operation', 'recovery', 'manifest', 'recoveryStore', 'guardCoordinator', 'guard', 'application', 'databaseEvidence', 'installationState', 'committedStore', 'before', 'preparation');
    } catch (Throwable $exception) {
        try { $server->exec('DROP DATABASE IF EXISTS `' . $database . '`'); } catch (Throwable) {}
        $remove($root);
        $remove($recoveryRoot);
        throw $exception;
    }
};

$run = static function (array $fixture) use ($remove, $assert): void {
    try {
        $wrong = false;
        try { $fixture['service']->continueCommittedLifecycleMetadataCleanup($fixture['operation'] . '-wrong'); }
        catch (Throwable) { $wrong = true; }
        $assert($wrong, 'Mismatched operation locator was accepted.');

        $guardPath = $fixture['storage'] . DIRECTORY_SEPARATOR . '.copot-lifecycle' . DIRECTORY_SEPARATOR . 'application-write-guard.json';
        $guardBytes = file_get_contents($guardPath);
        $fixture['guardCoordinator']->leaveMaintenance($fixture['recovery']);
        try {
            $fixture['service']->continueCommittedLifecycleMetadataCleanup($fixture['operation']);
            throw new RuntimeException('Cleanup proceeded without durable Guard ownership.');
        } catch (Throwable $exception) {
            $assert(str_contains($exception->getMessage(), 'Guard'), 'Guard rejection failed for an unexpected reason.');
        }
        $assert($fixture['recoveryStore']->read($fixture['recovery'])->state() === RecoveryLifecycleState::CLEANUP_PENDING, 'Guard rejection changed recovery state.');
        file_put_contents($guardPath, $guardBytes);

        $marker = $fixture['installationState']->readMarker();
        $fixture['installationState']->replaceMarker('0.13.0', '2025-01-02T00:00:00+00:00');
        try {
            $fixture['service']->continueCommittedLifecycleMetadataCleanup($fixture['operation']);
            throw new RuntimeException('Metadata gate accepted marker drift.');
        } catch (Throwable $exception) {
            $assert(str_contains($exception->getMessage(), 'marker'), 'Metadata gate rejected marker drift for an unexpected reason.');
        }
        $fixture['installationState']->replaceMarker($marker['version'], $marker['installed_at']);
        $fixture['guardCoordinator']->enterScopedMaintenance($fixture['recovery'], $fixture['application'], $fixture['databaseEvidence']);
        $retryLock = $fixture['guard']->activeLock();
        $fixture['guard']->detach();
        $retryLock?->release();

        $cleaned = $fixture['service']->continueCommittedLifecycleMetadataCleanup($fixture['operation']);
        $assert($cleaned->state() === RecoveryLifecycleState::CLEANED, 'Cleanup did not reach CLEANED.');
        $assert((new \Copot\Core\LifecycleOperationStore($fixture['storage']))->read() === null, 'Cleanup created or retained an active operation.');
        $assert(!is_file($guardPath) || json_decode((string) file_get_contents($guardPath), true)['active'] !== true, 'Guard remained active after cleanup.');
        $assert($fixture['committedStore']->read()?->schemaStateIdentity() !== $fixture['before']->schemaStateIdentity(), 'Cleanup unexpectedly restored pre-reconciliation metadata.');
        $assert($fixture['recoveryStore']->read($fixture['recovery'])->manifestIdentity() === $fixture['manifest'], 'Cleanup changed recovery binding.');

        $repeated = $fixture['service']->continueCommittedLifecycleMetadataCleanup($fixture['operation']);
        $assert($repeated->state() === RecoveryLifecycleState::CLEANED, 'Repeated cleanup was not idempotent.');

        // Simulate an interruption after CLEANED was durably persisted but before
        // the process released its scoped Guard, then prove canonical continuation.
        $fixture['guardCoordinator']->enterScopedMaintenance($fixture['recovery'], $fixture['application'], $fixture['databaseEvidence']);
        $interruptedLock = $fixture['guard']->activeLock();
        $fixture['guard']->detach();
        $interruptedLock?->release();
        $continued = $fixture['service']->continueCommittedLifecycleMetadataCleanup($fixture['operation']);
        $assert($continued->state() === RecoveryLifecycleState::CLEANED, 'CLEANED continuation changed terminal state.');
        $assert(json_decode((string) file_get_contents($guardPath), true)['active'] !== true, 'CLEANED continuation did not release the Guard.');
    } finally {
        $remove($fixture['root']);
        $remove($fixture['recoveryRoot']);
    }
};

$fixture = null;
try {
    $fixture = $buildFixture();
    $run($fixture);
    echo "committed_lifecycle_metadata_cleanup_continuation: {$assertions} assertions passed\n";
} finally {
    try { $server->exec('DROP DATABASE IF EXISTS `' . ($fixture['database'] ?? '') . '`'); } catch (Throwable) {}
}
