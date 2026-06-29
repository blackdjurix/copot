<?php

declare(strict_types=1);

use Copot\Core\Admin\AdminPageRenderer;
use Copot\Core\Admin\AdminUrl;
use Copot\Core\AdminNavigation;
use Copot\Core\Config;
use Copot\Core\Database;
use Copot\Core\PermissionChecker;
use Copot\Core\User;
use Copot\Core\View;

$basePath = dirname(__DIR__);

require $basePath . '/bootstrap/autoload.php';

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
        $expected === $actual,
        $message . sprintf(' Expected [%s], received [%s].', var_export($expected, true), var_export($actual, true))
    );
};

$temporaryDirectory = static function (string $label) use (&$temporaryPaths): string {
    $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-' . $label . '-' . bin2hex(random_bytes(6));

    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException("Unable to create temporary directory [{$path}].");
    }

    $temporaryPaths[] = $path;

    return $path;
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

        if (is_dir($candidate)) {
            $removeDirectory($candidate);
        } else {
            unlink($candidate);
        }
    }

    rmdir($path);
};

$adminUrlFor = static function (mixed $path) use ($temporaryDirectory): AdminUrl {
    $configPath = $temporaryDirectory('admin-config');
    $config = '<?php return ' . var_export(['path' => $path], true) . ';';
    file_put_contents($configPath . DIRECTORY_SEPARATOR . 'admin.php', $config);

    return new AdminUrl(new Config($configPath));
};

