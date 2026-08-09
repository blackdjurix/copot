<?php

declare(strict_types=1);

use Copot\Core\CommittedLifecycleState;
use Copot\Core\HealthGateMatrix;
use Copot\Core\HealthGateResult;
use Copot\Core\InstalledStateInspection;
use Copot\Core\InstallationIdentity;
use Copot\Core\LifecycleOperationRecord;
use Copot\Core\SystemHealthAggregator;
use Copot\Core\SystemHealthContext;
use Copot\Core\SystemHealthFindingSeverity;
use Copot\Core\SystemHealthOverallStatus;
use Copot\Core\SystemHealthProducer;
use Copot\Core\SystemHealthProducerAvailability;
use Copot\Core\SystemHealthProducerResult;
use Copot\Core\WebcoreLifecycleHealthEvidence;
use Copot\Core\WebcoreLifecycleHealthEvidenceSource;
use Copot\Core\WebcoreLifecycleHealthProducer;

$basePath = dirname(__DIR__);
chdir($basePath);
require $basePath . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) { throw new RuntimeException($message); }
};

final class Wu2EvidenceSource implements WebcoreLifecycleHealthEvidenceSource
{
    public function __construct(private WebcoreLifecycleHealthEvidence $evidence) {}
    public function collect(SystemHealthContext $context): WebcoreLifecycleHealthEvidence { return $this->evidence; }
}

final class Wu2ThrowingSource implements WebcoreLifecycleHealthEvidenceSource
{
    public function collect(SystemHealthContext $context): WebcoreLifecycleHealthEvidence { throw new RuntimeException('SQLSTATE[HY000] /private/lifecycle-operation'); }
}

final class Wu2FakeProducer implements SystemHealthProducer
{
    public function __construct(private string $name, private $callback) {}
    public function source(): string { return $this->name; }
    public function required(): bool { return false; }
    public function report(SystemHealthContext $context): SystemHealthProducerResult { return ($this->callback)($context); }
}

$context = new SystemHealthContext(new InstallationIdentity('inst_' . str_repeat('c', 32)), 'viewer');
$committed = new CommittedLifecycleState('0.13.0', 'release', 'tree', 1, 'schema', 'migration', new DateTimeImmutable('now'), 'integrity');
$readyEvidence = new WebcoreLifecycleHealthEvidence(InstalledStateInspection::committed($committed->snapshot()), $committed, new HealthGateMatrix([HealthGateResult::pass('database-schema')]), new HealthGateMatrix([HealthGateResult::pass('migration-ledger')]), new HealthGateMatrix([HealthGateResult::pass('runtime')]), null, null, true, '2026-08-09T00:00:00+00:00', 'fresh');
$producer = new WebcoreLifecycleHealthProducer(new Wu2EvidenceSource($readyEvidence));
$report = (new SystemHealthAggregator())->aggregate($context, [$producer]);
$assert($report->status() === SystemHealthOverallStatus::OPERATIONAL && $report->findings() === [], 'Ready Webcore evidence did not produce an operational zero-finding report.');
$assert($report->toArray()['producers'][0]['source'] === WebcoreLifecycleHealthProducer::SOURCE, 'Webcore producer source was not exposed correctly.');

$failedEvidence = new WebcoreLifecycleHealthEvidence(
    InstalledStateInspection::inconsistent('SQLSTATE[HY000] /var/private/lifecycle-operation'),
    null,
    new HealthGateMatrix([HealthGateResult::fail('database-schema', 'Required Core tables are missing: app_users')]),
    new HealthGateMatrix([HealthGateResult::fail('migration-identity', 'Migration identity contains internal details.')]),
    new HealthGateMatrix([HealthGateResult::fail('runtime', 'Runtime check failed at C:\\internal\\runtime.php')]),
    new HealthGateMatrix([HealthGateResult::fail('package-file:app/foo.php', 'Package integrity mismatch at /private/package/foo.php')]),
    null,
    true
);
$failedReport = (new SystemHealthAggregator())->aggregate($context, [new WebcoreLifecycleHealthProducer(new Wu2EvidenceSource($failedEvidence))]);
$failedPayload = $failedReport->toArray();
$assert($failedReport->status() === SystemHealthOverallStatus::DEGRADED, 'Webcore ERROR findings did not aggregate to DEGRADED.');
$assert(count($failedReport->findings()) === 5, 'Each supplied authoritative failed lifecycle evidence source was not adapted into a finding.');
$assert(!str_contains(json_encode($failedPayload, JSON_THROW_ON_ERROR), 'SQLSTATE') && !str_contains(json_encode($failedPayload, JSON_THROW_ON_ERROR), 'C:\\internal') && !str_contains(json_encode($failedPayload, JSON_THROW_ON_ERROR), 'app_users'), 'Unsafe lifecycle diagnostic detail leaked through WU2.');
$assert(count(array_filter($failedReport->findings(), static fn ($finding): bool => $finding->severity() === SystemHealthFindingSeverity::ERROR)) === 5, 'Webcore producer did not preserve its producer-owned ERROR severity.');

