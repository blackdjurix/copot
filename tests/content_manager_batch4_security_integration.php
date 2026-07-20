<?php

declare(strict_types=1);

use Copot\Core\Application;
use Copot\Core\Env;
use Copot\Core\InstallerSchemaRunner;
use Copot\Core\Request;
use Copot\Core\Response;
use Copot\Core\ThemeDiscovery;

$basePath = dirname(__DIR__);
chdir($basePath);
session_save_path(sys_get_temp_dir());
session_id('copotm34batch4' . bin2hex(random_bytes(5)));
require $basePath . '/bootstrap/autoload.php';
Env::load($basePath . '/.env');

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;

    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$statusOf = static fn (Response $response): int => (int) (new ReflectionProperty(Response::class, 'status'))->getValue($response);
$contentOf = static fn (Response $response): string => (string) (new ReflectionProperty(Response::class, 'content'))->getValue($response);

$host = (string) Env::get('DB_HOST', '127.0.0.1');
$port = (int) Env::get('DB_PORT', '3306');
$username = (string) Env::get('DB_USERNAME', 'root');
$password = (string) Env::get('DB_PASSWORD', '');
$databaseName = 'copot_m34_batch4_' . bin2hex(random_bytes(6));
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

require_once $basePath . '/modules/content/Services/Content.php';
require_once $basePath . '/modules/content/Services/ContentRepository.php';
require_once $basePath . '/modules/content/Services/ContentService.php';
require_once $basePath . '/modules/taxonomy/Services/TaxonomyAssignmentRepository.php';

