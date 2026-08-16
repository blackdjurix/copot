<?php

namespace Copot\Core;

use Copot\Core\BackupRecovery\DatabaseQuiescenceCapability;
use Copot\Core\BackupRecovery\FilesystemRecoveryDomain;
use Copot\Core\BackupRecovery\FilesystemRecoveryPathGuard;
use Copot\Core\BackupRecovery\InstallationRecoveryMaintenance;
use Copot\Core\BackupRecovery\InstalledLockRecoveryDomain;
use Copot\Core\BackupRecovery\LifecycleRecoveryDomain;
use Copot\Core\BackupRecovery\MariaDbReadOnlyQuiescence;
use Copot\Core\BackupRecovery\MySqlRecoveryProvider;
use Copot\Core\BackupRecovery\NormalWebcoreRecoveryCaptureService;
use Copot\Core\BackupRecovery\RecoveryArtifactStore;
use Copot\Core\BackupRecovery\RecoveryLifecycleCoordinator;
use Copot\Core\BackupRecovery\RecoveryLifecycleStore;
use Copot\Core\BackupRecovery\RecoveryOrchestrator;
use Copot\Core\BackupRecovery\RecoveryRootResolver;
use Copot\Core\BackupRecovery\RecoveryStorageRoot;
use Copot\Core\BackupRecovery\UnavailableDatabaseQuiescence;
use PDO;

