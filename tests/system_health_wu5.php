<?php

declare(strict_types=1);

use Copot\Core\BundledCoreModuleHealthEvidence;
use Copot\Core\BundledCoreModuleHealthEvidenceSource;
use Copot\Core\BundledCoreModuleHealthProducer;
use Copot\Core\CommittedLifecycleState;
use Copot\Core\HealthGateMatrix;
use Copot\Core\HealthGateResult;
use Copot\Core\InstalledStateInspection;
use Copot\Core\InstallationIdentity;
use Copot\Core\LifecycleOperationRecord;
use Copot\Core\ModuleLifecycleInspection;
use Copot\Core\ServerRuntimeHealthEvidence;
use Copot\Core\ServerRuntimeHealthEvidenceSource;
use Copot\Core\ServerRuntimeHealthProducer;
use Copot\Core\SystemHealthAggregator;
use Copot\Core\SystemHealthContext;
use Copot\Core\SystemHealthFindingSeverity;
use Copot\Core\SystemHealthOverallStatus;
use Copot\Core\SystemHealthProducer;
use Copot\Core\SystemHealthProducerResult;
use Copot\Core\SystemHealthProducerAvailability;
use Copot\Core\WebcoreLifecycleHealthEvidence;
use Copot\Core\WebcoreLifecycleHealthEvidenceSource;
use Copot\Core\WebcoreLifecycleHealthProducer;

$basePath = dirname(__DIR__);
chdir($basePath);
require $basePath . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};

final class Wu5WebcoreSource implements WebcoreLifecycleHealthEvidenceSource
{
    public function __construct(private WebcoreLifecycleHealthEvidence $evidence) {}
    public function collect(SystemHealthContext $context): WebcoreLifecycleHealthEvidence { return $this->evidence; }
}

final class Wu5ModuleSource implements BundledCoreModuleHealthEvidenceSource
{
    public function __construct(private array $evidence) {}
    public function collect(SystemHealthContext $context): array { return $this->evidence; }
}

final class Wu5RuntimeSource implements ServerRuntimeHealthEvidenceSource
{
    public function __construct(private ServerRuntimeHealthEvidence $evidence) {}
    public function collect(SystemHealthContext $context): ServerRuntimeHealthEvidence { return $this->evidence; }
}

final class Wu5ThrowingProducer implements SystemHealthProducer
{
    public function source(): string { return 'zz.throwing'; }
    public function required(): bool { return true; }
    public function report(SystemHealthContext $context): SystemHealthProducerResult { throw new RuntimeException('SQLSTATE[HY000] /private/secret'); }
}

$context = new SystemHealthContext(new InstallationIdentity('inst_' . str_repeat('3', 32)), 'viewer');
$committed = new CommittedLifecycleState('0.13.0', 'release', 'tree', 1, 'schema', 'migration', new DateTimeImmutable('now'), 'integrity');
$readyWebcore = new WebcoreLifecycleHealthEvidence(InstalledStateInspection::committed($committed->snapshot()), $committed, new HealthGateMatrix([HealthGateResult::pass('database')]), new HealthGateMatrix([HealthGateResult::pass('migration')]), new HealthGateMatrix([HealthGateResult::pass('runtime')]), null, null, true);
$readyModules = [
    new BundledCoreModuleHealthEvidence('content', ModuleLifecycleInspection::fresh()),
    new BundledCoreModuleHealthEvidence('media', ModuleLifecycleInspection::fresh()),
];
$readyRuntime = new ServerRuntimeHealthEvidence([
    ['name' => 'php', 'label' => 'PHP 8.2+', 'passed' => true],
    ['name' => 'pdo', 'label' => 'PDO extension', 'passed' => true],
]);
$producers = [
    new ServerRuntimeHealthProducer(new Wu5RuntimeSource($readyRuntime)),
    new BundledCoreModuleHealthProducer(new Wu5ModuleSource($readyModules)),
    new WebcoreLifecycleHealthProducer(new Wu5WebcoreSource($readyWebcore)),
];
$healthy = (new SystemHealthAggregator())->aggregate($context, $producers);
$healthyPayload = $healthy->toArray();
$assert($healthy->status() === SystemHealthOverallStatus::OPERATIONAL, 'All-ready combined producers did not produce OPERATIONAL.');
$assert($healthy->findings() === [], 'All-ready combined producers produced findings.');
$assert(array_column($healthyPayload['producers'], 'source') === ['webcore.bundled-modules', 'webcore.lifecycle', 'webcore.server-runtime'], 'Combined producer ordering was not deterministic.');
$assert($healthyPayload === (new SystemHealthAggregator())->aggregate($context, array_reverse($producers))->toArray(), 'Equivalent combined input did not produce an equivalent report.');

