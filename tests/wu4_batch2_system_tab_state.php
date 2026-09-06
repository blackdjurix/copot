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

$assert(str_contains($routes, '$systemPath = $path . \'/system\';'), 'System action endpoint base is missing.');
$assert(str_contains($routes, "require_once \$app->path('app/Core/SystemManagerRecoveryGate.php');"), 'Site Settings does not load the existing recovery-gate dependency required for safe System projection rendering.');
$assert(!str_contains($routes, "'?section=system'"), 'Competing query-string System redirect remains.');
$assert(str_contains($view, '$initialArea = in_array'), 'The initial Site Settings area is not normalized once.');
$assert(str_contains($view, 'data-initial-tab="<?= $escape($initialArea) ?>"'), 'data-initial-tab does not use the resolved fragment key.');
$assert(str_contains($view, '$active = $id === $initialArea'), 'Tab active state does not use the resolved initial area.');
$assert(str_contains($view, 'aria-selected="<?= $active ? \'true\' : \'false\' ?>"'), 'Tab aria-selected does not use the resolved initial area.');
$assert(str_contains($view, 'tabindex="<?= $active ? \'0\' : \'-1\' ?>"'), 'Tab tabindex does not use the resolved initial area.');
$assert(str_contains($view, 'data-settings-panel="site-settings-system"<?= $initialArea === \'system\' ? \'\' : \' hidden\' ?>'), 'System panel visibility does not use the resolved initial area.');
$js = (string) file_get_contents($base . '/public/admin-assets/js/admin-settings.js');
$assert(str_contains($js, 'data-settings-tab-key') || str_contains($view, 'data-settings-tab-key'), 'Fragment keys are not explicitly mapped separately from DOM identifiers.');
$assert(str_contains($js, 'window.location.hash !== `#${key}`'), 'Tab changes do not produce canonical fragment URLs.');
$assert(str_contains($js, "tab.addEventListener('click', () => activate(tabKey(tab)))"), 'Tab clicks do not activate using the canonical tab key.');
$assert(str_contains($js, 'activate(tabKey(tabs[next]), { focus: true })'), 'Keyboard tab navigation does not activate using the canonical tab key.');
$assert(str_contains($js, "replace(/^#/, '')"), 'Canonical fragment reading is missing.');
$assert(!str_contains($js, "#site-settings-"), 'Internal DOM identifiers remain exposed as canonical fragment URLs.');
$assert(!str_contains($js, 'section='), 'JavaScript retains the competing query-string tab model.');

echo "WU4 Batch 2 System canonical tab URL regression passed ({$assertions} assertions)." . PHP_EOL;