final class PackageLifecycleFactory
{
    public static function forProject(DeploymentContext|string $deployment): PackageLifecycleService
    {
        $deploymentContext = is_string($deployment)
            ? DeploymentContext::forApplicationRoot($deployment)
            : $deployment;
        $basePath = $deploymentContext->appRoot();
        $publicRoot = $deploymentContext->publicRoot();
        $storage = $basePath . DIRECTORY_SEPARATOR . 'storage';
        $installationIdentity = (new InstallationIdentityStore($storage))->getOrCreate();
        $database = new Database(new Config($basePath . DIRECTORY_SEPARATOR . 'config'));
        $baselineCatalog = CanonicalSchemaBaselineCatalog::forProject($basePath);
        $registry = new CoreMigrationRegistry('copot-core-current', []);
        $ledger = new CoreMigrationLedger($database->tables());
        $mutex = new InstallationMutex($storage);
        $operationStore = new LifecycleOperationStore($storage);
        $maintenance = new MaintenanceCoordinator($operationStore);
        $liveGuard = new LiveTreePathGuard($basePath);
        $intake = new ZipIntakeService($basePath, null, null, null, null, $installationIdentity->value());
        $installationState = new InstallationState($storage);
        $committedStore = new CommittedLifecycleStateStore($storage);
        $migrationRunner = new CoreMigrationRunner($ledger);
        [$recoveryComposition, $recoveryUnavailableReason] = self::recoveryComposition(
            $basePath,
            $publicRoot,
            $storage,
            $installationState,
            $committedStore,
            $liveGuard
        );
        $normalRecoveryCapture = $recoveryComposition?->normalCaptureService();

        $applyTemporaryRoot = PackageApplyTemporaryRoot::forProject($basePath, $installationIdentity->value());
        $applier = new PackageOwnedFileApplier($liveGuard, LiveFileActivationCapability::current(), $applyTemporaryRoot);
        $applyCoordinator = new WebcoreApplyCoordinator($mutex, $maintenance, $applier, static function (CoreMigrationPlan $plan, string $operationId, string $classification) use ($database, $migrationRunner, $installationIdentity): MigrationRunResult {
            $catalog = DatabaseTableOwnershipCatalog::current();
            return $migrationRunner->run($database->connection(), $plan, null, static function (CoreMigrationDescriptor $migration) use ($database, $catalog, $installationIdentity, $operationId, $classification, $plan): AuthorizedMigrationContext {
                $authorization = new MigrationAuthorizationContext($installationIdentity, $database->tables(), $operationId, $classification, DatabaseTableOwner::webcore(), $migration->id(), $migration->checksum(), $plan->initialWebcoreVersion(), $plan->virtualFinalWebcoreVersion(), true, $migration->schemaSurface());
                return new AuthorizedMigrationContext($database->connection(), $authorization, $catalog);
            });
        }, new RuntimeRegistry($storage, $installationIdentity, $mutex));
        $healthCoordinator = new HealthIntegrityCommitCoordinator(
            $mutex,
            $maintenance,
            $installationState,
            $committedStore,
            new TargetPackageIntegrityVerifier(),
            new DatabaseHealthVerifier($database->tables()),
            new CoreMigrationHealthVerifier($ledger),
            new RuntimeHealthVerifier(),
            $registry
        );

        [$reconciliationOperator, $reconciliationUnavailableReason] = self::reconciliationOperator(
            $basePath,
            $deploymentContext,
            $database,
            $registry,
            $ledger,
            $intake,
            $installationState,
            $committedStore,
            $liveGuard,
            $applier,
            $recoveryComposition,
            $recoveryUnavailableReason
        );

        return new PackageLifecycleService(
            $intake,
            new PackageManifestReader(),
            new PackageInventoryVerifier(),
            new InstalledStateInspector($committedStore),
            $installationState,
            static function () use ($database, $basePath): ExistingInstallEvidence {
                $schema = false;
                try { $schema = (new InstallerSchemaState($database))->isReady(); } catch (\Throwable) { }
                return new ExistingInstallEvidence($schema, is_file($basePath . DIRECTORY_SEPARATOR . '.env'));
            },
            new TransitionPlanner(),
            new CoreMigrationPlanner(),
            $registry,
            $ledger,
            static fn (): PDO => $database->connection(),
            $applyCoordinator,
            $healthCoordinator,
            $liveGuard,
            $maintenance,
            $mutex,
            static function () use ($database): RuntimeCompatibilityContext {
                $version = (string) $database->connection()->query('SELECT VERSION()')->fetchColumn();
                preg_match('/(\\d+\\.\\d+(?:\\.\\d+)?)/', $version, $match);
                return new RuntimeCompatibilityContext(PHP_VERSION, ['mysql' => $match[1] ?? '0.0.0'], get_loaded_extensions());
            },
            static function () use ($basePath, $deploymentContext): array {
                $app = null;
                $bootstrap = static function () use (&$app, $basePath, $deploymentContext): Application {
                    if (!$app instanceof Application) {
                        $loaded = require $basePath . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'app.php';
                        if (!$loaded instanceof Application) { throw new \RuntimeException('Application bootstrap did not return an application.'); }
                        $app = $loaded;
                    }
                    return $app;
                };

                return [
                    'bootstrap' => static function () use ($bootstrap): bool { $bootstrap(); return true; },
                    'runtime' => static function () use ($bootstrap): bool {
                        $application = $bootstrap();
                        return class_exists(Application::class) && $application->router() instanceof Router;
                    },
                    'modules' => static function () use ($bootstrap): bool { return $bootstrap()->moduleLoader()->errors() === []; },
                    'theme' => static function () use ($bootstrap, $basePath): bool {
                        $application = $bootstrap();
                        return (new ThemeLoader(new ThemeRepository($application->database()), $basePath))->layoutPath() !== '';
                    },
                    'public' => static function () use ($bootstrap): bool {
                        return $bootstrap()->run(new Request('GET', '/'))->statusCode() < 500;
                    },
                    'admin' => static function () use ($bootstrap): bool {
                        $application = $bootstrap();
                        return $application->run(new Request('GET', $application->adminUrl()->routeBaseUrl()))->statusCode() < 500;
                    },
                ];
            },
            new CanonicalSchemaBaselineVerifier($database->tables()),
            $basePath . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema.sql',
            null,
            null,
            $reconciliationOperator,
            $reconciliationUnavailableReason,
            $baselineCatalog
        );
    }

