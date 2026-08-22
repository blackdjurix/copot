<?php

declare(strict_types=1);

use Copot\Core\Config;
use Copot\Core\Application;
use Copot\Core\ContentDeliveryService;
use Copot\Core\ContentRepository;
use Copot\Core\ContentService;
use Copot\Core\Database;
use Copot\Core\DatabaseTableNames;
use Copot\Core\DatabaseTableOwner;
use Copot\Core\DatabaseTableOwnershipCatalog;
use Copot\Core\Env;
use Copot\Core\InstallationIdentity;
use Copot\Core\InstallerSchemaRunner;
use Copot\Core\MigrationAuthorizationContext;
use Copot\Core\MigrationSchemaSurface;
use Copot\Core\Request;

$basePath = dirname(__DIR__);
chdir($basePath);
require $basePath . '/bootstrap/autoload.php';
Env::load($basePath . '/.env');

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};

$host = (string) Env::get('DB_HOST', '127.0.0.1');
$port = (int) Env::get('DB_PORT', '3306');
$username = (string) Env::get('DB_USERNAME', 'root');
$password = (string) Env::get('DB_PASSWORD', '');
$name = 'copot_wu3_core_' . bin2hex(random_bytes(6));
$quoted = '`' . str_replace('`', '``', $name) . '`';
$server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
$server->exec('CREATE DATABASE ' . $quoted . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

try {
    (new InstallerSchemaRunner($basePath . '/database/schema.sql'))->install(['host'=>$host,'port'=>$port,'database'=>$name,'username'=>$username,'password'=>$password]);
    $_ENV['DB_DATABASE'] = $name;
    putenv('DB_DATABASE=' . $name);
    $database = new Database(new Config($basePath . '/config'));
    $repository = new ContentRepository($database);
    $service = new ContentService($database, $repository);

    $assert($database->tables()->resolve('content') === $database->table('content'), 'Content did not resolve through the Webcore table boundary.');
    $assert((new DatabaseTableNames('alpha'))->resolve('content') === 'alpha_content', 'Namespaced Content did not remain stable after extraction.');
    $id = $service->create(['type'=>'page','title'=>'Core Page','slug'=>'core-page','body'=>'Plain body','status'=>'draft','author_id'=>null]);
    $service->publish($id);
    $entry = $repository->findPublishedBySlug('core-page');
    $assert($entry !== null && $entry->isPublished(), 'Baseline Content could not create and publish a Page without the Content Manager.');
    $delivery = new ContentDeliveryService($repository);
    $renderData = $delivery->findPublishedBySlug('core-page');
    $assert(is_array($renderData) && $renderData['title'] === 'Core Page' && $renderData['body'] === 'Plain body', 'Content delivery did not produce normalized render data.');
    $runtime = new Application($basePath);
    $app = $runtime;
    require $basePath . '/routes/web.php';
    $publicResponse = $runtime->run(new Request('GET', '/content/core-page'));
    $responseContent = $publicResponse->body();
    $assert($publicResponse->statusCode() === 200 && str_contains($responseContent, '<h1>Core Page</h1>') && str_contains($responseContent, 'Plain body'), 'Webcore public Content route did not render baseline Page data without Content Manager routes.');
    $articleId = $service->create(['type'=>'article','title'=>'Core Article','slug'=>'core-article','body'=>'Article body','status'=>'published','author_id'=>null]);
    $articleResponse = $runtime->run(new Request('GET', '/content/core-article'));
    $assert($articleResponse->statusCode() === 200 && str_contains($articleResponse->body(), '<h1>Core Article</h1>') && str_contains($articleResponse->body(), 'Article body'), 'Webcore public Content route did not render baseline Article data.');
    $service->archive($id);
    $assert($repository->findById($id)?->isArchived() === true, 'Baseline archive lifecycle did not complete.');
    $service->restore($id);
    $assert($repository->findById($id)?->status() === 'draft', 'Baseline restore lifecycle did not complete.');

    $catalog = DatabaseTableOwnershipCatalog::current();
    $assert($catalog->owner('content')->isWebcore() && !$catalog->ownership('content')->isTargetTransitionPending(), 'Content ownership transition was not closed as Webcore-owned.');
    $assert($catalog->owner('media')->isWebcore() && $catalog->owner('media_variants')->moduleIdentity()?->value() === 'media', 'WU3/WU4 Media ownership boundary is invalid.');
    $assert($catalog->owner('navigation_menus')->isWebcore() && $catalog->owner('redirects')->isWebcore(), 'WU3/WU5 Navigation or Redirect ownership boundary is invalid.');
    $assert($catalog->owner('taxonomy_terms')->moduleIdentity()?->value() === 'taxonomy', 'WU3 changed Taxonomy ownership.');
    $webcore = new MigrationAuthorizationContext(InstallationIdentity::generate(), new DatabaseTableNames(), 'wu3-webcore', 'upgrade', DatabaseTableOwner::webcore(), 'webcore.content', str_repeat('a', 64), '1.0.0', '1.1.0', true, new MigrationSchemaSurface(['content']));
    $webcore->authorizeTable($catalog, 'content');
    $module = new MigrationAuthorizationContext(InstallationIdentity::generate(), new DatabaseTableNames(), 'wu3-module', 'upgrade', DatabaseTableOwner::module('content'), 'content.migration', str_repeat('b', 64), '1.0.0', '1.1.0', true, new MigrationSchemaSurface(['content']));
    try { $module->authorizeTable($catalog, 'content'); $assert(false, 'Content Manager retained baseline table mutation authority.'); } catch (RuntimeException) { $assert(true, 'Content Manager cannot mutate the Webcore-owned baseline table.'); }

    $webRoutes = (string) file_get_contents($basePath . '/routes/web.php');
    $moduleRoutes = (string) file_get_contents($basePath . '/modules/content/routes.php');
    $assert(str_contains($webRoutes, 'ContentDeliveryService') && str_contains($webRoutes, "'/content/{slug}'"), 'Webcore public Content delivery route is missing.');
    $assert(!str_contains($moduleRoutes, "get('/content/{slug}'"), 'Content Manager still owns the baseline public route.');
} finally {
    try { $server->exec('DROP DATABASE ' . $quoted); } catch (Throwable) { }
}

echo "WU3 Webcore Content extraction tests passed ({$assertions} assertions)." . PHP_EOL;
