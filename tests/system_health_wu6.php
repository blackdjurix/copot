<?php

declare(strict_types=1);

use Copot\Core\InstallationIdentity;
use Copot\Core\SystemHealthContext;
use Copot\Core\SystemHealthDashboardConsumer;
use Copot\Core\SystemHealthFinding;
use Copot\Core\SystemHealthFindingSeverity;
use Copot\Core\SystemHealthOverallStatus;
use Copot\Core\SystemHealthProducerAvailability;
use Copot\Core\SystemHealthProducerResult;
use Copot\Core\SystemHealthReport;
use Copot\Core\SystemHealthReportProvider;

$basePath = dirname(__DIR__);
chdir($basePath);
require $basePath . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};

$context = new SystemHealthContext(new InstallationIdentity('inst_' . str_repeat('4', 32)), 'admin-viewer');
$operational = new SystemHealthReport($context, SystemHealthOverallStatus::OPERATIONAL, [
    new SystemHealthProducerResult('webcore.lifecycle', SystemHealthProducerAvailability::READY),
], []);
$consumer = new SystemHealthDashboardConsumer();
$healthy = $consumer->content($operational);
$assert($healthy['available'] === true && $healthy['status'] === SystemHealthOverallStatus::OPERATIONAL, 'Operational report was not consumed as healthy.');
$assert($healthy['status_label'] === 'Operational' && $healthy['findings'] === [], 'Healthy presentation did not preserve the report status or zero findings.');

$finding = new SystemHealthFinding('webcore.lifecycle:runtime', 'webcore.lifecycle', null, 'runtime.failure', SystemHealthFindingSeverity::ERROR, 'Runtime requirement failed.', 'C:\\private\\runtime.php');
$problem = new SystemHealthReport($context, SystemHealthOverallStatus::DEGRADED, [
    new SystemHealthProducerResult('webcore.lifecycle', SystemHealthProducerAvailability::READY, [$finding], true),
], [$finding]);
$problemContent = $consumer->content($problem);
$assert($problemContent['status'] === SystemHealthOverallStatus::DEGRADED && $problemContent['status_label'] === 'Degraded', 'Problem status was not presented from the authorized report.');
$assert($problemContent['findings'][0]['severity'] === SystemHealthFindingSeverity::ERROR, 'Dashboard recalculated or changed producer-owned severity.');
$assert(!str_contains(json_encode($problemContent, JSON_THROW_ON_ERROR), 'private'), 'Sanitized report data was not preserved at the consumer boundary.');

$unavailable = $consumer->content(null);
$assert($unavailable['available'] === false && $unavailable['status'] === 'unavailable', 'Unavailable report was presented as healthy.');
$assert($unavailable['status_label'] === 'Health data unavailable', 'Unavailable report state is not bounded.');

$providerSeen = [];
$provider = new SystemHealthReportProvider(static function (SystemHealthContext $received) use (&$providerSeen, $operational): SystemHealthReport {
    $providerSeen[] = $received->installation()->value();
    return $operational;
});
$provided = $provider->report($context);
$assert($provided === $operational && $providerSeen === [$context->installation()->value()], 'Authorized installation-scoped report was not passed through the provider.');
$failingProvider = new SystemHealthReportProvider(static function (): SystemHealthReport { throw new RuntimeException('raw internal failure'); });
$assert($failingProvider->report($context) === null, 'Report provider failure was not contained.');

$dashboardView = (string) file_get_contents($basePath . '/resources/views/admin/dashboard.php');
$adminRoutes = (string) file_get_contents($basePath . '/routes/admin.php');
$assert(str_contains($adminRoutes, "'core.system-health'") && str_contains($adminRoutes, 'systemHealthReport'), 'Dashboard did not register the System Health consumer through the existing route/registry boundary.');
$assert(str_contains($dashboardView, 'data-widget-id="core.system-health"') && str_contains($dashboardView, 'status_label'), 'Dashboard System Health presentation boundary is missing.');
$assert(!str_contains($dashboardView, 'SystemHealthAggregator') && !str_contains($dashboardView, 'Database') && !str_contains($dashboardView, 'LifecycleOperation'), 'Dashboard view contains diagnosis or private subsystem inspection.');

$render = static function (array $data) use ($basePath): string {
    extract($data, EXTR_SKIP);
    ob_start();
    try {
        require $basePath . '/resources/views/admin/dashboard.php';
        return (string) ob_get_clean();
    } catch (Throwable $exception) {
        ob_end_clean();
        throw $exception;
    }
};
$renderedHealthy = $render(['appName' => 'Copot', 'adminBaseUrl' => '/dapur', 'userName' => 'Admin', 'userEmail' => 'admin@example.test', 'frameworkStatus' => 'Shell', 'widgets' => [['id' => 'core.system-health', 'content' => $healthy]]]);
$renderedUnavailable = $render(['appName' => 'Copot', 'adminBaseUrl' => '/dapur', 'userName' => 'Admin', 'userEmail' => 'admin@example.test', 'frameworkStatus' => 'Shell', 'widgets' => [['id' => 'core.system-health', 'content' => $unavailable]]]);
$assert(str_contains($renderedHealthy, 'data-health-status="operational"') && str_contains($renderedHealthy, 'Operational'), 'Healthy System Health state was not rendered by the Dashboard.');
$assert(str_contains($renderedUnavailable, 'data-health-status="unavailable"') && str_contains($renderedUnavailable, 'Health data unavailable'), 'Unavailable System Health state was rendered as healthy.');

echo "System Health WU6 focused tests passed ({$assertions} assertions)." . PHP_EOL;