    private static function reconciliationOperator(
        string $basePath,
        DeploymentContext $deploymentContext,
        Database $database,
        CoreMigrationRegistry $registry,
        CoreMigrationLedger $ledger,
        ZipIntakeService $intake,
        InstallationState $installation,
        CommittedLifecycleStateStore $committedStore,
        LiveTreePathGuard $liveGuard,
        PackageOwnedFileApplier $applier,
        ?PackageLifecycleRecoveryComposition $recovery,
        ?string $recoveryUnavailableReason
    ): array {
        if (!$recovery instanceof PackageLifecycleRecoveryComposition) {
            return [null, $recoveryUnavailableReason ?? 'Recovery composition is unavailable.'];
        }

        try {
            $recoveryOrchestrator = new LegacyReconciliationRecoveryOrchestrator($recovery->coordinator, $recovery->store, $recovery->quiescence);
            $integrated = new LegacyReconciliationIntegratedLifecycle($recovery->store, $recovery->coordinator, new RecoveryOrchestrator($recovery->store, $recovery->mutex, $recovery->quiescence, $recovery->maintenance));
            $migrationRunner = new CoreMigrationRunner($ledger);
            $schemaPath = $basePath . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema.sql';
            $baselineCatalog = CanonicalSchemaBaselineCatalog::forProject($basePath);
            $runtime = static function () use ($database): RuntimeCompatibilityContext {
                $version = (string) $database->connection()->query('SELECT VERSION()')->fetchColumn();
                preg_match('/(\\d+\\.\\d+(?:\\.\\d+)?)/', $version, $match);
                return new RuntimeCompatibilityContext(PHP_VERSION, ['mysql' => $match[1] ?? '0.0.0'], get_loaded_extensions());
            };
            $runtimeChecks = static function () use ($basePath, $deploymentContext): array {
                $app = null;
                $bootstrap = static function () use (&$app, $basePath, $deploymentContext): Application {
                    if (!$app instanceof Application) { $loaded = require $basePath . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'app.php'; if (!$loaded instanceof Application) throw new \RuntimeException('Application bootstrap did not return an application.'); $app = $loaded; }
                    return $app;
                };
                return [
                    'bootstrap' => static function () use ($bootstrap): bool { $bootstrap(); return true; },
                    'runtime' => static function () use ($bootstrap): bool { return $bootstrap()->router() instanceof Router; },
                    'modules' => static function () use ($bootstrap): bool { return $bootstrap()->moduleLoader()->errors() === []; },
                    'theme' => static function () use ($bootstrap, $basePath): bool { $application = $bootstrap(); return (new ThemeLoader(new ThemeRepository($application->database()), $basePath))->layoutPath() !== ''; },
                    'public' => static function () use ($bootstrap): bool { return $bootstrap()->run(new Request('GET', '/'))->statusCode() < 500; },
                    'admin' => static function () use ($bootstrap): bool { $application = $bootstrap(); return $application->run(new Request('GET', $application->adminUrl()->routeBaseUrl()))->statusCode() < 500; },
                ];
            };
            return [new LegacyReconciliationOperator(
                $intake,
                new PackageManifestReader(),
                new PackageInventoryVerifier(),
                new InstalledStateInspector($committedStore),
                $installation,
                static function () use ($database, $basePath): ExistingInstallEvidence { $schema = false; try { $schema = (new InstallerSchemaState($database))->isReady(); } catch (\Throwable) {} return new ExistingInstallEvidence($schema, is_file($basePath . DIRECTORY_SEPARATOR . '.env')); },
                new LegacyRuntimeClassifier(new CanonicalSchemaBaselineVerifier($database->tables()), $baselineCatalog, $ledger),
                new LegacyReconciliationPlanner(),
                $registry,
                $ledger,
                static fn (): PDO => $database->connection(),
                $recovery->databaseConnection(),
                $recovery->databaseConnection(),
                $runtime,
                $runtimeChecks,
                $recovery->databaseIdentity,
                $schemaPath,
                new CanonicalSchemaBaselineVerifier($database->tables()),
                $liveGuard,
                $applier,
                new LegacyReconciliationDatabaseReconciler($ledger, $migrationRunner, new CanonicalSchemaBaselineVerifier($database->tables()), new CoreMigrationHealthVerifier($ledger), $baselineCatalog),
                new LegacyReconciliationFinalizer($recovery->store, new TargetPackageIntegrityVerifier(), new DatabaseHealthVerifier($database->tables()), new CoreMigrationHealthVerifier($ledger), new RuntimeHealthVerifier()),
                $recoveryOrchestrator,
                $integrated,
                $recovery->coordinator,
                $recovery->store,
                $recovery->artifacts,
                $recovery->filesystem,
                $recovery->lifecycle,
                $recovery->installedLock,
                $recovery->database,
                $recovery->quiescence,
                $recovery->rootResolver,
                $committedStore,
                $recovery->mutex
            ), null];
        } catch (\Throwable $exception) {
            return [null, $exception->getMessage()];
        }
    }

