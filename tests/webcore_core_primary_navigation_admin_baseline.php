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
session_id('copot-wu3-' . bin2hex(random_bytes(6)));
require $basePath . '/bootstrap/autoload.php';
Env::load($basePath . '/.env');
$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void { $assertions++; if (!$condition) throw new RuntimeException($message); };
$status = static function (Response $response): int { $property = new ReflectionProperty(Response::class, 'status'); $property->setAccessible(true); return (int) $property->getValue($response); };
$body = static function (Response $response): string { $property = new ReflectionProperty(Response::class, 'content'); $property->setAccessible(true); return (string) $property->getValue($response); };
$headers = static function (Response $response): array { $property = new ReflectionProperty(Response::class, 'headers'); $property->setAccessible(true); return (array) $property->getValue($response); };
$host = (string) Env::get('DB_HOST', '127.0.0.1'); $port = (int) Env::get('DB_PORT', '3306'); $username = (string) Env::get('DB_USERNAME', 'root'); $password = (string) Env::get('DB_PASSWORD', '');
$databaseName = 'copot_wu3_' . bin2hex(random_bytes(6)); $quoted = '`' . $databaseName . '`';
$server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$server->exec("CREATE DATABASE {$quoted} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
try {
    (new InstallerSchemaRunner($basePath . '/database/schema.sql'))->install(['host'=>$host,'port'=>$port,'databaseName'=>$databaseName,'database'=>$databaseName,'username'=>$username,'password'=>$password]);
    $_ENV['DB_DATABASE'] = $databaseName; putenv('DB_DATABASE=' . $databaseName);
    $app = new Application($basePath); $app->session()->start();
    require $basePath . '/routes/web.php'; require $basePath . '/routes/auth.php'; require $basePath . '/routes/admin.php'; require $basePath . '/routes/content_admin.php'; require $basePath . '/routes/media_admin.php'; require $basePath . '/routes/navigation_admin.php';
    $app->modules()->install('navigation'); $app->modules()->enable('navigation'); $app->moduleLoader()->loadRoutes($app);
    $db = $app->database()->connection();
    $permissions = []; foreach (['admin.access','navigation.manage'] as $slug) { $q=$db->prepare('SELECT id FROM permissions WHERE slug=:slug'); $q->execute(['slug'=>$slug]); $permissions[$slug]=(int)$q->fetchColumn(); }
    $db->exec("INSERT INTO users (name,email,password_hash,status,created_at,updated_at) VALUES ('WU3','wu3@example.test','x','active',NOW(),NOW())"); $userId=(int)$db->lastInsertId(); $db->exec("INSERT INTO roles (name,slug,created_at,updated_at) VALUES ('WU3 role','wu3-role',NOW(),NOW())"); $roleId=(int)$db->lastInsertId(); $db->exec("INSERT INTO user_roles (user_id,role_id) VALUES ({$userId},{$roleId})"); foreach($permissions as $permissionId)$db->exec("INSERT INTO role_permissions (role_id,permission_id) VALUES ({$roleId},{$permissionId})"); $app->session()->set('_copot_user_id',$userId);
    $navigation = $app->adminUrl()->childUrl('navigation'); $empty=$app->run(new Request('GET',$navigation)); $assert($status($empty)===200 && str_contains($body($empty),'Primary Navigation') && str_contains($body($empty),'Navigation item'),'Core Primary Navigation workspace did not render without Navigation Manager: status=' . $status($empty) . ' tail=' . substr($body($empty), -1000)); $assert($status($app->run(new Request('GET',$app->adminUrl()->childUrl('navigation-manager'))))===200,'Navigation Manager extension route did not coexist.'); $assert((int)$db->query("SELECT COUNT(*) FROM navigation_menus WHERE slug='primary'")->fetchColumn()===1,'Canonical primary menu was not materialized.');
    $workspaceBody = $body($empty); $assert(str_contains($workspaceBody, 'data-navigation-workspace') && str_contains($workspaceBody, 'Navigation item') && !str_contains($workspaceBody, 'name="label"') && !str_contains($workspaceBody, 'name="target_choice"') && !str_contains($workspaceBody, 'Target kind') && !str_contains($workspaceBody, 'Target reference') && str_contains($workspaceBody, 'disabled') && str_contains($workspaceBody, 'hidden'), 'Master-detail empty state is incorrect.'); $createView = $app->run(new Request('GET', $navigation, ['create' => '1'])); $assert($status($createView) === 200 && str_contains($body($createView), 'Add navigation item') && str_contains($body($createView), 'name="target_choice"') && str_contains($body($createView), 'data-navigation-dismiss'), 'Create mode did not remain in the workspace.');
    $token=$app->session()->csrfToken(); $create=$app->run(new Request('POST',$app->adminUrl()->childUrl('navigation/items'),[],['_token'=>$token,'label'=>'Home','target_choice'=>'custom','custom_url'=>'/','is_visible'=>'1'])); $assert($status($create)===302 && str_contains($headers($create)['Location'] ?? '', '?item='), 'Custom URL item creation failed.'); $selectedView = $app->run(new Request('GET', $navigation, ['item' => '1'])); $assert($status($selectedView) === 200 && str_contains($body($selectedView), 'Navigation item preview') && str_contains($body($selectedView), 'Home'), 'Selected item did not populate the detail card.');
    $selectedBody = $body($selectedView); $assert(str_contains($selectedBody, 'data-navigation-dismiss') && !str_contains($selectedBody, 'admin-navigation-tree__actions'), 'Selected navigation view did not expose the mobile dismissal surface without row actions.');
    $db->exec("INSERT INTO content (type,title,slug,excerpt,body,status,published_at,created_at,updated_at) VALUES ('article','Published','published-article','Excerpt','Body','published',NOW(),NOW(),NOW())"); $articleId=(int)$db->lastInsertId();
    $createArticle=$app->run(new Request('POST',$app->adminUrl()->childUrl('navigation/items'),[],['_token'=>$token,'label'=>'Articles','target_choice'=>'article_collection','is_visible'=>'1'])); $assert($status($createArticle)===302,'Article Collection item creation failed.');
    $createContent=$app->run(new Request('POST',$app->adminUrl()->childUrl('navigation/items'),[],['_token'=>$token,'label'=>'Story','target_choice'=>'content','content_reference'=>'published-article','is_visible'=>'1'])); $assert($status($createContent)===302,'Published Content target creation failed.');
    $delete=$app->run(new Request('POST',$app->adminUrl()->childUrl('navigation/items/1/delete'),[],['_token'=>$token])); $assert($status($delete)===302 && ($headers($delete)['Location'] ?? '')===$navigation,'Navigation item deletion did not resolve the specific POST route.'); $assert((int)$db->query("SELECT COUNT(*) FROM navigation_items WHERE id=1")->fetchColumn()===0,'Navigation item deletion did not remove the selected item.'); $missingDelete=$app->run(new Request('POST',$app->adminUrl()->childUrl('navigation/items/999/delete'),[],['_token'=>$token])); $assert($status($missingDelete)===404,'Missing Navigation item deletion was not sanitized as 404.');
    $assert((int)$db->query("SELECT COUNT(*) FROM content WHERE type='article' AND status='published'")->fetchColumn()===1,'Published article fixture was not stored.'); $delivery = new \Copot\Core\ContentDeliveryService(new \Copot\Core\ContentRepository($app->database())); $assert(count($delivery->publishedArticles())===1,'Core Article Collection query returned no published article.'); $articles=$app->run(new Request('GET','/articles')); $assert($status($articles)===200 && str_contains($body($articles),'Published'),'Article Collection delivery failed: status=' . $status($articles) . ' tail=' . substr($body($articles), -800));
    $draftRejected=$app->run(new Request('POST',$app->adminUrl()->childUrl('navigation/items'),[],['_token'=>$token,'label'=>'Draft','target_choice'=>'content','content_reference'=>'missing','is_visible'=>'1'])); $assert($status($draftRejected)===422,'Unavailable Content target was not rejected safely.'); $csrfRejected=$app->run(new Request('POST',$app->adminUrl()->childUrl('navigation/items'),[],['label'=>'No CSRF','target_choice'=>'custom','custom_url'=>'/x'])); $assert($status($csrfRejected)===419,'CSRF boundary did not reject missing token.');
    echo "WU3 Core Primary Navigation baseline passed ({$assertions} assertions)." . PHP_EOL;
} finally { unset($app, $db, $navigationService, $navigationRepository, $contentRepository, $primaryNavigation, $delivery); gc_collect_cycles(); $server->exec("DROP DATABASE IF EXISTS {$quoted}"); }
