<?php

declare(strict_types=1);

use Copot\Core\InstallationIdentity;
use Copot\Core\PermissionChecker;
use Copot\Core\SystemHealthAggregator;
use Copot\Core\SystemHealthContext;
use Copot\Core\SystemHealthDashboardConsumer;
use Copot\Core\SystemHealthFinding;
use Copot\Core\SystemHealthFindingSeverity;
use Copot\Core\SystemHealthOverallStatus;
use Copot\Core\SystemHealthProducer;
use Copot\Core\SystemHealthProducerAvailability;
use Copot\Core\SystemHealthProducerResult;
use Copot\Core\SystemHealthReportProvider;
use Copot\Core\User;

$basePath = dirname(__DIR__);
require_once $basePath . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    ++$assertions;
    if (!$condition) throw new RuntimeException($message);
};
$read = static fn (string $path): string => (string) file_get_contents($basePath . '/' . $path);

$routes = $read('routes/site_settings.php');
$view = $read('resources/views/admin/site-settings.php');
$bootstrap = $read('bootstrap/app.php');
$assert(!str_contains($view, "'security' => 'Security'") && !str_contains($view, "'email' => 'Email'") && !str_contains($view, 'site-settings-security') && !str_contains($view, 'site-settings-email'), 'Withdrawn Security or Email areas remain on the Site Settings product surface.');
$assert(str_contains($view, "'health' => 'System Health'") && str_contains($routes, 'SystemHealthDashboardConsumer'), 'System Health is not retained as the capability-backed Site Settings area.');
$assert(str_contains($bootstrap, 'new SiteSettingsModuleHealthProducer($app)') && str_contains($bootstrap, 'SystemHealthAggregator'), 'Existing accepted Module health composition was not retained.');
$assert(!str_contains($bootstrap, 'findings() === []'), 'Sufficient zero-finding health evidence is still suppressed.');

$context = new SystemHealthContext(new InstallationIdentity('inst_' . str_repeat('3', 32)), 'viewer');
$producer = new class implements SystemHealthProducer {
    public function source(): string { return 'producer.accepted'; }
    public function required(): bool { return true; }
    public function report(SystemHealthContext $context): SystemHealthProducerResult { return new SystemHealthProducerResult($this->source(), SystemHealthProducerAvailability::READY, [], true); }
};
$operational = (new SystemHealthAggregator())->aggregate($context, [$producer]);
$assert($operational->status() === SystemHealthOverallStatus::OPERATIONAL && $operational->findings() === [], 'Sufficient zero-finding evidence did not produce OPERATIONAL.');
$consumer = new SystemHealthDashboardConsumer();
$operationalContent = $consumer->content($operational);
$assert($operationalContent['available'] === true && $operationalContent['status_label'] === 'Operational' && $operationalContent['message'] === 'No material health findings were reported.', 'Operational no-findings state was not presented clearly.');

$unavailableProducer = new class implements SystemHealthProducer {
    public function source(): string { return 'producer.unavailable'; }
    public function required(): bool { return true; }
    public function report(SystemHealthContext $context): SystemHealthProducerResult { return new SystemHealthProducerResult($this->source(), SystemHealthProducerAvailability::UNAVAILABLE, [], true); }
};
$insufficient = (new SystemHealthAggregator())->aggregate($context, [$unavailableProducer]);
$assert($insufficient->status() === SystemHealthOverallStatus::ATTENTION_REQUIRED, 'Insufficient evidence rendered as operational.');

$finding = new SystemHealthFinding('producer.visible:finding', 'producer.visible', 'visible', 'condition', SystemHealthFindingSeverity::ERROR, 'A bounded health condition requires review.', 'C:\\private\\diagnostic.php');
$visibleProducer = new class($finding) implements SystemHealthProducer {
    public function __construct(private SystemHealthFinding $finding) {}
    public function source(): string { return 'producer.visible'; }
    public function required(): bool { return true; }
    public function report(SystemHealthContext $context): SystemHealthProducerResult { return new SystemHealthProducerResult($this->source(), SystemHealthProducerAvailability::READY, [$this->finding], true, null, null, static fn (mixed $viewer): bool => $viewer === 'allowed'); }
};
$provider = new SystemHealthReportProvider(static function (SystemHealthContext $received) use ($visibleProducer): \Copot\Core\SystemHealthReport {
    return (new SystemHealthAggregator())->aggregate($received, [$visibleProducer]);
});
$assert($provider->report(new SystemHealthContext($context->installation(), 'denied'))?->findings() === [], 'Viewer-ineligible health evidence leaked through provider composition.');
$authorizedReport = $provider->report(new SystemHealthContext($context->installation(), 'allowed'));
$authorized = $consumer->content($authorizedReport);
$assert($authorized['status_label'] === 'Degraded' && !str_contains(json_encode($authorized, JSON_THROW_ON_ERROR), 'private'), 'Authorized health sanitization or status projection regressed.');

$permissions = new class extends PermissionChecker {
    public function __construct() {}
    public function userHasRole(int $userId, string $role): bool { return false; }
    public function userCan(int $userId, string $permission): bool { return $permission === 'admin.access'; }
};
$readOnly = new User(['id' => 1, 'name' => 'Read only', 'email' => 'read@example.test', 'password_hash' => 'unused', 'status' => 'active'], $permissions);
$assert($readOnly->can('admin.access') && !$readOnly->can('settings.update'), 'Underlying User permission behavior changed while removing projections.');

echo "WU4 Batch 3 System Health remediation tests passed ({$assertions} assertions)." . PHP_EOL;
