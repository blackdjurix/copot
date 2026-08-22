<?php

declare(strict_types=1);

use Copot\Core\SiteBranding;
use Copot\Core\ThemeAssets;
use Copot\Core\ThemeLoader;
use Copot\Core\ThemeRepository;
use Copot\Core\ViewResolver;
use Copot\Core\ViewRenderer;
use Copot\Core\WebcoreBranding;

$basePath = dirname(__DIR__);
require $basePath . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

final class Wu2FakeThemeRepository extends ThemeRepository
{
    public function __construct(private ?array $active)
    {
    }

    public function activeFrontend(): ?array
    {
        return $this->active;
    }

    public function setActive(?array $active): void
    {
        $this->active = $active;
    }
}

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-wu2-public-' . bin2hex(random_bytes(4));
$coreViews = $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views';
$moduleViews = $root . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'sample' . DIRECTORY_SEPARATOR . 'views';
$themeRoot = $root . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR . 'clean';
foreach ([$coreViews, $moduleViews, $themeRoot . DIRECTORY_SEPARATOR . 'views', $themeRoot . DIRECTORY_SEPARATOR . 'layouts'] as $directory) {
    mkdir($directory, 0777, true);
}

file_put_contents($coreViews . DIRECTORY_SEPARATOR . 'home.php', '<h1>Built-in home</h1>');
file_put_contents($coreViews . DIRECTORY_SEPARATOR . 'layout.php', '<body data-presentation="builtin"><?= $content ?></body>');
file_put_contents($moduleViews . DIRECTORY_SEPARATOR . 'show.php', '<p>Module content</p>');
file_put_contents($themeRoot . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'home.php', '<h1>Theme home</h1>');
file_put_contents($themeRoot . DIRECTORY_SEPARATOR . 'layouts' . DIRECTORY_SEPARATOR . 'app.php', '<body data-presentation="theme"><?= $content ?></body>');

$themeRow = [
    'theme_id' => 'clean',
    'name' => 'Clean Theme',
    'version' => '1.0.0',
    'type' => 'frontend',
    'path' => 'themes/clean',
    'metadata' => json_encode(['entry' => ['layout' => 'layouts/app.php']], JSON_THROW_ON_ERROR),
];
$repository = new Wu2FakeThemeRepository(null);
$loader = new ThemeLoader($repository, $root);
$resolver = new ViewResolver($loader, $coreViews, $root . DIRECTORY_SEPARATOR . 'modules');
$renderer = new ViewRenderer(
    $loader,
    new ThemeAssets($loader),
    new SiteBranding('Example Site', 'A simple tagline', null, null, ['accent' => '#1769e0']),
    builtInLayoutPath: $coreViews . DIRECTORY_SEPARATOR . 'layout.php'
);

$builtinHome = $renderer->renderFile($resolver->resolve('core::home'), [], null, 'Home');
$assert(str_contains($builtinHome, 'data-presentation="builtin"'), 'No active Theme did not select Built-in Public View.');
$assert(str_contains($renderer->renderFile($resolver->resolve('sample::show')), 'Module content'), 'Module view did not resolve without an active Theme.');

$repository->setActive($themeRow);
$themeHome = $renderer->renderFile($resolver->resolve('core::home'), [], null, 'Home');
$assert(str_contains($themeHome, 'data-presentation="theme"'), 'Compatible active Theme did not replace Built-in Public View.');
$assert(str_contains($themeHome, 'Theme home'), 'Active Theme core view was not selected.');

$repository->setActive(null);
$afterDeactivation = $renderer->renderFile($resolver->resolve('core::home'), [], null, 'Home');
$assert(str_contains($afterDeactivation, 'data-presentation="builtin"'), 'Theme deactivation did not restore Built-in Public View.');

$accent = WebcoreBranding::builtInAccentPalette('#1769e0');
$assert($accent['accent'] === '#1769e0' && $accent['accent-soft'] !== $accent['accent'], 'Built-in accent derivation did not produce bounded variants.');
$assert(WebcoreBranding::contrastRatio($accent['accent'], $accent['accent-foreground']) >= 4.5, 'Built-in accent foreground does not meet contrast.');
$fallbackAccent = WebcoreBranding::builtInAccentPalette('invalid');
$assert($fallbackAccent['accent'] === WebcoreBranding::defaults()['accent'], 'Invalid accent did not safely fall back to the Webcore default.');
echo "WU2 Built-in Public View tests passed ({$assertions} assertions)." . PHP_EOL;
