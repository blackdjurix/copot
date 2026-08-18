<?php

use Copot\Core\ModuleDiscovery;
use Copot\Core\ModuleRepository;
use Copot\Core\Response;
use Copot\Core\SystemHealthDashboardConsumer;
use Copot\Core\SystemManagerBrandingService;
use Copot\Core\SystemManagerLifecycleService;
use Copot\Core\SystemManagerModuleFallback;
use Copot\Core\SystemManagerModulePackageFallback;
use Copot\Core\SystemManagerPackageUpload;
use Copot\Core\SystemManagerSettingsService;
use Copot\Core\UnavailableSystemManagerRecoveryGate;

require_once $app->path('app/Core/SystemManagerRecoveryGate.php');

$adminUrl = $app->adminUrl();
$path = $adminUrl->childUrl('settings/system-manager');
$preflightPath = $path . '/preflight';
$applyPath = $path . '/apply';
$retryPath = $path . '/retry';
$reconcilePath = $path . '/reconcile';
$brandingPath = $path . '/branding';
$localizationPath = $path . '/localization';
$workspaceSavePath = $path . '/save';
$moduleActionPath = $path . '/modules/action';
$permission = 'system.webcore.manage';
$manager = static fn (): SystemManagerLifecycleService => new SystemManagerLifecycleService(
    $app->packageLifecycle(), new UnavailableSystemManagerRecoveryGate(),
    new SystemManagerPackageUpload($app->path('storage/.system-manager-packages'))
);
$branding = static fn (): SystemManagerBrandingService => new SystemManagerBrandingService($app->settings(), $app->database());
$settings = static fn (): SystemManagerSettingsService => new SystemManagerSettingsService($app->settings(), $app->database());
$moduleFallback = static fn (): SystemManagerModuleFallback => new SystemManagerModuleFallback(
    new ModuleDiscovery($app->path('modules')), new ModuleRepository($app->database())
);
$modulePackageFallback = static fn (): SystemManagerModulePackageFallback => new SystemManagerModulePackageFallback(
    $app, new SystemManagerPackageUpload($app->path('storage/.system-manager-packages'))
);
$app->adminNavigation()->add('System Manager', $path, $permission, 'settings', 75);

$requireUser = static function ($request) use ($app, $permission) {
    if (!$app->auth()->check()) return Response::redirect($app->adminUrl()->baseUrl());
    $user = $app->auth()->user();
    if (!$user?->can($app->config()->get('admin.permission', 'admin.access')) || !$user->can($permission)) {
        return $app->adminErrors()->response($request, 403);
    }
    return $user;
};

$releaseMetadata = static function () use ($app): array {
    $releasePath = $app->path('release.json');
    if (!is_file($releasePath) || !is_readable($releasePath)) return [];
    $metadata = json_decode((string) file_get_contents($releasePath), true);
    return is_array($metadata) ? $metadata : [];
};