$operation = new LifecycleOperationRecord('operation', 'update', '0.13.0', 'release', str_repeat('a', 64), 'staging', str_repeat('b', 64), str_repeat('c', 64), LifecycleOperationRecord::INDETERMINATE, 1, null, null, 'indeterminate', gmdate(DATE_ATOM), gmdate(DATE_ATOM), 'internal operation detail');
$criticalWebcore = new WebcoreLifecycleHealthEvidence(InstalledStateInspection::committed($committed->snapshot()), $committed, null, null, null, null, $operation, true);
$warningModules = [new BundledCoreModuleHealthEvidence('taxonomy', ModuleLifecycleInspection::legacy(['name' => 'taxonomy', 'version' => '0.1.0', 'status' => 'enabled']))];
$errorRuntime = new ServerRuntimeHealthEvidence([['name' => 'pdo_mysql', 'label' => 'PDO SQLSTATE /var/private/config.php', 'passed' => false]]);
$mixed = (new SystemHealthAggregator())->aggregate($context, [
    new BundledCoreModuleHealthProducer(new Wu5ModuleSource($warningModules)),
    new ServerRuntimeHealthProducer(new Wu5RuntimeSource($errorRuntime)),
    new WebcoreLifecycleHealthProducer(new Wu5WebcoreSource($criticalWebcore)),
]);
$mixedPayload = json_encode($mixed->toArray(), JSON_THROW_ON_ERROR);
$assert($mixed->status() === SystemHealthOverallStatus::CRITICAL, 'Mixed WARNING/ERROR/CRITICAL producers did not produce CRITICAL.');
$assert(array_map(static fn ($finding): string => $finding->severity(), $mixed->findings()) === [SystemHealthFindingSeverity::CRITICAL, SystemHealthFindingSeverity::ERROR, SystemHealthFindingSeverity::WARNING], 'Mixed finding severity ordering was not deterministic.');
$assert(!str_contains($mixedPayload, 'SQLSTATE') && !str_contains($mixedPayload, '/var/private') && !str_contains($mixedPayload, 'internal operation'), 'Combined report sanitization leaked unsafe diagnostics.');

$isolated = (new SystemHealthAggregator())->aggregate($context, [new Wu5ThrowingProducer(), new ServerRuntimeHealthProducer(new Wu5RuntimeSource($readyRuntime))]);
$isolatedPayload = $isolated->toArray();
$assert(count($isolatedPayload['producers']) === 2, 'A failing producer suppressed an unrelated producer.');
$throwingResult = array_values(array_filter($isolatedPayload['producers'], static fn (array $producer): bool => $producer['source'] === 'zz.throwing'))[0] ?? null;
$assert($throwingResult !== null && $throwingResult['availability'] === SystemHealthProducerAvailability::PRODUCER_ERROR, 'Producer failure did not remain an explicit producer error.');
$assert($isolated->status() === SystemHealthOverallStatus::ATTENTION_REQUIRED, 'Required producer failure without findings was incorrectly operational.');
$assert(!str_contains(json_encode($isolatedPayload, JSON_THROW_ON_ERROR), 'SQLSTATE') && !str_contains(json_encode($isolatedPayload, JSON_THROW_ON_ERROR), 'secret'), 'Producer exception details leaked during isolation.');

$partial = (new SystemHealthAggregator())->aggregate($context, [
    new WebcoreLifecycleHealthProducer(new Wu5WebcoreSource(new WebcoreLifecycleHealthEvidence(null, null, null, null, null, null, null, false))),
    new BundledCoreModuleHealthProducer(new Wu5ModuleSource($readyModules)),
]);
$assert($partial->status() === SystemHealthOverallStatus::DEGRADED, 'Partial required evidence became healthy.');

$hidden = (new SystemHealthAggregator())->aggregate($context, [
    new WebcoreLifecycleHealthProducer(new Wu5WebcoreSource(new WebcoreLifecycleHealthEvidence(null, null, null, null, null, null, null, false)), static fn (mixed $viewer): bool => false),
    new ServerRuntimeHealthProducer(new Wu5RuntimeSource($readyRuntime)),
]);
$hiddenPayload = $hidden->toArray();
$assert(count($hiddenPayload['producers']) === 1 && $hiddenPayload['producers'][0]['source'] === ServerRuntimeHealthProducer::SOURCE, 'Viewer filtering did not remove the unauthorized producer.');
$assert($hidden->status() === SystemHealthOverallStatus::OPERATIONAL, 'Unauthorized producer evidence affected the authorized viewer status.');
$assert(!str_contains(json_encode($hiddenPayload, JSON_THROW_ON_ERROR), 'evidence-unavailable'), 'Unauthorized producer findings leaked through viewer filtering.');

echo "System Health WU5 focused tests passed ({$assertions} assertions)." . PHP_EOL;
