<?php

declare(strict_types=1);

use Copot\Core\Application;
use Copot\Core\SettingsException;
use Copot\Core\SettingsRegistry;
use Copot\Core\SettingsRepository;
use Copot\Core\SettingsService;
use Copot\Core\SiteBranding;

$basePath = dirname(__DIR__);

require $basePath . '/bootstrap/autoload.php';

final class Batch3SettingsRepository extends SettingsRepository
{
    public function __construct(private array $overrides = [])
    {
    }

    public function findOverride(string $namespace, string $key): ?array
    {
        return $this->overrides[$namespace . '.' . $key] ?? null;
    }

    public function upsertOverride(
        string $namespace,
        string $key,
        string $storedValue,
        string $valueType
    ): void {
        $this->overrides[$namespace . '.' . $key] = [
            'setting_value' => $storedValue,
            'value_type' => $valueType,
        ];
    }

    public function deleteOverride(string $namespace, string $key): void
    {
        unset($this->overrides[$namespace . '.' . $key]);
    }

    public function stored(string $identifier): ?array
    {
        return $this->overrides[$identifier] ?? null;
    }
}

$assertions = 0;
$temporaryPaths = [];

$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;

    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$assertSame = static function (mixed $expected, mixed $actual, string $message) use ($assert): void {
    $assert(
        $actual === $expected,
        $message . ' Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . '.'
    );
};

$expectSettingsFailure = static function (callable $operation, string $message) use ($assert): void {
    $caught = null;

    try {
        $operation();
    } catch (SettingsException $exception) {
        $caught = $exception;
    }

    $assert($caught instanceof SettingsException, $message);
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
            unlink($candidate);
        }
    }

    rmdir($path);
};

$applicationRoot = sys_get_temp_dir()
    . DIRECTORY_SEPARATOR
    . 'copot-m2-3-batch3-'
    . bin2hex(random_bytes(6));

if (!mkdir($applicationRoot . DIRECTORY_SEPARATOR . 'config', 0777, true)) {
    throw new RuntimeException('Unable to create the application fixture directory.');
}

$temporaryPaths[] = $applicationRoot;

file_put_contents(
    $applicationRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php',
    <<<'PHP'
<?php
return [
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => '1',
            'database' => 'copot_batch3_fixture',
            'username' => 'fixture',
            'password' => 'fixture',
            'charset' => 'utf8mb4',
        ],
    ],
];
PHP
);

