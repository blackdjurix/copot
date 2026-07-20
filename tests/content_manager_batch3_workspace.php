<?php

declare(strict_types=1);

use Copot\Core\Application;
use Copot\Core\Config;
use Copot\Core\Env;
use Copot\Core\InstallerSchemaRunner;
use Copot\Core\Request;
use Copot\Core\Response;

$basePath = dirname(__DIR__);
chdir($basePath);
session_save_path(sys_get_temp_dir());
session_id('copotm34workspace' . bin2hex(random_bytes(5)));
require $basePath . '/bootstrap/autoload.php';
Env::load($basePath . '/.env');

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;

    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$statusOf = static fn (Response $response): int => (int) (new ReflectionProperty($response, 'status'))->getValue($response);
$contentOf = static fn (Response $response): string => (string) (new ReflectionProperty($response, 'content'))->getValue($response);

$host = (string) Env::get('DB_HOST', '127.0.0.1');
$port = (int) Env::get('DB_PORT', '3306');
$username = (string) Env::get('DB_USERNAME', 'root');
$password = (string) Env::get('DB_PASSWORD', '');
$databaseName = 'copot_m34_content_workspace_' . bin2hex(random_bytes(6));
$databaseIdentifier = '`' . str_replace('`', '``', $databaseName) . '`';
$configuration = [
    'host' => $host,
    'port' => $port,
    'database' => $databaseName,
    'username' => $username,
    'password' => $password,
];

