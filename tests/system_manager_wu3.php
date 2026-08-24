<?php

$base = dirname(__DIR__);
$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    if (!$condition) throw new RuntimeException($message);
    $assertions++;
};

$service = file_get_contents($base . '/app/Core/SystemManagerLifecycleService.php');
$routes = file_get_contents($base . '/routes/system_manager.php');
$upload = file_get_contents($base . '/app/Core/SystemManagerPackageUpload.php');
$coreOperator = file_get_contents($base . '/app/Core/ModulePackageOperator.php');
$modulePackageFallback = file_get_contents($base . '/app/Core/SystemManagerModulePackageFallback.php');
$fallback = file_get_contents($base . '/app/Core/SystemManagerModuleFallback.php');
$assert(is_string($service) && str_contains($service, "TransitionPlan::PATCH, TransitionPlan::UPDATE => 'Update'"), 'Patch/update action mapping missing.');
$assert(str_contains($service, "TransitionPlan::UPGRADE => 'Upgrade'"), 'Upgrade action mapping missing.');
$assert(str_contains($service, "TransitionPlan::REPAIR => 'Repair'"), 'Repair action mapping missing.');
$assert(str_contains($service, "TransitionPlan::DATABASE_UPDATE => 'Database-only Update'"), 'Database-only Update action mapping missing.');
$assert(!str_contains($service, 'recovery->capture'), 'System Manager must not capture recovery directly.');
$assert(!str_contains($service, 'authorizeMutation'), 'System Manager must not authorize mutation directly.');
$assert(str_contains($service, 'lifecycle->plan'), 'Preflight does not use PackageLifecycleService::plan.');
$assert(str_contains($service, 'lifecycle->apply'), 'Execution does not use PackageLifecycleService::apply.');
$assert(str_contains($service, 'The package lifecycle request was rejected or is unavailable.'), 'Result sanitization missing.');
$assert(is_string($routes) && str_contains($routes, "system.webcore.manage"), 'Dedicated permission missing.');
$assert(str_contains($routes, 'validateOrReject'), 'CSRF boundary missing.');
$assert(str_contains($routes, "add('System Manager'"), 'Settings-area navigation entry missing.');
$assert(is_string($upload) && str_contains($upload, "storage/.system-manager-packages") === false, 'Upload adapter must not hardcode a public directory.');
$assert(str_contains($upload, 'is_uploaded_file'), 'Browser execution paths are not rejected.');
$assert(str_contains($upload, '0600'), 'Private staged package permissions missing.');
$assert(!str_contains($routes, 'modules/module-manager/Services/ModulePackageOperator.php'), 'System Manager still requires the standalone Module package operator.');
$assert(str_contains($routes, "'modules' => 'Modules'") && !str_contains($routes, "if (\$operational && \$section === 'modules')"), 'Modules is still conditionally suppressed by standalone Module Manager availability.');
$assert(is_string($coreOperator) && str_contains($coreOperator, 'class ModulePackageOperator'), 'Core-owned Module package operator boundary is missing.');
$assert(is_string($coreOperator) && !str_contains($coreOperator, '$classification === ModuleTransitionPlan::INSTALL'), 'Core Module package operator contains the preflight undefined-classification defect.');
$assert(is_string($modulePackageFallback) && !str_contains($modulePackageFallback, 'if ($classification === ModuleTransitionPlan::INSTALL)'), 'System Manager Module preflight retains the undefined classification defect.');
$assert(is_string($fallback) && !str_contains($fallback, 'canonical_operator'), 'System Manager retains stale standalone-operator self-management denial.');
$assert(is_string($coreOperator) && str_contains($coreOperator, 'executeUploadResult') && str_contains($coreOperator, "'next_action' => 'Review Module state'"), 'Module package completion evidence is incomplete.');
echo "system_manager_wu3: {$assertions} assertions passed\n";