try {
    $registry = SettingsRegistry::core();
    $logoDefinition = $registry->find('site', 'logo');
    $faviconDefinition = $registry->find('site', 'favicon');

    $assert($logoDefinition !== null, 'The site.logo definition is missing.');
    $assert($faviconDefinition !== null, 'The site.favicon definition is missing.');
    $assertSame('json', $logoDefinition?->type(), 'site.logo does not use the JSON type.');
    $assertSame('json', $faviconDefinition?->type(), 'site.favicon does not use the JSON type.');
    $assertSame(null, $logoDefinition?->defaultValue(), 'site.logo does not default to null.');
    $assertSame(null, $faviconDefinition?->defaultValue(), 'site.favicon does not default to null.');
    $assertSame(4, count($registry->all('site')), 'The Core site namespace does not contain four definitions.');

    $logoPng = [
        'filename' => 'logo-' . str_repeat('a', 32) . '.png',
        'mime_type' => 'image/png',
        'size' => 2048,
    ];
    $logoJpeg = [
        'size' => 4096,
        'filename' => 'logo-' . str_repeat('b', 32) . '.jpg',
        'mime_type' => 'image/jpeg',
    ];
    $logoWebp = [
        'mime_type' => 'image/webp',
        'size' => 8192,
        'filename' => 'logo-' . str_repeat('c', 32) . '.webp',
    ];
    $faviconPng = [
        'filename' => 'favicon-' . str_repeat('d', 32) . '.png',
        'mime_type' => 'image/png',
        'size' => 1024,
    ];
    $faviconIco = [
        'filename' => 'favicon-' . str_repeat('e', 32) . '.ico',
        'mime_type' => 'image/x-icon',
        'size' => 2048,
    ];
    $faviconVendorIco = [
        'filename' => 'favicon-' . str_repeat('f', 32) . '.ico',
        'mime_type' => 'image/vnd.microsoft.icon',
        'size' => 4096,
    ];

    foreach ([$logoPng, $logoJpeg, $logoWebp, array_replace($logoPng, ['size' => 2097152]), null] as $descriptor) {
        $logoDefinition?->validate($descriptor);
        $assert(true, 'A valid Logo descriptor was rejected.');
    }

    foreach ([$faviconPng, $faviconIco, $faviconVendorIco, array_replace($faviconPng, ['size' => 524288]), null] as $descriptor) {
        $faviconDefinition?->validate($descriptor);
        $assert(true, 'A valid Favicon descriptor was rejected.');
    }

    $invalidLogoDescriptors = [
        'not-an-array',
        [],
        ['filename' => $logoPng['filename'], 'mime_type' => 'image/png'],
        $logoPng + ['original_name' => 'logo.png'],
        array_replace($logoPng, ['filename' => '../logo.png']),
        array_replace($logoPng, ['filename' => '/logo.png']),
        array_replace($logoPng, ['filename' => 'C:\\logo.png']),
        array_replace($logoPng, ['filename' => 'https://example.test/logo.png']),
        array_replace($logoPng, ['filename' => "logo-" . str_repeat('a', 32) . ".png\0.php"]),
        array_replace($logoPng, ['filename' => 'favicon-' . str_repeat('a', 32) . '.png']),
        array_replace($logoPng, ['filename' => 'logo-' . str_repeat('A', 32) . '.png']),
        array_replace($logoPng, ['filename' => 'logo-' . str_repeat('a', 31) . '.png']),
        array_replace($logoPng, ['filename' => 'logo-' . str_repeat('a', 32) . '.jpeg']),
        array_replace($logoPng, ['mime_type' => 'image/jpeg']),
        array_replace($logoPng, ['mime_type' => 'image/svg+xml']),
        array_replace($logoPng, ['mime_type' => 123]),
        array_replace($logoPng, ['size' => '2048']),
        array_replace($logoPng, ['size' => 2048.0]),
        array_replace($logoPng, ['size' => 0]),
        array_replace($logoPng, ['size' => 2097153]),
    ];

    foreach ($invalidLogoDescriptors as $descriptor) {
        $expectSettingsFailure(
            static fn () => $logoDefinition?->validate($descriptor),
            'An invalid Logo descriptor was accepted.'
        );
    }

    $invalidFaviconDescriptors = [
        array_replace($faviconPng, ['filename' => 'logo-' . str_repeat('d', 32) . '.png']),
        array_replace($faviconPng, ['filename' => 'favicon-' . str_repeat('d', 32) . '.svg']),
        array_replace($faviconPng, ['mime_type' => 'image/x-icon']),
        array_replace($faviconIco, ['mime_type' => 'image/png']),
        array_replace($faviconPng, ['size' => -1]),
        array_replace($faviconPng, ['size' => 524289]),
    ];

    foreach ($invalidFaviconDescriptors as $descriptor) {
        $expectSettingsFailure(
            static fn () => $faviconDefinition?->validate($descriptor),
            'An invalid Favicon descriptor was accepted.'
        );
    }

    $repository = new Batch3SettingsRepository();
    $settings = new SettingsService($registry, $repository);
    $settings->set('site', 'logo', $logoPng);
    $settings->set('site', 'favicon', $faviconIco);

    $assertSame($logoPng, $settings->get('site', 'logo'), 'Logo descriptor did not round-trip.');
    $assertSame($faviconIco, $settings->get('site', 'favicon'), 'Favicon descriptor did not round-trip.');
    $assertSame('json', $repository->stored('site.logo')['value_type'] ?? null, 'Logo descriptor stored the wrong type.');

    $settings->set('site', 'logo', null);
    $assertSame(null, $settings->get('site', 'logo'), 'Explicit null Logo did not round-trip.');

    $fallbackCases = [
        ['setting_value' => '{malformed', 'value_type' => 'json'],
        ['setting_value' => json_encode($logoPng, JSON_THROW_ON_ERROR), 'value_type' => 'string'],
        [
            'setting_value' => json_encode(array_replace($logoPng, ['filename' => '../logo.png']), JSON_THROW_ON_ERROR),
            'value_type' => 'json',
        ],
    ];

    foreach ($fallbackCases as $override) {
        $fallbackSettings = new SettingsService(
            SettingsRegistry::core(),
            new Batch3SettingsRepository(['site.logo' => $override])
        );
        $assertSame(null, $fallbackSettings->get('site', 'logo'), 'Invalid stored Logo did not fall back to null.');
    }

    $branding = new SiteBranding('Example Site', 'Example Tagline');
    $assertSame('Example Site', $branding->name(), 'SiteBranding name is incorrect.');
    $assertSame('Example Tagline', $branding->tagline(), 'SiteBranding tagline is incorrect.');
    $assertSame(null, $branding->logoUrl(), 'Batch 3 SiteBranding exposed a Logo URL.');
    $assertSame(null, $branding->faviconUrl(), 'Batch 3 SiteBranding exposed a Favicon URL.');
    $assertSame('copot', (new SiteBranding('   ', ''))->name(), 'SiteBranding did not apply the safe name fallback.');

    $application = new Application($applicationRoot);
    $secondApplication = new Application($applicationRoot);
    $assert($application->branding() === $application->branding(), 'Application did not retain one SiteBranding instance.');
    $assert($application->branding() !== $secondApplication->branding(), 'Application instances shared SiteBranding.');
    $assertSame('copot', $application->branding()->name(), 'Application branding did not use the default Site Name.');
    $assertSame('', $application->branding()->tagline(), 'Application branding did not use the default Tagline.');
    $assertSame(null, $application->branding()->logoUrl(), 'Application branding exposed a Logo URL before Batch 4.');
    $assertSame(null, $application->branding()->faviconUrl(), 'Application branding exposed a Favicon URL before Batch 4.');

    $brandingSource = (string) file_get_contents($basePath . '/app/Core/SiteBranding.php');
    $applicationSource = (string) file_get_contents($basePath . '/app/Core/Application.php');
    $routesSource = (string) file_get_contents($basePath . '/routes/web.php');
    $assert(!str_contains($brandingSource, 'filename'), 'SiteBranding exposes descriptor filename data.');
    $assert(!str_contains($brandingSource, 'mime_type'), 'SiteBranding exposes descriptor MIME data.');
    $assert(!str_contains($brandingSource, 'SettingsService'), 'SiteBranding exposes or retains SettingsService.');
    $assert(!str_contains($brandingSource, 'dispatch('), 'SiteBranding dispatches a production event.');

    echo "M2.3 Batch 3 branding smoke tests passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    foreach (array_reverse($temporaryPaths) as $path) {
        $removeDirectory($path);
    }
}
