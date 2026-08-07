<?php

namespace Copot\Core;

use Copot\Core\BackupRecovery\DatabaseQuiescenceCapability;
use Copot\Core\BackupRecovery\InstallationRecoveryMaintenance;
use Copot\Core\BackupRecovery\MariaDbReadOnlyQuiescence;
use Copot\Core\BackupRecovery\RecoveryArtifactStore;
use Copot\Core\BackupRecovery\RecoveryLifecycleCoordinator;
use Copot\Core\BackupRecovery\RecoveryLifecycleStore;
use Copot\Core\BackupRecovery\RecoveryOrchestrator;
use Copot\Core\BackupRecovery\RecoveryRootResolver;
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
        $database = new Database(new Config($basePath . DIRECTORY_SEPARATOR . 'config'));
        $baselineCatalog = CanonicalSchemaBaselineCatalog::forProject($basePath);
        $registry = new CoreMigrationRegistry('copot-core-current', []);
        $ledger = new CoreMigrationLedger();
        $mutex = new InstallationMutex($storage);
        $operationStore = new LifecycleOperationStore($storage);
        $maintenance = new MaintenanceCoordinator($operationStore);
        $liveGuard = new LiveTreePathGuard($basePath);
        $intake = new ZipIntakeService($basePath);
        $installationState = new InstallationState($storage);
        $committedStore = new CommittedLifecycleStateStore($storage);
        $migrationRunner = new CoreMigrationRunner($ledger);
        $applyTemporaryRoot = PackageApplyTemporaryRoot::forProject($basePath);
        $applier = new PackageOwnedFileApplier($liveGuard, LiveFileActivationCapability::current(), $applyTemporaryRoot);
        $applyCoordinator = new WebcoreApplyCoordinator($mutex, $maintenance, $applier, static function (CoreMigrationPlan $plan) use ($database, $migrationRunner): MigrationRunResult {
            return $migrationRunner->run($database->connection(), $plan);
        });
        $healthCoordinator = new HealthIntegrityCommitCoordinator(
            $mutex,
            $maintenance,
            $installationState,
            $committedStore,
            new TargetPackageIntegrityVerifier(),
            new DatabaseHealthVerifier(),
            new CoreMigrationHealthVerifier(),
            new RuntimeHealthVerifier(),
            $registry
        );

        [$reconciliationOperator, $reconciliationUnavailableReason] = self::reconciliationOperator(
            $basePath,
            $deploymentContext,
            $publicRoot,
            $storage,
            $database,
            $registry,
            $ledger,
            $intake,
            $installationState,
            $committedStore,
            $liveGuard,
            $applier
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
            new CanonicalSchemaBaselineVerifier(),
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
        string $publicRoot,
        string $storage,
        Database $database,
        CoreMigrationRegistry $registry,
        CoreMigrationLedger $ledger,
        ZipIntakeService $intake,
        InstallationState $installation,
        CommittedLifecycleStateStore $committedStore,
        LiveTreePathGuard $liveGuard,
        PackageOwnedFileApplier $applier
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
            $reconciliationConnection = $freshConnection;
            if (is_string($adminUser) && is_string($adminPassword) && $adminUser !== '') {
                $admin = new PDO($dsn, $adminUser, $adminPassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $reconciliationConnection = static fn (): PDO => new PDO($dsn, $adminUser, $adminPassword, [
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
                $quiescence = new MariaDbReadOnlyQuiescence($admin, $reconciliationConnection, $evidence, $databaseName);
            }

            $rootResolver = new RecoveryRootResolver($basePath, $configuredRoot, [$storage, $basePath . DIRECTORY_SEPARATOR . 'storage'], [$publicRoot]);
            $root = $rootResolver->resolve();
            $recoveryStore = new RecoveryLifecycleStore($root);
            $artifactStore = new RecoveryArtifactStore($root);
            $recoveryMaintenance = new InstallationRecoveryMaintenance(new InstallationMutex($storage));
            $recoveryCoordinator = new RecoveryLifecycleCoordinator($recoveryStore, new InstallationMutex($storage), $quiescence, $recoveryMaintenance);
            $recoveryOrchestrator = new LegacyReconciliationRecoveryOrchestrator($recoveryCoordinator, $recoveryStore, $quiescence);
            $integrated = new LegacyReconciliationIntegratedLifecycle($recoveryStore, $recoveryCoordinator, new RecoveryOrchestrator($recoveryStore, new InstallationMutex($storage), $quiescence, $recoveryMaintenance));
            $filesystemRecovery = new BackupRecovery\FilesystemRecoveryDomain(new BackupRecovery\FilesystemRecoveryPathGuard($liveGuard), $artifactStore);
            $lifecycleRecovery = new BackupRecovery\LifecycleRecoveryDomain($committedStore, new BackupRecovery\FilesystemRecoveryPathGuard($liveGuard));
            $installedLockRecovery = new BackupRecovery\InstalledLockRecoveryDomain($installation, new BackupRecovery\FilesystemRecoveryPathGuard($liveGuard));
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
                new LegacyRuntimeClassifier(new CanonicalSchemaBaselineVerifier(), $baselineCatalog),
                new LegacyReconciliationPlanner(),
                $registry,
                $ledger,
                static fn (): PDO => $database->connection(),
                $reconciliationConnection,
                $freshConnection,
                $runtime,
                $runtimeChecks,
                $databaseName,
                $schemaPath,
                new CanonicalSchemaBaselineVerifier(),
                $liveGuard,
                $applier,
                new LegacyReconciliationDatabaseReconciler($ledger, $migrationRunner, new CanonicalSchemaBaselineVerifier(), new CoreMigrationHealthVerifier(), $baselineCatalog),
                new LegacyReconciliationFinalizer($recoveryStore, new TargetPackageIntegrityVerifier(), new DatabaseHealthVerifier(), new CoreMigrationHealthVerifier(), new RuntimeHealthVerifier()),
                $recoveryOrchestrator,
                $integrated,
                $recoveryCoordinator,
                $recoveryStore,
                $artifactStore,
                $filesystemRecovery,
                $lifecycleRecovery,
                $installedLockRecovery,
                new BackupRecovery\MySqlRecoveryProvider(),
                $quiescence,
                $rootResolver,
                $committedStore,
                new InstallationMutex($storage)
            ), null];
        } catch (\Throwable $exception) {
            return [null, $exception->getMessage()];
        }
    }
}
