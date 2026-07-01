<?php

declare(strict_types=1);

namespace Copot\Core {
    class User
    {
        public function __construct(private array $permissions = [])
        {
        }

        public function can(string $permission): bool
        {
            return in_array($permission, $this->permissions, true);
        }
    }
}

namespace {
    use Copot\Core\Admin\AdminDashboardRegistry;
    use Copot\Core\User;

    $basePath = dirname(__DIR__);
    $assertions = 0;

    $assert = static function (bool $condition, string $message) use (&$assertions): void {
        $assertions++;

        if (!$condition) {
            fwrite(STDERR, "FAIL: {$message}" . PHP_EOL);
            exit(1);
        }
    };

    $readFile = static function (string $path) use ($assert): string {
        $assert(is_file($path), "Required file is missing [{$path}].");

        return (string) file_get_contents($path);
    };

    $render = static function (string $path, array $data): string {
        extract($data, EXTR_SKIP);
        ob_start();

        try {
            require $path;

            return (string) ob_get_clean();
        } catch (\Throwable $throwable) {
            ob_end_clean();

            throw $throwable;
        }
    };

    $registryFile = $basePath . '/app/Core/Admin/AdminDashboardRegistry.php';
    $applicationFile = $basePath . '/app/Core/Application.php';
    $adminRoutesFile = $basePath . '/routes/admin.php';
    $contentRoutesFile = $basePath . '/modules/content/routes.php';
    $taxonomyRoutesFile = $basePath . '/modules/taxonomy/routes.php';
    $dashboardViewFile = $basePath . '/resources/views/admin/dashboard.php';

    require_once $registryFile;

    $registrySource = $readFile($registryFile);
    $applicationSource = $readFile($applicationFile);
    $adminRoutesSource = $readFile($adminRoutesFile);
    $contentRoutesSource = $readFile($contentRoutesFile);
    $taxonomyRoutesSource = $readFile($taxonomyRoutesFile);
    $dashboardSource = $readFile($dashboardViewFile);

    $registry = new AdminDashboardRegistry();
    $registry->add('core.public', 'Public widget', 'Visible to every authenticated admin.', '/dapur/public', null, 100);
    $registry->add('content.overview', 'Content', 'Manage content.', '/dapur/content', ['content.update', 'content.publish'], 200);
    $registry->add('taxonomy.overview', 'Taxonomy', 'Manage taxonomy.', '/dapur/taxonomy', 'taxonomy.update', 200);
    $registry->add('core.later', 'Later', 'Stable registration order.', null, null, 200);

    $contentUser = new User(['content.update']);
    $contentWidgets = $registry->itemsFor($contentUser);

    $assert(count($contentWidgets) === 3, 'Permission filtering returned an unexpected widget count.');
    $assert($contentWidgets[0]['id'] === 'core.public', 'Priority ordering did not place the Core widget first.');
    $assert($contentWidgets[1]['id'] === 'content.overview', 'Matching permission did not expose the Content widget.');
    $assert($contentWidgets[2]['id'] === 'core.later', 'Equal-priority registration order is not stable.');
    $assert($registry->itemsFor(null) === [], 'Anonymous users must not receive Admin dashboard widgets.');

    $duplicateRejected = false;

    try {
        $registry->add('content.overview', 'Duplicate', 'Duplicate widget.', '/dapur/content');
    } catch (\InvalidArgumentException) {
        $duplicateRejected = true;
    }

    $assert($duplicateRejected, 'Duplicate widget IDs must be rejected.');

    $invalidIdRejected = false;

    try {
        $registry->add('Invalid Widget', 'Invalid', 'Invalid ID.');
    } catch (\InvalidArgumentException) {
        $invalidIdRejected = true;
    }

    $assert($invalidIdRejected, 'Invalid widget IDs must be rejected.');

    $externalUrlRejected = false;

    try {
        $registry->add('core.external', 'External', 'Unsafe URL.', 'https://example.com');
    } catch (\InvalidArgumentException) {
        $externalUrlRejected = true;
    }

    $assert($externalUrlRejected, 'Dashboard widget URLs must remain root-relative.');

    $dashboard = $render($dashboardViewFile, [
        'appName' => 'Copot',
        'frameworkStatus' => 'M2.1 Admin UI Foundation',
        'adminBaseUrl' => '/dapur',
        'userName' => 'Admin',
        'userEmail' => 'admin@example.test',
        'widgets' => $contentWidgets,
    ]);

    $emptyDashboard = $render($dashboardViewFile, [
        'appName' => 'Copot',
        'frameworkStatus' => 'M2.1 Admin UI Foundation',
        'adminBaseUrl' => '/dapur',
        'userName' => 'Admin',
        'userEmail' => 'admin@example.test',
        'widgets' => [],
    ]);

    $assert(str_contains($dashboard, 'id="module-overview-title"'), 'Dashboard widget region lacks a labelled heading.');
    $assert(str_contains($dashboard, 'class="admin-dashboard-widgets"'), 'Dashboard widget collection is missing.');
    $assert(str_contains($dashboard, 'id="dashboard-widget-content.overview"'), 'Stable widget ID is not reflected in heading semantics.');
    $assert(str_contains($dashboard, 'href="/dapur/content"'), 'Dashboard widget action URL changed unexpectedly.');
    $assert(!str_contains($dashboard, 'Taxonomy'), 'Permission-hidden widgets leaked into rendered output.');
    $assert(str_contains($emptyDashboard, 'class="admin-empty-state"'), 'Dashboard empty state is missing.');

    $assert(str_contains($applicationSource, 'private AdminDashboardRegistry $adminDashboard;'), 'Application does not own the Admin dashboard registry.');
    $assert(str_contains($applicationSource, 'public function adminDashboard(): AdminDashboardRegistry'), 'Application does not expose the Admin dashboard registry.');
    $assert(str_contains($adminRoutesSource, "'widgets' => \$app->adminDashboard()->itemsFor(\$user)"), 'Dashboard route does not resolve permission-filtered widgets.');
    $assert(str_contains($adminRoutesSource, "'Dashboard',"), 'Dashboard page title is not explicit.');
    $assert(str_contains($contentRoutesSource, "'content.overview'"), 'Content module does not register a dashboard widget.');
    $assert(str_contains($taxonomyRoutesSource, "'taxonomy.overview'"), 'Taxonomy module does not register a dashboard widget.');
    $assert(str_contains($contentRoutesSource, "\$app->adminDashboard()->add("), 'Content dashboard registration does not use the shared registry.');
    $assert(str_contains($taxonomyRoutesSource, "\$app->adminDashboard()->add("), 'Taxonomy dashboard registration does not use the shared registry.');

    $assert(!str_contains($registrySource, '<script'), 'JavaScript entered the dashboard registry.');
    $assert(!str_contains($dashboardSource, '<script'), 'JavaScript entered the Dashboard view.');
    $assert(!preg_match('/theme-assets|themes\//', $dashboardSource), 'Dashboard widgets depend on frontend theme assets.');
    $assert(!is_file($basePath . '/package.json'), 'A Node/build dependency was introduced.');

    echo "Admin UI Batch 5 smoke tests passed ({$assertions} assertions)." . PHP_EOL;
    echo "Note: registry behavior covers validation, stable ordering, permission filtering, rendering, and module extension registration." . PHP_EOL;
}
