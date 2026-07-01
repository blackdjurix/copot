<?php

use Copot\Core\Response;
use Copot\Core\SettingsException;

$adminUrl = $app->adminUrl();
$adminPermission = $app->config()->get('admin.permission', 'admin.access');

if (!is_string($adminPermission) || trim($adminPermission) === '') {
    throw new RuntimeException('Invalid admin permission configuration.');
}

$adminPermission = trim($adminPermission);
$adminBase = $adminUrl->baseUrl();
$settingsPath = $adminUrl->childUrl('settings');
$documentLocale = $app->adminPageRenderer()->documentLocale();

$app->adminNavigation()->add('Settings', $settingsPath, 'settings.update');

$renderAdminLogin = function (string $email = '', ?string $error = null) use ($app, $adminBase, $documentLocale): string {
    return $app->view()->render('admin/login', [
        'appName' => $app->config()->get('app.name', 'Copot'),
        'siteName' => $app->siteName(),
        'documentLocale' => $documentLocale,
        'adminBaseUrl' => $adminBase,
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
    string $currentPath = '',
    int $status = 200
) use ($app, $settingsPath): Response {
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

    return Response::html($app->adminPageRenderer()->render(
        'Settings',
        $content,
        $user,
        $app->csrf()->token(),
        $currentPath
    ), $status);
};

$app->router()->get($adminBase, function ($request) use ($app, $adminPermission, $renderAdminLogin, $renderAdminDashboard): Response {
    if (!$app->auth()->check()) {
        return Response::html($renderAdminLogin());
    }

    $user = $app->auth()->user();

    if (!$user?->can($adminPermission)) {
        return Response::html('403 Forbidden', 403);
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

    return Response::redirect($adminBase);
});

$app->router()->post($adminUrl->childUrl('logout'), function ($request) use ($app, $adminBase): Response {
    $token = $request->input('_token');

    if (!$app->session()->validateCsrf(is_string($token) ? $token : null)) {
        return Response::html('Invalid CSRF token.', 419);
    }

    $app->auth()->logout();

    return Response::redirect($adminBase);
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

    return $renderSettings($user, $values, [], $request->input('saved') === '1', $request->path());
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
        return $renderSettings($user, $values, $errors, false, $request->path(), 422);
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
