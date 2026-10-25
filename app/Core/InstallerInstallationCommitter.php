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
        private InstallationMutex $mutex,
        private ?InstallerAdoptionIntegration $adoptionIntegration = null
    ) {
    }

    public function commit(array $databaseConfiguration, array $administratorInput, bool $requirementsPassed, string $intent, ?AdoptionOrchestrationResult $adoptionResult = null): array
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
        $createdByInstaller = [];
        $approvedCleanupTables = [];
        $lifecycleFiles = $this->filesUnder($this->basePath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . '.copot-lifecycle');

        try {
            if (!$requirementsPassed) {
                throw new InstallationException('Installer requirements are not satisfied.');
            }
            if ($this->installationState->isInstalled() && $intent !== InstallerIntent::ADOPT) {
                throw new InstallationException('Installation has already been finalized.');
            }

            $databaseInput = $databaseConfiguration;
            if (is_int($databaseInput['port'] ?? null)) {
                $databaseInput['port'] = (string) $databaseInput['port'];
            }
            $configuration = (new InstallerDatabaseValidator())->validate($databaseInput);
            $configuration['namespace'] = (string) ($databaseConfiguration['namespace'] ?? '');
            $approvedCleanupTables = $this->approvedCleanupTables($configuration);
            $inspection = $this->probe->inspect($configuration);
            $routing = (new InstallerRoutingPlanner())->plan($inspection['occupancy'], $intent, $configuration['namespace']);
            if ($routing->route() === InstallerRoutingPlanner::ADOPT) {
                if (!$adoptionResult instanceof AdoptionOrchestrationResult) {
                    throw new InstallationException('Installer Adoption requires a fresh Adoption orchestration result.');
                }
                $decision = ($this->adoptionIntegration ?? new InstallerAdoptionIntegration())->decide($routing, $adoptionResult, $inspection['proofs'] ?? []);
                if (!$decision->terminal()) {
                    throw new InstallationException($decision->detail());
                }
                if ($administratorInput !== []) {
                    throw new InstallationException('Adopt does not accept Administrator & Site input.');
                }

                return [
                    'route' => $routing->route(),
                    'namespace' => $routing->namespace(),
                    'statement_count' => 0,
                    'administrator' => null,
                    'finalization' => null,
                    'adopted' => true,
                    'installation_identity' => $decision->installationIdentity(),
                    'existing_state_preserved' => $decision->preservesExistingState(),
                ];
            }
            // Validate every staged input before the first environment/schema mutation.
            InstallerAdministratorValidator::validate($administratorInput);

            $connection = $this->connect($configuration);
            $beforeTables = $this->tables($connection);
            $this->environment->persist($configuration);
            $this->schema->setCreatedTableObserver(function (string $table) use (&$createdByInstaller): void {
                $createdByInstaller[] = $table;
            });
            try {
                $statementCount = $this->schema->install($configuration);
            } finally {
                $this->schema->setCreatedTableObserver(null);
            }
            $this->recordCoreSchemaGeneration($connection, $configuration, $beforeTables, $createdByInstaller);

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
                $this->mutex,
                new PasswordPolicy($settings)
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
            (new InstallationIdentityStore($this->basePath . '/storage'))->getOrCreate();
            $finalization = $finalizer->finalizePrepared();

            return [
                'route' => $routing->route(),
                'namespace' => $routing->namespace(),
                'statement_count' => $statementCount,
                'administrator' => $administratorResult,
                'finalization' => $finalization,
            ];
        } catch (InstallerValidationException|InstallationException $exception) {
            $rollbackComplete = $this->rollback(
                $connection,
                $beforeTables,
                $createdByInstaller,
                $approvedCleanupTables,
                $lifecycleFiles,
                $environmentPath,
                $environmentExisted,
                $previousEnvironment
            );
            if (!$rollbackComplete) {
                throw new InstallationException(
                    'Installation failed and canonical rollback is incomplete; recovery inspection is required.',
                    0,
                    $exception
                );
            }
            throw $exception;
        } catch (\Throwable $exception) {
            $rollbackComplete = $this->rollback(
                $connection,
                $beforeTables,
                $createdByInstaller,
                $approvedCleanupTables,
                $lifecycleFiles,
                $environmentPath,
                $environmentExisted,
                $previousEnvironment
            );
            if (!$rollbackComplete) {
                throw new InstallationException(
                    'Installation failed and canonical rollback is incomplete; recovery inspection is required.',
                    0,
                    $exception
                );
            }
            throw new InstallationException('Installation could not be completed safely.', 0, $exception);
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

    private function recordCoreSchemaGeneration(PDO $connection, array $configuration, array $beforeTables, array &$createdByInstaller): void
    {
        $tables = new DatabaseTableNames((string) ($configuration['namespace'] ?? ''));
        $schemaPath = $this->basePath . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema.sql';
        $generation = (new CoreSchemaMaterializer())->generationIdentity($schemaPath);
        $store = new CoreSchemaGenerationStore($tables);
        $store->ensure($connection);
        $generationTable = $tables->table('core_schema_generation');
        if (!in_array($generationTable, $beforeTables, true) && in_array($generationTable, $this->tables($connection), true)) {
            $createdByInstaller[] = $generationTable;
        }
        $store->record($connection, $generation, 'compatible', $tables->namespace());
    }

    /** @return list<string> */
    private function approvedCleanupTables(array $configuration): array
    {
        $schemaPath = $this->basePath . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema.sql';
        $schema = @file_get_contents($schemaPath);
        if (!is_string($schema)) {
            throw new InstallationException('Database schema is unavailable.');
        }

        $namespace = new DatabaseTableNames((string) ($configuration['namespace'] ?? ''));
        $statements = (new InstallerSchemaRunner($schemaPath))->statements($schema);
        if ($namespace->namespace() !== '') {
            $statements = (new DatabaseSchemaMaterializer())->namespaceStatements($statements, $namespace);
        }

        $approved = [$namespace->table('core_schema_generation')];
        foreach ($statements as $statement) {
            if (preg_match('/\ACREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?\s+`?([a-z][a-z0-9_]*)`?/i', $statement, $match) === 1) {
                $approved[] = strtolower($match[1]);
            }
        }

        return array_values(array_unique($approved));
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

    private function rollback(
        ?PDO $connection,
        array $beforeTables,
        array $createdByInstaller,
        array $approvedCleanupTables,
        array $lifecycleFiles,
        string $environmentPath,
        bool $environmentExisted,
        string $previousEnvironment
    ): bool
    {
        $complete = true;
        if ($connection instanceof PDO) {
            $foreignKeysDisabled = false;
            try {
                $after = $this->tables($connection);
                $created = array_values(array_diff($after, $beforeTables));
                $observedNew = $created; // Snapshot difference is observation only, never ownership authority.
                $provenCreated = array_values(array_unique(array_intersect($createdByInstaller, $approvedCleanupTables)));
                $cleanupTables = array_values(array_intersect($observedNew, $provenCreated));
                if (array_values(array_diff($observedNew, $cleanupTables)) !== []) {
                    $complete = false;
                }
                try {
                    $connection->exec('SET FOREIGN_KEY_CHECKS=0');
                    $foreignKeysDisabled = true;
                } catch (\Throwable) {
                    $complete = false;
                }
                foreach (array_reverse($cleanupTables) as $table) {
                    if (preg_match('/\A[a-z][a-z0-9_]{0,63}\z/i', $table) === 1) {
                        try {
                            $connection->exec('DROP TABLE `' . str_replace('`', '``', $table) . '`');
                        } catch (\Throwable) {
                            $complete = false;
                        }
                    } else {
                        $complete = false;
                    }
                }
                if ($foreignKeysDisabled) {
                    try {
                        $connection->exec('SET FOREIGN_KEY_CHECKS=1');
                    } catch (\Throwable) {
                        $complete = false;
                    }
                }
                try {
                    if (array_intersect($cleanupTables, $this->tables($connection)) !== []) {
                        $complete = false;
                    }
                } catch (\Throwable) {
                    $complete = false;
                }
            } catch (\Throwable) {
                $complete = false;
            }
        }
        if (!$environmentExisted && is_file($environmentPath)) {
            if (!@unlink($environmentPath) && is_file($environmentPath)) {
                $complete = false;
            }
        } elseif ($environmentExisted && @file_get_contents($environmentPath) !== $previousEnvironment) {
            if (@file_put_contents($environmentPath, $previousEnvironment, LOCK_EX) === false) {
                $complete = false;
            }
        }
        $currentFiles = $this->filesUnder(dirname($environmentPath) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . '.copot-lifecycle');
        foreach (array_diff($currentFiles, $lifecycleFiles) as $file) {
            if (!@unlink($file) && is_file($file)) {
                $complete = false;
            }
        }

        return $complete;
    }
}
