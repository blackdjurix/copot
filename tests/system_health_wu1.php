<?php

declare(strict_types=1);

use Copot\Core\InstallationIdentity;
use Copot\Core\SystemHealthAggregator;
use Copot\Core\SystemHealthContext;
use Copot\Core\SystemHealthFinding;
use Copot\Core\SystemHealthFindingSeverity;
use Copot\Core\SystemHealthOverallStatus;
use Copot\Core\SystemHealthProducer;
use Copot\Core\SystemHealthProducerAvailability;
use Copot\Core\SystemHealthProducerResult;

$basePath = dirname(__DIR__);
chdir($basePath);
require $basePath . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

final class Wu1SystemHealthProducer implements SystemHealthProducer
{
    public function __construct(
        private string $name,
        private bool $required,
        private $callback
    ) {
    }

    public function source(): string { return $this->name; }
    public function required(): bool { return $this->required; }
    public function report(SystemHealthContext $context): SystemHealthProducerResult { return ($this->callback)($context); }
}

$finding = static fn (string $identity, string $severity, string $summary, ?string $detail = null, string $source = 'core.lifecycle'): SystemHealthFinding => new SystemHealthFinding(
    $identity,
    $source,
    null,
    'state.drift',
    $severity,
    $summary,
    $detail,
    'Review the installation state.',
    '/admin/status'
);

$contextA = new SystemHealthContext(new InstallationIdentity('inst_' . str_repeat('a', 32)), 'viewer-a');
$contextB = new SystemHealthContext(new InstallationIdentity('inst_' . str_repeat('b', 32)), 'viewer-a');
$aggregator = new SystemHealthAggregator();

$normalized = $finding('finding-1', SystemHealthFindingSeverity::WARNING, 'State requires attention.', 'SQLSTATE[HY000] at C:\\internal\\app.php');
$assert($normalized->severity() === SystemHealthFindingSeverity::WARNING, 'Producer-owned finding severity was not preserved.');
$assert($normalized->detail() === 'Diagnostic detail withheld.', 'Unsafe diagnostic detail was not sanitized.');
$unsafeSummary = $finding('finding-summary', SystemHealthFindingSeverity::WARNING, 'SQLSTATE[HY000] /private/internal.php');
$assert($unsafeSummary->summary() === 'Diagnostic summary withheld.', 'Unsafe diagnostic summary was not sanitized.');
$assert($normalized->actionTarget() === '/admin/status', 'Safe action target was not retained.');
$assert($normalized->recommendedAction() === 'Review the installation state.', 'Producer-owned remediation recommendation was not retained as data.');

$dispositions = [
    SystemHealthProducerAvailability::READY,
    SystemHealthProducerAvailability::NOT_APPLICABLE,
    SystemHealthProducerAvailability::NOT_ADOPTED,
    SystemHealthProducerAvailability::UNAVAILABLE,
    SystemHealthProducerAvailability::PRODUCER_ERROR,
];
$dispositionResults = array_map(static fn (string $availability): SystemHealthProducer => new Wu1SystemHealthProducer(
    'producer.' . $availability,
    false,
    static fn (): SystemHealthProducerResult => new SystemHealthProducerResult('producer.' . $availability, $availability)
), $dispositions);
$dispositionReport = $aggregator->aggregate($contextA, $dispositionResults);
$reportedDispositions = array_column($dispositionReport->toArray()['producers'], 'availability');
$expectedDispositions = $dispositions;
sort($expectedDispositions);
sort($reportedDispositions);
$assert($reportedDispositions === $expectedDispositions, 'Producer availability/adoption dispositions were not kept distinct.');

$healthy = $aggregator->aggregate($contextA, [new Wu1SystemHealthProducer('producer.ready', true, static fn (): SystemHealthProducerResult => new SystemHealthProducerResult('producer.ready', SystemHealthProducerAvailability::READY))]);
$assert($healthy->status() === SystemHealthOverallStatus::OPERATIONAL && $healthy->findings() === [], 'Sufficient zero-finding evidence was not operational.');

$insufficient = $aggregator->aggregate($contextA, [new Wu1SystemHealthProducer('producer.required', true, static fn (): SystemHealthProducerResult => new SystemHealthProducerResult('producer.required', SystemHealthProducerAvailability::UNAVAILABLE, [], true))]);
$assert($insufficient->status() === SystemHealthOverallStatus::ATTENTION_REQUIRED, 'Insufficient required evidence became operational.');

foreach ([
    [SystemHealthFindingSeverity::WARNING, SystemHealthOverallStatus::ATTENTION_REQUIRED],
    [SystemHealthFindingSeverity::ERROR, SystemHealthOverallStatus::DEGRADED],
    [SystemHealthFindingSeverity::CRITICAL, SystemHealthOverallStatus::CRITICAL],
] as [$severity, $status]) {
    $report = $aggregator->aggregate($contextA, [new Wu1SystemHealthProducer('producer.finding', true, static fn (): SystemHealthProducerResult => new SystemHealthProducerResult('producer.finding', SystemHealthProducerAvailability::READY, [$finding('finding-' . $severity, $severity, 'Finding summary.', null, 'producer.finding')]))]);
    $assert($report->status() === $status, "{$severity} did not map to the locked overall status.");
    $assert($report->findings()[0]->severity() === $severity, "{$severity} severity was rewritten.");
}

