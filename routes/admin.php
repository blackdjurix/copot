<?php

use Copot\Core\Response;

$adminUrl = $app->adminUrl();
$adminPermission = $app->config()->get('admin.permission', 'admin.access');

if (!is_string($adminPermission) || trim($adminPermission) === '') {
    throw new RuntimeException('Invalid admin permission configuration.');
}

$adminPermission = trim($adminPermission);
$adminBase = $adminUrl->baseUrl();
$documentLocale = $app->adminPageRenderer()->documentLocale();

$app->adminDashboard()->add(
    'core.system-health',
    'System Health',
    'Authorized System Health summary for this installation and viewer.',
    null,
    'admin.access',
    90,
    [
        'owner' => 'core',
        'purpose' => 'status',
        'footprint' => 'wide',
        'provider' => static function ($user) use ($app): array {
            return (new \Copot\Core\SystemHealthDashboardConsumer())->content($app->systemHealthReport($user));
        },
    ]
);

$app->adminDashboard()->add(
    'core.system-overview',
    'System overview',
    'Application, Admin path, current user, and safe framework status.',
    null,
    'admin.access',
    100,
    [
        'owner' => 'core',
        'purpose' => 'status',
        'footprint' => 'wide',
        'provider' => static function ($user) use ($app): array {
            return [
                'application' => (string) $app->config()->get('app.name', 'Copot'),
                'admin_path' => $app->adminUrl()->baseUrl(),
                'user_name' => $user->name(),
                'user_email' => $user->email(),
                'framework_status' => 'M1.4.1 Admin Shell',
            ];
        },
    ]
);

$renderAdminLogin = function (string $email = '', ?string $error = null) use ($app, $adminBase, $documentLocale): string {
    return $app->view()->render('admin/login', [
        'appName' => $app->config()->get('app.name', 'Copot'),
        'siteName' => $app->siteName(),
        'documentLocale' => $documentLocale,
        'adminBaseUrl' => $adminBase,
        'url' => fn (string $path): string => $app->url($path),
        'csrfToken' => $app->session()->csrfToken(),
        'email' => $email,
        'error' => $error,
    ]);
};

$renderAdminDashboard = function (string $currentPath, $user) use ($app, $adminBase): string {
    $viewData = [
        'appName' => $app->config()->get('app.name', 'Copot'),
        'siteName' => $app->siteName(),
        'adminBaseUrl' => $adminBase,
        'frameworkStatus' => 'M1.4.1 Admin Shell',
        'userName' => $user->name(),
        'userEmail' => $user->email(),
        'widgets' => $app->adminDashboard()->itemsFor($user),
    ];

    $content = $app->view()->render('admin/dashboard', $viewData);

    return $app->adminPageRenderer()->render(
        'Dashboard',
        $content,
        $user,
        $app->session()->csrfToken(),
        $currentPath
    );
};

$app->router()->get($adminBase, function ($request) use ($app, $adminPermission, $renderAdminLogin, $renderAdminDashboard): Response {
    if (!$app->auth()->check()) {
        return Response::html($renderAdminLogin());
    }

    $user = $app->auth()->user();

    if (!$user?->can($adminPermission)) {
        return $app->adminErrors()->response($request, 403);
    }

    return Response::html($renderAdminDashboard($request->path(), $user));
});

$app->router()->post($adminBase, function ($request) use ($app, $adminBase, $renderAdminLogin): Response {
    $email = strtolower(trim((string) $request->input('email', '')));
    $password = (string) $request->input('password', '');
    $token = $request->input('_token');

    if (!$app->session()->validateCsrf(is_string($token) ? $token : null)) {
        return Response::html('Invalid CSRF token.', 419);
    }

    if ($email === '' || $password === '' || !$app->auth()->attempt($email, $password)) {
        return Response::html($renderAdminLogin($email, 'Invalid credentials or inactive account.'), 422);
    }

    return Response::redirect($app->url($adminBase));
});

$app->router()->post($adminUrl->childUrl('logout'), function ($request) use ($app, $adminBase): Response {
    $token = $request->input('_token');

    if (!$app->session()->validateCsrf(is_string($token) ? $token : null)) {
        return Response::html('Invalid CSRF token.', 419);
    }

    $app->auth()->logout();

    return Response::redirect($app->url($adminBase));
});