    private static function recoveryComposition(
        string $basePath,
        string $publicRoot,
        string $storage,
        InstallationState $installation,
        CommittedLifecycleStateStore $committedStore,
        LiveTreePathGuard $liveGuard
    ): array {
        $configuredRoot = Env::get('COPOT_RECOVERY_ROOT');
        if (!is_string($configuredRoot) || trim($configuredRoot) === '') {
            return [null, 'A configured private recovery root is required.'];
        }

        try {
            $config = new Config($basePath . DIRECTORY_SEPARATOR . 'config');
            $host = (string) $config->get('database.connections.mysql.host', '127.0.0.1');
            $port = (string) $config->get('database.connections.mysql.port', '3306');
            $databaseName = (string) $config->get('database.connections.mysql.database', '');
            $username = (string) $config->get('database.connections.mysql.username', 'root');
            $password = (string) $config->get('database.connections.mysql.password', '');
            $dsn = "mysql:host={$host};port={$port};dbname={$databaseName};charset=utf8mb4";
            $freshConnection = static fn (): PDO => new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $adminUser = Env::get('COPOT_MARIADB_ADMIN_USERNAME');
            $adminPassword = Env::get('COPOT_MARIADB_ADMIN_PASSWORD');
            $quiescence = new UnavailableDatabaseQuiescence();
            $recoveryConnection = $freshConnection;
            if (is_string($adminUser) && is_string($adminPassword) && $adminUser !== '') {
                $admin = new PDO($dsn, $adminUser, $adminPassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $recoveryConnection = static fn (): PDO => new PDO($dsn, $adminUser, $adminPassword, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
                $evidence = static function () use ($admin, $freshConnection, $username): bool {
                    if (Env::get('COPOT_MARIADB_QUIESCENCE_CONFIRMED', false) !== true) {
                        return false;
                    }
                    $adminGrants = implode(' ', $admin->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN));
                    $runtime = $freshConnection();
                    $runtimeGrants = implode(' ', $runtime->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN));
                    $adminileged = preg_match('/\\bSUPER\\b|SYSTEM_VARIABLES_ADMIN/i', $adminGrants) === 1;
                    $runtimeBypass = preg_match('/\\bSUPER\\b|READ_ONLY ADMIN|SYSTEM_VARIABLES_ADMIN/i', $runtimeGrants) === 1;
                    return $adminileged && !$runtimeBypass && $username !== '' && (int) $admin->query('SELECT @@GLOBAL.read_only')->fetchColumn() === 0;
                };
                $quiescence = new MariaDbReadOnlyQuiescence($admin, $recoveryConnection, $evidence, $databaseName);
            }

            $rootResolver = new RecoveryRootResolver($basePath, $configuredRoot, [$storage, $basePath . DIRECTORY_SEPARATOR . 'storage'], [$publicRoot]);
            $root = $rootResolver->resolve();
            $store = new RecoveryLifecycleStore($root);
            $artifacts = new RecoveryArtifactStore($root);
            $mutex = new InstallationMutex($storage);
            $maintenance = new InstallationRecoveryMaintenance($mutex);
            $coordinator = new RecoveryLifecycleCoordinator($store, $mutex, $quiescence, $maintenance);
            $pathGuard = new FilesystemRecoveryPathGuard($liveGuard);

            return [new PackageLifecycleRecoveryComposition(
                $root,
                $rootResolver,
                $store,
                $artifacts,
                $maintenance,
                $coordinator,
                $quiescence,
                new FilesystemRecoveryDomain($pathGuard, $artifacts),
                new LifecycleRecoveryDomain($committedStore, $pathGuard),
                new InstalledLockRecoveryDomain($installation, $pathGuard),
                new MySqlRecoveryProvider(),
                $recoveryConnection,
                $databaseName,
                $mutex
            ), null];
        } catch (\Throwable $exception) {
            return [null, $exception->getMessage()];
        }
    }
}

final class PackageLifecycleRecoveryComposition
{
    private $databaseConnection;

    public function __construct(
        public readonly RecoveryStorageRoot $root,
        public readonly RecoveryRootResolver $rootResolver,
        public readonly RecoveryLifecycleStore $store,
        public readonly RecoveryArtifactStore $artifacts,
        public readonly InstallationRecoveryMaintenance $maintenance,
        public readonly RecoveryLifecycleCoordinator $coordinator,
        public readonly DatabaseQuiescenceCapability $quiescence,
        public readonly FilesystemRecoveryDomain $filesystem,
        public readonly LifecycleRecoveryDomain $lifecycle,
        public readonly InstalledLockRecoveryDomain $installedLock,
        public readonly MySqlRecoveryProvider $database,
        callable $databaseConnection,
        public readonly string $databaseIdentity,
        public readonly InstallationMutex $mutex
    ) {
        $this->databaseConnection = $databaseConnection;
    }

    public function databaseConnection(): callable
    {
        return $this->databaseConnection;
    }

    public function normalCaptureService(): NormalWebcoreRecoveryCaptureService
    {
        return new NormalWebcoreRecoveryCaptureService(
            $this->coordinator,
            $this->store,
            $this->artifacts,
            $this->filesystem,
            $this->lifecycle,
            $this->installedLock,
            $this->database,
            $this->quiescence,
            $this->databaseConnection,
            $this->databaseConnection
        );
    }
}