final class Batch4FailingTaxonomyAssignmentRepository extends TaxonomyAssignmentRepository
{
    public function syncForType(string $entityType, int $entityId, string $typeSlug, array $termIds): void
    {
        throw new RuntimeException('BATCH4_RAW_TAXONOMY_FAILURE');
    }
}

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
    $theme = (new ThemeDiscovery($basePath . '/themes'))->discover()[0];
    $app->themes()->register($theme);
    $app->themes()->activate($theme->id());

    $connection = $app->database()->connection();
    $permissionIds = [];
    foreach (['admin.access', 'content.read', 'content.create', 'content.update', 'content.publish', 'content.delete'] as $slug) {
        $statement = $connection->prepare('SELECT id FROM permissions WHERE slug = :slug LIMIT 1');
        $statement->execute(['slug' => $slug]);
        $permissionIds[$slug] = (int) $statement->fetchColumn();
    }

    $createActor = static function (string $label, array $permissions) use ($connection, $permissionIds): int {
        $suffix = bin2hex(random_bytes(4));
        $connection->prepare(
            'INSERT INTO users (name, email, password_hash, status, created_at, updated_at)
             VALUES (:name, :email, :password_hash, :status, NOW(), NOW())'
        )->execute([
            'name' => $label,
            'email' => $label . '-' . $suffix . '@example.test',
            'password_hash' => 'test',
            'status' => 'active',
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

    $fullActor = $createActor('batch4-full', array_keys($permissionIds));
    $readActor = $createActor('batch4-read', ['admin.access', 'content.read']);
    $noneActor = $createActor('batch4-none', ['admin.access']);
    $switch = static function (int $userId) use ($app): void {
        $app->auth()->logout();
        $app->session()->set((string) $app->config()->get('auth.session_key', '_copot_user_id'), $userId);
    };
    $token = static fn (): string => $app->session()->csrfToken();
    $contentUrl = $app->adminUrl()->childUrl('content');
    $repository = new ContentRepository($app->database());
    $publishedId = $repository->create([
        'type' => 'article', 'title' => 'Published', 'slug' => 'batch4-published', 'body' => 'Published body', 'status' => 'published', 'author_id' => null,
    ]);
    $draftId = $repository->create([
        'type' => 'article', 'title' => 'Draft', 'slug' => 'batch4-draft', 'body' => 'Draft body', 'status' => 'draft', 'author_id' => null,
    ]);
    $archivedId = $repository->create([
        'type' => 'article', 'title' => 'Archived', 'slug' => 'batch4-archived', 'body' => 'Archived body', 'status' => 'archived', 'author_id' => null,
    ]);

    $count = static fn (): int => (int) $connection->query('SELECT COUNT(*) FROM content')->fetchColumn();

    $switch($noneActor);
    $before = $count();
    $unauthorized = $app->run(new Request('POST', $contentUrl, [], [
        'type' => 'article', 'title' => 'Unauthorized', 'body' => 'No write',
    ]));
    $assert($statusOf($unauthorized) === 403, 'Unauthorized Content write was not denied before CSRF or mutation.');
    $assert($count() === $before, 'Unauthorized Content write mutated state.');

    $switch($fullActor);
    foreach ([[], ['_token' => 'invalid']] as $post) {
        $before = $count();
        $post['type'] = 'article';
        $post['title'] = 'Missing or invalid CSRF';
        $post['body'] = 'Must not persist';
        $response = $app->run(new Request('POST', $contentUrl, [], $post));
        $assert($statusOf($response) === 419, 'Missing or invalid CSRF did not return 419.');
        $assert($count() === $before, 'CSRF failure changed Content state.');
    }

    $malformed = $app->run(new Request('POST', $contentUrl, [], [
        '_token' => $token(), 'type' => ['article'], 'title' => 'Malformed', 'body' => 'Malformed',
    ]));
    $assert($statusOf($malformed) === 422, 'Malformed Content payload was not controlled.');
    $assert($count() === $before, 'Malformed Content payload mutated state.');
    $assert(!str_contains($contentOf($malformed), 'Array to string conversion'), 'Malformed payload leaked PHP conversion details.');

    $invalidTaxonomy = $app->run(new Request('POST', $contentUrl, [], [
        '_token' => $token(), 'type' => 'article', 'title' => 'Invalid taxonomy', 'body' => 'Invalid taxonomy',
        'category_ids' => ['not-an-id'],
    ]));
    $assert($statusOf($invalidTaxonomy) === 422, 'Invalid Taxonomy input was not controlled.');
    $assert($count() === $before, 'Invalid Taxonomy input mutated Content state.');
    $assert(!str_contains($contentOf($invalidTaxonomy), 'BATCH4_RAW'), 'Invalid Taxonomy input leaked internal details.');

    $invalidEdit = $app->run(new Request('GET', $app->adminUrl()->childUrl('content/not-an-id/edit')));
    $assert($statusOf($invalidEdit) === 404, 'Invalid Content edit identifier was not controlled.');
    $invalidAction = $app->run(new Request('POST', $app->adminUrl()->childUrl('content/not-an-id/publish'), [], ['_token' => $token()]));
    $assert($statusOf($invalidAction) === 404, 'Invalid Content action identifier was not controlled.');
    $coercedAction = $app->run(new Request('POST', $app->adminUrl()->childUrl('content/01/publish'), [], ['_token' => $token()]));
    $assert($statusOf($coercedAction) === 404, 'Non-canonical Content action identifier was coerced to a valid ID.');

    $invalidTransition = $app->run(new Request('POST', $app->adminUrl()->childUrl("content/{$archivedId}/publish"), [], ['_token' => $token()]));
    $assert($statusOf($invalidTransition) === 422, 'Invalid lifecycle transition was not controlled.');
    $assert($repository->findById($archivedId)?->status() === 'archived', 'Invalid lifecycle transition mutated archived Content.');

    $staleEntry = $repository->findById($draftId);
    $staleService = new ContentService($app->database(), $repository);
    try {
        $staleService->update($draftId, [
            'type' => $staleEntry->type(), 'title' => 'Stale overwrite', 'slug' => $staleEntry->slug(),
            'body' => $staleEntry->body(), 'status' => $staleEntry->status(), 'author_id' => $staleEntry->authorId(),
        ], [], '2000-01-01 00:00:00');
        $assert(false, 'Stale Content update unexpectedly succeeded.');
    } catch (ContentStaleWriteException) {
        $assert(true, 'Stale Content update was rejected.');
    }
    $assert($repository->findById($draftId)?->title() === 'Draft', 'Stale Content update overwrote newer state.');

    $malformedVersion = $app->run(new Request('POST', $app->adminUrl()->childUrl("content/{$draftId}"), [], [
        '_token' => $token(), 'type' => 'article', 'title' => 'Malformed version', 'body' => 'Must not persist',
        'status' => 'draft', 'expected_updated_at' => ['unexpected'],
    ]));
    $assert($statusOf($malformedVersion) === 422, 'Malformed Content version token was not controlled.');
    $assert($repository->findById($draftId)?->title() === 'Draft', 'Malformed Content version token mutated state.');

    $switch($readActor);
    foreach ([$draftId => 'batch4-draft', $archivedId => 'batch4-archived'] as $id => $slug) {
        $response = $app->run(new Request('GET', '/content/' . $slug));
        $assert($statusOf($response) === 404, 'Unpublished Content became publicly exposed.');
    }
    $public = $app->run(new Request('GET', '/content/batch4-published'));
    $assert($statusOf($public) === 200, 'Published Content public route regressed.');

    $failingTaxonomy = new Batch4FailingTaxonomyAssignmentRepository($app->database());
    $service = new ContentService($app->database(), $repository, $failingTaxonomy);
    $before = $count();
    try {
        $service->create([
            'type' => 'article', 'title' => 'Injected taxonomy failure', 'slug' => 'batch4-taxonomy-failure', 'body' => 'Must roll back', 'status' => 'draft', 'author_id' => null,
        ], ['category_ids' => [1]]);
        $assert(false, 'Injected Taxonomy failure unexpectedly succeeded.');
    } catch (ContentWriteException $exception) {
        $assert($exception->getMessage() === 'Content could not be saved.', 'Taxonomy failure was not sanitized by the service.');
    }
    $assert($count() === $before, 'Taxonomy failure left partial Content mutation.');
    $assert(!$connection->inTransaction(), 'Taxonomy failure left an open transaction.');

    $switch($fullActor);
    $duplicate = $app->run(new Request('POST', $contentUrl, [], [
        '_token' => $token(), 'type' => 'article', 'title' => 'Duplicate', 'slug' => 'batch4-published', 'body' => 'Duplicate slug',
    ]));
    $assert($statusOf($duplicate) === 422, 'Duplicate slug was not controlled.');
    $assert(!str_contains($contentOf($duplicate), 'SQLSTATE') && !str_contains($contentOf($duplicate), 'BATCH4_RAW'), 'Duplicate slug response leaked persistence details.');

    $manifest = require $basePath . '/build/package_manifest.php';
    $assert(in_array('modules/content', $manifest['include'] ?? [], true), 'Package manifest omitted the Content module.');
    $assert((int) $connection->query("SELECT COUNT(*) FROM permissions WHERE slug = 'content.read'")->fetchColumn() === 1, 'Clean-install schema did not provision content.read exactly once.');

    $connection->exec('DROP TABLE content');
    $failure = $app->run(new Request('POST', $contentUrl, [], [
        '_token' => $token(), 'type' => 'article', 'title' => 'Persistence failure', 'body' => 'Must be sanitized',
    ]));
    $failureHtml = $contentOf($failure);
    $assert($statusOf($failure) === 503, 'Unexpected Content persistence failure did not return sanitized 503.');
    $assert(!str_contains($failureHtml, 'SQLSTATE') && !str_contains($failureHtml, 'BATCH4_RAW') && !str_contains($failureHtml, 'base_path'), 'Persistence failure leaked raw database or internal details.');

    echo "M3.4 Content Batch 4 security/integration passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    $server->exec('DROP DATABASE IF EXISTS ' . $databaseIdentifier);
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}
