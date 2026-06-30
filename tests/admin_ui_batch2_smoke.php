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

    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}" . PHP_EOL);
        exit(1);
    }
};

$temporaryPaths = [];

$makeTemporaryDirectory = static function () use (&$temporaryPaths): string {
    $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-admin-batch2-' . bin2hex(random_bytes(6));

    if (!mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create temporary test directory.');
    }

    $temporaryPaths[] = $directory;

    return $directory;
};

$removeDirectory = static function (string $path) use (&$removeDirectory): void {
    if (!is_dir($path)) {
        return;
    }

    foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) {
        $entryPath = $path . DIRECTORY_SEPARATOR . $entry;

        if (is_dir($entryPath)) {
            $removeDirectory($entryPath);
            continue;
        }

        unlink($entryPath);
    }

    rmdir($path);
};

$renderWithPath = static function (string $adminPath, string $currentPath) use ($makeTemporaryDirectory): array {
    $temporaryDirectory = $makeTemporaryDirectory();
    $configPath = $temporaryDirectory . DIRECTORY_SEPARATOR . 'config';
    $viewsPath = $temporaryDirectory . DIRECTORY_SEPARATOR . 'views';

    mkdir($configPath, 0777, true);
    mkdir($viewsPath . DIRECTORY_SEPARATOR . 'admin', 0777, true);

    file_put_contents($configPath . DIRECTORY_SEPARATOR . 'admin.php', "<?php\nreturn ['path' => '{$adminPath}'];\n");
    file_put_contents($viewsPath . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'layout.php', <<<'PHP'
<?php
$GLOBALS['copot_admin_batch2_rendered'] = get_defined_vars();
foreach (($navigation ?? []) as $item) {
    echo '<a href="' . htmlspecialchars($item['url'] ?? '#', ENT_QUOTES, 'UTF-8') . '"' . (!empty($item['active']) ? ' aria-current="page"' : '') . '>' . htmlspecialchars($item['label'] ?? '', ENT_QUOTES, 'UTF-8') . '</a>';
}
PHP);

    $adminUrl = new AdminUrl(new Config($configPath));
    $navigation = new AdminNavigation();
    $navigation->add('Dashboard', $adminUrl->baseUrl());
    $navigation->add('Content', $adminUrl->childUrl('content'), 'content.create');
    $navigation->add('Taxonomy', $adminUrl->childUrl('taxonomy'), 'taxonomy.create');

    $renderer = new AdminPageRenderer(
        new View($viewsPath),
        $adminUrl,
        $navigation,
        'Copot',
        'copot',
        'id_ID'
    );

    $permissions = new class extends PermissionChecker {
        public function __construct()
        {
        }

        public function userHasRole(int $userId, string $role): bool
        {
            return false;
        }

        public function userCan(int $userId, string $permission): bool
        {
            return in_array($permission, ['content.create', 'taxonomy.create'], true);
        }
    };
    $user = new User([
        'id' => 1,
        'name' => 'Admin',
        'email' => 'admin@example.test',
        'password_hash' => 'not-used',
        'status' => 'active',
    ], $permissions);
    $html = $renderer->render('Content', '<section>Body</section>', $user, 'csrf-token', $currentPath);

    return [$html, $GLOBALS['copot_admin_batch2_rendered'] ?? []];
};

$renderLoginWithLocale = static function (?string $locale) use ($makeTemporaryDirectory): string {
    $temporaryDirectory = $makeTemporaryDirectory();
    $configPath = $temporaryDirectory . DIRECTORY_SEPARATOR . 'config';

    mkdir($configPath, 0777, true);
    file_put_contents($configPath . DIRECTORY_SEPARATOR . 'admin.php', "<?php\nreturn ['path' => 'dapur'];\n");

    $adminUrl = new AdminUrl(new Config($configPath));
    $rendererArguments = [
        new View(dirname(__DIR__) . '/resources/views'),
        $adminUrl,
        new AdminNavigation(),
        'Copot',
        'copot',
    ];

    if ($locale !== null) {
        $rendererArguments[] = $locale;
    }

    $renderer = new AdminPageRenderer(...$rendererArguments);

    return (new View(dirname(__DIR__) . '/resources/views'))->render('admin/login', [
        'appName' => 'Copot',
        'siteName' => 'copot',
        'documentLocale' => $renderer->documentLocale(),
        'adminBaseUrl' => $adminUrl->baseUrl(),
        'csrfToken' => 'csrf-token',
        'email' => '',
        'error' => null,
    ]);
};

