<?php

declare(strict_types=1);

$basePath = dirname(__DIR__);
$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};

$coreContent = (string) file_get_contents($basePath . '/routes/content_admin.php');
$mediaList = (string) file_get_contents($basePath . '/modules/media/views/admin/list.php');
$mediaUpload = (string) file_get_contents($basePath . '/modules/media/views/admin/upload.php');
$systemManager = (string) file_get_contents($basePath . '/resources/views/admin/system-manager.php');
$adminCss = (string) file_get_contents($basePath . '/public/admin-assets/css/admin.css');

$assert(str_contains($coreContent, 'admin-content-page admin-stack'), 'Core Content list does not consume the canonical stack artifact.');
$assert(str_contains($coreContent, 'admin-content-form-page admin-stack'), 'Core Content form does not consume the canonical stack artifact.');
$assert(!str_contains($adminCss, ".admin-content-page,\n.admin-content-form-page {\n    display: grid;"), 'Core Content retains redundant local stack base styling.');
$assert(str_contains($mediaList, 'admin-filter-toolbar'), 'Media library does not consume the canonical filter-toolbar artifact.');
$assert(str_contains($mediaUpload, 'admin-inline-field'), 'Media upload does not consume the canonical inline-field artifact.');
$assert(str_contains($mediaList, 'admin-panel') && str_contains($mediaList, 'admin-actions'), 'Media library does not retain canonical panel/action artifacts.');
$assert(str_contains($systemManager, 'admin-panel') && str_contains($systemManager, 'admin-button'), 'System Manager does not retain canonical panel/button artifacts.');
$assert(str_contains($systemManager, 'system-manager-module-card__actions'), 'System Manager module-card action specialization was not removed.');

echo "MR.2 WU7 representative propagation static tests passed ({$assertions} assertions)." . PHP_EOL;
