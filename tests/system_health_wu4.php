<?php

declare(strict_types=1);

use Copot\Core\ServerRuntimeHealthEvidence;
use Copot\Core\ServerRuntimeHealthEvidenceSource;
use Copot\Core\ServerRuntimeHealthProducer;
use Copot\Core\InstallationIdentity;
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

final class Wu4EvidenceSource implements ServerRuntimeHealthEvidenceSource
{
    public function __construct(private ServerRuntimeHealthEvidence $evidence, private array &$installations = []) {}
    public function collect(SystemHealthContext $context): ServerRuntimeHealthEvidence
    {
        $this->installations[] = $context->installation()->value();
        return $this->evidence;
    }
}

$context = new SystemHealthContext(new InstallationIdentity('inst_' . str_repeat('1', 32)), 'viewer');
$requirements = [
    ['name' => 'php', 'label' => 'PHP 8.2+', 'passed' => true],
    ['name' => 'pdo', 'label' => 'PDO extension', 'passed' => true],
    ['name' => 'pdo_mysql', 'label' => 'PDO MySQL extension', 'passed' => true],
    ['name' => 'session', 'label' => 'Session support', 'passed' => true],
    ['name' => 'json', 'label' => 'JSON extension', 'passed' => true],
    ['name' => 'filter', 'label' => 'Filter extension', 'passed' => true],
    ['name' => 'storage', 'label' => 'Writable storage', 'passed' => true],
    ['name' => 'environment', 'label' => 'Writable environment configuration', 'passed' => true],
];
$ready = (new SystemHealthAggregator())->aggregate($context, [new ServerRuntimeHealthProducer(new Wu4EvidenceSource(new ServerRuntimeHealthEvidence($requirements, true, '2026-08-09T00:00:00+00:00', 'fresh')))]);
$assert($ready->status() === SystemHealthOverallStatus::OPERATIONAL, 'Passing deterministic Copot requirements did not produce OPERATIONAL.');
$assert($ready->findings() === [], 'Passing deterministic Copot requirements produced an unexpected finding.');
$assert($ready->toArray()['producers'][0]['source'] === ServerRuntimeHealthProducer::SOURCE, 'Server/runtime producer source was not exposed.');

$failedRequirements = $requirements;
$failedRequirements[2]['passed'] = false;
$failedRequirements[2]['label'] = 'PDO SQLSTATE /var/private/config.php';
$failedRequirements[6]['passed'] = false;
$failed = (new SystemHealthAggregator())->aggregate($context, [new ServerRuntimeHealthProducer(new Wu4EvidenceSource(new ServerRuntimeHealthEvidence($failedRequirements))) ]);
$payload = json_encode($failed->toArray(), JSON_THROW_ON_ERROR);
$assert($failed->status() === SystemHealthOverallStatus::DEGRADED, 'A failed required runtime capability did not produce DEGRADED.');
$assert(count($failed->findings()) === 2, 'Each failed deterministic runtime requirement was not represented.');
$assert(count(array_filter($failed->findings(), static fn ($finding): bool => $finding->severity() === SystemHealthFindingSeverity::ERROR)) === 2, 'Runtime requirement severity was not producer-owned ERROR.');
$assert(!str_contains($payload, 'SQLSTATE') && !str_contains($payload, '/var/private'), 'Unsafe runtime diagnostic detail leaked through the report boundary.');

$missing = (new SystemHealthAggregator())->aggregate($context, [new ServerRuntimeHealthProducer(new Wu4EvidenceSource(new ServerRuntimeHealthEvidence([], false)))]);
$assert($missing->status() === SystemHealthOverallStatus::DEGRADED, 'Missing required runtime evidence became healthy.');
$assert($missing->toArray()['producers'][0]['availability'] === 'unavailable', 'Missing runtime evidence did not remain distinct from READY.');

$scope = [];
$scoped = new Wu4EvidenceSource(new ServerRuntimeHealthEvidence($requirements), $scope);
$producer = new ServerRuntimeHealthProducer($scoped);
$aggregator = new SystemHealthAggregator();
$aggregator->aggregate($context, [$producer]);
$aggregator->aggregate(new SystemHealthContext(new InstallationIdentity('inst_' . str_repeat('2', 32)), 'viewer'), [$producer]);
$assert(count(array_unique($scope)) === 2, 'Server/runtime evidence collection was not installation-scoped.');

$assert(!str_contains($payload, 'CPU') && !str_contains($payload, 'memory') && !str_contains($payload, 'uptime'), 'Generic host-monitoring semantics entered the producer report.');
$assert($ready->findings() === [], 'WU4 introduced an affirmative healthy finding.');

echo "System Health WU4 focused tests passed ({$assertions} assertions)." . PHP_EOL;
