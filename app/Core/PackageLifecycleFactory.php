<?php

namespace Copot\Core;

use PDO;

final class PackageLifecycleFactory
{
    public static function forProject(string $basePath): PackageLifecycleService
    {
        $basePath = rtrim($basePath, '/\\');
        $storage = $basePath . DIRECTORY_SEPARATOR . 'storage';
        $database = new Database(new Config($basePath . DIRECTORY_SEPARATOR . 'config'));
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
        $applier = new PackageOwnedFileApplier($liveGuard, LiveFileActivationCapability::current(), $storage . DIRECTORY_SEPARATOR . '.copot-apply');
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
            static function () use ($basePath): array {
                $app = null;
                $bootstrap = static function () use (&$app, $basePath): Application {
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
                        return $application->run(new Request('GET', $application->adminUrl()->baseUrl()))->statusCode() < 500;
                    },
                ];
            }
        );
    }
}
