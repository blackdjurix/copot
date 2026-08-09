<?php

declare(strict_types=1);

use Copot\Core\DatabaseTableNames;
use Copot\Core\InstallerDatabaseOccupancy;
use Copot\Core\InstallerDatabaseOccupancyClassifier;
use Copot\Core\InstallerIntent;
use Copot\Core\InstallerNamespaceAvailability;
use Copot\Core\InstallerNamespaceAnalyzer;
use Copot\Core\InstallerRoutingPlanner;
use Copot\Core\InstallationIdentity;
use Copot\Core\InstallerOwnershipProof;
use Copot\Core\InstallerOwnershipProofAssembler;
use Copot\Core\InstallerDatabaseProbe;
use Copot\Core\InstallerDatabaseSetup;
use Copot\Core\InstallerEnvironmentWriter;
use Copot\Core\InstallerSchemaRunner;
use Copot\Core\InstallationMutex;
use Copot\Core\CoreMigrationRegistry;

$basePath = dirname(__DIR__);
chdir($basePath);
require $basePath . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};
$objects = static function (string $namespace = ''): array {
    $tables = new DatabaseTableNames($namespace);
    return array_merge(
        array_map(fn (string $name): string => $tables->table($name), DatabaseTableNames::coreTables()),
        array_map(fn (string $name): string => $tables->moduleTable($name), DatabaseTableNames::moduleTables())
    );
};

$classifier = new InstallerDatabaseOccupancyClassifier();
$empty = $classifier->classify([]);
$assert($empty->classification() === InstallerDatabaseOccupancy::EMPTY, 'Empty database was not classified as EMPTY.');
$foreign = $classifier->classify(['external_orders']);
$assert($foreign->classification() === InstallerDatabaseOccupancy::FOREIGN_ONLY, 'Foreign-only database was not classified as FOREIGN_ONLY.');
$assert($classifier->classify(['users'])->classification() === InstallerDatabaseOccupancy::FOREIGN_ONLY, 'A generic physical collision falsely proved COPOT ownership.');
$unprovenComplete = $classifier->classify($objects());
$assert($unprovenComplete->classification() === InstallerDatabaseOccupancy::AMBIGUOUS, 'A complete COPOT-shaped foreign table set falsely proved ownership.');
$proof = static function (string $namespace, string $hex): InstallerOwnershipProof {
    return new InstallerOwnershipProof(new InstallationIdentity('inst_' . $hex), $namespace, 'core-schema-generation:test', str_repeat($hex[0], 64));
};
$emptyProof = $proof('', 'a123456789abcdef123456789abcdef1');
$alphaProof = $proof('alpha', 'b123456789abcdef123456789abcdef1');
$betaProof = $proof('beta', 'c123456789abcdef123456789abcdef1');
$copotEmpty = $classifier->classify($objects(), [$emptyProof]);
$assert($copotEmpty->classification() === InstallerDatabaseOccupancy::COPOT && $copotEmpty->copotNamespaces() === [''], 'Existing COPOT empty namespace was not detected.');
$copotAlpha = $classifier->classify($objects('alpha'), [$alphaProof]);
$assert($copotAlpha->classification() === InstallerDatabaseOccupancy::COPOT && $copotAlpha->copotNamespaces() === ['alpha'], 'Existing non-empty COPOT namespace was not detected.');
$multiple = $classifier->classify(array_merge($objects('alpha'), $objects('beta')), [$alphaProof, $betaProof]);
$assert($multiple->classification() === InstallerDatabaseOccupancy::MULTIPLE_COPOT, 'Multiple COPOT installations were not classified.');
$mixed = $classifier->classify(array_merge($objects('alpha'), ['external_orders']), [$alphaProof]);
$assert($mixed->classification() === InstallerDatabaseOccupancy::MIXED, 'Mixed COPOT and foreign objects were not classified.');
$ambiguous = $classifier->classify(['users', 'roles']);
$assert($ambiguous->classification() === InstallerDatabaseOccupancy::AMBIGUOUS, 'Incomplete COPOT-shaped evidence did not fail closed.');

$analyzer = new InstallerNamespaceAnalyzer();
$available = $analyzer->analyze(['external_orders'], 'alpha', $foreign);
$assert($available->availability() === InstallerNamespaceAvailability::AVAILABLE, 'Unused namespace was not available in a foreign database.');
$partial = $analyzer->analyze(['alpha_users'], 'alpha', $foreign);
$assert($partial->availability() === InstallerNamespaceAvailability::PARTIAL_COLLISION, 'Partial namespace collision was not classified.');
$full = $analyzer->analyze($objects('alpha'), 'alpha', $foreign);
$assert($full->availability() === InstallerNamespaceAvailability::FULL_COLLISION, 'Unproven full namespace collision was not classified.');
$owned = $analyzer->analyze($objects('alpha'), 'alpha', $copotAlpha);
$assert($owned->availability() === InstallerNamespaceAvailability::OWNED_BY_COPOT, 'COPOT-owned namespace was not classified.');
$assert($analyzer->analyze(['users', 'roles'], '', $ambiguous)->availability() === InstallerNamespaceAvailability::AMBIGUOUS, 'Ambiguous namespace evidence did not fail closed.');

