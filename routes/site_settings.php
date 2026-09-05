<?php

use Copot\Core\HomepageHeroImageService;
use Copot\Core\MediaLifecycleService;
use Copot\Core\MediaRepository;
use Copot\Core\MediaUsageRepository;
use Copot\Core\Response;
use Copot\Core\SettingsException;
use Copot\Core\SiteAssetException;
use Copot\Core\WebcoreColorScheme;
use Copot\Core\ContentRepository;

require_once $app->path('app/Core/WebcoreColorScheme.php');
require_once $app->path('app/Core/HomepageHeroImageService.php');

$adminUrl = $app->adminUrl();
$path = $adminUrl->childUrl('settings');
$permission = 'settings.update';
$hero = static fn (): HomepageHeroImageService => new HomepageHeroImageService(
    $app->settings(),
    $app->database(),
    new MediaRepository($app->database()),
    new MediaUsageRepository($app->database()),
    new MediaLifecycleService($app->database(), new MediaRepository($app->database()), null, new MediaUsageRepository($app->database()))
);

$requireUser = static function ($request) use ($app, $permission) {
    if (!$app->auth()->check()) return Response::redirect($app->adminUrl()->baseUrl());
    $user = $app->auth()->user();
    $adminPermission = $app->config()->get('admin.permission', 'admin.access');
    if (!$user || !$user->can((string) $adminPermission) || !$user->can($permission)) return $app->adminErrors()->response($request, 403);
    return $user;
};

$value = static fn (string $namespace, string $key, mixed $default = null): mixed => $app->settings()->get($namespace, $key, $default);
$render = static function ($request, $user, array $errors = [], ?string $notice = null, int $status = 200) use ($app, $adminUrl, $path, $value, $hero): Response {
    try {
        $selected = $hero()->selected();
        $media = $user->can('media.use') ? (new MediaRepository($app->database()))->paginate('image', 100, 0) : [];
        $pageOptions = (new ContentRepository($app->database()))->workspace(['type' => 'page', 'status' => 'published'], 100, 0)['items'];
        $view = $app->view()->render('admin/site-settings', [
            'path' => $path,
            'csrfToken' => $app->csrf()->token(),
            'values' => [
                'name' => $value('site', 'name', 'copot'), 'tagline' => $value('site', 'tagline', ''),
                'locale' => $value('localization', 'locale', 'en_US'), 'timezone' => $value('localization', 'timezone', 'UTC'),
                'date_format' => $value('localization', 'date_format', 'Y-m-d'), 'time_format' => $value('localization', 'time_format', 'H:i'),
                'main_color' => $value('appearance', 'main_color', '#1769e0'), 'hero_media' => $selected?->id()->value(),
                'homepage_content' => $value('site', 'homepage_content', null),
            ],
            'homepageContentPages' => $pageOptions,
            'media' => $media, 'mediaUrl' => static fn (int $id): string => $app->url('/media/' . $id), 'mediaPickerUrl' => $adminUrl->childUrl('media/select'), 'mediaPickerUploadUrl' => $adminUrl->childUrl('media/select/upload'), 'canMediaUpload' => $user->can('media.upload'), 'errors' => $errors, 'notice' => $notice,
            'locales' => \Copot\Core\SettingsRegistry::core()->find('localization', 'locale')?->allowedValues() ?? [],
            'timezones' => timezone_identifiers_list(),
            'dateFormats' => \Copot\Core\SettingsRegistry::core()->find('localization', 'date_format')?->allowedValues() ?? [],
            'timeFormats' => \Copot\Core\SettingsRegistry::core()->find('localization', 'time_format')?->allowedValues() ?? [],
            'siteAssets' => $app->siteAssets(),
            'logoUploadAction' => $adminUrl->childUrl('settings/site-assets/logo'),
            'logoRemoveAction' => $adminUrl->childUrl('settings/site-assets/logo/remove'),
            'faviconUploadAction' => $adminUrl->childUrl('settings/site-assets/favicon'),
            'faviconRemoveAction' => $adminUrl->childUrl('settings/site-assets/favicon/remove'),
            'colorScheme' => WebcoreColorScheme::resolve($value('appearance', 'main_color', '#1769e0')),
        ]);
        return Response::html($app->adminPageRenderer()->render('Site Settings', $view, $user, $app->csrf()->token(), $request->path(), ['description' => 'Configure the site identity and baseline appearance.', 'surface' => 'transparent', 'spacing' => 'default']), $status);
    } catch (Throwable) { return $app->adminErrors()->response($request, 503); }
};

$app->adminNavigation()->add('Site Settings', $path, $permission, 'settings', 70);