$render = static function ($request, $user, ?string $message = null, ?string $error = null, int $statusCode = 200, ?string $sectionOverride = null) use (
    $app, $path, $preflightPath, $applyPath, $retryPath, $reconcilePath, $brandingPath, $workspaceSavePath,
    $localizationPath, $moduleActionPath, $manager, $branding, $settings, $moduleFallback,
    $releaseMetadata
): Response {
    $section = $sectionOverride ?? (string) $request->input('section', 'system');
    if (!in_array($section, ['system', 'branding', 'modules', 'health'], true)) $section = 'system';
    try {
        $lifecycleStatus = $manager()->status();
        $brandingState = $branding()->effective();
        $localization = $settings()->localization();
        $operational = $moduleFallback()->operational();
        $modules = $operational ? [] : $moduleFallback()->inventory();
        $health = (new SystemHealthDashboardConsumer())->content($app->systemHealthReport($user));
    } catch (Throwable) {
        return $app->adminErrors()->response($request, 503);
    }
    if ($operational && $section === 'modules') $section = 'system';
    $labels = ['system' => 'System', 'branding' => 'Branding', 'health' => 'System Health'];
    if (!$operational) $labels['modules'] = 'Modules';
    $tabs = [];
    foreach ($labels as $key => $label) {
        $tabs[] = '<a class="admin-settings-tab' . ($section === $key ? ' is-active' : '') . '" data-admin-capability-tab="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '" href="' . htmlspecialchars($path . '?section=' . $key, ENT_QUOTES, 'UTF-8') . '"' . ($section === $key ? ' aria-current="page"' : '') . '>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '<span class="admin-capability-tab__dirty" aria-hidden="true" hidden>•</span></a>';
    }
    $tabsMarkup = '<nav class="admin-settings-tabs-wrap system-manager-tabs-wrap" aria-label="System Manager areas"><div class="admin-settings-tabs">' . implode('', $tabs) . '</div></nav>';
    $view = $app->view()->render('admin/system-manager', [
        'section' => $section, 'status' => $lifecycleStatus, 'branding' => $brandingState,
        'localization' => $localization, 'health' => $health, 'modules' => $modules,
        'release' => $releaseMetadata(), 'message' => $message, 'error' => $error,
        'systemManagerPath' => $path, 'preflightPath' => $preflightPath, 'applyPath' => $applyPath,
        'retryPath' => $retryPath, 'reconcilePath' => $reconcilePath, 'brandingPath' => $brandingPath,
        'localizationPath' => $localizationPath, 'moduleActionPath' => $moduleActionPath,
        'csrfToken' => $app->csrf()->token(),
        'clearCapability' => match ($request->input('notice')) {
            'localization_saved' => 'localization',
            'branding_saved' => 'branding',
            default => '',
        },
    ]);
    $saveMarkup = '<form class="admin-workspace-save" method="post" action="' . htmlspecialchars($workspaceSavePath, ENT_QUOTES, 'UTF-8') . '" data-admin-workspace-save><input type="hidden" name="_token" value="' . htmlspecialchars($app->csrf()->token(), ENT_QUOTES, 'UTF-8') . '"><input type="hidden" name="section" value="' . htmlspecialchars($section, ENT_QUOTES, 'UTF-8') . '"><input type="hidden" name="payload" value="" data-admin-workspace-payload><button class="admin-button admin-button--primary" type="submit" data-admin-workspace-save-button disabled>Save Changes</button><span class="admin-workspace-save__status" data-admin-workspace-save-status role="status" aria-live="polite"></span></form>';
    $content = '<div class="system-manager-workspace-shell" data-admin-draft-scope data-admin-clear-workspace="' . ($request->input('notice') === 'workspace_saved' ? '1' : '0') . '" data-admin-workspace-capabilities="localization,branding">' . $tabsMarkup . $view . $saveMarkup . '</div>';
    $content .= '<script defer src="' . htmlspecialchars($app->adminUrl()->url('/admin-assets/js/admin-form-capabilities.js?v=wu2-workspace-save-3'), ENT_QUOTES, 'UTF-8') . '"></script>';
    $content .= '<script defer src="' . htmlspecialchars($app->adminUrl()->url('/admin-assets/js/system-manager.js?v=wu2-lifecycle-stable'), ENT_QUOTES, 'UTF-8') . '"></script>';
    return Response::html($app->adminPageRenderer()->render(
        'System Manager', $content, $user, $app->csrf()->token(), $request->path(), [
            'title' => 'System Manager',
            'description' => 'Webcore-owned system administration and recovery baseline.',
            'bar' => null,
            'surface' => 'transparent', 'spacing' => 'default',
        ]
    ), $statusCode);
};

$app->router()->get($path, function ($request) use ($requireUser, $render) {
    $user = $requireUser($request); if ($user instanceof Response) return $user;
    $notice = match ($request->input('notice')) {
        'branding_saved' => 'Branding saved successfully.',
        'localization_saved' => 'Localization saved successfully.',
        'workspace_saved' => 'System Manager changes saved successfully.',
        'module_action' => 'Module lifecycle action completed.',
        default => null,
    };
    return $render($request, $user, $notice);
});