$operation = new LifecycleOperationRecord('operation', 'update', '0.13.0', 'release', str_repeat('a', 64), 'staging', str_repeat('b', 64), str_repeat('c', 64), LifecycleOperationRecord::INDETERMINATE, 1, null, null, 'indeterminate', gmdate(DATE_ATOM), gmdate(DATE_ATOM), 'Operation contains sensitive internal detail.');
$operationEvidence = new WebcoreLifecycleHealthEvidence(InstalledStateInspection::committed($committed->snapshot()), $committed, null, null, null, null, $operation, true);
$operationReport = (new SystemHealthAggregator())->aggregate($context, [new WebcoreLifecycleHealthProducer(new Wu2EvidenceSource($operationEvidence))]);
$assert($operationReport->status() === SystemHealthOverallStatus::CRITICAL, 'Indeterminate lifecycle operation did not become CRITICAL.');
$assert($operationReport->findings()[0]->severity() === SystemHealthFindingSeverity::CRITICAL, 'Indeterminate operation severity was not preserved.');

$missingEvidence = new WebcoreLifecycleHealthEvidence(null, null, null, null, null, null, null, false);
$missingReport = (new SystemHealthAggregator())->aggregate($context, [new WebcoreLifecycleHealthProducer(new Wu2EvidenceSource($missingEvidence))]);
$assert($missingReport->status() === SystemHealthOverallStatus::DEGRADED, 'Missing required Webcore evidence did not become non-operational.');
$assert($missingReport->toArray()['producers'][0]['availability'] === SystemHealthProducerAvailability::UNAVAILABLE, 'Missing Webcore evidence did not remain distinct from READY.');

$throwingReport = (new SystemHealthAggregator())->aggregate($context, [
    new WebcoreLifecycleHealthProducer(new Wu2ThrowingSource()),
    new Wu2FakeProducer('producer.other', static fn (): SystemHealthProducerResult => new SystemHealthProducerResult('producer.other', SystemHealthProducerAvailability::READY)),
]);
$throwingPayload = $throwingReport->toArray();
$assert(count($throwingPayload['producers']) === 2, 'Webcore producer failure did not remain isolated from another producer.');
$assert(!str_contains(json_encode($throwingPayload, JSON_THROW_ON_ERROR), 'SQLSTATE') && !str_contains(json_encode($throwingPayload, JSON_THROW_ON_ERROR), 'lifecycle-operation'), 'Webcore producer exception detail leaked through failure isolation.');

$scopeSeen = [];
$scopedSource = new class($scopeSeen) implements WebcoreLifecycleHealthEvidenceSource {
    private array $seen;
    public function __construct(array &$seen) { $this->seen =& $seen; }
    public function collect(SystemHealthContext $context): WebcoreLifecycleHealthEvidence { $this->seen[] = $context->installation()->value(); return new WebcoreLifecycleHealthEvidence(InstalledStateInspection::fresh(), null, null, null, null, null, null, true); }
};
$scopedProducer = new WebcoreLifecycleHealthProducer($scopedSource);
$aggregator = new SystemHealthAggregator();
$aggregator->aggregate($context, [$scopedProducer]);
$aggregator->aggregate(new SystemHealthContext(new InstallationIdentity('inst_' . str_repeat('d', 32)), 'viewer'), [$scopedProducer]);
$assert(count(array_unique($scopeSeen)) === 2, 'Webcore evidence collection was not installation-scoped.');

$noScan = new class implements WebcoreLifecycleHealthEvidenceSource {
    public function collect(SystemHealthContext $context): WebcoreLifecycleHealthEvidence { return new WebcoreLifecycleHealthEvidence(InstalledStateInspection::fresh(), null, null, null, null, null, null, true); }
};
$noScanReport = (new WebcoreLifecycleHealthProducer($noScan))->report($context);
$assert($noScanReport->findings() === [], 'Normal WU2 producer execution invented an expensive package/filesystem check.');

echo "System Health WU2 focused tests passed ({$assertions} assertions)." . PHP_EOL;
