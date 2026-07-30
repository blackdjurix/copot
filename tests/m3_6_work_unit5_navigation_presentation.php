<?php

declare(strict_types=1);

$basePath = dirname(__DIR__);
$context = ['navigation' => ['locations' => ['primary' => [['label' => '<Home>', 'url' => '/?q="x"', 'children' => []]]]]];
$title = 'Test'; $branding = null; $themeAsset = null; $content = '<main>Content</main>';
ob_start(); require $basePath . '/themes/default/layouts/app.php'; $html = (string) ob_get_clean();
if (!str_contains($html, '&lt;Home&gt;') || !str_contains($html, '/?q=&quot;x&quot;') || !str_contains($html, '<nav')) {
    throw new RuntimeException('Default Theme Navigation presentation did not escape or render the resolved context.');
}
echo "M3.6 WU5 Navigation presentation passed (3 assertions)." . PHP_EOL;
