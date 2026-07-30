<?php

declare(strict_types=1);

use Copot\Core\Env;
use Copot\Core\InstallerSchemaRunner;
use Copot\Core\ThemeDiscovery;
use Copot\Core\ThemeSettingsService;
use Copot\Core\ThemeSettingsStorage;

$basePath = dirname(__DIR__);
chdir($basePath);
require $basePath . '/bootstrap/autoload.php';
Env::load($basePath . '/.env');
$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void { $assertions++; if (!$condition) throw new RuntimeException($message); };
$host = (string) Env::get('DB_HOST', '127.0.0.1'); $port = (int) Env::get('DB_PORT', '3306'); $username = (string) Env::get('DB_USERNAME', 'root'); $password = (string) Env::get('DB_PASSWORD', '');
$databaseName = 'copot_m37_wu5_' . bin2hex(random_bytes(5)); $configuration = compact('host', 'port', 'username', 'password') + ['database' => $databaseName];
$server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$server->exec('CREATE DATABASE `' . $databaseName . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$fixtureRoot = $basePath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . '.m37-wu5-' . bin2hex(random_bytes(4)); mkdir($fixtureRoot, 0777, true);
$cleanup = static function () use ($server, $databaseName, $fixtureRoot): void { if (is_dir($fixtureRoot)) { $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fixtureRoot, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST); foreach ($it as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); rmdir($fixtureRoot); } $server->exec('DROP DATABASE IF EXISTS `' . $databaseName . '`'); };
try {
    (new InstallerSchemaRunner($basePath . '/database/schema.sql'))->install($configuration); $_ENV['DB_DATABASE'] = $databaseName; putenv('DB_DATABASE=' . $databaseName);
    $make = static function (string $id, string $name) use ($fixtureRoot): void { $path = $fixtureRoot . DIRECTORY_SEPARATOR . $id; mkdir($path . DIRECTORY_SEPARATOR . 'layouts', 0777, true); file_put_contents($path . '/layouts/app.php', '<?php echo $themeSettings["accent"] ?? "default";'); file_put_contents($path . '/theme.json', json_encode(['id'=>$id,'name'=>$name,'version'=>'1.0.0','type'=>'frontend','entry'=>['layout'=>'layouts/app.php'],'settings'=>['version'=>1,'sections'=>[['id'=>'appearance','label'=>'Appearance','fields'=>[['key'=>'accent','label'=>'Accent','type'=>'string','control'=>'color','default'=>'#ffffff','validation'=>['format'=>'hex_color']],['key'=>'enabled','label'=>'Enabled','type'=>'boolean','control'=>'checkbox','default'=>false,'validation'=>[]]]]]]], JSON_THROW_ON_ERROR)); };
    $make('inactive-theme-with-a-very-long-identifier-that-must-map-safely', 'Inactive'); $make('active', 'Active');
    $discovery = new ThemeDiscovery($fixtureRoot); $definitions = $discovery->discover(); $inactive = $definitions[0]->id() === 'active' ? $definitions[1] : $definitions[0]; $active = $definitions[0]->id() === 'active' ? $definitions[0] : $definitions[1];
    $assert(strlen(ThemeSettingsStorage::namespaceFor($inactive->id())) <= 64, 'Long Theme ID produced an oversized namespace.'); $assert(ThemeSettingsStorage::namespaceFor($inactive->id()) !== ThemeSettingsStorage::namespaceFor($active->id()), 'Theme namespace mapper collided.');
    $service = new ThemeSettingsService(new Copot\Core\SettingsRepository(new Copot\Core\Database(new Copot\Core\Config($basePath . '/config'))), new Copot\Core\Database(new Copot\Core\Config($basePath . '/config')));
    $assert($service->values($inactive) === ['accent'=>'#ffffff','enabled'=>false], 'Declared defaults did not resolve deterministically.');
    $service->save($inactive, ['accent'=>'#112233','enabled'=>'1']); $assert($service->values($inactive) === ['accent'=>'#112233','enabled'=>true], 'Inactive Theme settings did not persist with strict types.');
    $service->reset($inactive); $assert($service->values($inactive) === ['accent'=>'#ffffff','enabled'=>false], 'Theme reset did not restore defaults.');
    $db = (new Copot\Core\Database(new Copot\Core\Config($basePath . '/config')))->connection(); $namespace = ThemeSettingsStorage::namespaceFor($inactive->id()); $db->prepare('INSERT INTO settings (namespace,setting_key,setting_value,value_type,created_at,updated_at) VALUES (?,?,?,?,NOW(),NOW())')->execute([$namespace,'accent','not-a-color','string']); $assert($service->values($inactive)['accent'] === '#ffffff', 'Corrupt override did not fail closed to default.');
    $db->prepare('INSERT INTO themes (theme_id,name,version,type,path,is_active,metadata,created_at,updated_at) VALUES (?,?,?,?,?,1,?,NOW(),NOW())')->execute([$active->id(),$active->name(),$active->version(),$active->type(),'storage/.m37-wu5-' . basename($fixtureRoot) . '/' . $active->id(),json_encode($active->metadata(), JSON_THROW_ON_ERROR)]);
    $resolver = new Copot\Core\ThemeSettingsResolver(new Copot\Core\ThemeRepository(new Copot\Core\Database(new Copot\Core\Config($basePath . '/config'))), $service);
    $assert($resolver->resolve() === ['accent'=>'#ffffff','enabled'=>false], 'Runtime resolver did not expose only active Theme defaults.');
    $db->prepare('UPDATE settings SET setting_value = ? WHERE namespace = ? AND setting_key = ?')->execute(['#abcdef', ThemeSettingsStorage::namespaceFor($inactive->id()), 'accent']);
    $assert($resolver->resolve() === ['accent'=>'#ffffff','enabled'=>false], 'Inactive Theme settings leaked into active runtime output.');
    echo "M3.7 Work Unit 5 Theme settings passed ({$assertions} assertions).\n";
} finally { $cleanup(); }
