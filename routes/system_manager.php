<?php

use Copot\Core\Response;
use Copot\Core\SystemManagerLifecycleService;
use Copot\Core\SystemManagerPackageUpload;
use Copot\Core\UnavailableSystemManagerRecoveryGate;

$adminUrl = $app->adminUrl();
$path = $adminUrl->childUrl('settings/system-manager');
$preflightPath = $path . '/preflight';
$applyPath = $path . '/apply';
$retryPath = $path . '/retry';
$reconcilePath = $path . '/reconcile';
$permission = 'system.webcore.manage';
$manager = static fn (): SystemManagerLifecycleService => new SystemManagerLifecycleService(
    $app->packageLifecycle(), new UnavailableSystemManagerRecoveryGate(),
    new SystemManagerPackageUpload($app->path('storage/.system-manager-packages'))
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
$render = static function ($user, array $status, string $path) use ($app): Response {
    $content = '<h1>System Manager</h1><pre>' . htmlspecialchars((string) json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') . '</pre>';
    return Response::html($app->adminPageRenderer()->render('System Manager', $content, $user, $app->csrf()->token(), $path));
};

$app->router()->get($path, function ($request) use ($requireUser, $manager, $render, $path) {
    $user = $requireUser($request); if ($user instanceof Response) return $user;
    return $render($user, $manager()->status(), $path);
});
$app->router()->post($preflightPath, function ($request) use ($app, $requireUser, $manager) {
    $user = $requireUser($request); if ($user instanceof Response) return $user;
    if ($app->csrf()->validateOrReject($request) instanceof Response) return $app->adminErrors()->response($request, 419);
    $upload = $request->file('package');
    try {
        return Response::content(json_encode($manager()->preflightUpload($upload), JSON_UNESCAPED_SLASHES), 200, ['Content-Type' => 'application/json']);
    } catch (Throwable) { return Response::content(json_encode(['accepted'=>false,'status'=>'invalid_package','reason'=>'Package intake failed.']), 422, ['Content-Type'=>'application/json']); }
});
$app->router()->post($applyPath, function ($request) use ($app, $requireUser, $manager) {
    $user = $requireUser($request); if ($user instanceof Response) return $user;
    if ($app->csrf()->validateOrReject($request) instanceof Response) return $app->adminErrors()->response($request, 419);
    $upload = $request->file('package'); $action = (string) $request->post('action', '');
    try {
        return Response::content(json_encode($manager()->executeUpload($upload, $action), JSON_UNESCAPED_SLASHES), 200, ['Content-Type' => 'application/json']);
    } catch (Throwable) { return Response::content(json_encode(['accepted'=>false,'status'=>'invalid_package','reason'=>'Package intake failed.']), 422, ['Content-Type'=>'application/json']); }
});
$app->router()->post($retryPath, function ($request) use ($app, $requireUser, $manager) {
    $user = $requireUser($request); if ($user instanceof Response) return $user;
    if ($app->csrf()->validateOrReject($request) instanceof Response) return $app->adminErrors()->response($request, 419);
    $result = $manager()->retry((string) $request->post('operation_id', ''));
    return Response::content(json_encode($result, JSON_UNESCAPED_SLASHES), 200, ['Content-Type' => 'application/json']);
});
$app->router()->post($reconcilePath, function ($request) use ($app, $requireUser, $manager) {
    $user = $requireUser($request); if ($user instanceof Response) return $user;
    if ($app->csrf()->validateOrReject($request) instanceof Response) return $app->adminErrors()->response($request, 419);
    try {
        return Response::content(json_encode($manager()->reconcile((string) $request->post('package_path', ''), $request->post('confirmed') === '1'), JSON_UNESCAPED_SLASHES), 200, ['Content-Type' => 'application/json']);
    } catch (Throwable) { return Response::content(json_encode(['accepted'=>false,'status'=>'unavailable','reason'=>'Reconciliation is unavailable.']), 503, ['Content-Type'=>'application/json']); }
});
