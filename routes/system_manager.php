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
    $app, $path, $preflightPath, $applyPath, $retryPath, $reconcilePath, $brandingPath,
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
        $tabs[] = '<a class="admin-tab' . ($section === $key ? ' is-active' : '') . '" href="' . htmlspecialchars($path . '?section=' . $key, ENT_QUOTES, 'UTF-8') . '"' . ($section === $key ? ' aria-current="page"' : '') . '>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
    }
    $content = $app->view()->render('admin/system-manager', [
        'section' => $section, 'status' => $lifecycleStatus, 'branding' => $brandingState,
        'localization' => $localization, 'health' => $health, 'modules' => $modules,
        'release' => $releaseMetadata(), 'message' => $message, 'error' => $error,
        'systemManagerPath' => $path, 'preflightPath' => $preflightPath, 'applyPath' => $applyPath,
        'retryPath' => $retryPath, 'reconcilePath' => $reconcilePath, 'brandingPath' => $brandingPath,
        'localizationPath' => $localizationPath, 'moduleActionPath' => $moduleActionPath,
        'csrfToken' => $app->csrf()->token(),
    ]);
    $content .= '<script defer src="' . htmlspecialchars($app->adminUrl()->url('/admin-assets/js/system-manager.js'), ENT_QUOTES, 'UTF-8') . '"></script>';
    return Response::html($app->adminPageRenderer()->render(
        'System Manager', $content, $user, $app->csrf()->token(), $request->path(), [
            'title' => 'System Manager',
            'description' => 'Webcore-owned system administration and recovery baseline.',
            'bar' => '<nav class="admin-tabs" aria-label="System Manager areas">' . implode('', $tabs) . '</nav>',
            'surface' => 'transparent', 'spacing' => 'default',
        ]
    ), $statusCode);
};

$app->router()->get($path, function ($request) use ($requireUser, $render) {
    $user = $requireUser($request); if ($user instanceof Response) return $user;
    $notice = match ($request->input('notice')) {
        'branding_saved' => 'Branding saved successfully.',
        'localization_saved' => 'Localization saved successfully.',
        'module_action' => 'Module lifecycle action completed.',
        default => null,
    };
    return $render($request, $user, $notice);
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
        return Response::redirect($app->adminUrl()->childUrl('settings/system-manager') . '?section=branding&notice=localization_saved');
    } catch (Throwable) {
        return $render($request, $user, null, 'Localization could not be saved. Review the submitted values.', 422, 'branding');
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
