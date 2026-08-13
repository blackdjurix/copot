<?php

namespace Copot\Core;

use PDO;

/** Owns the single Review & Install mutation boundary. */
final class InstallerInstallationCommitter
{
    public function __construct(
        private string $basePath,
        private InstallationState $installationState,
        private InstallerDatabaseProbe $probe,
        private InstallerSchemaRunner $schema,
        private InstallerEnvironmentWriter $environment,
        private InstallationMutex $mutex
    ) {
    }

    public function commit(array $databaseConfiguration, array $administratorInput, bool $requirementsPassed, string $intent): array
    {
        $lock = $this->mutex->acquire();
        if (!$lock instanceof InstallationLock) {
            throw new InstallationException('Another installation process is already running.');
        }

        $environmentPath = $this->basePath . DIRECTORY_SEPARATOR . '.env';
        $environmentExisted = is_file($environmentPath);
        $previousEnvironment = is_file($environmentPath) ? @file_get_contents($environmentPath) : '';
        if (!is_string($previousEnvironment)) {
            throw new InstallationException('Installation environment could not be read.');
        }

        $connection = null;
        $beforeTables = [];
        $lifecycleFiles = $this->filesUnder($this->basePath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . '.copot-lifecycle');

        try {
            if ($this->installationState->isInstalled()) {
                throw new InstallationException('Installation has already been finalized.');
            }

            $databaseInput = $databaseConfiguration;
            if (is_int($databaseInput['port'] ?? null)) {
                $databaseInput['port'] = (string) $databaseInput['port'];
            }
            $configuration = (new InstallerDatabaseValidator())->validate($databaseInput);
            $configuration['namespace'] = (string) ($databaseConfiguration['namespace'] ?? '');
            $inspection = $this->probe->inspect($configuration);
            $routing = (new InstallerRoutingPlanner())->plan($inspection['occupancy'], $intent, $configuration['namespace']);
            if (!in_array($routing->route(), [InstallerRoutingPlanner::FRESH, InstallerRoutingPlanner::COEXIST], true)) {
                throw new InstallationException('Adopt or migrate requires an existing-installation lifecycle path.');
            }
            // Validate every staged input before the first environment/schema mutation.
            InstallerAdministratorValidator::validate($administratorInput);

            $connection = $this->connect($configuration);
            $beforeTables = $this->tables($connection);
            $this->environment->persist($configuration);
            $statementCount = $this->schema->install($configuration);

            Env::load($environmentPath);
            $database = new Database(new Config($this->basePath . '/config'));
            $schemaState = new InstallerSchemaState($database);
            $settingsRepository = new SettingsRepository($database);
            $settings = new SettingsService(SettingsRegistry::core(), $settingsRepository);
            $administrator = new InstallerAdministratorSetup(
                $database,
                new UserProvider($database),
                new PasswordHasher(),
                $settings,
                $schemaState,
                $this->mutex
            );
            $administratorResult = $administrator->installPrepared($administratorInput, $requirementsPassed);
            $finalizer = new InstallerFinalizer(
                $database,
                $schemaState,
                $settings,
                $settingsRepository,
                new ThemeDiscovery($this->basePath . '/themes'),
                new ThemeManager(new ThemeRepository($database), $database, $this->basePath),
                new ModuleManager(new ModuleDiscovery($this->basePath . '/modules'), new ModuleRepository($database)),
                $this->installationState,
                new CommittedLifecycleStateStore($this->basePath . '/storage'),
                $this->mutex
            );
            $finalization = $finalizer->finalizePrepared();

            return [
                'route' => $routing->route(),
                'namespace' => $routing->namespace(),
                'statement_count' => $statementCount,
                'administrator' => $administratorResult,
                'finalization' => $finalization,
            ];
        } catch (InstallerValidationException|InstallationException $exception) {
            $this->rollback($connection, $beforeTables, $lifecycleFiles, $environmentPath, $environmentExisted, $previousEnvironment);
            throw $exception;
        } catch (\Throwable $exception) {
            $this->rollback($connection, $beforeTables, $lifecycleFiles, $environmentPath, $environmentExisted, $previousEnvironment);
            throw new InstallationException('Installation could not be completed safely.');
        } finally {
            $lock->release();
        }
    }

    private function connect(array $configuration): PDO
    {
        return new PDO(
            sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $configuration['host'], $configuration['port'], $configuration['database']),
            $configuration['username'],
            $configuration['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
        );
    }

    /** @return list<string> */
    private function tables(PDO $connection): array
    {
        $result = $connection->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        return is_array($result) ? array_values(array_filter($result, 'is_string')) : [];
    }

    /** @return list<string> */
    private function filesUnder(string $directory): array
    {
        if (!is_dir($directory)) { return []; }
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) { if ($file->isFile() && !$file->isLink()) { $files[] = $file->getPathname(); } }
        return $files;
    }

    private function rollback(?PDO $connection, array $beforeTables, array $lifecycleFiles, string $environmentPath, bool $environmentExisted, string $previousEnvironment): void
    {
        if ($connection instanceof PDO) {
            try {
                $after = $this->tables($connection);
                $created = array_values(array_diff($after, $beforeTables));
                foreach (array_reverse($created) as $table) {
                    if (preg_match('/\A[a-z][a-z0-9_]{0,63}\z/i', $table) === 1) {
                        $connection->exec('DROP TABLE `' . str_replace('`', '``', $table) . '`');
                    }
                }
            } catch (\Throwable) {
                // Preserve the original failure; recovery inspection must catch any incomplete cleanup.
            }
        }
        if (!$environmentExisted && is_file($environmentPath)) {
            @unlink($environmentPath);
        } elseif ($environmentExisted && @file_get_contents($environmentPath) !== $previousEnvironment) {
            @file_put_contents($environmentPath, $previousEnvironment, LOCK_EX);
        }
        $currentFiles = $this->filesUnder(dirname($environmentPath) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . '.copot-lifecycle');
        foreach (array_diff($currentFiles, $lifecycleFiles) as $file) { @unlink($file); }
    }
}
