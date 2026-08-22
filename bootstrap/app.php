<?php

use Copot\Core\Application;
use Copot\Core\DeploymentContext;
use Copot\Core\Env;

$basePath = dirname(__DIR__);

require_once $basePath . '/bootstrap/autoload.php';

$deploymentContext ??= DeploymentContext::forApplicationRoot($basePath);
$basePath = $deploymentContext->appRoot();

require_once $basePath . '/app/Core/Content.php';
require_once $basePath . '/app/Core/ContentRepository.php';
require_once $basePath . '/app/Core/ContentDeliveryService.php';
require_once $basePath . '/app/Core/ContentService.php';
require_once $basePath . '/app/Core/Slugger.php';

Env::load($basePath . '/.env');

$app = new Application($deploymentContext);
$app->session()->start();

require $basePath . '/routes/web.php';
require $basePath . '/routes/auth.php';
require $basePath . '/routes/admin.php';
require $basePath . '/routes/system_manager.php';

$app->moduleLoader()->loadListeners($app);
$app->moduleLoader()->loadResolvers($app);
$app->moduleLoader()->loadFrontendContextContributors($app);
$app->moduleLoader()->loadRoutes($app);
$app->frontendThemeContext()->freeze();
require $basePath . '/routes/admin_fallback.php';

return $app;
