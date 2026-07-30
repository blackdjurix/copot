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
foreach (['NavigationMenu.php','NavigationItem.php','NavigationRenderItem.php','NavigationTargetResolver.php','NavigationTargetResolverRegistry.php','NavigationTargetResolverRegistryFactory.php','NavigationRepository.php','NavigationService.php','NavigationFrontendReader.php'] as $file) {
    require_once $basePath . '/modules/navigation/Services/' . $file;
}
foreach (['Content.php','ContentRepository.php','ContentNavigationTargetResolver.php'] as $file) {
    require_once $basePath . '/modules/content/Services/' . $file;
}
$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void { $assertions++; if (!$condition) throw new RuntimeException($message); };
$host = (string) Env::get('DB_HOST', '127.0.0.1'); $port = (int) Env::get('DB_PORT', '3306'); $username = (string) Env::get('DB_USERNAME', 'root'); $password = (string) Env::get('DB_PASSWORD', '');
$name = 'copot_m36_wu5_' . bin2hex(random_bytes(6)); $quoted = '`' . str_replace('`', '``', $name) . '`';
$server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$server->exec('CREATE DATABASE ' . $quoted . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
try {
    (new InstallerSchemaRunner($basePath . '/database/schema.sql'))->install(['host'=>$host,'port'=>$port,'database'=>$name,'username'=>$username,'password'=>$password]);
    $_ENV['DB_DATABASE'] = $name; putenv('DB_DATABASE=' . $name);
    $database = new Database(new Config($basePath . '/config')); $db = $database->connection();
    $db->exec("INSERT INTO modules (name,title,version,path,status,installed_at,created_at,updated_at) VALUES ('content','Content','0.1.0','modules/content','enabled',NOW(),NOW(),NOW())");
    $db->exec("INSERT INTO themes (theme_id,name,version,type,path,is_active,metadata,created_at,updated_at) VALUES ('default','Default','0.1.0','frontend','themes/default',1,'{" . '"supports":{"navigation_locations":["primary","secondary"]}}' . "',NOW(),NOW())");
    $content = new ContentRepository($database); $content->create(['type'=>'page','title'=>'Published','slug'=>'published','body'=>'Body','status'=>'published','author_id'=>null]);
    $repository = new NavigationRepository($database); $service = new NavigationService($database, $repository); $menu = $service->createMenu(['name'=>'Primary','slug'=>'primary']);
    $root = $service->createItem(['menu_id'=>$menu,'parent_id'=>null,'label'=>'Home','target_kind'=>'custom','target_reference'=>null,'custom_url'=>'/','is_visible'=>true]);
    $service->createItem(['menu_id'=>$menu,'parent_id'=>$root,'label'=>'Published','target_kind'=>'content','target_reference'=>'published','custom_url'=>null,'is_visible'=>true]);
    $service->createItem(['menu_id'=>$menu,'parent_id'=>null,'label'=>'Hidden','target_kind'=>'custom','target_reference'=>null,'custom_url'=>'/hidden','is_visible'=>false]);
    $service->createItem(['menu_id'=>$menu,'parent_id'=>null,'label'=>'Unavailable','target_kind'=>'unknown','target_reference'=>'missing','custom_url'=>null,'is_visible'=>true]);
    $db->exec("INSERT INTO navigation_menu_assignments (theme_id,location_key,menu_id,created_at,updated_at) VALUES ('default','primary',{$menu},NOW(),NOW())");
    $reader = new NavigationFrontendReader($repository, $service); $locations = $reader->locationsForTheme('default', ['primary','secondary'], (new NavigationTargetResolverRegistryFactory($database))->create());
    $assert(count($locations['primary']) === 1 && $locations['primary'][0]['label'] === 'Home', 'Resolved primary location shape or filtering is incorrect.');
    $assert($locations['primary'][0]['children'][0]['url'] === '/content/published', 'Published provider target did not resolve.');
    $assert($locations['secondary'] === [], 'Unassigned Theme location was not empty.');
    $db->exec("UPDATE modules SET status='disabled' WHERE name='content'");
    $disabled = $reader->locationsForTheme('default', ['primary'], (new NavigationTargetResolverRegistryFactory($database))->create());
    $assert(count($disabled['primary']) === 1 && $disabled['primary'][0]['children'] === [], 'Disabled provider did not fail closed.');
    echo "M3.6 WU5 Navigation consumption passed ({$assertions} assertions)." . PHP_EOL;
} finally { $server->exec('DROP DATABASE IF EXISTS ' . $quoted); }
