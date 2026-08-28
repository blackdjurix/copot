<?php

declare(strict_types=1);

use Copot\Core\Admin\AdminPageRenderer;
use Copot\Core\Admin\AdminUrl;
use Copot\Core\AdminNavigation;
use Copot\Core\Config;
use Copot\Core\View;

$basePath = dirname(__DIR__);
require $basePath . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;

    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$temporaryDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-mr2-wu1-' . bin2hex(random_bytes(5));
mkdir($temporaryDirectory . DIRECTORY_SEPARATOR . 'config', 0777, true);
file_put_contents($temporaryDirectory . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'admin.php', "<?php return ['path' => 'dapur'];\n");

try {
    $renderer = new AdminPageRenderer(
        new View($basePath . '/resources/views'),
        new AdminUrl(new Config($temporaryDirectory . DIRECTORY_SEPARATOR . 'config')),
        new AdminNavigation(),
        'Copot',
        'copot'
    );

    $complete = $renderer->renderPageFrame([
        'title' => 'Users <test>',
        'description' => 'Description <test>',
        'bar' => '<button type="button">Action</button>',
        'content' => '<div data-consumer-owned><strong>Consumer content</strong></div>',
        'footer' => '<small>Footer content</small>',
        'surface' => 'panel',
        'spacing' => 'default',
    ]);
    $assert(str_contains($complete, 'admin-page-frame--surface-panel'), 'Panel surface intent was not rendered.');
    $assert(str_contains($complete, 'admin-page-frame--spacing-default'), 'Default spacing intent was not rendered.');
    $assert(str_contains($complete, '<h1') && str_contains($complete, 'Users &lt;test&gt;'), 'Frame title was not rendered as the primary heading and escaped.');
    $assert(str_contains($complete, 'Description &lt;test&gt;'), 'Frame description was not rendered and escaped.');
    $assert(str_contains($complete, '<button type="button">Action</button>'), 'Optional bar content was rewritten.');
    $assert(str_contains($complete, '<div data-consumer-owned><strong>Consumer content</strong></div>'), 'Consumer content was rewritten.');
    $assert(str_contains($complete, '<small>Footer content</small>'), 'Optional footer content was not rendered.');
    $assert(str_contains($complete, 'aria-describedby='), 'Description accessibility association is missing.');

    $minimal = $renderer->renderPageFrame([
        'title' => 'Minimal',
        'content' => '<fieldset><legend>Consumer form</legend></fieldset>',
        'surface' => 'transparent',
        'spacing' => 'none',
    ]);
    $assert(str_contains($minimal, 'admin-page-frame--surface-transparent'), 'Transparent surface intent was not rendered.');
    $assert(str_contains($minimal, 'admin-page-frame--spacing-none'), 'None spacing intent was not rendered.');
    $assert(!str_contains($minimal, 'admin-page-frame__bar'), 'Absent bar rendered a broken optional region.');
    $assert(!str_contains($minimal, 'admin-page-frame__footer'), 'Absent footer rendered a broken optional region.');
    $assert(str_contains($minimal, '<fieldset><legend>Consumer form</legend></fieldset>'), 'Arbitrary consumer markup was not preserved.');
    $assert(!str_contains($minimal, 'aria-describedby='), 'Absent description created a broken accessibility association.');

    foreach ([
        ['surface' => 'card'],
        ['spacing' => 'compact'],
        ['title' => 'Valid', 'content' => 42],
    ] as $invalid) {
        try {
            $renderer->renderPageFrame($invalid + ['title' => 'Valid', 'content' => '<p>Content</p>']);
            throw new RuntimeException('Invalid Page Frame input was accepted.');
        } catch (InvalidArgumentException) {
            $assert(true, 'Invalid Page Frame input was rejected.');
        }
    }

    $usersView = (string) file_get_contents($basePath . '/modules/users-access/views/admin/list.php');
    $usersRoutes = (string) file_get_contents($basePath . '/modules/users-access/routes.php');
    $assert(str_contains($usersRoutes, "'surface' => 'panel'"), 'Users did not adopt the Page Frame surface contract.');
    $assert(str_contains($usersRoutes, "'spacing' => 'default'"), 'Users did not adopt the Page Frame spacing contract.');
    $assert(str_contains($usersRoutes, "'title' => 'User accounts'"), 'Users Page Frame title is missing.');
    $assert(!str_contains($usersView, 'users-list-title'), 'Users retained a duplicate page-level title.');
    $assert(str_contains($usersView, 'admin-users-table'), 'Users consumer-owned table content was removed.');

    $css = (string) file_get_contents($basePath . '/public/admin-assets/css/admin.css');
    $assert(str_contains($css, '.admin-page-frame--surface-panel'), 'Page Frame panel CSS is missing.');
    $assert(str_contains($css, '.admin-page-frame--surface-transparent'), 'Page Frame transparent CSS is missing.');
    $assert(str_contains($css, '@media (max-width: 560px)'), 'Page Frame responsive rule is missing.');
    $assert(!str_contains($css, '.admin-page-frame__content > * {'), 'Page Frame does not impose consumer content composition.');

    echo "MR.2 WU1 Admin Page Frame passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    $remove = static function (string $path) use (&$remove): void {
        if (is_dir($path)) {
            foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) {
                $remove($path . DIRECTORY_SEPARATOR . $entry);
            }
            rmdir($path);
        } elseif (is_file($path)) {
            unlink($path);
        }
    };

    $remove($temporaryDirectory);
}