try {
    foreach (['admin', 'backend', 'administrator', 'dapur'] as $validPath) {
        $adminUrl = $adminUrlFor($validPath);
        $assertSame($validPath, $adminUrl->path(), "Valid admin path [{$validPath}] was not preserved.");
        $assertSame('/' . $validPath, $adminUrl->baseUrl(), "Admin base URL [{$validPath}] is invalid.");
    }

    $defaultConfigPath = $temporaryDirectory('admin-default');
    $defaultAdminUrl = new AdminUrl(new Config($defaultConfigPath));
    $assertSame('admin', $defaultAdminUrl->path(), 'Missing configuration must use the existing admin default.');

    foreach (['', '/admin', 'admin/panel', '../admin', 'Admin', 'admin\\panel', 123, null] as $invalidPath) {
        $thrown = false;

        try {
            $adminUrlFor($invalidPath);
        } catch (RuntimeException) {
            $thrown = true;
        }

        $assert($thrown, 'Invalid admin path must be rejected: ' . var_export($invalidPath, true));
    }

    $adminUrl = $adminUrlFor('dapur');
    $assertSame('/dapur', $adminUrl->childUrl(''), 'Empty child path must resolve to the admin base URL.');
    $assertSame('/dapur/content', $adminUrl->childUrl('content'), 'Child URL generation failed.');
    $assertSame('/dapur/content/42/edit', $adminUrl->childUrl('/content//42/edit/'), 'Duplicate slashes were not normalized.');
    $assertSame('/dapur/content/{id}/edit', $adminUrl->childUrl('content/{id}/edit'), 'Route placeholders must remain supported.');

    foreach (['../content', 'content\\edit', 'content?draft=1', 'content#edit', 'content entry', '%2e%2e/content'] as $unsafeChild) {
        $thrown = false;

        try {
            $adminUrl->childUrl($unsafeChild);
        } catch (InvalidArgumentException) {
            $thrown = true;
        }

        $assert($thrown, "Unsafe admin child path [{$unsafeChild}] must be rejected.");
    }

    $viewPath = $temporaryDirectory('admin-views');
    mkdir($viewPath . DIRECTORY_SEPARATOR . 'admin');
    file_put_contents(
        $viewPath . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'layout.php',
        <<<'PHP'
<?php
echo json_encode([
    'title' => $title,
    'appName' => $appName,
    'siteName' => $siteName,
    'adminBaseUrl' => $adminBaseUrl,
    'adminLogoutUrl' => $adminLogoutUrl,
    'csrfToken' => $csrfToken,
    'userName' => $userName,
    'userEmail' => $userEmail,
    'currentPath' => $currentPath,
    'navigation' => $navigation,
    'content' => $content,
    'hasApplication' => isset($app),
], JSON_THROW_ON_ERROR);
PHP
    );

    $navigation = new AdminNavigation();
    $navigation->add('Dashboard', $adminUrl->baseUrl());
    $permissionConfigPath = $temporaryDirectory('permission-config');
    $user = new User([
        'id' => 7,
        'name' => 'Admin User',
        'email' => 'admin@example.test',
        'password_hash' => 'unused',
        'status' => 'active',
    ], new PermissionChecker(new Database(new Config($permissionConfigPath))));
    $renderer = new AdminPageRenderer(
        new View($viewPath),
        $adminUrl,
        $navigation,
        'Copot Test',
        'Test Site'
    );
    $rendered = json_decode(
        $renderer->render('Settings', '<section>Content</section>', $user, 'csrf-token', '/dapur/settings'),
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    $assertSame('Settings', $rendered['title'], 'Renderer page title context is incorrect.');
    $assertSame('Copot Test', $rendered['appName'], 'Renderer application name context is incorrect.');
    $assertSame('Test Site', $rendered['siteName'], 'Renderer site name context is incorrect.');
    $assertSame('/dapur', $rendered['adminBaseUrl'], 'Renderer admin base context is incorrect.');
    $assertSame('/dapur/logout', $rendered['adminLogoutUrl'], 'Renderer logout URL context is incorrect.');
    $assertSame('/dapur/settings', $rendered['currentPath'], 'Renderer current path context is incorrect.');
    $assertSame('Admin User', $rendered['userName'], 'Renderer user name context is incorrect.');
    $assertSame('admin@example.test', $rendered['userEmail'], 'Renderer user email context is incorrect.');
    $assertSame('<section>Content</section>', $rendered['content'], 'Renderer content context is incorrect.');
    $assertSame([['label' => 'Dashboard', 'url' => '/dapur']], $rendered['navigation'], 'Renderer navigation context is incorrect.');
    $assertSame(false, $rendered['hasApplication'], 'Renderer must not expose the Application to the layout.');

    $migratedTemplates = [
        'resources/views/admin/layout.php',
        'resources/views/admin/dashboard.php',
        'resources/views/admin/login.php',
        'modules/content/views/admin/list.php',
        'modules/content/views/admin/form.php',
        'modules/taxonomy/views/admin/types.php',
        'modules/taxonomy/views/admin/terms.php',
        'modules/taxonomy/views/admin/form.php',
    ];

    foreach ($migratedTemplates as $file) {
        $source = (string) file_get_contents($basePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file));
        $assert(!preg_match('/[\'\"]\/admin[\'\"]/', $source), "Runtime /admin literal remains in [{$file}].");

        if (str_starts_with($file, 'modules/')) {
            $assert(!str_contains($source, '$adminBase'), "Manual module child URL construction remains in [{$file}].");
        }
    }

    $directShellRenderFiles = [
        'routes/admin.php',
        'modules/content/routes.php',
        'modules/taxonomy/routes.php',
    ];

    foreach ($directShellRenderFiles as $file) {
        $source = (string) file_get_contents($basePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file));
        $assert(!str_contains($source, "render('admin/layout'"), "Direct Admin Shell rendering remains in [{$file}].");
        $assert(!str_contains($source, "get('admin.path'"), "Direct admin path retrieval remains in [{$file}].");
        $assert(!preg_match('/\$[A-Za-z]+AdminBase\s*\./', $source), "Manual route child URL construction remains in [{$file}].");
    }

    $applicationSource = (string) file_get_contents($basePath . '/app/Core/Application.php');
    $installerSource = (string) file_get_contents($basePath . '/bootstrap/installer.php');
    $adminRoutes = (string) file_get_contents($basePath . '/routes/admin.php');
    $contentRoutes = (string) file_get_contents($basePath . '/modules/content/routes.php');
    $taxonomyRoutes = (string) file_get_contents($basePath . '/modules/taxonomy/routes.php');

    $assert(!str_contains($applicationSource, "get('admin.path'"), 'Application still retrieves admin.path directly.');
    $assert(!str_contains($installerSource, "get('admin.path'"), 'Installer still retrieves admin.path directly.');
    $assert(str_contains($adminRoutes, '$app->auth()->check()'), 'Admin authentication guard was removed.');
    $assert(str_contains($adminRoutes, '$user?->can($adminPermission)'), 'Admin permission guard was removed.');
    $assert(str_contains($adminRoutes, 'validateCsrf'), 'Admin login/logout CSRF validation was removed.');
    $assert(str_contains($adminRoutes, "'settings.update'"), 'Settings permission guard was removed.');
    $assert(str_contains($adminRoutes, 'Response::redirect($adminBase)'), 'Admin redirect behavior no longer uses the centralized base URL.');
    $assert(str_contains($contentRoutes, "'content.create'"), 'Content permission behavior was removed.');
    $assert(str_contains($contentRoutes, 'validateOrReject'), 'Content CSRF behavior was removed.');
    $assert(str_contains($taxonomyRoutes, "'taxonomy.create'"), 'Taxonomy permission behavior was removed.');
    $assert(str_contains($taxonomyRoutes, 'validateOrReject'), 'Taxonomy CSRF behavior was removed.');

    echo "Admin UI Batch 1 smoke tests passed ({$assertions} assertions)." . PHP_EOL;
    echo "Note: exact-source guards are supplemental regression checks, not a replacement for behavioral coverage." . PHP_EOL;
} finally {
    foreach (array_reverse($temporaryPaths) as $path) {
        $removeDirectory($path);
    }
}
