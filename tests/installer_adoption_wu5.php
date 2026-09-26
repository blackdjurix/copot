<?php

declare(strict_types=1);

use Copot\Core\AdoptionBoundaryClassification;
use Copot\Core\AdoptionOrchestrationResult;
use Copot\Core\InstallerAdoptionDecision;
use Copot\Core\InstallerAdoptionIntegration;
use Copot\Core\InstallerDatabaseOccupancy;
use Copot\Core\InstallerDatabaseOccupancyResult;
use Copot\Core\InstallerIntent;
use Copot\Core\InstallerOwnershipProof;
use Copot\Core\InstallerRoutingPlanner;
use Copot\Core\InstallationIdentity;

$basePath = dirname(__DIR__);
chdir($basePath);
require $basePath . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};

$proof = new InstallerOwnershipProof(
    new InstallationIdentity('inst_aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'),
    'alpha',
    'core-schema-generation:test',
    str_repeat('a', 64)
);
$occupancy = new InstallerDatabaseOccupancyResult(InstallerDatabaseOccupancy::COPOT, [], ['alpha'], [], true);
$routing = (new InstallerRoutingPlanner())->plan($occupancy, InstallerIntent::ADOPT, 'alpha');
$integration = new InstallerAdoptionIntegration();
$ready = static fn (string $classification = AdoptionBoundaryClassification::GENERALIZED_ADOPTION_COMPATIBLE): AdoptionOrchestrationResult => new AdoptionOrchestrationResult(
    AdoptionOrchestrationResult::READY,
    str_repeat('1', 64),
    str_repeat('2', 64),
    $classification,
    [],
    'Fresh compatibility proof passed.'
);

// Ready generalized and zero-resolution Adoption reaches terminal Adopt without mutation authority.
$decision = $integration->decide($routing, $ready(), [$proof]);
$assert($decision->state() === InstallerAdoptionDecision::TERMINAL_ADOPT && $decision->nextAction() === 'complete_adoption', 'Ready generalized Adoption did not reach terminal Adopt.');
$assert($decision->namespace() === 'alpha' && $decision->installationIdentity() === $proof->installationId(), 'Installer did not preserve installation identity and namespace.');
$assert($decision->preservesExistingState() && !$decision->administratorInputAllowed(), 'Terminal Adopt did not preserve existing Administrator/User/Site state.');

// Historical exact-match remains an allowed positive terminal path.
$exact = $integration->decide($routing, $ready(AdoptionBoundaryClassification::EXACT_MATCH_ADOPTION), [$proof]);
$assert($exact->terminal(), 'Historical exact-match Adoption was not preserved at the Installer boundary.');

// Blocked, suspended/recovery, and stale orchestration results cannot reach terminal Adopt.
$blocked = new AdoptionOrchestrationResult(AdoptionOrchestrationResult::BLOCKED, str_repeat('3', 64), str_repeat('4', 64), AdoptionBoundaryClassification::FAIL_CLOSED, [], 'No authorized continuation.');
$suspended = new AdoptionOrchestrationResult(AdoptionOrchestrationResult::SUSPENDED, str_repeat('5', 64), str_repeat('6', 64), AdoptionBoundaryClassification::GENERALIZED_ADOPTION_RESOLVABLE_GAPS, [], 'Recovery is required.');
$stale = new AdoptionOrchestrationResult(AdoptionOrchestrationResult::STALE, str_repeat('7', 64), str_repeat('8', 64), AdoptionBoundaryClassification::GENERALIZED_ADOPTION_RESOLVABLE_GAPS, [], 'Installation identity changed.');
$assert($integration->decide($routing, $blocked, [$proof])->state() === InstallerAdoptionDecision::BLOCKED, 'Blocked Adoption reached terminal Adopt.');
$assert($integration->decide($routing, $suspended, [$proof])->state() === InstallerAdoptionDecision::SUSPENDED, 'Suspended/recovery-required Adoption reached terminal Adopt.');
$assert($integration->decide($routing, $stale, [$proof])->state() === InstallerAdoptionDecision::STALE, 'Stale Adoption evidence reached terminal Adopt.');

// Missing, mismatched, or ambiguous ownership evidence fails closed and does not replace state.
$missingProof = $integration->decide($routing, $ready(), []);
$wrongNamespace = new InstallerOwnershipProof(new InstallationIdentity('inst_bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'), 'beta', 'core-schema-generation:test', str_repeat('b', 64));
$wrongProof = $integration->decide($routing, $ready(), [$wrongNamespace]);
$duplicateProof = new InstallerOwnershipProof(new InstallationIdentity('inst_cccccccccccccccccccccccccccccccc'), 'alpha', 'core-schema-generation:test', str_repeat('c', 64));
$ambiguousProof = $integration->decide($routing, $ready(), [$proof, $duplicateProof]);
$assert($missingProof->state() === InstallerAdoptionDecision::STALE && $wrongProof->state() === InstallerAdoptionDecision::STALE && $ambiguousProof->state() === InstallerAdoptionDecision::STALE, 'Invalid ownership/namespace evidence was not fail-closed.');

// Installer source remains a consumer/decision boundary, not a lifecycle or mutation authority.
$committerSource = (string) file_get_contents($basePath . '/app/Core/InstallerInstallationCommitter.php');
$integrationSource = (string) file_get_contents($basePath . '/app/Core/InstallerAdoptionIntegration.php');
$adoptStart = strpos($committerSource, 'if ($routing->route() === InstallerRoutingPlanner::ADOPT)');
$adoptEnd = strpos($committerSource, '// Validate every staged input', $adoptStart === false ? 0 : $adoptStart);
$adoptBlock = $adoptStart !== false && $adoptEnd !== false ? substr($committerSource, $adoptStart, $adoptEnd - $adoptStart) : '';
$assert(str_contains($committerSource, 'AdoptionOrchestrationResult $adoptionResult'), 'Installer committer does not consume the WU4 orchestration result.');
$assert(str_contains($committerSource, 'new InstallerAdoptionIntegration()'), 'Installer committer does not use the bounded Adoption integration gate.');
$assert(!str_contains($integrationSource, 'PDO') && !str_contains($integrationSource, 'schema') && !str_contains($integrationSource, 'migration') && !str_contains($integrationSource, 'execute('), 'Installer Adoption integration acquired lifecycle or schema authority.');
$assert(str_contains($committerSource, 'administratorInput') && str_contains($committerSource, "'administrator' => null"), 'Installer Adoption did not retain the Administrator/User/Site preservation boundary.');
$assert($adoptBlock !== '' && !str_contains($adoptBlock, 'environment->persist') && !str_contains($adoptBlock, 'schema->install'), 'Terminal Adopt acquired environment or schema mutation.');

echo "WU5 Installer Adoption integration focused tests passed ({$assertions} assertions)." . PHP_EOL;
