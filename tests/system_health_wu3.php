<?php

declare(strict_types=1);

use Copot\Core\BundledCoreModuleHealthEvidence;
use Copot\Core\BundledCoreModuleHealthEvidenceSource;
use Copot\Core\BundledCoreModuleHealthProducer;
use Copot\Core\HealthGateMatrix;
use Copot\Core\HealthGateResult;
use Copot\Core\InstalledStateStatus;
use Copot\Core\InstallationIdentity;
use Copot\Core\ModuleLifecycleInspection;
use Copot\Core\SystemHealthAggregator;
use Copot\Core\SystemHealthContext;
use Copot\Core\SystemHealthFindingSeverity;
use Copot\Core\SystemHealthOverallStatus;

$basePath = dirname(__DIR__);
chdir($basePath);
require $basePath . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};

final class Wu3EvidenceSource implements BundledCoreModuleHealthEvidenceSource
{
    public function __construct(private array $evidence, private array &$installations = []) {}
    public function collect(SystemHealthContext $context): array
    {
        $this->installations[] = $context->installation()->value();
        return $this->evidence;
    }
}

$first = new BundledCoreModuleHealthEvidence('content', ModuleLifecycleInspection::fresh(), null, null, null, true, '2026-08-09T00:00:00+00:00', 'fresh');
$second = new BundledCoreModuleHealthEvidence('media', ModuleLifecycleInspection::fresh());
$source = new Wu3EvidenceSource([$first, $second]);
$context = new SystemHealthContext(new InstallationIdentity('inst_' . str_repeat('e', 32)), 'viewer');
$report = (new SystemHealthAggregator())->aggregate($context, [new BundledCoreModuleHealthProducer($source)]);
$assert($report->status() === SystemHealthOverallStatus::OPERATIONAL, 'Healthy bundled Core Module evidence did not produce OPERATIONAL.');
$assert($report->findings() === [], 'Healthy bundled Core Module evidence produced an unexpected finding.');
$assert($report->toArray()['producers'][0]['source'] === BundledCoreModuleHealthProducer::SOURCE, 'Bundled Core Module producer source was not exposed.');

$failed = new BundledCoreModuleHealthEvidence(
    'taxonomy',
    ModuleLifecycleInspection::inconsistent('SQLSTATE[HY000] /private/modules/taxonomy'),
    new HealthGateMatrix([HealthGateResult::fail('schema-table', 'Missing table app_taxonomy at C:\\internal\\db')]),
    new HealthGateMatrix([HealthGateResult::fail('migration-indeterminate', 'Migration internals at /private/migration')]),
    new HealthGateMatrix([HealthGateResult::fail('package-file', 'Package path /private/modules/taxonomy/file.php')])
);
$failedReport = (new SystemHealthAggregator())->aggregate($context, [new BundledCoreModuleHealthProducer(new Wu3EvidenceSource([$failed]))]);
$failedPayload = json_encode($failedReport->toArray(), JSON_THROW_ON_ERROR);
$assert($failedReport->status() === SystemHealthOverallStatus::CRITICAL, 'Critical bundled module evidence did not aggregate to CRITICAL.');
$assert(count($failedReport->findings()) === 4, 'Bundled lifecycle, schema, migration, and integrity findings were not all adapted.');
$assert(count(array_filter($failedReport->findings(), static fn ($finding): bool => $finding->severity() === SystemHealthFindingSeverity::ERROR)) === 3, 'Producer-owned ERROR severities were not preserved.');
$assert(count(array_filter($failedReport->findings(), static fn ($finding): bool => $finding->severity() === SystemHealthFindingSeverity::CRITICAL)) === 1, 'Indeterminate module evidence did not preserve CRITICAL severity.');
$assert(!str_contains($failedPayload, 'SQLSTATE') && !str_contains($failedPayload, 'private') && !str_contains($failedPayload, 'app_taxonomy'), 'Unsafe module evidence leaked through the report boundary.');
$assert(array_reduce($failedReport->findings(), static fn (bool $ok, $finding): bool => $ok && $finding->target() === 'taxonomy', true), 'Module finding target ownership was not preserved.');

$missing = (new SystemHealthAggregator())->aggregate($context, [new BundledCoreModuleHealthProducer(new Wu3EvidenceSource([
    new BundledCoreModuleHealthEvidence('users-access', null, null, null, null, false),
]))]);
$assert($missing->status() === SystemHealthOverallStatus::DEGRADED, 'Missing required module evidence became healthy or only attention-level.');
$assert($missing->toArray()['producers'][0]['availability'] === 'unavailable', 'Missing module evidence did not remain distinct from READY.');

$scope = [];
$scoped = new Wu3EvidenceSource([$second], $scope);
$producer = new BundledCoreModuleHealthProducer($scoped);
$aggregator = new SystemHealthAggregator();
$aggregator->aggregate($context, [$producer]);
$aggregator->aggregate(new SystemHealthContext(new InstallationIdentity('inst_' . str_repeat('f', 32)), 'viewer'), [$producer]);
$assert(count(array_unique($scope)) === 2, 'Bundled module collection was not installation-scoped.');

$assert(count($report->producers()) === 1, 'WU3 introduced per-module reporters instead of one shared adoption producer.');
$assert($report->findings() === [], 'WU3 healthy adoption introduced a synthetic OK finding.');

echo "System Health WU3 focused tests passed ({$assertions} assertions)." . PHP_EOL;
