<?php

use Copot\Core\Response;

require_once __DIR__ . '/Services/ModuleActionPolicy.php';
require_once __DIR__ . '/Services/ModuleInventoryBuilder.php';
require_once __DIR__ . '/Services/ModuleManagerAdmin.php';

$modulesAdmin = new ModuleManagerAdmin($app);
$modulesPath = $app->adminUrl()->childUrl('modules');

$app->adminNavigation()->add('Modules', $modulesPath, 'modules.manage');

$app->router()->get($modulesPath, static function ($request) use ($modulesAdmin): Response {
    return $modulesAdmin->inventoryResponse($request);
});

foreach (['install', 'enable', 'disable', 'uninstall'] as $action) {
    $path = $app->adminUrl()->childUrl('modules/' . $action);

    $app->router()->post($path, static function ($request) use ($modulesAdmin, $action): Response {
        return $modulesAdmin->mutationResponse($request, $action);
    });
}
