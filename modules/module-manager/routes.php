<?php

use Copot\Core\Response;

require_once __DIR__ . '/Services/ModuleActionPolicy.php';
require_once __DIR__ . '/Services/ModuleInventoryBuilder.php';
require_once __DIR__ . '/Services/ModuleManagerAdmin.php';
require_once __DIR__ . '/Services/ModulePackageOperator.php';

$modulesAdmin = new ModuleManagerAdmin($app);
$modulesPath = $app->adminUrl()->childUrl('modules');

$app->adminNavigation()->add('Modules', $modulesPath, 'modules.manage', 'modules', 60);

$app->router()->get($modulesPath, static function ($request) use ($modulesAdmin): Response {
    return $modulesAdmin->inventoryResponse($request);
});

$app->router()->get($app->adminUrl()->childUrl('modules/{name}'), static function ($request, array $params) use ($modulesAdmin): Response {
    return $modulesAdmin->detailResponse($request, (string) ($params['name'] ?? ''));
});

foreach (['install', 'enable', 'disable', 'uninstall'] as $action) {
    $path = $app->adminUrl()->childUrl('modules/' . $action);

    $app->router()->post($path, static function ($request) use ($modulesAdmin, $action): Response {
        return $modulesAdmin->mutationResponse($request, $action);
    });
}

$addPath = $app->adminUrl()->childUrl('modules/add');
$app->router()->post($addPath, static fn ($request): Response => $modulesAdmin->addPackageResponse($request));
$lifecyclePath = $app->adminUrl()->childUrl('modules/lifecycle');
$app->router()->post($lifecyclePath, static fn ($request): Response => $modulesAdmin->lifecycleResponse($request));