$planner = new InstallerRoutingPlanner();
$assert($planner->plan($empty, InstallerIntent::FRESH)->route() === InstallerRoutingPlanner::FRESH, 'Fresh empty-database routing failed.');
$assert($planner->plan($empty, InstallerIntent::FRESH, 'alpha')->namespace() === 'alpha', 'Explicit non-empty fresh namespace was not preserved.');
$assert($planner->plan($foreign, InstallerIntent::COEXIST, 'alpha')->route() === InstallerRoutingPlanner::COEXIST, 'Available coexistence routing failed.');
$blocked = static function (callable $operation) use ($assert): void {
    try { $operation(); $assert(false, 'Unsafe routing unexpectedly succeeded.'); } catch (Throwable) { $assert(true, 'Unsafe routing was blocked.'); }
};
$blocked(fn () => $planner->plan($foreign, InstallerIntent::FRESH));
$blocked(fn () => $planner->plan($foreign, InstallerIntent::COEXIST, ''));
$blocked(fn () => $planner->plan($copotAlpha, InstallerIntent::FRESH, 'beta'));
$blocked(fn () => $planner->plan($unprovenComplete, InstallerIntent::ADOPT));
$contradictory = $classifier->classify($objects('alpha'), [$proof('', 'd123456789abcdef123456789abcdef1')]);
$assert($contradictory->classification() === InstallerDatabaseOccupancy::AMBIGUOUS, 'Contradictory namespace ownership evidence did not fail closed.');
$duplicateProof = $classifier->classify($objects('alpha'), [$alphaProof, $proof('alpha', 'e123456789abcdef123456789abcdef1')]);
$assert($duplicateProof->classification() === InstallerDatabaseOccupancy::AMBIGUOUS, 'Conflicting COPOT ownership evidence did not fail closed.');
$unhealthyProof = new InstallerOwnershipProof(new InstallationIdentity('inst_f123456789abcdef123456789abcdef1'), 'alpha', 'core-schema-generation:test', str_repeat('f', 64), false, true);
$assert($classifier->classify($objects('alpha'), [$unhealthyProof])->classification() === InstallerDatabaseOccupancy::AMBIGUOUS, 'Unhealthy ownership evidence did not fail closed.');
$assert($planner->plan($copotEmpty, InstallerIntent::ADOPT)->namespace() === '', 'Adoption did not preserve the legitimate empty namespace.');
$assert($planner->plan($copotAlpha, InstallerIntent::ADOPT)->namespace() === 'alpha', 'Adoption did not preserve the non-empty namespace.');
$assert($planner->plan($copotAlpha, InstallerIntent::MIGRATE)->route() === InstallerRoutingPlanner::MIGRATE, 'Migration/update routing failed.');
$blocked(fn () => $planner->plan($mixed, InstallerIntent::ADOPT));
$blocked(fn () => $planner->plan($ambiguous, InstallerIntent::COEXIST, 'alpha'));

$assert((new DatabaseTableNames())->table('users') === 'users', 'WU2 empty Core naming compatibility changed.');
$assert((new DatabaseTableNames('alpha'))->table('users') === 'alpha_users', 'WU2 namespaced Core naming compatibility changed.');
$assert((new DatabaseTableNames('alpha'))->moduleTable('content') === 'alpha_content', 'WU3 Module namespace compatibility changed.');
$assert(in_array('external_orders', $mixed->objects(), true), 'Foreign objects were not retained as unclaimed evidence.');

$proofStorage = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-wu5-proof-' . bin2hex(random_bytes(5));
mkdir($proofStorage, 0700, true);
$pdoWithoutDriver = (new ReflectionClass(PDO::class))->newInstanceWithoutConstructor();
$assembler = new InstallerOwnershipProofAssembler($proofStorage, new CoreMigrationRegistry('copot-core-current', []));
$assert($assembler->assemble($pdoWithoutDriver, $objects('alpha')) === [], 'Missing authoritative installation evidence did not fail closed.');

$serviceStorage = $proofStorage . DIRECTORY_SEPARATOR . 'service';
mkdir($serviceStorage, 0700, true);
$serviceInspection = ['server' => ['vendor' => 'test', 'version' => '1.0.0'], 'occupancy' => $copotAlpha];
$serviceProbe = new class($serviceInspection) extends InstallerDatabaseProbe {
    public function __construct(private array $inspection) { parent::__construct(); }
    public function inspect(array $configuration, array $proofs = []): array { return $this->inspection; }
};
$service = new InstallerDatabaseSetup(
    $serviceProbe,
    new InstallerEnvironmentWriter($serviceStorage . DIRECTORY_SEPARATOR . '.env'),
    new InstallerSchemaRunner($serviceStorage . DIRECTORY_SEPARATOR . 'unused-schema.sql'),
    new InstallationMutex($serviceStorage)
);
$serviceConfiguration = ['host' => '127.0.0.1', 'port' => 3306, 'database' => 'existing', 'username' => 'root', 'password' => '', 'namespace' => 'alpha'];
$adoptionResult = $service->install($serviceConfiguration, true, InstallerIntent::ADOPT, [$alphaProof]);
$assert($adoptionResult['route'] === InstallerRoutingPlanner::ADOPT && $adoptionResult['namespace'] === 'alpha' && $adoptionResult['statement_count'] === 0, 'Adoption did not succeed through the installer service path.');
$migrationResult = $service->install($serviceConfiguration, true, InstallerIntent::MIGRATE, [$alphaProof]);
$assert($migrationResult['route'] === InstallerRoutingPlanner::MIGRATE && $migrationResult['namespace'] === 'alpha' && $migrationResult['statement_count'] === 0, 'Migration/update did not succeed through the installer service path.');
@unlink($serviceStorage . DIRECTORY_SEPARATOR . '.env');
@rmdir($serviceStorage);
@rmdir($proofStorage);

echo "WU5 installer routing focused tests passed ({$assertions} assertions)." . PHP_EOL;