$app->router()->get($path, function ($request) use ($requireUser, $render): Response {
    $user = $requireUser($request); if ($user instanceof Response) return $user;
    return $render($request, $user, [], $request->input('saved') === '1' ? 'Site Settings saved successfully.' : null);
});

$app->router()->post($path, function ($request) use ($app, $requireUser, $render, $hero, $path): Response {
    $user = $requireUser($request); if ($user instanceof Response) return $user;
    if ($app->csrf()->validateOrReject($request) instanceof Response) return $app->adminErrors()->response($request, 419);
    $submitted = [
        'site.name' => (string) $request->post('site_name', ''), 'site.tagline' => (string) $request->post('site_tagline', ''),
        'localization.locale' => (string) $request->post('locale', ''), 'localization.timezone' => (string) $request->post('timezone', ''),
        'localization.date_format' => (string) $request->post('date_format', ''), 'localization.time_format' => (string) $request->post('time_format', ''),
        'appearance.main_color' => (string) $request->post('main_color', ''),
    ];
    $errors = [];
    $homepageContentChoice = (string) $request->post('homepage_content_type', 'none');
    $homepageContent = null;
    if ($homepageContentChoice === 'page') {
        $pageId = $request->post('homepage_content_page');
        if (!is_scalar($pageId) || !preg_match('/^[1-9][0-9]*$/', (string) $pageId)) $errors['homepage_content'] = 'Select a valid published Page.';
        else {
            $page = (new ContentRepository($app->database()))->findById((int) $pageId);
            if ($page === null || !$page->isPublished() || $page->type() !== 'page') $errors['homepage_content'] = 'Select a valid published Page.';
            else $homepageContent = ['type' => 'page', 'id' => $page->id()];
        }
    } elseif ($homepageContentChoice === 'article_collection') {
        $homepageContent = ['type' => 'article_collection', 'reference' => 'articles'];
    } elseif ($homepageContentChoice !== 'none') $errors['homepage_content'] = 'Select a valid Homepage Content source.';
    foreach ($submitted as $identifier => $candidate) {
        [$namespace, $key] = explode('.', $identifier, 2);
        try { $app->settings()->validate($namespace, $key, $candidate, 'string'); } catch (Throwable) { $errors[$identifier] = 'Enter a valid value.'; }
    }
    $heroId = $request->post('hero_media');
    $heroId = $heroId === null || $heroId === '' ? null : (preg_match('/^[1-9][0-9]*$/', (string) $heroId) ? (int) $heroId : -1);
    if ($heroId !== null && !$user->can('media.use')) $errors['hero_media'] = 'Media selection permission is required.';
    try { if ($heroId === -1) throw new SettingsException('The selected Hero Image is invalid.'); } catch (Throwable) { $errors['hero_media'] = 'The selected Hero Image is unavailable.'; }
    if ($errors !== []) return $render($request, $user, $errors, null, 422);
    $connection = $app->database()->connection(); $connection->beginTransaction();
    try { $hero()->set($heroId); foreach ($submitted as $identifier => $candidate) { [$namespace, $key] = explode('.', $identifier, 2); $app->settings()->set($namespace, $key, $candidate, 'string'); } $app->settings()->set('site', 'homepage_content', $homepageContent, 'json'); $connection->commit();
    } catch (Throwable $failure) { if ($connection->inTransaction()) $connection->rollBack(); return $render($request, $user, ['form' => 'Site Settings could not be saved.'], null, 422); }
    return Response::redirect($path . '?saved=1');
});

$assetRoute = static function (string $slot, string $action) use ($app, $adminUrl, $requireUser, $render, $path): void {
    $route = $adminUrl->childUrl('settings/site-assets/' . $slot . ($action === 'remove' ? '/remove' : ''));
    $app->router()->post($route, function ($request) use ($app, $requireUser, $render, $path, $slot, $action): Response {
        $user = $requireUser($request); if ($user instanceof Response) return $user;
        if ($app->csrf()->validateOrReject($request) instanceof Response) return $app->adminErrors()->response($request, 419);
        try {
            if ($action === 'remove') $app->siteAssets()->remove($slot);
            else { $upload = $request->file('site_asset'); $tmp = is_array($upload) ? ($upload['tmp_name'] ?? '') : ''; if (!is_string($tmp) || !is_uploaded_file($tmp)) throw new SiteAssetException('Invalid upload.'); $app->siteAssets()->store($slot, $tmp); }
            return Response::redirect($path . '?saved=1');
        } catch (Throwable) { return $render($request, $user, [$slot => 'The site asset operation could not be completed.'], null, 422); }
    });
};
$assetRoute('logo', 'upload'); $assetRoute('logo', 'remove'); $assetRoute('favicon', 'upload'); $assetRoute('favicon', 'remove');
