<?php

declare(strict_types=1);

use Copot\Core\Admin\AdminPageRenderer;
use Copot\Core\Admin\AdminUrl;
use Copot\Core\AdminNavigation;
use Copot\Core\Config;
use Copot\Core\PermissionChecker;
use Copot\Core\User;
use Copot\Core\View;

$basePath = dirname(__DIR__);
require $basePath . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};

$temporaryDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-mr2-breadcrumb-' . bin2hex(random_bytes(5));
mkdir($temporaryDirectory . DIRECTORY_SEPARATOR . 'config', 0777, true);
file_put_contents($temporaryDirectory . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'admin.php', "<?php return ['path' => 'admin'];\n");

try {
    $adminUrl = new AdminUrl(new Config($temporaryDirectory . DIRECTORY_SEPARATOR . 'config'));
    $navigation = new AdminNavigation();
    $navigation->add('Dashboard', $adminUrl->baseUrl());
    $navigation->add('Content', $adminUrl->childUrl('content'));
    $permissions = new class extends PermissionChecker {
        public function __construct() {}
        public function userHasRole(int $userId, string $role): bool { return false; }
        public function userCan(int $userId, string $permission): bool { return true; }
    };
    $user = new User(['id' => 1, 'name' => 'Admin', 'email' => 'admin@example.test', 'password_hash' => 'unused', 'status' => 'active'], $permissions);
    $renderer = new AdminPageRenderer(new View($basePath . '/resources/views'), $adminUrl, $navigation, 'Copot', 'copot');

    $html = $renderer->render('Edit Content', '<h2>Consumer section</h2>', $user, 'csrf', '/admin/content/7/edit', null, [
        ['label' => 'Content', 'url' => $adminUrl->childUrl('content')],
        ['label' => 'Quarterly report', 'url' => $adminUrl->childUrl('content/7')],
        ['label' => 'Edit'],
    ]);
    $assert(substr_count($html, '<h1') === 1, 'Exactly one primary heading was rendered.');
    $assert(str_contains($html, '<h1 class="admin-page-heading__title"'), 'Standalone primary heading is in main content.');
    $assert(!str_contains($html, 'admin-breadcrumb h1'), 'Breadcrumb retains no heading element.');
    $assert(str_contains($html, 'Quarterly report'), 'Semantic entity breadcrumb label is present.');
    $assert(str_contains($html, 'href="/admin/content"'), 'Semantic ancestor link is present.');
    $assert(str_contains($html, 'aria-current="page"'), 'Breadcrumb current item is marked.');
    $assert(!str_contains($html, '/7/edit</'), 'Technical route segments are not rendered as breadcrumb labels.');

    $frame = $renderer->render('System Manager', '<h2>Consumer content</h2>', $user, 'csrf', '/admin/settings/system-manager', [
        'title' => 'System Manager',
        'description' => 'Administration baseline.',
        'surface' => 'transparent',
        'spacing' => 'default',
    ]);
    $assert(substr_count($frame, '<h1') === 1, 'Page Frame renders one primary heading.');
    $assert(str_contains($frame, 'admin-page-frame__title'), 'Page Frame title remains in the main page frame.');
    $assert(!str_contains($frame, '<nav class="admin-breadcrumb" aria-label="Breadcrumb"><ol><li><a'), 'Breadcrumb and heading remain separate structures.');

    echo "MR.2 Breadcrumb/Page Heading baseline passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    $remove = static function (string $path) use (&$remove): void {
        if (is_dir($path)) { foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) $remove($path . DIRECTORY_SEPARATOR . $entry); rmdir($path); }
        elseif (is_file($path)) unlink($path);
    };
    $remove($temporaryDirectory);
}
