<?php

declare(strict_types=1);

$base = dirname(__DIR__);
$view = (string) file_get_contents($base . '/resources/views/admin/site-settings.php');
$routes = (string) file_get_contents($base . '/routes/site_settings.php');
$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    ++$assertions;
    if (!$condition) throw new RuntimeException($message);
};

$assert(str_contains($routes, '$systemPath = $path . \'/system\';'), 'Canonical System entry path is missing.');
$assert(str_contains($routes, "return Response::redirect(\$path . '?section=system');"), 'Direct System entry does not resolve to the canonical Site Settings area.');
$assert(str_contains($view, '$initialArea = in_array'), 'The initial Site Settings area is not normalized once.');
$assert(str_contains($view, 'data-initial-tab="site-settings-<?= $escape($initialArea) ?>"'), 'data-initial-tab does not use the resolved initial area.');
$assert(str_contains($view, '$active = $id === $initialArea'), 'Tab active state does not use the resolved initial area.');
$assert(str_contains($view, 'aria-selected="<?= $active ? \'true\' : \'false\' ?>"'), 'Tab aria-selected does not use the resolved initial area.');
$assert(str_contains($view, 'tabindex="<?= $active ? \'0\' : \'-1\' ?>"'), 'Tab tabindex does not use the resolved initial area.');
$assert(str_contains($view, 'data-settings-panel="site-settings-system"<?= $initialArea === \'system\' ? \'\' : \' hidden\' ?>'), 'System panel visibility does not use the resolved initial area.');

echo "WU4 Batch 2 System tab-state regression passed ({$assertions} assertions)." . PHP_EOL;