try {
    $cssFile = $basePath . '/public/admin-assets/css/admin.css';
    $layoutFile = $basePath . '/resources/views/admin/layout.php';
    $loginFile = $basePath . '/resources/views/admin/login.php';
    $assert(is_file($cssFile), 'Static Admin CSS file is missing.');

    $css = (string) file_get_contents($cssFile);
    $layout = (string) file_get_contents($layoutFile);
    $login = (string) file_get_contents($loginFile);

    $assert(substr_count($layout, '<link rel="stylesheet" href="/admin-assets/css/admin.css">') === 1, 'Admin Shell must link exactly one Admin CSS asset.');
    $assert(substr_count($login, '<link rel="stylesheet" href="/admin-assets/css/admin.css">') === 1, 'Admin login must link exactly one Admin CSS asset.');
    $assert(!str_contains($layout, '<style>'), 'Old large inline stylesheet remains in Admin Shell layout.');
    $assert(!str_contains($login, '<style>'), 'Old large inline stylesheet remains in Admin login.');
    $assert(str_contains($layout, 'class="admin-skip-link" href="#admin-main"'), 'Skip link is missing or targets the wrong element.');
    $assert(str_contains($layout, '<main id="admin-main"'), 'Main content target is missing.');
    $assert(str_contains($layout, 'aria-current="page"'), 'Active navigation markup must emit aria-current.');
    $assert(str_contains($layout, 'aria-label="Admin navigation"'), 'Admin navigation label is missing.');
    $assert(str_contains($layout, '<aside class="admin-sidebar"'), 'Admin Shell sidebar landmark is missing.');
    $assert(str_contains($css, ':focus-visible'), 'Visible focus treatment is missing.');
    $assert(str_contains($css, '--admin-color-bg'), 'Design token baseline is missing page background token.');
    $assert(str_contains($css, '--admin-color-navigation'), 'Design token baseline is missing navigation background token.');
    $assert(str_contains($css, '--admin-color-primary'), 'Design token baseline is missing primary accent token.');
    $assert(str_contains($css, '--admin-color-success'), 'Design token baseline is missing success token.');
    $assert(str_contains($css, '--admin-color-warning'), 'Design token baseline is missing warning token.');
    $assert(str_contains($css, '--admin-color-danger'), 'Design token baseline is missing danger token.');
    $assert(str_contains($css, '--admin-color-danger-soft'), 'Design token baseline is missing soft danger background token.');
    $assert(str_contains($css, '--admin-color-info'), 'Design token baseline is missing info token.');
    $assert(str_contains($css, '--admin-color-navigation-foreground'), 'Design token baseline is missing navigation foreground token.');
    $assert(str_contains($css, '--admin-color-navigation-foreground-strong'), 'Design token baseline is missing strong navigation foreground token.');
    $assert(str_contains($css, '--admin-color-navigation-control-surface'), 'Design token baseline is missing navigation control surface token.');
    $assert(str_contains($css, '--admin-control-height'), 'Design token baseline is missing control height token.');
    $assert(str_contains($css, '--admin-focus-ring'), 'Design token baseline is missing focus ring token.');
    $assert(str_contains($css, '--admin-focus-ring-inverse'), 'Inverse focus ring token for dark navigation is missing.');
    $assert(str_contains($css, '--admin-shell-sidebar-width'), 'Design token baseline is missing shell/sidebar dimension token.');
    $assert(str_contains($css, '@media (max-width: 900px)'), 'Tablet responsive breakpoint is missing.');
    $assert(str_contains($css, '@media (max-width: 560px)'), 'Mobile responsive breakpoint is missing.');
    $assert(str_contains($css, '@media (prefers-reduced-motion: reduce)'), 'Reduced-motion rule is missing.');
    $assert(str_contains($css, '.admin-sidebar-footer button:focus-visible'), 'Logout button lacks explicit focus-visible treatment.');
    $assert(str_contains($css, 'position: sticky'), 'Desktop sidebar does not remain available within the viewport.');
    $assert(str_contains($css, 'height: 100vh'), 'Desktop sidebar lacks a viewport-height baseline.');
    $assert(!preg_match('/theme-assets|themes\//', $css), 'Admin CSS must not depend on frontend theme assets.');
    $cssOutsideTokenDefinitions = (string) preg_replace('/\A:root\s*\{.*?^\}/ms', '', $css);
    $assert(!preg_match('/#[0-9a-f]{3,8}\b/i', $cssOutsideTokenDefinitions), 'Hardcoded hex color remains outside the Admin token definitions.');
    $assert(!preg_match('/\brgba?\(/i', $cssOutsideTokenDefinitions), 'Hardcoded RGB color remains outside the Admin token definitions.');
    $assert(!is_file($basePath . '/package.json'), 'Node/build dependency was introduced.');

    $normalizedLogin = $renderLoginWithLocale('id_ID');
    $invalidLogin = $renderLoginWithLocale('en_US\" onload=\"alert(1)');
    $defaultLogin = $renderLoginWithLocale(null);
    $assert(str_contains($normalizedLogin, '<html lang="id-ID">'), 'Admin login locale was not safely normalized.');
    $assert(str_contains($invalidLogin, '<html lang="en">'), 'Invalid Admin login locale did not safely fall back to en.');
    $assert(!str_contains($invalidLogin, 'onload='), 'Unsafe Admin login locale escaped the controlled locale boundary.');
    $assert(str_contains($defaultLogin, '<html lang="en">'), 'Missing Admin login locale did not safely fall back to en.');
    $assert(!str_contains($login, '<html lang="en">'), 'Admin login view retains a hardcoded document-language contract.');

    foreach ([
        'resources/views/admin/settings.php',
        'modules/content/views/admin/list.php',
        'modules/content/views/admin/form.php',
        'modules/taxonomy/views/admin/types.php',
        'modules/taxonomy/views/admin/terms.php',
        'modules/taxonomy/views/admin/form.php',
    ] as $viewFile) {
        $viewSource = (string) file_get_contents($basePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $viewFile));
        $assert(!preg_match('/<h1\b|<h2\b/i', $viewSource), "Duplicate page heading remains in [{$viewFile}].");
    }

    [, $dashboardRender] = $renderWithPath('dapur', '/dapur');
    $dashboardItems = $dashboardRender['navigation'] ?? [];
    $assert(($dashboardItems[0]['active'] ?? null) === true, 'Dashboard exact base-path match failed.');
    $assert(($dashboardItems[1]['active'] ?? null) === false, 'Dashboard base path must not activate Content.');
    $assert(($dashboardRender['documentLocale'] ?? null) === 'id-ID', 'Document locale was not safely normalized.');

    [$contentHtml, $contentRender] = $renderWithPath('dapur', '/dapur/content/42/edit');
    $contentItems = $contentRender['navigation'] ?? [];
    $assert(($contentItems[1]['active'] ?? null) === true, 'Child route did not activate parent Content navigation item.');
    $assert(str_contains($contentHtml, 'href="/dapur/content" aria-current="page"'), 'Active child navigation did not emit aria-current.');

    [, $falsePositiveRender] = $renderWithPath('dapur', '/dapur/content-old');
    $falsePositiveItems = $falsePositiveRender['navigation'] ?? [];
    $assert(($falsePositiveItems[1]['active'] ?? null) === false, 'Prefix false-positive activated Content navigation.');

    foreach ([
        'resources/views/admin/layout.php',
        'resources/views/admin/login.php',
    ] as $file) {
        $source = (string) file_get_contents($basePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file));
        $assert(!preg_match('/[\'"]\/admin[\'"]/', $source), "Runtime /admin literal remains in [{$file}].");
    }

    echo "Admin UI Batch 2 smoke tests passed ({$assertions} assertions)." . PHP_EOL;
    echo "Note: source guards supplement rendered behavior checks for the shared shell baseline." . PHP_EOL;
} finally {
    foreach (array_reverse($temporaryPaths) as $path) {
        $removeDirectory($path);
    }
}
