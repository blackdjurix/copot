<?php

declare(strict_types=1);

use Copot\Core\Application;
use Copot\Core\Database;
use Copot\Core\Response;
use Copot\Core\SettingsRegistry;
use Copot\Core\SettingsRepository;
use Copot\Core\SettingsService;
use Copot\Core\SiteAssetException;
use Copot\Core\SiteAssetStorage;
use Copot\Core\SiteBranding;

$basePath = dirname(__DIR__);
require $basePath . '/bootstrap/autoload.php';

final class Batch4SettingsRepository extends SettingsRepository
{
    private array $overrides;
    private bool $failWrites = false;

    public function __construct(array $overrides = [])
    {
        $this->overrides = $overrides;
    }

    public function findOverride(string $namespace, string $key): ?array
    {
        return $this->overrides[$namespace . '.' . $key] ?? null;
    }

    public function upsertOverride(string $namespace, string $key, string $storedValue, string $valueType): void
    {
        if ($this->failWrites) {
            throw new PDOException('fixture write failure');
        }

        $this->overrides[$namespace . '.' . $key] = [
            'setting_value' => $storedValue,
            'value_type' => $valueType,
        ];
    }

    public function deleteOverride(string $namespace, string $key): void
    {
        unset($this->overrides[$namespace . '.' . $key]);
    }

    public function failWrites(bool $fail): void
    {
        $this->failWrites = $fail;
    }
}

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$assertSame = static function (mixed $expected, mixed $actual, string $message) use (&$assertions): void {
    $assertions++;
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . '.');
    }
};
$expectFailure = static function (callable $callback, string $message) use (&$assertions): void {
    $assertions++;
    try {
        $callback();
    } catch (SiteAssetException) {
        return;
    }
    throw new RuntimeException($message);
};
$responseValue = static function (Response $response, string $property): mixed {
    return (new ReflectionProperty($response, $property))->getValue($response);
};
$removeDirectory = static function (string $path) use (&$removeDirectory): void {
    if (!is_dir($path)) {
        return;
    }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $candidate = $path . DIRECTORY_SEPARATOR . $entry;
        if (is_dir($candidate) && !is_link($candidate)) {
            $removeDirectory($candidate);
        } else {
            @unlink($candidate);
        }
    }
    @rmdir($path);
};

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-m2-3-batch4-' . bin2hex(random_bytes(6));
$storageParent = $root . DIRECTORY_SEPARATOR . 'storage';
$fixtureDirectory = $root . DIRECTORY_SEPARATOR . 'fixtures';
mkdir($storageParent, 0777, true);
mkdir($fixtureDirectory, 0777, true);

$fixtures = [
    'logo.png' => 'iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAYAAABytg0kAAAAFElEQVR4nGP8z8Dwn4GBgYGJAQoAHxcCAk+Uzr4AAAAASUVORK5CYII=',
    'logo.jpg' => '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAACAAIDASIAAhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/8QAHwEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSExBhJBUQdhcRMiMoEIFEKRobHBCSMzUvAVYnLRChYkNOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOEhYaHiImKkpOUlZaXmJmaoqOkpaanqKmqsrO0tba3uLm6wsPExcbHyMnK0tPU1dbX2Nna4uPk5ebn6Onq8vP09fb3+Pn6/9oADAMBAAIRAxEAPwDi6KKK+ZP3E//Z',
    'logo.webp' => 'UklGRjwAAABXRUJQVlA4IDAAAADQAQCdASoCAAIAAUAmJaACdLoB+AADsAD+8ut//NgVzXPv9//S4P0uD9Lg/9KQAAA=',
    'favicon.ico' => 'AAABAAEAEBAAAAAAIABWAAAAFgAAAIlQTkcNChoKAAAADUlIRFIAAAAQAAAAEAgGAAAAH/P/YQAAAB1JREFUeJxj/M/A8J+BAsBEieZRA0YNGDVgMBkAAFhtAh71zM+iAAAAAElFTkSuQmCC',
    'wide.png' => 'iVBORw0KGgoAAAANSUhEUgAAE4gAAAABCAYAAAAyEUSIAAAALUlEQVR4nO3BMQEAAAwCINc/tMbYA1yTBgAAAAAAAAAAAAAAAAAAAAAAAIB3A0YpAgA/m8UmAAAAAElFTkSuQmCC',
];
foreach ($fixtures as $name => $encoded) {
    file_put_contents($fixtureDirectory . DIRECTORY_SEPARATOR . $name, base64_decode($encoded, true));
}
file_put_contents($fixtureDirectory . DIRECTORY_SEPARATOR . 'invalid.txt', 'not an image');
file_put_contents(
    $fixtureDirectory . DIRECTORY_SEPARATOR . 'oversized.png',
    base64_decode($fixtures['logo.png'], true) . str_repeat('x', 2097152)
);

