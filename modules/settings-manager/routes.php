<?php

use Copot\Core\Response;
use Copot\Core\SettingsException;
use Copot\Core\SiteAssetException;

require_once __DIR__ . '/Services/SettingsManagerPolicy.php';
require_once __DIR__ . '/Services/SettingsField.php';
require_once __DIR__ . '/Services/SettingsSection.php';
require_once __DIR__ . '/Services/SettingsFieldMapper.php';
require_once __DIR__ . '/Services/SettingsValidationException.php';
require_once __DIR__ . '/Services/SettingsManager.php';

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
$settingsManager = new SettingsManager(
    $app->settings(),
    new SettingsFieldMapper(SettingsManagerPolicy::defaults()),
    $app->database()
);

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

$settingsEffectiveValues = function (array $sections) use ($app): array {
    $values = [];

    foreach ($sections as $section) {
        foreach ($section->fields() as $field) {
            $values[$field->identifier()] = $app->settings()->get($field->namespace(), $field->key());
        }
    }

    return $values;
};

$renderSettings = function (
    $user,
    array $sections,
    array $values,
    array $fieldErrors = [],
    array $formErrors = [],
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
    $content = $settingsRenderView([
        'formAction' => $settingsPath,
        'csrfToken' => $app->csrf()->token(),
        'sections' => $sections,
        'values' => $values,
        'fieldErrors' => $fieldErrors,
        'formErrors' => $formErrors,
        'saved' => $saved,
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
    $settingsManager,
    $settingsRequireUser,
    $settingsEffectiveValues,
    $renderSettings
): Response {
    $user = $settingsRequireUser($request);

    if ($user instanceof Response) {
        return $user;
    }

    try {
        $sections = $settingsManager->sections();
        $values = $settingsEffectiveValues($sections);
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
        $sections,
        $values,
        [],
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
    $settingsManager,
    $settingsRequireUser,
    $settingsEffectiveValues,
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

    try {
        $sections = $settingsManager->sections();
        $submitted = $request->post('settings');

        if (!is_array($submitted)) {
            $values = $settingsEffectiveValues($sections);

            return $renderSettings(
                $user,
                $sections,
                $values,
                [],
                ['The submitted settings payload is invalid.'],
                false,
                $request->path(),
                422
            );
        }

        $settingsManager->save($submitted);
    } catch (SettingsValidationException $exception) {
        try {
            $values = array_replace(
                $settingsEffectiveValues($sections),
                $exception->submittedValues()
            );
        } catch (SettingsException|\PDOException) {
            return $app->adminErrors()->response($request, 503);
        }

        return $renderSettings(
            $user,
            $sections,
            $values,
            $exception->fieldErrors(),
            $exception->formErrors(),
            false,
            $request->path(),
            422
        );
    } catch (SettingsException|\PDOException) {
        return $app->adminErrors()->response($request, 503);
    }

    return Response::redirect($settingsPath . '?saved=1');
});

$renderAssetFailure = function (
    $request,
    $user,
    string $slot,
    string $message,
    int $status = 422
) use ($app, $settingsManager, $settingsEffectiveValues, $renderSettings, $settingsPath): Response {
    try {
        $sections = $settingsManager->sections();
        $values = $settingsEffectiveValues($sections);
    } catch (\PDOException) {
        return $app->adminErrors()->response($request, 503);
    }

    return $renderSettings(
        $user,
        $sections,
        $values,
        [],
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
