<?php

$base = dirname(__DIR__);
$service = (string) file_get_contents($base . '/app/Core/PackageLifecycleService.php');
$factory = (string) file_get_contents($base . '/app/Core/PackageLifecycleFactory.php');

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$assert(str_contains($service, 'prepareCommittedSourceAuthorityFromRuntime'), 'Production source-authority orchestration entrypoint is missing.');
$assert(str_contains($service, 'DatabaseScope::fromOwnershipCatalog'), 'Orchestration does not resolve the canonical database scope.');
$assert(str_contains($service, 'CoreSchemaGenerationStore'), 'Orchestration does not read canonical schema-generation evidence.');
$assert(str_contains($service, 'CoreSchemaMaterializer())->generationIdentity'), 'Orchestration does not verify the authoritative schema-generation identity.');
$assert(str_contains($service, '$generationEvidence'), 'Orchestration does not bind verified schema-generation evidence to the database scope.');
$assert(str_contains($service, 'produceEvidence($recovery, $scope)'), 'Orchestration does not obtain Guard evidence from the canonical producer.');
$assert(str_contains($service, 'new ScopedDatabaseConsistencyEvidence'), 'Orchestration does not compose scoped database evidence.');
$assert(str_contains($service, 'stateIdentity($connection, $scope->databaseIdentity(), $scope)'), 'Orchestration does not perform bounded database state revalidation.');
$assert(str_contains($service, 'prepareCommittedSourceAuthority($committed'), 'Orchestration does not delegate to the canonical preparation path.');
$assert(str_contains($service, 'NormalWebcoreRecoveryCaptureService::forOperation'), 'Recovery identity is not derived through the canonical source-authority identity algorithm.');
$assert(str_contains($factory, '$storage,') && str_contains($factory, '$recoveryComposition?->databaseIdentity'), 'Factory does not bind the runtime composition to the installation storage/database.');
$assert(str_contains($factory, '$recoveryComposition?->maintenance instanceof InstallationScopedMaintenanceGuard'), 'Factory does not pass the scoped Guard composition.');
$assert(!str_contains($service, 'MariaDbReadOnlyQuiescence'), 'Source-authority orchestration directly introduced global MariaDB quiescence.');

echo "Committed source-authority runtime orchestration assertions: {$assertions}\n";