$app->router()->post($workspaceSavePath, function ($request) use ($app, $requireUser, $render, $path) {
    $user = $requireUser($request); if ($user instanceof Response) return $user;
    if ($app->csrf()->validateOrReject($request) instanceof Response) return $app->adminErrors()->response($request, 419);
    $payload = json_decode((string) $request->post('payload', ''), true);
    $capabilities = is_array($payload['capabilities'] ?? null) ? $payload['capabilities'] : [];
    $brandingValues = is_array($capabilities['branding'] ?? null) ? $capabilities['branding'] : null;
    $localizationValues = is_array($capabilities['localization'] ?? null) ? $capabilities['localization'] : null;
    $section = in_array((string) $request->post('section', 'system'), ['system', 'branding', 'modules', 'health'], true) ? (string) $request->post('section', 'system') : 'system';
    try {
        $brandingService = new SystemManagerBrandingService($app->settings(), $app->database());
        $settingsService = new SystemManagerSettingsService($app->settings(), $app->database());
        if ($localizationValues !== null) $settingsService->validateLocalization($localizationValues);
        if ($brandingValues !== null) $brandingService->validate($brandingValues);
        if ($localizationValues !== null) $settingsService->saveLocalization($localizationValues);
        if ($brandingValues !== null) $brandingService->save($brandingValues);
        return Response::redirect($path . '?section=' . rawurlencode($section) . '&notice=workspace_saved');
    } catch (Throwable $failure) {
        return $render($request, $user, null, $failure->getMessage() !== '' ? $failure->getMessage() : 'System Manager changes could not be saved.', 422, $section);
    }
});

$app->router()->post($brandingPath, function ($request) use ($app, $requireUser, $render) {
    $user = $requireUser($request); if ($user instanceof Response) return $user;
    if ($app->csrf()->validateOrReject($request) instanceof Response) return $app->adminErrors()->response($request, 419);
    try {
        (new SystemManagerBrandingService($app->settings(), $app->database()))->save([
            'main' => $request->post('main'), 'accent' => $request->post('accent'),
            'neutral-dark' => $request->post('neutral-dark'), 'neutral-light' => $request->post('neutral-light'),
            'identity_mode' => $request->post('identity_mode'), 'identity_color' => $request->post('identity_color'),
        ]);
        return Response::redirect($app->adminUrl()->childUrl('settings/system-manager') . '?section=branding&notice=branding_saved');
    } catch (Throwable) {
        return $render($request, $user, null, 'Branding could not be saved. Review the palette contrast and submitted values.', 422, 'branding');
    }
});

$app->router()->post($localizationPath, function ($request) use ($app, $requireUser, $render) {
    $user = $requireUser($request); if ($user instanceof Response) return $user;
    if ($app->csrf()->validateOrReject($request) instanceof Response) return $app->adminErrors()->response($request, 419);
    try {
        (new SystemManagerSettingsService($app->settings(), $app->database()))->saveLocalization([
            'locale' => $request->post('locale'), 'timezone' => $request->post('timezone'),
            'date_format' => $request->post('date_format'), 'time_format' => $request->post('time_format'),
        ]);
        return Response::redirect($app->adminUrl()->childUrl('settings/system-manager') . '?section=system&notice=localization_saved');
    } catch (Throwable) {
        return $render($request, $user, null, 'Localization could not be saved. Review the submitted values.', 422, 'system');
    }
});

