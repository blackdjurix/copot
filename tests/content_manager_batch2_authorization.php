<?php

declare(strict_types=1);

use Copot\Core\Application;
use Copot\Core\Env;
use Copot\Core\InstallerSchemaRunner;
use Copot\Core\Request;
use Copot\Core\Response;

$basePath = dirname(__DIR__);
chdir($basePath);
session_save_path(sys_get_temp_dir());
session_id('copotm34auth' . bin2hex(random_bytes(5)));
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
$databaseName = 'copot_m34_content_auth_' . bin2hex(random_bytes(6));
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

    $connection = $app->database()->connection();
    $permissionIds = [];
    foreach (['admin.access', 'content.read', 'content.create', 'content.update', 'content.publish', 'content.delete'] as $slug) {
        $statement = $connection->prepare('SELECT id FROM permissions WHERE slug = :slug LIMIT 1');
        $statement->execute(['slug' => $slug]);
        $permissionId = $statement->fetchColumn();
        $assert(is_numeric($permissionId), "Permission [{$slug}] was not provisioned.");
        $permissionIds[$slug] = (int) $permissionId;
    }

    $createActor = static function (string $label, array $permissions) use ($connection, $permissionIds): int {
        $suffix = bin2hex(random_bytes(5));
        $connection->prepare(
            "INSERT INTO users (name, email, password_hash, status, created_at, updated_at)
            VALUES (:name, :email, 'test', 'active', NOW(), NOW())"
        )->execute([
            'name' => $label,
            'email' => $label . '-' . $suffix . '@example.test',
        ]);
        $userId = (int) $connection->lastInsertId();
        $connection->prepare(
            'INSERT INTO roles (name, slug, created_at, updated_at) VALUES (:name, :slug, NOW(), NOW())'
        )->execute(['name' => $label . ' role', 'slug' => $label . '-' . $suffix]);
        $roleId = (int) $connection->lastInsertId();
        $connection->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)')->execute([
            'user_id' => $userId,
            'role_id' => $roleId,
        ]);

        foreach ($permissions as $permission) {
            $connection->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)')->execute([
                'role_id' => $roleId,
                'permission_id' => $permissionIds[$permission],
            ]);
        }

        return $userId;
    };

    $actors = [
        'none' => $createActor('none', []),
        'read' => $createActor('read', ['admin.access', 'content.read']),
        'create' => $createActor('create', ['admin.access', 'content.create']),
        'read_create' => $createActor('read-create', ['admin.access', 'content.read', 'content.create']),
        'read_update' => $createActor('read-update', ['admin.access', 'content.read', 'content.update']),
        'read_publish' => $createActor('read-publish', ['admin.access', 'content.read', 'content.publish']),
        'read_delete' => $createActor('read-delete', ['admin.access', 'content.read', 'content.delete']),
        'read_publish_delete' => $createActor('read-publish-delete', ['admin.access', 'content.read', 'content.publish', 'content.delete']),
    ];

    require_once $basePath . '/modules/content/Services/Content.php';
    require_once $basePath . '/modules/content/Services/ContentRepository.php';
    $repository = new ContentRepository($app->database());
    $publishedId = $repository->create([
        'type' => 'article', 'title' => 'Public article', 'slug' => 'public-article', 'body' => 'Public body', 'status' => 'published', 'author_id' => null,
    ]);
    $draftId = $repository->create([
        'type' => 'article', 'title' => 'Draft article', 'slug' => 'draft-article', 'body' => 'Draft body', 'status' => 'draft', 'author_id' => null,
    ]);
    $archivedId = $repository->create([
        'type' => 'article', 'title' => 'Archived article', 'slug' => 'archived-article', 'body' => 'Archived body', 'status' => 'archived', 'author_id' => null,
    ]);

    $switch = static function (int $userId) use ($app): void {
        $app->auth()->logout();
        $app->session()->set((string) $app->config()->get('auth.session_key', '_copot_user_id'), $userId);
    };
    $contentUrl = $app->adminUrl()->childUrl('content');
    $token = static function () use ($app): string {
        return $app->session()->csrfToken();
    };

    $switch($actors['read_publish_delete']);
    $archivedList = $app->run(new Request('GET', $contentUrl));
    $archivedRowMatches = [];
    $assert(
        preg_match('/<tr>.*?Archived article.*?<\/tr>/s', $contentOf($archivedList), $archivedRowMatches) === 1,
        'Archived Content row was not rendered.'
    );
    $archivedRow = $archivedRowMatches[0] ?? '';
    $assert(str_contains($archivedRow, '/content/' . $archivedId . '/restore'), 'Archived Content did not render the restore route.');
    $assert(str_contains($archivedRow, '>Restore</button>'), 'Archived Content did not render a Restore action.');
    $assert(!str_contains($archivedRow, '/content/' . $archivedId . '/publish'), 'Archived Content rendered a direct Publish action.');

    $switch($actors['read']);
    $unauthorizedRestore = $app->run(new Request('POST', $app->adminUrl()->childUrl("content/{$archivedId}/restore"), [], ['_token' => $token()]));
    $assert($statusOf($unauthorizedRestore) === 403, 'Restore without content.delete was not denied.');
    $assert($repository->findById($archivedId)?->status() === 'archived', 'Unauthorized restore mutated archived Content.');

    $switch($actors['read_publish_delete']);
    $invalidPublish = $app->run(new Request('POST', $app->adminUrl()->childUrl("content/{$archivedId}/publish"), [], ['_token' => $token()]));
    $assert($statusOf($invalidPublish) === 422, 'Invalid archived publish did not return HTTP 422.');
    $assert($repository->findById($archivedId)?->status() === 'archived', 'Invalid archived publish mutated Content.');
    $assert($statusOf($app->adminErrors()->response(new Request('GET', $contentUrl), 422)) === 422, 'AdminErrorRenderer did not preserve HTTP 422.');
    $assert($statusOf($app->adminErrors()->response(new Request('GET', $contentUrl), 403)) === 403, 'AdminErrorRenderer regressed HTTP 403 handling.');
    $assert($statusOf($app->adminErrors()->response(new Request('GET', $contentUrl), 418)) === 500, 'AdminErrorRenderer changed unsupported-status handling.');

    $authorizedRestore = $app->run(new Request('POST', $app->adminUrl()->childUrl("content/{$archivedId}/restore"), [], ['_token' => $token()]));
    $assert($statusOf($authorizedRestore) === 302, 'Authorized restore did not redirect successfully.');
    $assert($repository->findById($archivedId)?->status() === 'draft', 'Authorized restore did not return archived Content to draft.');

    $switch($actors['read']);
    $navigation = $app->adminNavigation()->itemsFor($app->auth()->user());
    $assert(count(array_filter($navigation, static fn (array $item): bool => ($item['label'] ?? '') === 'Content')) === 1, 'Read-only user did not see Content navigation.');
    $assert($statusOf($app->run(new Request('GET', $contentUrl))) === 200, 'Read-only user could not access Content listing.');
    foreach ([
        ['POST', $app->adminUrl()->childUrl('content')],
        ['POST', $app->adminUrl()->childUrl("content/{$draftId}")],
        ['POST', $app->adminUrl()->childUrl("content/{$draftId}/publish")],
        ['POST', $app->adminUrl()->childUrl("content/{$draftId}/archive")],
        ['POST', $app->adminUrl()->childUrl("content/{$draftId}/restore")],
    ] as [$method, $path]) {
        $assert($statusOf($app->run(new Request($method, $path, [], ['_token' => $token()]))) === 403, 'Read-only user reached an action route.');
    }

    $switch($actors['create']);
    $assert(count(array_filter($app->adminNavigation()->itemsFor($app->auth()->user()), static fn (array $item): bool => ($item['label'] ?? '') === 'Content')) === 0, 'Action-only user saw Content navigation.');
    $assert($statusOf($app->run(new Request('GET', $contentUrl))) === 403, 'Action-only user accessed Content listing.');

    $switch($actors['none']);
    $assert($statusOf($app->run(new Request('GET', $contentUrl))) === 403, 'No-permission user accessed Content listing.');

    $actionRoutes = [
        'create' => $app->adminUrl()->childUrl('content'),
        'update' => $app->adminUrl()->childUrl("content/{$draftId}"),
        'publish' => $app->adminUrl()->childUrl("content/{$draftId}/publish"),
        'archive' => $app->adminUrl()->childUrl("content/{$draftId}/archive"),
        'restore' => $app->adminUrl()->childUrl("content/{$draftId}/restore"),
    ];
    foreach (['read_create' => 'create', 'read_update' => 'update', 'read_publish' => 'publish', 'read_delete' => 'archive'] as $actor => $allowedAction) {
        $switch($actors[$actor]);
        foreach ($actionRoutes as $action => $path) {
            if ($action === $allowedAction || ($allowedAction === 'archive' && $action === 'restore')) {
                continue;
            }

            $assert($statusOf($app->run(new Request('POST', $path, [], ['_token' => $token()]))) === 403, "[{$actor}] unexpectedly reached [{$action}].");
        }
    }

    $switch($actors['read_create']);
    $assert($statusOf($app->run(new Request('POST', $contentUrl, [], [
        '_token' => $token(), 'type' => 'article', 'title' => 'Created', 'slug' => 'created', 'body' => 'Body', 'status' => 'draft',
    ]))) === 302, 'Read plus create did not grant create behavior.');

    $switch($actors['read_update']);
    $updateEntry = $repository->findById($draftId);
    $assert($statusOf($app->run(new Request('POST', $app->adminUrl()->childUrl("content/{$draftId}"), [], [
        '_token' => $token(), 'type' => $updateEntry->type(), 'title' => 'Updated', 'slug' => '', 'body' => $updateEntry->body(), 'status' => 'draft', 'expected_updated_at' => $updateEntry->updatedAt(),
    ]))) === 302, 'Read plus update did not grant update behavior.');
    $assert($repository->findById($draftId)?->slug() === 'draft-article', 'Title-only route update changed the existing slug.');

    $switch($actors['read_publish']);
    $assert($statusOf($app->run(new Request('POST', $app->adminUrl()->childUrl("content/{$draftId}/publish"), [], ['_token' => $token()]))) === 302, 'Read plus publish did not grant publish behavior.');

    $switch($actors['read_delete']);
    $assert($statusOf($app->run(new Request('POST', $app->adminUrl()->childUrl("content/{$draftId}/archive"), [], ['_token' => $token()]))) === 302, 'Read plus delete did not grant archive behavior.');
    $assert($statusOf($app->run(new Request('POST', $app->adminUrl()->childUrl("content/{$draftId}/restore"), [], ['_token' => $token()]))) === 302, 'Read plus delete did not grant restore behavior.');

    $app->auth()->logout();
    $publishedResponse = $app->run(new Request('GET', '/content/public-article'));
    $draftResponse = $app->run(new Request('GET', '/content/draft-article'));
    $rootSlugResponse = $app->run(new Request('GET', '/public-article'));
    $assert($statusOf($publishedResponse) === 200, 'Published public Content route was affected by Admin permissions: ' . $statusOf($publishedResponse));
    $assert($statusOf($draftResponse) === 404, 'Draft Content became publicly available: ' . $statusOf($draftResponse));
    $assert($statusOf($rootSlugResponse) === 404, 'A root-level slug route was introduced unexpectedly.');

    echo "M3.4 Content authorization matrix passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    $server->exec('DROP DATABASE IF EXISTS ' . $databaseIdentifier);
}
