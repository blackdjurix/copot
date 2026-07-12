<?php

declare(strict_types=1);

use Copot\Core\Request;
use Copot\Core\SiteBranding;

$basePath = dirname(__DIR__);

require $basePath . '/bootstrap/autoload.php';

$assertions = 0;

$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;

    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$assertSame = static function (mixed $expected, mixed $actual, string $message) use ($assert): void {
    $assert(
        $expected === $actual,
        $message . ' Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . '.'
    );
};

$upload = [
    'name' => 'logo.png',
    'type' => 'image/png',
    'tmp_name' => '/tmp/php-upload',
    'error' => UPLOAD_ERR_OK,
    'size' => 123,
];

$request = new Request('POST', '/admin/settings/site-assets/logo', [], [], ['site_asset' => $upload]);
$assertSame($upload, $request->file('site_asset'), 'Request did not expose the controlled upload entry.');
$assertSame(null, $request->file('missing'), 'Missing upload did not return null.');
$nested = $upload;
$nested['name'] = ['logo.png'];
$nestedRequest = new Request('POST', '/', [], [], ['site_asset' => $nested]);
$assertSame(null, $nestedRequest->file('site_asset'), 'Nested upload shape was accepted.');

$branding = new SiteBranding('Example Site', 'A safe tagline.', '/site-assets/logo', '/site-assets/favicon');
$themeAsset = static fn (string $path): string => '/theme-assets/default/' . $path;
$title = $branding->name();

$render = static function (string $path, array $variables): string {
    extract($variables, EXTR_SKIP);
    ob_start();

    try {
        require $path;

        return (string) ob_get_clean();
    } catch (Throwable $exception) {
        ob_end_clean();

        throw $exception;
    }
};

$content = $render($basePath . '/themes/default/views/home.php', [
    'title' => $title,
    'branding' => $branding,
    'themeAsset' => $themeAsset,
    'context' => [],
]);

$html = $render($basePath . '/themes/default/layouts/app.php', [
    'content' => $content,
    'title' => $title,
    'branding' => $branding,
    'themeAsset' => $themeAsset,
    'context' => [],
]);

$assert(str_contains($html, '<title>Example Site</title>'), 'Theme title did not consume SiteBranding.');
$assert(str_contains($html, 'href="/site-assets/favicon"'), 'Theme did not render the controlled Favicon URL.');
$assert(str_contains($html, 'src="/site-assets/logo"'), 'Theme did not render the controlled Logo URL.');
$assert(str_contains($html, 'Example Site'), 'Theme did not render the site name.');
$assert(str_contains($html, 'A safe tagline.'), 'Theme did not render the site tagline.');
$assert(!str_contains($html, 'logo-'), 'Theme exposed an internal asset descriptor.');

$emptyBranding = new SiteBranding('Fallback Site', '');
$emptyContent = $render($basePath . '/themes/default/views/home.php', [
    'title' => 'Fallback Site',
    'branding' => $emptyBranding,
    'themeAsset' => $themeAsset,
    'context' => [],
]);
$emptyHtml = $render($basePath . '/themes/default/layouts/app.php', [
    'content' => $emptyContent,
    'title' => 'Fallback Site',
    'branding' => $emptyBranding,
    'themeAsset' => $themeAsset,
    'context' => [],
]);
$assert(!str_contains($emptyHtml, 'rel="icon"'), 'Unset Favicon still rendered a link.');
$assert(!str_contains($emptyHtml, 'site-header__logo'), 'Unset Logo still rendered an image.');
$assert(str_contains($emptyHtml, 'Default frontend theme rendering is active.'), 'Empty Tagline fallback is missing.');

$adminRoutes = (string) file_get_contents($basePath . '/modules/settings-manager/routes.php');
$settingsView = (string) file_get_contents($basePath . '/modules/settings-manager/views/admin/settings.php');
$requestSource = (string) file_get_contents($basePath . '/app/Core/Request.php');
$rendererSource = (string) file_get_contents($basePath . '/app/Core/ViewRenderer.php');
$applicationSource = (string) file_get_contents($basePath . '/app/Core/Application.php');
$webRoutes = (string) file_get_contents($basePath . '/routes/web.php');
$adminCss = (string) file_get_contents($basePath . '/public/admin-assets/css/admin.css');
$themeCss = (string) file_get_contents($basePath . '/themes/default/assets/css/app.css');

foreach ([
    "'settings/site-assets/logo'",
    "'settings/site-assets/logo/remove'",
    "'settings/site-assets/favicon'",
    "'settings/site-assets/favicon/remove'",
] as $routeFragment) {
    $assert(str_contains($adminRoutes, $routeFragment), "Admin asset route {$routeFragment} is missing.");
}

$assert(str_contains($adminRoutes, '$settingsRequireUser'), 'Admin asset routes do not reuse the permission guard.');
$assert(str_contains($adminRoutes, 'validateOrReject'), 'Admin asset routes do not enforce CSRF.');
$assert(str_contains($adminRoutes, 'is_uploaded_file'), 'Admin upload adapter does not require a PHP upload source.');
$assert(str_contains($adminRoutes, "file('site_asset')"), 'Admin upload adapter does not use Request file access.');
$assert(str_contains($adminRoutes, 'SiteAssetException'), 'Admin upload adapter does not sanitize asset failures.');
$assert(!str_contains($adminRoutes, '$_FILES'), 'Admin routes read upload globals directly.');
$assert(!str_contains($adminRoutes, "'/site-assets/{"), 'Admin added an arbitrary public asset route.');

$assert(substr_count($settingsView, 'enctype="multipart/form-data"') === 2, 'Settings UI does not contain exactly two upload forms.');
$assert(str_contains($settingsView, 'accept="image/png,image/jpeg,image/webp"'), 'Logo input allowlist is missing.');
$assert(str_contains($settingsView, 'accept="image/png,image/x-icon,image/vnd.microsoft.icon,.ico"'), 'Favicon input allowlist is missing.');
$assert(str_contains($settingsView, 'Remove Logo'), 'Logo removal control is missing.');
$assert(str_contains($settingsView, 'Remove Favicon'), 'Favicon removal control is missing.');
$assert(str_contains($settingsView, 'admin-asset-preview'), 'Asset preview UI is missing.');

$assert(str_contains($requestSource, 'private array $files = []'), 'Request upload storage is missing.');
$assert(str_contains($requestSource, '$_FILES'), 'Request capture does not receive PHP uploads.');
$assert(str_contains($rendererSource, 'private SiteBranding $branding'), 'ViewRenderer does not own SiteBranding.');
$assert(str_contains($rendererSource, "'branding' => \$this->branding"), 'ViewRenderer does not expose SiteBranding to Theme files.');
$assert(str_contains($applicationSource, 'new ViewRenderer($this->themeLoader, $this->themeAssets, $this->branding)'), 'Application does not wire SiteBranding into ViewRenderer.');
$assert(str_contains($webRoutes, '$app->branding()->name()'), 'Public home title does not use SiteBranding.');
$assert(str_contains($adminCss, '.admin-asset-grid'), 'Admin asset layout styles are missing.');
$assert(str_contains($themeCss, '.site-header__logo'), 'Theme Logo styles are missing.');

echo "M2.3 Batch 5 branding integration smoke tests passed ({$assertions} assertions)." . PHP_EOL;
