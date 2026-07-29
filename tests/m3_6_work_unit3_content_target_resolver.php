<?php

declare(strict_types=1);

use Copot\Core\Config;
use Copot\Core\Database;
use Copot\Core\Env;
use Copot\Core\InstallerSchemaRunner;

$basePath = dirname(__DIR__);
chdir($basePath);
require $basePath . '/bootstrap/autoload.php';
Env::load($basePath . '/.env');
foreach ([
    'modules/navigation/Services/NavigationRenderItem.php',
    'modules/navigation/Services/NavigationTargetResolver.php',
    'modules/navigation/Services/NavigationTargetResolverRegistry.php',
    'modules/navigation/Services/NavigationTargetResolverRegistryFactory.php',
    'modules/content/Services/Content.php',
    'modules/content/Services/ContentRepository.php',
    'modules/content/Services/ContentNavigationTargetResolver.php',
] as $file) {
    require_once $basePath . '/' . $file;
}

final class M36Wu3DuplicateResolver implements NavigationTargetResolver
{
    public function kind(): string { return 'content'; }
    public function resolve(string $reference): ?NavigationRenderItem { return null; }
}

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$rejects = static function (callable $operation, string $message) use ($assert): void {
    try {
        $operation();
        $assert(false, $message);
    } catch (InvalidArgumentException) {
        $assert(true, $message);
    }
};

$host = (string) Env::get('DB_HOST', '127.0.0.1');
$port = (int) Env::get('DB_PORT', '3306');
$username = (string) Env::get('DB_USERNAME', 'root');
$password = (string) Env::get('DB_PASSWORD', '');
$name = 'copot_m36_wu3_' . bin2hex(random_bytes(6));
$quoted = '`' . str_replace('`', '``', $name) . '`';
$server = new PDO(
    "mysql:host={$host};port={$port};charset=utf8mb4",
    $username,
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
);
$server->exec('CREATE DATABASE ' . $quoted . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

try {
    (new InstallerSchemaRunner($basePath . '/database/schema.sql'))->install([
        'host' => $host,
        'port' => $port,
        'database' => $name,
        'username' => $username,
        'password' => $password,
    ]);
    $_ENV['DB_DATABASE'] = $name;
    putenv('DB_DATABASE=' . $name);
    $database = new Database(new Config($basePath . '/config'));
    $connection = $database->connection();
    $connection->exec("INSERT INTO modules (name, title, version, path, status, installed_at, created_at, updated_at) VALUES ('content', 'Content Module', '0.1.0', 'modules/content', 'enabled', NOW(), NOW(), NOW())");
    $content = new ContentRepository($database);
    $publishedId = $content->create([
        'type' => 'page', 'title' => 'Published title', 'slug' => 'published-page', 'body' => 'Body', 'status' => 'published', 'author_id' => null,
    ]);
    $content->create([
        'type' => 'page', 'title' => 'Draft title', 'slug' => 'draft-page', 'body' => 'Body', 'status' => 'draft', 'author_id' => null,
    ]);
    $content->create([
        'type' => 'article', 'title' => 'Archived title', 'slug' => 'archived-page', 'body' => 'Body', 'status' => 'archived', 'author_id' => null,
    ]);

    $factory = new NavigationTargetResolverRegistryFactory($database);
    $first = $factory->create();
    $second = $factory->create();
    $assert($first !== $second && $first->has('content') && $second->has('content'), 'Enabled Content was not registered in independent registries.');
    $resolved = $first->resolve('content', ' published-page ');
    $assert($resolved instanceof NavigationRenderItem && $resolved->toArray() === [
        'kind' => 'content', 'reference' => 'published-page', 'label' => 'Published title', 'url' => '/content/published-page', 'is_visible' => true,
    ], 'Published Content did not resolve to the canonical render item.');
    $rejects(fn () => $first->register(new M36Wu3DuplicateResolver()), 'Duplicate Content resolver registration was accepted.');
    foreach (['', 'Published-page', 'published/page', 'published-page?x=1', 'published-page#fragment', 'missing-page', 'stale-page'] as $reference) {
        $assert($first->resolve('content', $reference) === null, "Invalid or unavailable reference [{$reference}] resolved.");
    }
    $assert($first->resolve('content', 'draft-page') === null, 'Draft Content resolved.');
    $assert($first->resolve('content', 'archived-page') === null, 'Archived Content resolved.');
    $assert($first->resolve('unknown', 'published-page') === null, 'Unknown provider kind resolved.');

    $connection->exec("UPDATE content SET title = 'Updated title' WHERE id = {$publishedId}");
    $assert($first->resolve('content', 'published-page')?->label() === 'Updated title', 'Title change was cached.');
    $connection->exec("UPDATE content SET slug = 'renamed-page' WHERE id = {$publishedId}");
    $assert($first->resolve('content', 'published-page') === null, 'Stale slug resolved after rename.');
    $assert($second->resolve('content', 'renamed-page')?->url() === '/content/renamed-page', 'New slug did not resolve without a new request registry.');
    $connection->exec("UPDATE content SET status = 'draft' WHERE id = {$publishedId}");
    $assert($second->resolve('content', 'renamed-page') === null, 'Publication change was cached.');
    $connection->exec("UPDATE modules SET status = 'disabled' WHERE name = 'content'");
    $disabled = $factory->create();
    $assert(!$disabled->has('content') && $disabled->resolve('content', 'renamed-page') === null, 'Disabled Content did not fail closed.');
    $connection->exec("UPDATE modules SET status = 'enabled' WHERE name = 'content'");
    $unavailable = (new NavigationTargetResolverRegistryFactory($database, $basePath . '/missing-content-services'))->create();
    $assert(!$unavailable->has('content') && $unavailable->resolve('content', 'renamed-page') === null, 'Unavailable Content files did not fail closed.');

    echo "M3.6 Work Unit 3 Content target resolver passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    $server->exec('DROP DATABASE IF EXISTS ' . $quoted);
}
