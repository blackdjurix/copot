<?php

use Copot\Core\Response;
use Copot\Core\SettingsException;

$adminPath = $app->config()->get('admin.path', 'admin');
$adminPermission = $app->config()->get('admin.permission', 'admin.access');

if (!is_string($adminPath) || !preg_match('/^[a-z0-9-]+$/', $adminPath)) {
    throw new RuntimeException('Invalid admin path configuration.');
}

if (!is_string($adminPermission) || trim($adminPermission) === '') {
    throw new RuntimeException('Invalid admin permission configuration.');
}

$adminPermission = trim($adminPermission);
$adminBase = '/' . $adminPath;
$settingsPath = $adminBase . '/settings';

$app->adminNavigation()->add('Settings', $settingsPath, 'settings.update');

$renderAdminLogin = function (string $path, string $email = '', ?string $error = null) use ($app): string {
    return $app->view()->render('admin/login', [
        'appName' => $app->config()->get('app.name', 'Copot'),
        'siteName' => $app->siteName(),
        'adminPath' => $path,
        'csrfToken' => $app->session()->csrfToken(),
        'email' => $email,
        'error' => $error,
    ]);
};

$renderAdminDashboard = function (string $path, $user) use ($app): string {
    $viewData = [
        'appName' => $app->config()->get('app.name', 'Copot'),
        'siteName' => $app->siteName(),
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

$settingsFields = [
    'site_name' => ['site', 'name'],
    'site_tagline' => ['site', 'tagline'],
    'localization_timezone' => ['localization', 'timezone'],
    'localization_locale' => ['localization', 'locale'],
    'localization_date_format' => ['localization', 'date_format'],
    'localization_time_format' => ['localization', 'time_format'],
];

$settingsErrorMessages = [
    'site_name' => 'Site Name is required and must not exceed 150 characters.',
    'site_tagline' => 'Site Tagline must not exceed 255 characters.',
    'localization_timezone' => 'Invalid timezone.',
    'localization_locale' => 'Unsupported locale.',
    'localization_date_format' => 'Unsupported date format.',
    'localization_time_format' => 'Unsupported time format.',
];

$settingsRequireUser = function () use ($app, $adminBase, $adminPermission) {
    if (!$app->auth()->check()) {
        return Response::redirect($adminBase);
    }

    $user = $app->auth()->user();

    if (!$user?->can($adminPermission) || !$user->can('settings.update')) {
        return Response::html('403 Forbidden', 403);
    }

    return $user;
};

$settingsEffectiveValues = function () use ($app): array {
    $site = $app->settings()->all('site');
    $localization = $app->settings()->all('localization');

    return [
        'site_name' => $site['name'],
        'site_tagline' => $site['tagline'],
        'localization_timezone' => $localization['timezone'],
        'localization_locale' => $localization['locale'],
        'localization_date_format' => $localization['date_format'],
        'localization_time_format' => $localization['time_format'],
    ];
};

$renderSettings = function (
    $user,
    array $values,
    array $errors = [],
    bool $saved = false,
    int $status = 200
) use ($app, $adminBase, $settingsPath): Response {
    $timezones = array_values(array_filter(
        timezone_identifiers_list(),
        static fn (string $timezone): bool => $timezone !== 'UTC'
    ));
    sort($timezones, SORT_STRING);
    array_unshift($timezones, 'UTC');

    $content = $app->view()->render('admin/settings', [
        'formAction' => $settingsPath,
        'csrfToken' => $app->csrf()->token(),
        'values' => $values,
        'errors' => $errors,
        'saved' => $saved,
        'timezones' => $timezones,
        'locales' => ['en_US', 'id_ID'],
        'dateFormats' => ['Y-m-d', 'd/m/Y', 'm/d/Y', 'd M Y'],
        'timeFormats' => ['H:i', 'h:i A'],
    ]);

    return Response::html($app->view()->render('admin/layout', [
        'title' => 'Settings',
        'appName' => $app->config()->get('app.name', 'Copot'),
        'siteName' => $app->siteName(),
        'adminPath' => $adminBase,
        'csrfToken' => $app->csrf()->token(),
        'userName' => $user->name(),
        'userEmail' => $user->email(),
        'navigation' => $app->adminNavigation()->itemsFor($user),
        'content' => $content,
    ]), $status);
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

$app->router()->get($settingsPath, function ($request) use (
    $settingsRequireUser,
    $settingsEffectiveValues,
    $renderSettings
): Response {
    $user = $settingsRequireUser();

    if ($user instanceof Response) {
        return $user;
    }

    try {
        $values = $settingsEffectiveValues();
    } catch (\PDOException) {
        return Response::html('Settings storage is unavailable.', 503);
    }

    return $renderSettings($user, $values, [], $request->input('saved') === '1');
});

$app->router()->post($settingsPath, function ($request) use (
    $app,
    $settingsFields,
    $settingsErrorMessages,
    $settingsRequireUser,
    $renderSettings,
    $settingsPath
): Response {
    $user = $settingsRequireUser();

    if ($user instanceof Response) {
        return $user;
    }

    $csrfResponse = $app->csrf()->validateOrReject($request);

    if ($csrfResponse instanceof Response) {
        return $csrfResponse;
    }

    $values = [];
    $errors = [];

    foreach ($settingsFields as $field => [$namespace, $key]) {
        $value = $request->post($field, '');
        $values[$field] = is_string($value) ? $value : '';

        try {
            $app->settings()->validate($namespace, $key, $values[$field]);
        } catch (SettingsException) {
            $errors[$field] = $settingsErrorMessages[$field];
        }
    }

    if ($errors !== []) {
        return $renderSettings($user, $values, $errors, false, 422);
    }

    $connection = null;

    try {
        $connection = $app->database()->connection();
        $connection->beginTransaction();

        foreach ($settingsFields as $field => [$namespace, $key]) {
            $app->settings()->set($namespace, $key, $values[$field]);
        }

        $connection->commit();
    } catch (\PDOException) {
        if ($connection?->inTransaction()) {
            $connection->rollBack();
        }

        return Response::html('Settings storage is unavailable.', 503);
    } catch (\Throwable $exception) {
        if ($connection?->inTransaction()) {
            $connection->rollBack();
        }

        throw $exception;
    }

    return Response::redirect($settingsPath . '?saved=1');
});
