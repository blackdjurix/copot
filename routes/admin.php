<?php

use Copot\Core\Response;

$adminPath = $app->config()->get('admin.path', 'admin');
$adminPermission = $app->config()->get('admin.permission', 'admin.access');

if (!is_string($adminPath) || !preg_match('/^[a-z0-9-]+$/', $adminPath)) {
    throw new RuntimeException('Invalid admin path configuration.');
}

if (!is_string($adminPermission) || trim($adminPermission) === '') {
    throw new RuntimeException('Invalid admin permission configuration.');
}

$adminPermission = trim($adminPermission);

$renderAdminLogin = function (string $path, string $email = '', ?string $error = null) use ($app): string {
    return $app->view()->render('admin/login', [
        'appName' => $app->config()->get('app.name', 'Copot'),
        'adminPath' => $path,
        'csrfToken' => $app->session()->csrfToken(),
        'email' => $email,
        'error' => $error,
    ]);
};

$renderAdminDashboard = function (string $path, $user) use ($app): string {
    $viewData = [
        'appName' => $app->config()->get('app.name', 'Copot'),
        'adminPath' => $path,
        'frameworkStatus' => 'M1.4.1 Admin Shell',
        'userName' => $user->name(),
        'userEmail' => $user->email(),
    ];

    $content = $app->view()->render('admin/dashboard', $viewData);

    return $app->view()->render('admin/layout', array_merge($viewData, [
        'title' => 'Admin Shell',
        'csrfToken' => $app->session()->csrfToken(),
        'navigation' => $app->adminNavigation()->itemsFor($user),
        'content' => $content,
    ]));
};

$app->router()->get('/' . $adminPath, function () use ($app, $adminPath, $adminPermission, $renderAdminLogin, $renderAdminDashboard): Response {
    $path = '/' . $adminPath;

    if (!$app->auth()->check()) {
        return Response::html($renderAdminLogin($path));
    }

    $user = $app->auth()->user();

    if (!$user?->can($adminPermission)) {
        return Response::html('403 Forbidden', 403);
    }

    return Response::html($renderAdminDashboard($path, $user));
});

$app->router()->post('/' . $adminPath, function ($request) use ($app, $adminPath, $renderAdminLogin): Response {
    $path = '/' . $adminPath;
    $email = strtolower(trim((string) $request->input('email', '')));
    $password = (string) $request->input('password', '');
    $token = $request->input('_token');

    if (!$app->session()->validateCsrf(is_string($token) ? $token : null)) {
        return Response::html('Invalid CSRF token.', 419);
    }

    if ($email === '' || $password === '' || !$app->auth()->attempt($email, $password)) {
        return Response::html($renderAdminLogin($path, $email, 'Invalid credentials or inactive account.'), 422);
    }

    return Response::redirect($path);
});

$app->router()->post('/' . $adminPath . '/logout', function ($request) use ($app, $adminPath): Response {
    $path = '/' . $adminPath;
    $token = $request->input('_token');

    if (!$app->session()->validateCsrf(is_string($token) ? $token : null)) {
        return Response::html('Invalid CSRF token.', 419);
    }

    $app->auth()->logout();

    return Response::redirect($path);
});