$ordered = $aggregator->aggregate($contextA, [
    new Wu1SystemHealthProducer('producer.zeta', false, static fn (): SystemHealthProducerResult => new SystemHealthProducerResult('producer.zeta', SystemHealthProducerAvailability::READY, [$finding('zeta-error', SystemHealthFindingSeverity::ERROR, 'Error.', null, 'producer.zeta')])),
    new Wu1SystemHealthProducer('producer.alpha', false, static fn (): SystemHealthProducerResult => new SystemHealthProducerResult('producer.alpha', SystemHealthProducerAvailability::READY, [$finding('alpha-critical', SystemHealthFindingSeverity::CRITICAL, 'Critical.', null, 'producer.alpha')])),
    new Wu1SystemHealthProducer('producer.beta', false, static fn (): SystemHealthProducerResult => new SystemHealthProducerResult('producer.beta', SystemHealthProducerAvailability::READY, [$finding('beta-warning', SystemHealthFindingSeverity::WARNING, 'Warning.', null, 'producer.beta')])),
]);
$assert(array_column($ordered->toArray()['findings'], 'identity') === ['alpha-critical', 'zeta-error', 'beta-warning'], 'Findings were not deterministically ordered by severity and identity.');
$assert(array_column($ordered->toArray()['producers'], 'source') === ['producer.alpha', 'producer.beta', 'producer.zeta'], 'Producers were not deterministically ordered.');

$failureReport = $aggregator->aggregate($contextA, [
    new Wu1SystemHealthProducer('producer.broken', true, static function (): SystemHealthProducerResult { throw new RuntimeException('SQLSTATE[HY000] /var/private/lifecycle-operation'); }),
    new Wu1SystemHealthProducer('producer.good', false, static fn (): SystemHealthProducerResult => new SystemHealthProducerResult('producer.good', SystemHealthProducerAvailability::READY, [$finding('good-warning', SystemHealthFindingSeverity::WARNING, 'Good producer warning.', null, 'producer.good')])),
]);
$failurePayload = $failureReport->toArray();
$assert(count($failureReport->findings()) === 1 && $failureReport->findings()[0]->identity() === 'good-warning', 'A producer failure was not isolated from a healthy producer.');
$assert(($failurePayload['producers'][0]['availability'] ?? null) === SystemHealthProducerAvailability::PRODUCER_ERROR, 'Producer failure was not converted to a controlled disposition.');
$assert(!str_contains(json_encode($failurePayload, JSON_THROW_ON_ERROR), 'SQLSTATE') && !str_contains(json_encode($failurePayload, JSON_THROW_ON_ERROR), 'lifecycle-operation'), 'Raw producer failure detail leaked into the report.');

$visibilityReport = $aggregator->aggregate($contextA, [
    new Wu1SystemHealthProducer('producer.hidden', false, static fn (): SystemHealthProducerResult => new SystemHealthProducerResult('producer.hidden', SystemHealthProducerAvailability::READY, [$finding('hidden', SystemHealthFindingSeverity::CRITICAL, 'Hidden.', null, 'producer.hidden')] , false, null, null, static fn (mixed $viewer): bool => $viewer === 'other-viewer')),
    new Wu1SystemHealthProducer('producer.visible', false, static fn (): SystemHealthProducerResult => new SystemHealthProducerResult('producer.visible', SystemHealthProducerAvailability::READY, [$finding('visible', SystemHealthFindingSeverity::WARNING, 'Visible.', null, 'producer.visible')])),
]);
$visibilityPayload = $visibilityReport->toArray();
$assert(array_column($visibilityPayload['producers'], 'source') === ['producer.visible'], 'Unauthorized producer visibility was not filtered.');
$assert(array_column($visibilityPayload['findings'], 'identity') === ['visible'], 'Unauthorized finding existence leaked.');

$seenInstallations = [];
$scopedProducer = new Wu1SystemHealthProducer('producer.scope', false, function (SystemHealthContext $context) use (&$seenInstallations): SystemHealthProducerResult {
    $seenInstallations[] = $context->installation()->value();
    return new SystemHealthProducerResult('producer.scope', SystemHealthProducerAvailability::READY);
});
$reportA = $aggregator->aggregate($contextA, [$scopedProducer]);
$reportB = $aggregator->aggregate($contextB, [$scopedProducer]);
$assert(count(array_unique($seenInstallations)) === 2, 'Reports were not collected in distinct installation contexts.');
$assert(!array_key_exists('installation_id', $reportA->toArray()) && !array_key_exists('installation_id', $reportB->toArray()), 'Installation identity leaked into the public report payload.');

echo "System Health WU1 focused tests passed ({$assertions} assertions)." . PHP_EOL;
