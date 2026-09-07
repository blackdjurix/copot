<?php

declare(strict_types=1);

use Copot\Core\InstallationIdentity;
use Copot\Core\PermissionChecker;
use Copot\Core\SystemHealthContext;
use Copot\Core\SystemHealthDashboardConsumer;
use Copot\Core\SystemHealthFinding;
use Copot\Core\SystemHealthFindingSeverity;
use Copot\Core\SystemHealthOverallStatus;
use Copot\Core\SystemHealthProducerAvailability;
use Copot\Core\SystemHealthProducerResult;
use Copot\Core\SystemHealthReport;
use Copot\Core\User;

$basePath = dirname(__DIR__);
require_once $basePath . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    ++$assertions;
    if (!$condition) throw new RuntimeException($message);
};

$routes = (string) file_get_contents($basePath . '/routes/site_settings.php');
$view = (string) file_get_contents($basePath . '/resources/views/admin/site-settings.php');
$bootstrap = (string) file_get_contents($basePath . '/bootstrap/app.php');

$assert(str_contains($routes, 'SystemHealthDashboardConsumer') && str_contains($routes, 'systemHealthReport($user)'), 'Site Settings does not consume the active System Health authority.');
$assert(str_contains($routes, "if (!\$user || !\$user->can(\$adminPermission))"), 'admin.access is not the Site Settings read baseline.');
$assert(str_contains($routes, "[\$adminPermission, \$permission, 'modules.manage', 'system.webcore.manage']"), 'The Site Settings navigation does not expose the admin-access read baseline.');
$assert(str_contains($bootstrap, "can('modules.manage')") && str_contains($bootstrap, 'new SiteSettingsModuleHealthProducer($app)'), 'Batch 3 changed the existing producer-specific System Health activation boundary.');
$assert(!str_contains($routes, 'SystemHealthAggregator') && !str_contains($routes, 'SiteSettingsModuleHealthProducer'), 'Site Settings introduced a health producer or aggregation engine.');

$permissions = new class extends PermissionChecker {
    public function __construct() {}
    public function userHasRole(int $userId, string $role): bool { return false; }
    public function userCan(int $userId, string $permission): bool { return $permission === 'admin.access'; }
};
$adminReadOnly = new User(['id' => 1, 'name' => 'Read only admin', 'email' => 'read@example.test', 'password_hash' => 'unused', 'status' => 'active'], $permissions);
$assert($adminReadOnly->can('admin.access') && !$adminReadOnly->can('settings.update') && !$adminReadOnly->can('system.webcore.manage') && !$adminReadOnly->can('modules.manage'), 'Read baseline fixture has a mutation or lifecycle capability.');

$finding = new SystemHealthFinding('webcore.modules:alpha', 'webcore.modules', 'alpha', 'dependency', SystemHealthFindingSeverity::ERROR, 'A Module dependency condition requires review.', 'C:\\private\\diagnostic.php');
$report = new SystemHealthReport(
    new SystemHealthContext(new InstallationIdentity('inst_' . str_repeat('3', 32)), $adminReadOnly),
    SystemHealthOverallStatus::DEGRADED,
    [new SystemHealthProducerResult('webcore.modules', SystemHealthProducerAvailability::READY, [$finding], true)],
    [$finding]
);
$health = (new SystemHealthDashboardConsumer())->content($report);
$assert($health['status'] === SystemHealthOverallStatus::DEGRADED && $health['findings'][0]['summary'] === 'A Module dependency condition requires review.', 'System Health projection did not preserve the authorized consumer result.');
$assert(!str_contains(json_encode($health, JSON_THROW_ON_ERROR), 'private'), 'System Health projection did not preserve sanitizer output.');

$render = static function (array $data) use ($basePath): string {
    extract($data, EXTR_SKIP);
    ob_start();
    try {
        require $basePath . '/resources/views/admin/site-settings.php';
        return (string) ob_get_clean();
    } catch (Throwable $exception) {
        ob_end_clean();
        throw $exception;
    }
};

$html = $render([
    'canUpdateSettings' => false,
    'canManageSystem' => false,
    'canManageModules' => false,
    'initialArea' => 'security',
    'health' => $health,
]);
$assert(str_contains($html, 'Delivered authentication safeguards') && str_contains($html, 'CSRF request validation') && str_contains($html, 'read-only on this surface'), 'Security projection is not truthful and explicitly read-only.');
$assert(str_contains($html, 'System email delivery is not supported') && str_contains($html, 'User email addresses identify user accounts; they do not configure email delivery.'), 'Email projection does not distinguish identity from unsupported delivery.');
$assert(str_contains($html, 'data-site-settings-health') && str_contains($html, 'Degraded') && str_contains($html, 'A Module dependency condition requires review.'), 'System Health presentation did not render the authorized consumer projection.');
$assert(!str_contains($html, 'Site Identity') && !str_contains($html, 'Save Site Settings') && !str_contains($html, 'Apply Update') && !str_contains($html, 'Add Module package'), 'admin.access-only presentation exposed a settings, system, or Module mutation path.');
$assert(!str_contains($html, 'diagnostic.php') && !str_contains($html, 'System Health presentation is available in a later WU4 batch.'), 'System Health projection leaked diagnostics or retained the Batch 1 placeholder.');

echo "WU4 Batch 3 Security, Email, and System Health projection tests passed ({$assertions} assertions)." . PHP_EOL;
