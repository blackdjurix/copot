<?php

use Copot\Core\Response;
use Copot\Core\SettingsException;
use Copot\Core\SiteAssetException;

$adminUrl = $app->adminUrl();
$adminPermission = $app->config()->get('admin.permission', 'admin.access');

if (!is_string($adminPermission) || trim($adminPermission) === '') {
    throw new RuntimeException('Invalid admin permission configuration.');
}

$adminPermission = trim($adminPermission);
$adminBase = $adminUrl->baseUrl();
$settingsPath = $adminUrl->childUrl('settings');
$logoUploadPath = $adminUrl->childUrl('settings/site-assets/logo');
$logoRemovePath = $adminUrl->childUrl('settings/site-assets/logo/remove');
$faviconUploadPath = $adminUrl->childUrl('settings/site-assets/favicon');
$faviconRemovePath = $adminUrl->childUrl('settings/site-assets/favicon/remove');
$app->adminNavigation()->add('Settings', $settingsPath, 'settings.update');

$settingsRenderView = static function (array $data): string {
    $file = __DIR__ . '/views/admin/settings.php';

    if (!is_file($file)) {
        throw new RuntimeException('Settings Manager view was not found.');
    }

    extract($data, EXTR_SKIP);
    $initialOutputLevel = ob_get_level();

    if (!@ob_start()) {
        throw new RuntimeException('Settings Manager view output buffer is unavailable.');
    }

    try {
        require $file;

        if (ob_get_level() !== $initialOutputLevel + 1) {
            throw new RuntimeException('Settings Manager view output buffer state is invalid.');
        }

        $rendered = @ob_get_clean();

        if (!is_string($rendered)) {
            throw new RuntimeException('Settings Manager view output buffer could not be read.');
        }

        return $rendered;
    } catch (Throwable $exception) {
        while (ob_get_level() > $initialOutputLevel) {
            $level = ob_get_level();

            if (!@ob_end_clean() || ob_get_level() >= $level) {
                break;
            }
        }

        throw $exception;
    }
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

$settingsRequireUser = function ($request) use ($app, $adminBase, $adminPermission) {
    if (!$app->auth()->check()) {
        return Response::redirect($adminBase);
    }

    $user = $app->auth()->user();

    if (!$user?->can($adminPermission) || !$user->can('settings.update')) {
        return $app->adminErrors()->response($request, 403);
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
    int $status = 200,
    array $assetErrors = [],
    ?string $assetNotice = null
) use (
    $app,
    $settingsPath,
    $logoUploadPath,
    $logoRemovePath,
    $faviconUploadPath,
    $faviconRemovePath,
    $settingsRenderView
): Response {
    $timezones = array_values(array_filter(
        timezone_identifiers_list(),
        static fn (string $timezone): bool => $timezone !== 'UTC'
    ));
    sort($timezones, SORT_STRING);
    array_unshift($timezones, 'UTC');

    $content = $settingsRenderView([
        'formAction' => $settingsPath,
        'csrfToken' => $app->csrf()->token(),
        'values' => $values,
        'errors' => $errors,
        'saved' => $saved,
        'timezones' => $timezones,
        'locales' => ['en_US', 'id_ID'],
        'dateFormats' => ['Y-m-d', 'd/m/Y', 'm/d/Y', 'd M Y'],
        'timeFormats' => ['H:i', 'h:i A'],
        'assetErrors' => $assetErrors,
        'assetNotice' => $assetNotice,
        'logoUrl' => $app->siteAssets()->url('logo'),
        'faviconUrl' => $app->siteAssets()->url('favicon'),
        'logoUploadAction' => $logoUploadPath,
        'logoRemoveAction' => $logoRemovePath,
        'faviconUploadAction' => $faviconUploadPath,
        'faviconRemoveAction' => $faviconRemovePath,
    ]);

    return Response::html($app->adminPageRenderer()->render(
        'Settings',
        $content,
        $user,
        $app->csrf()->token(),
        $currentPath
    ), $status);
};

$app->router()->get($settingsPath, function ($request) use (
    $app,
    $settingsRequireUser,
    $settingsEffectiveValues,
    $renderSettings
): Response {
    $user = $settingsRequireUser($request);

    if ($user instanceof Response) {
        return $user;
    }

    try {
        $values = $settingsEffectiveValues();
    } catch (\PDOException) {
        return $app->adminErrors()->response($request, 503);
    }

    $assetNotice = match ($request->input('asset')) {
        'logo-uploaded' => 'Logo uploaded successfully.',
        'logo-removed' => 'Logo removed successfully.',
        'favicon-uploaded' => 'Favicon uploaded successfully.',
        'favicon-removed' => 'Favicon removed successfully.',
        default => null,
    };

    return $renderSettings(
        $user,
        $values,
        [],
        $request->input('saved') === '1',
        $request->path(),
        200,
        [],
        $assetNotice
    );
});

$app->router()->post($settingsPath, function ($request) use (
    $app,
    $settingsFields,
    $settingsErrorMessages,
    $settingsRequireUser,
    $renderSettings,
    $settingsPath
): Response {
    $user = $settingsRequireUser($request);

    if ($user instanceof Response) {
        return $user;
    }

    $csrfResponse = $app->csrf()->validateOrReject($request);

    if ($csrfResponse instanceof Response) {
        return $app->adminErrors()->response($request, 419);
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

        return $app->adminErrors()->response($request, 503);
    } catch (\Throwable $exception) {
        if ($connection?->inTransaction()) {
            $connection->rollBack();
        }

        throw $exception;
    }

    return Response::redirect($settingsPath . '?saved=1');
});

$renderAssetFailure = function (
    $request,
    $user,
    string $slot,
    string $message,
    int $status = 422
) use ($app, $settingsEffectiveValues, $renderSettings, $settingsPath): Response {
    try {
        $values = $settingsEffectiveValues();
    } catch (\PDOException) {
        return $app->adminErrors()->response($request, 503);
    }

    return $renderSettings(
        $user,
        $values,
        [],
        false,
        $settingsPath,
        $status,
        [$slot => $message]
    );
};

$registerAssetUpload = function (
    string $slot,
    string $label,
    string $path
) use (
    $app,
    $settingsRequireUser,
    $renderAssetFailure,
    $settingsPath
): void {
    $app->router()->post($path, function ($request) use (
        $app,
        $slot,
        $label,
        $settingsRequireUser,
        $renderAssetFailure,
        $settingsPath
    ): Response {
        $user = $settingsRequireUser($request);

        if ($user instanceof Response) {
            return $user;
        }

        $csrfResponse = $app->csrf()->validateOrReject($request);

        if ($csrfResponse instanceof Response) {
            return $app->adminErrors()->response($request, 419);
        }

        $upload = $request->file('site_asset');

        if ($upload === null) {
            return $renderAssetFailure($request, $user, $slot, "Choose a {$label} file to upload.");
        }

        $error = $upload['error'];

        if (!is_int($error) || $error !== UPLOAD_ERR_OK) {
            $message = $error === UPLOAD_ERR_NO_FILE
                ? "Choose a {$label} file to upload."
                : "{$label} upload did not complete.";

            return $renderAssetFailure($request, $user, $slot, $message);
        }

        $temporaryPath = $upload['tmp_name'];

        if (!is_string($temporaryPath) || $temporaryPath === '' || !is_uploaded_file($temporaryPath)) {
            return $renderAssetFailure($request, $user, $slot, "{$label} upload source is invalid.");
        }

        try {
            $app->siteAssets()->store($slot, $temporaryPath);
        } catch (SiteAssetException) {
            return $renderAssetFailure(
                $request,
                $user,
                $slot,
                "{$label} could not be uploaded. Check the file type, dimensions, and size."
            );
        } catch (SettingsException|\PDOException) {
            return $renderAssetFailure($request, $user, $slot, 'Site asset storage is unavailable.', 503);
        }

        return Response::redirect($settingsPath . '?asset=' . $slot . '-uploaded');
    });
};

$registerAssetRemoval = function (
    string $slot,
    string $label,
    string $path
) use (
    $app,
    $settingsRequireUser,
    $renderAssetFailure,
    $settingsPath
): void {
    $app->router()->post($path, function ($request) use (
        $app,
        $slot,
        $label,
        $settingsRequireUser,
        $renderAssetFailure,
        $settingsPath
    ): Response {
        $user = $settingsRequireUser($request);

        if ($user instanceof Response) {
            return $user;
        }

        $csrfResponse = $app->csrf()->validateOrReject($request);

        if ($csrfResponse instanceof Response) {
            return $app->adminErrors()->response($request, 419);
        }

        try {
            $app->siteAssets()->remove($slot);
        } catch (SiteAssetException|SettingsException|\PDOException) {
            return $renderAssetFailure($request, $user, $slot, "{$label} could not be removed.", 503);
        }

        return Response::redirect($settingsPath . '?asset=' . $slot . '-removed');
    });
};

$registerAssetUpload('logo', 'Logo', $logoUploadPath);
$registerAssetRemoval('logo', 'Logo', $logoRemovePath);
$registerAssetUpload('favicon', 'Favicon', $faviconUploadPath);
$registerAssetRemoval('favicon', 'Favicon', $faviconRemovePath);
