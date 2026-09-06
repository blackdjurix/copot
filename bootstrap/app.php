<?php

use Copot\Core\Application;
use Copot\Core\DeploymentContext;
use Copot\Core\Env;
use Copot\Core\SystemHealthAggregator;
use Copot\Core\SystemHealthContext;
use Copot\Core\SystemHealthProducerAvailability;
use Copot\Core\SystemHealthReport;

$basePath = dirname(__DIR__);

require_once $basePath . '/bootstrap/autoload.php';

$deploymentContext ??= DeploymentContext::forApplicationRoot($basePath);
$basePath = $deploymentContext->appRoot();

require_once $basePath . '/app/Core/Content.php';
require_once $basePath . '/app/Core/ContentRepository.php';
require_once $basePath . '/app/Core/ContentDeliveryService.php';
require_once $basePath . '/app/Core/ContentService.php';
require_once $basePath . '/app/Core/Slugger.php';
require_once $basePath . '/modules/module-manager/Services/ModuleActionPolicy.php';
require_once $basePath . '/modules/module-manager/Services/ModuleInventoryBuilder.php';
require_once $basePath . '/modules/module-manager/Services/SiteSettingsModuleHealthProducer.php';

Env::load($basePath . '/.env');

$app = new Application($deploymentContext, static function (SystemHealthContext $context) use (&$app): ?SystemHealthReport {
    $viewer = $context->viewer();
    if (!$viewer instanceof \Copot\Core\User || !$viewer->can('modules.manage')) {
        return null;
    }

    $producer = new SiteSettingsModuleHealthProducer($app);
    $result = $producer->report($context);
    if (!$result->visibleTo($viewer)) {
        return null;
    }
    if ($result->findings() === [] && SystemHealthProducerAvailability::isEvidenceSufficient($result->availability())) {
        return null;
    }

    return (new SystemHealthAggregator())->aggregate($context, [$producer]);
});
$app->session()->start();

$app->frontendThemeContext()->register(new \Copot\Core\NavigationFrontendContextContributor($app->database()));

try {
    $redirectsEnabled = ((new \Copot\Core\ModuleRepository($app->database()))->findByName('redirects')['status'] ?? null) === 'enabled';
} catch (Throwable) {
    $redirectsEnabled = false;
}
if (!$redirectsEnabled) {
    $app->router()->setUnresolvedRouteResolver(new \Copot\Core\RedirectResolver(
        new \Copot\Core\RedirectRepository($app->database()),
        $app->adminUrl()->baseUrl()
    ));
}

require $basePath . '/routes/web.php';
require $basePath . '/routes/auth.php';
require $basePath . '/routes/admin.php';
require $basePath . '/routes/content_admin.php';
require $basePath . '/routes/media_admin.php';
require $basePath . '/routes/navigation_admin.php';
require $basePath . '/routes/site_settings.php';

$app->moduleLoader()->loadListeners($app);
$app->moduleLoader()->loadResolvers($app);
$app->moduleLoader()->loadFrontendContextContributors($app);
$app->moduleLoader()->loadRoutes($app);
$app->frontendThemeContext()->freeze();
require $basePath . '/routes/admin_fallback.php';

return $app;