$server = new PDO(
    "mysql:host={$host};port={$port};charset=utf8mb4",
    $username,
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
);
$server->exec('CREATE DATABASE ' . $databaseIdentifier . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$customConfigPath = null;

try {
    (new InstallerSchemaRunner($basePath . '/database/schema.sql'))->install($configuration);
    $_ENV['DB_DATABASE'] = $databaseName;
    putenv('DB_DATABASE=' . $databaseName);

    $app = new Application($basePath);
    $app->session()->start();
    require $basePath . '/routes/web.php';
    require $basePath . '/routes/auth.php';
    require $basePath . '/routes/admin.php';
    $app->modules()->install('content');
    $app->modules()->enable('content');
    $app->moduleLoader()->loadRoutes($app);
    require $basePath . '/routes/admin_fallback.php';
    $theme = (new Copot\Core\ThemeDiscovery($basePath . '/themes'))->discover()[0];
    $app->themes()->register($theme);
    $app->themes()->activate($theme->id());

    require_once $basePath . '/modules/content/Services/Content.php';
    require_once $basePath . '/modules/content/Services/ContentRepository.php';
    $repository = new ContentRepository($app->database());
    $connection = $app->database()->connection();
    $fixtureIds = [];

    for ($index = 1; $index <= 30; $index++) {
        $fixtureIds[] = $repository->create([
            'type' => $index === 1 ? 'page' : 'article',
            'title' => $index === 1 ? 'Alpha page' : 'Search Article ' . $index,
            'slug' => $index === 1 ? 'alpha-page' : 'search-article-' . $index,
            'body' => 'Fixture body ' . $index,
            'status' => $index === 1 ? 'draft' : ($index === 2 ? 'archived' : 'published'),
            'author_id' => null,
        ]);
    }
    $connection->exec("UPDATE content SET updated_at = '2026-01-01 00:00:00'");

    $workspace = $repository->workspace([], 25, 0);
    $assert($workspace['limit'] === 25, 'Default workspace page size was not 25.');
    $assert($workspace['total'] === 30 && count($workspace['items']) === 25, 'Default workspace count/page was incorrect.');
    $assert($workspace['items'][0]->id() === $fixtureIds[29], 'Workspace ordering was not deterministic by descending id.');

    $offsetWorkspace = $repository->workspace([], 10, 10);
    $assert(count($offsetWorkspace['items']) === 10 && $offsetWorkspace['items'][0]->id() === $fixtureIds[19], 'Workspace offset behavior was incorrect.');
    $boundedWorkspace = $repository->workspace([], 999, -10);
    $assert($boundedWorkspace['limit'] === 100 && $boundedWorkspace['offset'] === 0, 'Workspace bounds were not enforced.');
    $assert($repository->workspace(['search' => 'Alpha'])['total'] === 1, 'Title search did not match.');
    $assert($repository->workspace(['search' => 'search-article-30'])['total'] === 1, 'Slug search did not match.');
    $assert($repository->workspace(['type' => 'page'])['total'] === 1, 'Type filter did not match.');
    $assert($repository->workspace(['status' => 'archived'])['total'] === 1, 'Status filter did not match.');
    $assert($repository->workspace(['search' => 'Search', 'type' => 'article', 'status' => 'published'])['total'] === 28, 'Combined filters did not match.');
    $assert($repository->workspace(['type' => 'invalid', 'status' => 'invalid'])['total'] === 30, 'Invalid repository filters were not ignored.');

    $permissions = [];
    foreach (['admin.access', 'content.read', 'content.create', 'content.update', 'content.publish', 'content.delete'] as $slug) {
        $statement = $connection->prepare('SELECT id FROM permissions WHERE slug = :slug LIMIT 1');
        $statement->execute(['slug' => $slug]);
        $permissionId = $statement->fetchColumn();
        $assert(is_numeric($permissionId), "Permission [{$slug}] was not provisioned.");
        $permissions[$slug] = (int) $permissionId;
    }
    $createActor = static function (string $label, array $permissionSlugs) use ($connection, $permissions): int {
        $suffix = bin2hex(random_bytes(4));
        $connection->prepare("INSERT INTO users (name, email, password_hash, status, created_at, updated_at) VALUES (:name, :email, 'test', 'active', NOW(), NOW())")->execute([
            'name' => $label,
            'email' => $label . '-' . $suffix . '@example.test',
        ]);
        $userId = (int) $connection->lastInsertId();
        $connection->prepare('INSERT INTO roles (name, slug, created_at, updated_at) VALUES (:name, :slug, NOW(), NOW())')->execute([
            'name' => $label . ' role',
            'slug' => $label . '-' . $suffix,
        ]);
        $roleId = (int) $connection->lastInsertId();
        $connection->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)')->execute(['user_id' => $userId, 'role_id' => $roleId]);
        foreach ($permissionSlugs as $slug) {
            $connection->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)')->execute(['role_id' => $roleId, 'permission_id' => $permissions[$slug]]);
        }
        return $userId;
    };
    $fullActor = $createActor('workspace-full', array_keys($permissions));
    $readActor = $createActor('workspace-read', ['admin.access', 'content.read']);
    $noReadActor = $createActor('workspace-no-read', ['admin.access']);
    $switch = static function (int $userId) use ($app): void {
        $app->auth()->logout();
        $app->session()->set((string) $app->config()->get('auth.session_key', '_copot_user_id'), $userId);
    };
    $contentUrl = $app->adminUrl()->childUrl('content');

    $switch($fullActor);
    $defaultResponse = $app->run(new Request('GET', $contentUrl));
    $defaultHtml = $contentOf($defaultResponse);
    $assert($statusOf($defaultResponse) === 200 && substr_count($defaultHtml, '<tbody>') === 1, 'Default workspace route did not render.');
    $queryResponse = $app->run(new Request('GET', $contentUrl, ['q' => 'Search', 'type' => 'article', 'status' => 'published', 'per_page' => '25', 'page' => '1']));
    $queryHtml = $contentOf($queryResponse);
    $assert($statusOf($queryResponse) === 200, 'Filtered workspace route failed.');
    $assert(str_contains($queryHtml, 'value="Search"') && str_contains($queryHtml, 'value="25"'), 'Workspace controls did not preserve normalized input.');
    $assert(str_contains($queryHtml, 'q=Search') && str_contains($queryHtml, 'type=article') && str_contains($queryHtml, 'status=published') && str_contains($queryHtml, 'per_page=25'), 'Pagination links did not preserve active query parameters.');
    $invalidResponse = $app->run(new Request('GET', $contentUrl, ['type' => 'bad', 'status' => 'bad', 'page' => '0', 'per_page' => '0']));
    $assert($statusOf($invalidResponse) === 200 && str_contains($contentOf($invalidResponse), 'Page 1 of 2'), 'Invalid query normalization was incorrect.');
    $lastResponse = $app->run(new Request('GET', $contentUrl, ['page' => '999']));
    $assert($statusOf($lastResponse) === 200 && str_contains($contentOf($lastResponse), 'Page 2 of 2'), 'Out-of-range page handling was not deterministic.');
    $archivedResponse = $app->run(new Request('GET', $contentUrl, ['status' => 'archived']));
    $archivedHtml = $contentOf($archivedResponse);
    $assert(str_contains($archivedHtml, '/content/' . $fixtureIds[1] . '/restore') && !str_contains($archivedHtml, '/content/' . $fixtureIds[1] . '/publish'), 'Archived action presentation regressed.');

    $switch($readActor);
    $readHtml = $contentOf($app->run(new Request('GET', $contentUrl)));
    $assert(!str_contains($readHtml, 'Create content') && !str_contains($readHtml, '/restore'), 'Read-only action visibility regressed.');
    $switch($noReadActor);
    $assert($statusOf($app->run(new Request('GET', $contentUrl))) === 403, 'List access without content.read was not denied.');

    $switch($fullActor);
    $assert($statusOf($app->run(new Request('GET', $app->adminUrl()->childUrl('content/create')))) === 200, 'Create route regressed.');
    $assert($statusOf($app->run(new Request('GET', $app->adminUrl()->childUrl('content/' . $fixtureIds[0] . '/edit')))) === 200, 'Edit route regressed.');
    $customConfigPath = $basePath . '/tests/.batch3-admin-' . bin2hex(random_bytes(4));
    mkdir($customConfigPath, 0777, true);
    file_put_contents($customConfigPath . '/admin.php', "<?php return ['path' => 'control-panel'];");
    $customAdminUrl = new Copot\Core\Admin\AdminUrl(new Config($customConfigPath));
    $assert($customAdminUrl->childUrl('content') === '/control-panel/content', 'Configured Admin path URL generation regressed.');

    $renderEmpty = static function (bool $hasFilters) use ($basePath, $app): string {
        $adminUrl = fn (string $path = ''): string => $app->adminUrl()->childUrl($path);
        $contents = [];
        $taxonomyAvailable = false;
        $taxonomyTerms = [];
        $canCreate = false;
        $canUpdate = false;
        $canPublish = false;
        $canDelete = false;
        $csrfToken = '';
        $search = $hasFilters ? 'missing' : '';
        $selectedType = null;
        $selectedStatus = null;
        $perPage = 25;
        $total = 0;
        $page = 1;
        $lastPage = 1;
        $paginationUrl = static fn (int $targetPage): string => '/admin/content?page=' . $targetPage;
        ob_start();
        require $basePath . '/modules/content/views/admin/list.php';
        return (string) ob_get_clean();
    };
    $initialEmpty = $renderEmpty(false);
    $filteredEmpty = $renderEmpty(true);
    $assert(str_contains($initialEmpty, 'No content yet') && !str_contains($initialEmpty, 'No matching content'), 'Initial empty state was not distinct.');
    $assert(str_contains($filteredEmpty, 'No matching content') && str_contains($filteredEmpty, 'Clear filters'), 'Filtered empty state was not distinct.');

    $publishedResponse = $app->run(new Request('GET', '/content/search-article-3'));
    $rootResponse = $app->run(new Request('GET', '/search-article-3'));
    $assert($statusOf($publishedResponse) === 200, 'Published public Content route regressed.');
    $assert($statusOf($rootResponse) === 404, 'A root-level slug route was introduced unexpectedly.');

    echo "M3.4 Content workspace passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    if (is_string($customConfigPath)) {
        $customConfigFile = $customConfigPath . '/admin.php';

        if (is_file($customConfigFile)) {
            unlink($customConfigFile);
        }

        if (is_dir($customConfigPath)) {
            rmdir($customConfigPath);
        }
    }

    $server->exec('DROP DATABASE IF EXISTS ' . $databaseIdentifier);
}