try {
    $repository = new Batch4SettingsRepository();
    $settings = new SettingsService(SettingsRegistry::core(), $repository);
    $assets = new SiteAssetStorage($storageParent . DIRECTORY_SEPARATOR . 'site-assets', $settings);

    $assertSame(null, $assets->url('logo'), 'Unset Logo unexpectedly has a URL.');
    $notFound = $assets->serve('logo');
    $assertSame(404, $responseValue($notFound, 'status'), 'Unset Logo did not return 404.');
    $assertSame('404 Not Found', $responseValue($notFound, 'content'), 'Unset Logo response leaked details.');

    $logo = $assets->store('logo', $fixtureDirectory . DIRECTORY_SEPARATOR . 'logo.png');
    $assert((bool) preg_match('/^logo-[a-f0-9]{32}\.png$/', $logo['filename']), 'Logo filename is not canonical.');
    $assertSame('image/png', $logo['mime_type'], 'Logo MIME is incorrect.');
    $assertSame(filesize($fixtureDirectory . DIRECTORY_SEPARATOR . 'logo.png'), $logo['size'], 'Logo size is incorrect.');
    $assertSame($logo, $settings->get('site', 'logo'), 'Logo descriptor was not persisted.');
    $assertSame('/site-assets/logo', $assets->url('logo'), 'Active Logo URL is incorrect.');
    $logoPath = $storageParent . DIRECTORY_SEPARATOR . 'site-assets' . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $logo['filename'];
    $assert(is_file($logoPath), 'Stored Logo file is missing.');
    $assertSame(file_get_contents($fixtureDirectory . DIRECTORY_SEPARATOR . 'logo.png'), file_get_contents($logoPath), 'Stored Logo bytes changed.');

    $servedLogo = $assets->serve('logo');
    $headers = $responseValue($servedLogo, 'headers');
    $assertSame(200, $responseValue($servedLogo, 'status'), 'Stored Logo did not return 200.');
    $assertSame('image/png', $headers['Content-Type'] ?? null, 'Stored Logo response MIME is incorrect.');
    $assertSame('no-store', $headers['Cache-Control'] ?? null, 'Stored Logo response cache policy is incorrect.');
    $assertSame('nosniff', $headers['X-Content-Type-Options'] ?? null, 'Stored Logo response lacks nosniff.');
    $assertSame(file_get_contents($logoPath), $responseValue($servedLogo, 'content'), 'Stored Logo response bytes are incorrect.');

    $replacement = $assets->store('logo', $fixtureDirectory . DIRECTORY_SEPARATOR . 'logo.jpg');
    $replacementPath = dirname($logoPath) . DIRECTORY_SEPARATOR . $replacement['filename'];
    $assertSame('image/jpeg', $replacement['mime_type'], 'Replacement Logo MIME is incorrect.');
    $assert(is_file($replacementPath), 'Replacement Logo file is missing.');
    $assert(!file_exists($logoPath), 'Previous Logo file was not cleaned after replacement.');
    $assertSame($replacement, $settings->get('site', 'logo'), 'Replacement Logo descriptor is not active.');

    $favicon = $assets->store('favicon', $fixtureDirectory . DIRECTORY_SEPARATOR . 'favicon.ico');
    $assert((bool) preg_match('/^favicon-[a-f0-9]{32}\.ico$/', $favicon['filename']), 'Favicon filename is not canonical.');
    $assert(in_array($favicon['mime_type'], ['image/x-icon', 'image/vnd.microsoft.icon'], true), 'Favicon MIME is incorrect.');
    $assertSame('/site-assets/favicon', $assets->url('favicon'), 'Active Favicon URL is incorrect.');

    $branding = new SiteBranding('Example', 'Tagline', $assets->url('logo'), $assets->url('favicon'));
    $assertSame('/site-assets/logo', $branding->logoUrl(), 'SiteBranding did not expose the controlled Logo URL.');
    $assertSame('/site-assets/favicon', $branding->faviconUrl(), 'SiteBranding did not expose the controlled Favicon URL.');

    $expectFailure(static fn () => $assets->store('banner', $fixtureDirectory . DIRECTORY_SEPARATOR . 'logo.png'), 'Unknown slot was accepted.');
    $expectFailure(static fn () => $assets->store('logo', $fixtureDirectory . DIRECTORY_SEPARATOR . 'invalid.txt'), 'Non-image content was accepted.');
    $expectFailure(static fn () => $assets->store('favicon', $fixtureDirectory . DIRECTORY_SEPARATOR . 'logo.jpg'), 'Disallowed Favicon MIME was accepted.');
    $expectFailure(static fn () => $assets->store('logo', $fixtureDirectory . DIRECTORY_SEPARATOR . 'missing.png'), 'Missing source was accepted.');
    $expectFailure(static fn () => $assets->store('logo', $fixtureDirectory . DIRECTORY_SEPARATOR . 'oversized.png'), 'Oversized Logo was accepted.');
    $expectFailure(static fn () => $assets->store('logo', $fixtureDirectory . DIRECTORY_SEPARATOR . 'wide.png'), 'Oversized Logo dimensions were accepted.');

    $sourceLink = $fixtureDirectory . DIRECTORY_SEPARATOR . 'linked-logo.png';
    if (@symlink($fixtureDirectory . DIRECTORY_SEPARATOR . 'logo.png', $sourceLink)) {
        $expectFailure(static fn () => $assets->store('logo', $sourceLink), 'Symlinked source was accepted.');
    }

    $oldDescriptor = $settings->get('site', 'logo');
    $oldPath = dirname($replacementPath) . DIRECTORY_SEPARATOR . $oldDescriptor['filename'];
    $repository->failWrites(true);
    $expectFailure(static fn () => $assets->store('logo', $fixtureDirectory . DIRECTORY_SEPARATOR . 'logo.webp'), 'Persistence failure did not fail closed.');
    $repository->failWrites(false);
    $assertSame($oldDescriptor, $settings->get('site', 'logo'), 'Persistence failure changed the active descriptor.');
    $assert(is_file($oldPath), 'Persistence failure removed the active file.');
    $storedLogoFiles = array_values(array_filter(scandir(dirname($oldPath)) ?: [], static fn (string $name): bool => $name !== '.' && $name !== '..'));
    $assertSame(1, count($storedLogoFiles), 'Persistence failure left a new file or temporary file behind.');

    $assets->remove('logo');
    $assertSame(null, $settings->get('site', 'logo'), 'Logo removal did not persist null.');
    $assertSame(null, $assets->url('logo'), 'Removed Logo still has a URL.');
    $assert(!file_exists($oldPath), 'Removed Logo file still exists.');

    $repository->failWrites(true);
    $activeFavicon = $settings->get('site', 'favicon');
    $faviconPath = $storageParent . DIRECTORY_SEPARATOR . 'site-assets' . DIRECTORY_SEPARATOR . 'favicon' . DIRECTORY_SEPARATOR . $activeFavicon['filename'];
    $expectFailure(static fn () => $assets->remove('favicon'), 'Removal persistence failure did not fail closed.');
    $repository->failWrites(false);
    $assertSame($activeFavicon, $settings->get('site', 'favicon'), 'Failed removal changed the active Favicon descriptor.');
    $assert(is_file($faviconPath), 'Failed removal deleted the active Favicon file.');

    unlink($faviconPath);
    $assertSame(null, $assets->url('favicon'), 'Missing active file still produced a URL.');
    $assertSame(404, $responseValue($assets->serve('favicon'), 'status'), 'Missing active file did not return 404.');

    $routesSource = (string) file_get_contents($basePath . '/routes/web.php');
    $applicationSource = (string) file_get_contents($basePath . '/app/Core/Application.php');
    $assetSource = (string) file_get_contents($basePath . '/app/Core/SiteAssetStorage.php');
    $assert(str_contains($routesSource, "'/site-assets/logo'"), 'Fixed Logo delivery route is missing.');
    $assert(str_contains($routesSource, "'/site-assets/favicon'"), 'Fixed Favicon delivery route is missing.');
    $assert(!str_contains($routesSource, "'/site-assets/{"), 'Arbitrary site-asset route was added.');
    $assert(str_contains($applicationSource, 'new SiteAssetStorage'), 'Application does not own SiteAssetStorage.');
    $assert(str_contains($applicationSource, 'siteAssets()'), 'Application does not expose SiteAssetStorage.');
    $assert(!str_contains($assetSource, 'move_uploaded_file'), 'Batch 4 storage pretends to be the future HTTP adapter.');
    $assert(!str_contains($assetSource, 'dispatch('), 'Site asset storage dispatches a production event.');
    $assert(!str_contains($assetSource, '$_FILES'), 'Site asset storage reads HTTP upload globals.');

    $applicationRoot = $root . DIRECTORY_SEPARATOR . 'application';
    mkdir($applicationRoot . DIRECTORY_SEPARATOR . 'config', 0777, true);
    mkdir($applicationRoot . DIRECTORY_SEPARATOR . 'storage', 0777, true);
    file_put_contents(
        $applicationRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php',
        "<?php\nreturn ['default'=>'mysql','connections'=>['mysql'=>['driver'=>'mysql','host'=>'127.0.0.1','port'=>'1','database'=>'fixture','username'=>'fixture','password'=>'fixture','charset'=>'utf8mb4']]];\n"
    );
    $application = new Application($applicationRoot);
    $secondApplication = new Application($applicationRoot);
    $assert($application->siteAssets() === $application->siteAssets(), 'Application did not retain one SiteAssetStorage instance.');
    $assert($application->siteAssets() !== $secondApplication->siteAssets(), 'Application instances shared SiteAssetStorage.');
    $assertSame(null, $application->branding()->logoUrl(), 'Application exposed unavailable Logo storage.');
    $assertSame(null, $application->branding()->faviconUrl(), 'Application exposed unavailable Favicon storage.');

    echo "M2.3 Batch 4 site asset smoke tests passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    $removeDirectory($root);
}