$app->router()->post($moduleActionPath, function ($request) use ($app, $requireUser, $render, $moduleFallback, $path) {
    $user = $requireUser($request); if ($user instanceof Response) return $user;
    if ($app->csrf()->validateOrReject($request) instanceof Response) return $app->adminErrors()->response($request, 419);
    $name = (string) $request->post('module', ''); $action = (string) $request->post('action', '');
    if ($moduleFallback()->operational() || !preg_match('/^[a-z0-9][a-z0-9_-]*$/', $name) || !in_array($action, ['install', 'enable', 'disable', 'uninstall'], true)) return Response::redirect($path . '?section=modules');
    try {
        match ($action) {
            'install' => $app->modules()->install($name), 'enable' => $app->modules()->enable($name),
            'disable' => $app->modules()->disable($name), 'uninstall' => $app->modules()->uninstall($name),
        };
        return Response::redirect($path . '?section=modules&notice=module_action');
    } catch (Throwable) {
        return $render($request, $user, null, 'The Module lifecycle action was blocked or unavailable.', 422, 'modules');
    }
});

$app->router()->post($preflightPath, function ($request) use ($app, $requireUser, $manager, $moduleFallback, $modulePackageFallback) {
    $user = $requireUser($request); if ($user instanceof Response) return $user;
    if ($app->csrf()->validateOrReject($request) instanceof Response) return $app->adminErrors()->response($request, 419);
    try {
        $upload = $request->file('package');
        $moduleResult = $modulePackageFallback()->preflight($upload, $moduleFallback()->operational());
        if (is_array($moduleResult)) return Response::content(json_encode($moduleResult, JSON_UNESCAPED_SLASHES), 200, ['Content-Type' => 'application/json']);
        return Response::content(json_encode($manager()->preflightUpload($upload), JSON_UNESCAPED_SLASHES), 200, ['Content-Type' => 'application/json']);
    }
    catch (Throwable) { return Response::content(json_encode(['accepted' => false, 'status' => 'invalid_package', 'reason' => 'Package intake failed.']), 422, ['Content-Type' => 'application/json']); }
});
$app->router()->post($applyPath, function ($request) use ($app, $requireUser, $manager, $moduleFallback, $modulePackageFallback) {
    $user = $requireUser($request); if ($user instanceof Response) return $user;
    if ($app->csrf()->validateOrReject($request) instanceof Response) return $app->adminErrors()->response($request, 419);
    try {
        $upload = $request->file('package');
        $moduleResult = $modulePackageFallback()->execute($upload, $moduleFallback()->operational(), (string) $request->post('action', ''));
        if (is_array($moduleResult)) return Response::content(json_encode($moduleResult, JSON_UNESCAPED_SLASHES), 200, ['Content-Type' => 'application/json']);
        return Response::content(json_encode($manager()->executeUpload($upload, (string) $request->post('action', '')), JSON_UNESCAPED_SLASHES), 200, ['Content-Type' => 'application/json']);
    }
    catch (Throwable) { return Response::content(json_encode(['accepted' => false, 'status' => 'invalid_package', 'reason' => 'Package intake failed.']), 422, ['Content-Type' => 'application/json']); }
});
$app->router()->post($retryPath, function ($request) use ($app, $requireUser, $manager) {
    $user = $requireUser($request); if ($user instanceof Response) return $user;
    if ($app->csrf()->validateOrReject($request) instanceof Response) return $app->adminErrors()->response($request, 419);
    return Response::content(json_encode($manager()->retry((string) $request->post('operation_id', '')), JSON_UNESCAPED_SLASHES), 200, ['Content-Type' => 'application/json']);
});
$app->router()->post($reconcilePath, function ($request) use ($app, $requireUser, $manager) {
    $user = $requireUser($request); if ($user instanceof Response) return $user;
    if ($app->csrf()->validateOrReject($request) instanceof Response) return $app->adminErrors()->response($request, 419);
    try { return Response::content(json_encode($manager()->reconcile((string) $request->post('package_path', ''), $request->post('confirmed') === '1'), JSON_UNESCAPED_SLASHES), 200, ['Content-Type' => 'application/json']); }
    catch (Throwable) { return Response::content(json_encode(['accepted' => false, 'status' => 'unavailable', 'reason' => 'Reconciliation is unavailable.']), 503, ['Content-Type' => 'application/json']); }
});
