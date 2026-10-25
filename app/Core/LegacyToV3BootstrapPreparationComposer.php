<?php

namespace Copot\Core;

use PDO;

/**
 * Bounded LEGACY_TO_V3_BOOTSTRAP preparation composition.
 *
 * This composition deliberately stops before mutex ownership, recovery
 * capture, package application, migration execution, and WU6 finalization.
 */
final class LegacyToV3BootstrapPreparationComposer
{
    public function __construct(
        private DeploymentContext $deployment,
        private ZipIntakeService $intake,
        private PackageManifestReader $manifestReader,
        private PackageInventoryVerifier $inventoryVerifier,
        private LegacyToV3BootstrapEntry $entry
    ) {
    }

    public static function forProject(DeploymentContext|string $deployment): self
    {
        self::assertRetired();
        $deploymentContext = is_string($deployment)
            ? DeploymentContext::forApplicationRoot($deployment)
            : $deployment;
        $basePath = $deploymentContext->appRoot();
        $publicRoot = $deploymentContext->publicRoot();
        $storage = $basePath . DIRECTORY_SEPARATOR . 'storage';
        $identityStore = new InstallationIdentityStore($storage);
        $installationIdentity = $identityStore->getOrCreate();
        $database = new Database(new Config($basePath . DIRECTORY_SEPARATOR . 'config'));
        $baselineCatalog = CanonicalSchemaBaselineCatalog::forProject($basePath);
        $registry = CoreMigrationRegistry::forProject($basePath);
        $ledger = new CoreMigrationLedger($database->tables());
        $installationState = new InstallationState($storage);
        $committedStore = new CommittedLifecycleStateStore($storage);

        $entry = new LegacyToV3BootstrapEntry(
            $basePath,
            $publicRoot,
            $identityStore,
            $installationState,
            new InstalledStateInspector($committedStore),
            static function () use ($database, $basePath): ExistingInstallEvidence {
                $schema = false;
                try {
                    $schema = (new InstallerSchemaState($database))->isReady();
                } catch (\Throwable) {
                }
                return new ExistingInstallEvidence($schema, is_file($basePath . DIRECTORY_SEPARATOR . '.env'));
            },
            static function (InstalledStateInspection $installed, callable $connection, string $schemaPath, CoreMigrationRegistry $registry) use ($database, $baselineCatalog, $ledger): LegacyClassificationResult {
                return (new LegacyRuntimeClassifier(
                    new CanonicalSchemaBaselineVerifier($database->tables()),
                    $baselineCatalog,
                    $ledger
                ))->classify($installed, $connection(), $schemaPath, $registry);
            },
            static fn (): PDO => $database->connection(),
            $basePath . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema.sql',
            $registry,
            new PackageV3SourceRelativePlanner(),
            new LegacyBootstrapSourceArtifactStore($storage, $registry)
        );

        return new self(
            $deploymentContext,
            new ZipIntakeService($basePath, null, null, null, null, $installationIdentity->value()),
            new PackageManifestReader(),
            new PackageInventoryVerifier(),
            $entry
        );
    }

    public function prepare(string $zip): LegacyBootstrapPreparation
    {
        self::assertRetired();
        try {
            $payload = $this->intake->intake($zip);
            try {
                $manifest = $this->manifestReader->read($payload);
                $this->inventoryVerifier->verify($payload, $manifest->contract()->inventory(), [PackageManifestReader::PATH]);
                return $this->entry->prepare($manifest);
            } finally {
                $payload->cleanup();
            }
        } catch (\Throwable $exception) {
            return new LegacyBootstrapPreparation(
                LegacyBootstrapPreparation::BLOCKED,
                $exception->getMessage(),
                'legacy-to-v3-bootstrap-invalid-package',
                hash('sha256', $exception->getMessage()),
                '',
                LegacyClassificationResult::unknown($exception->getMessage()),
                null,
                [['action' => 'BLOCK', 'reason' => $exception->getMessage()]],
                null
            );
        }
    }

    public function deployment(): DeploymentContext
    {
        return $this->deployment;
    }

    private static function assertRetired(): never
    {
        throw new \RuntimeException('Legacy bootstrap preparation is retired.');
    }
}
