<?php

declare(strict_types=1);

use Copot\Core\Config;
use Copot\Core\Database;
use Copot\Core\Env;
use Copot\Core\InstallerSchemaRunner;
use Copot\Core\NavigationRepository;
use Copot\Core\NavigationService;

$base = dirname(__DIR__);
chdir($base);
require $base . '/bootstrap/autoload.php';
Env::load($base . '/.env');
$assertions = 0;
$assert = static function (bool $ok, string $message) use (&$assertions): void { $assertions++; if (!$ok) throw new RuntimeException($message); };
$rejects = static function (callable $operation, string $message) use ($assert): void { try { $operation(); $assert(false, $message); } catch (InvalidArgumentException) { $assert(true, $message); } };
$host = (string) Env::get('DB_HOST', '127.0.0.1'); $port = (int) Env::get('DB_PORT', '3306'); $username = (string) Env::get('DB_USERNAME', 'root'); $password = (string) Env::get('DB_PASSWORD', '');
$name = 'copot_wu3_reorder_' . bin2hex(random_bytes(5)); $quoted = '`' . $name . '`';
$server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$server->exec('CREATE DATABASE ' . $quoted . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
try {
    (new InstallerSchemaRunner($base . '/database/schema.sql'))->install(['host'=>$host,'port'=>$port,'database'=>$name,'username'=>$username,'password'=>$password]);
    $_ENV['DB_DATABASE'] = $name; putenv('DB_DATABASE=' . $name);
    $database = new Database(new Config($base . '/config')); $repository = new NavigationRepository($database); $service = new NavigationService($database, $repository);
    $menu = $service->createMenu(['name'=>'Primary','slug'=>'primary']); $other = $service->createMenu(['name'=>'Other','slug'=>'other']);
    $item = static fn (int $menuId, ?int $parent, string $label): array => ['menu_id'=>$menuId,'parent_id'=>$parent,'label'=>$label,'target_kind'=>'custom','target_reference'=>null,'custom_url'=>'/' . strtolower($label),'is_visible'=>true];
    $first = $service->createItem($item($menu, null, 'First')); $second = $service->createItem($item($menu, null, 'Second')); $child = $service->createItem($item($menu, $second, 'Child')); $grandchild = $service->createItem($item($menu, $child, 'Grandchild'));
    $service->moveItem($menu, $second, $first, [$second]);
    $assert($repository->findItem($second)?->parentId() === $first && $repository->findItem($child)?->parentId() === $second && $repository->findItem($grandchild)?->parentId() === $child, 'Cross-level move did not retain the complete subtree.');
    $service->moveItem($menu, $second, null, [$second, $first]); $assert($repository->findItem($second)?->parentId() === null, 'Move back to root did not persist.');
    $service->reorderSiblings($menu, null, [$second, $first]); $roots = $repository->siblingRows($menu, null); $assert(array_map(static fn(array $row): int => (int)$row['id'], $roots) === [$second, $first], 'Sibling reorder did not persist deterministic order.');
    $foreign = $service->createItem($item($other, null, 'Foreign')); $rejects(fn()=> $service->moveItem($menu, $first, $foreign, [$first]), 'Cross-menu parent was accepted.'); $rejects(fn()=> $service->moveItem($menu, $first, $first, [$first]), 'Self-parent move was accepted.'); $rejects(fn()=> $service->moveItem($menu, $second, $child, [$second]), 'Descendant-parent move was accepted.');
    echo "WU3 Navigation reorder passed ({$assertions} assertions)." . PHP_EOL;
} finally { $server->exec('DROP DATABASE IF EXISTS ' . $quoted); }
