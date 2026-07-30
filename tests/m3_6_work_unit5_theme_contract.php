<?php

declare(strict_types=1);

use Copot\Core\ThemeDiscovery;
use Copot\Core\ThemeException;

$basePath = dirname(__DIR__);
require $basePath . '/bootstrap/autoload.php';
$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void { $assertions++; if (!$condition) throw new RuntimeException($message); };

$theme = (new ThemeDiscovery($basePath . '/themes'))->discover()[0];
$assert($theme->supports()['navigation_locations'] === ['primary'], 'Default Theme did not declare primary navigation.');
$assert(str_contains((string) file_get_contents($basePath . '/themes/default/theme.json'), 'navigation_locations'), 'Theme manifest declaration is missing.');

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-wu5-theme-' . bin2hex(random_bytes(4));
mkdir($root . DIRECTORY_SEPARATOR . 'bad', 0777, true);
file_put_contents($root . '/bad/layout.php', '<?php echo "ok";');
file_put_contents($root . '/bad/theme.json', json_encode([
    'id' => 'bad', 'name' => 'Bad', 'version' => '1.0.0', 'type' => 'frontend',
    'entry' => ['layout' => 'layout.php'], 'supports' => ['navigation_locations' => ['Primary', 'primary']],
], JSON_THROW_ON_ERROR));
try {
    (new ThemeDiscovery($root))->discover();
    $assert(false, 'Invalid navigation location declaration was accepted.');
} catch (ThemeException) {
    $assert(true, 'Invalid navigation location declaration was rejected.');
}
$file = $root . '/bad/theme.json'; unlink($file); unlink($root . '/bad/layout.php'); rmdir($root . '/bad'); rmdir($root);
echo "M3.6 WU5 Theme contract passed ({$assertions} assertions)." . PHP_EOL;
