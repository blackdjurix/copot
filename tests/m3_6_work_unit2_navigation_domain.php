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
foreach (['NavigationMenu.php', 'NavigationItem.php', 'NavigationRepository.php', 'NavigationService.php'] as $file) { require_once $basePath . '/modules/navigation/Services/' . $file; }

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void { $assertions++; if (!$condition) throw new RuntimeException($message); };
$rejects = static function (callable $operation, string $message) use ($assert): void { try { $operation(); $assert(false, $message); } catch (InvalidArgumentException) { $assert(true, $message); } };
$host = (string) Env::get('DB_HOST', '127.0.0.1'); $port = (int) Env::get('DB_PORT', '3306'); $username = (string) Env::get('DB_USERNAME', 'root'); $password = (string) Env::get('DB_PASSWORD', '');
$name = 'copot_m36_wu2_' . bin2hex(random_bytes(6)); $quoted = '`' . str_replace('`', '``', $name) . '`';
$server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
$server->exec('CREATE DATABASE ' . $quoted . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
try {
    (new InstallerSchemaRunner($basePath . '/database/schema.sql'))->install(['host'=>$host,'port'=>$port,'database'=>$name,'username'=>$username,'password'=>$password]);
    $_ENV['DB_DATABASE']=$name; putenv('DB_DATABASE='.$name); $database = new Database(new Config($basePath . '/config')); $db=$database->connection(); $repository=new NavigationRepository($database); $service=new NavigationService($database,$repository);
    $menu=$service->createMenu(['name'=>'Main Navigation','slug'=>'Main Navigation']); $assert($menu>0 && !$db->inTransaction(),'Menu create failed.');
    $service->updateMenu($menu,['name'=>'Primary Navigation','slug'=>'primary navigation']); $assert($repository->findMenu($menu)?->slug()==='primary-navigation','Menu update did not normalize slug.');
    $rejects(fn()=> $service->createMenu(['name'=>'Duplicate','slug'=>'primary-navigation']),'Duplicate menu slug was accepted.');
    $other=$service->createMenu(['name'=>'Other','slug'=>'other']);
    $provider=static fn(int $menuId, mixed $parent=null, string $label='Item', bool $visible=true): array => ['menu_id'=>$menuId,'parent_id'=>$parent,'label'=>$label,'target_kind'=>'content.page','target_reference'=>'home','custom_url'=>null,'is_visible'=>$visible];
    $root=$service->createItem($provider($menu,null,'Root')); $child=$service->createItem($provider($menu,$root,'Child')); $third=$service->createItem($provider($menu,$child,'Third')); $fourth=$service->createItem($provider($menu,$third,'Fourth')); $fifth=$service->createItem($provider($menu,$fourth,'Fifth')); $assert($repository->findItem($fifth)?->parentId()===$fourth,'Five-level hierarchy failed.');
    $rejects(fn()=> $service->createItem($provider($menu,$fifth,'Sixth')),'Sixth hierarchy level was accepted.');
    $rejects(fn()=> $service->createItem($provider($menu,999999,'Stale')),'Stale parent was accepted.'); $rejects(fn()=> $service->createItem($provider($menu,'not-an-id','Malformed')),'Malformed parent was accepted.');
    $foreign=$service->createItem($provider($other,null,'Foreign')); $rejects(fn()=> $service->createItem($provider($menu,$foreign,'Cross menu')),'Cross-menu parent was accepted.');
    $rejects(fn()=> $service->updateItem($root,$provider($menu,$root,'Root')),'Self-parent was accepted.'); $rejects(fn()=> $service->updateItem($root,$provider($menu,$child,'Root')),'Descendant parent was accepted.');
    $targetRoot=$service->createItem($provider($other,null,'Target Root')); $targetChild=$service->createItem($provider($other,$targetRoot,'Target Child')); $targetThird=$service->createItem($provider($other,$targetChild,'Target Third')); $targetFourth=$service->createItem($provider($other,$targetThird,'Target Fourth')); $moveRoot=$service->createItem($provider($other,null,'Move Root')); $moveChild=$service->createItem($provider($other,$moveRoot,'Move Child')); $rejects(fn()=> $service->updateItem($moveRoot,$provider($other,$targetFourth,'Move Root')),'Moved subtree exceeded max depth.');
    $hidden=$service->createItem($provider($other,null,'Hidden',false)); $assert($repository->findItem($hidden)?->isVisible()===false,'Hidden item was not persisted.'); $service->updateItem($hidden,$provider($other,null,'Visible',true)); $assert($repository->findItem($hidden)?->label()==='Visible' && $repository->findItem($hidden)?->isVisible()===true,'Item update did not persist normalized values.');
    $custom=$service->createItem(['menu_id'=>$other,'parent_id'=>null,'label'=>'Custom','target_kind'=>'custom','target_reference'=>null,'custom_url'=>' https://example.test/path ','is_visible'=>'1']); $assert($repository->findItem($custom)?->customUrl()==='https://example.test/path','Custom URL was not trimmed.');
    foreach(['/about','http://example.test','https://example.test','#section'] as $url){$service->createItem(['menu_id'=>$other,'parent_id'=>null,'label'=>'URL '.$url,'target_kind'=>'custom','target_reference'=>null,'custom_url'=>$url,'is_visible'=>true]);}
    foreach(['//example.test','about/team','?q=x','',' javascript:alert(1)','data:text/plain,x','file:///tmp/a','mailto:a@example.test','tel:1',"/bad\npath",'/bad\\path'] as $url){$rejects(fn()=> $service->createItem(['menu_id'=>$other,'parent_id'=>null,'label'=>'Bad','target_kind'=>'custom','target_reference'=>null,'custom_url'=>$url,'is_visible'=>true]),'Invalid custom URL was accepted: '.$url);}
    $rejects(fn()=> $service->createItem(['menu_id'=>$other,'parent_id'=>null,'label'=>'Bad','target_kind'=>'custom','target_reference'=>'reference','custom_url'=>'/x','is_visible'=>true]),'Custom target reference was accepted.'); $rejects(fn()=> $service->createItem(['menu_id'=>$other,'parent_id'=>null,'label'=>'Bad','target_kind'=>'custom','target_reference'=>null,'custom_url'=>'','is_visible'=>true]),'Empty custom URL was accepted.');
    $rejects(fn()=> $service->createItem(['menu_id'=>$other,'parent_id'=>null,'label'=>'Bad','target_kind'=>'CUSTOM','target_reference'=>null,'custom_url'=>'/x','is_visible'=>true]),'Invalid provider target kind was accepted.');
    $rejects(fn()=> $service->createItem(['menu_id'=>$other,'parent_id'=>null,'label'=>'Bad','target_kind'=>'content.page','target_reference'=>'','custom_url'=>null,'is_visible'=>true]),'Missing provider reference was accepted.');
    $rejects(fn()=> $service->createItem(['menu_id'=>$other,'parent_id'=>null,'label'=>'Bad','target_kind'=>'content.page','target_reference'=>'x','custom_url'=>'/x','is_visible'=>true]),'Provider custom URL was accepted.');
    $a=$service->createItem($provider($other,null,'A')); $aChild=$service->createItem($provider($other,$a,'A Child')); $b=$service->createItem($provider($other,null,'B')); $service->reorderSiblings($other,null,[$b,$hidden,$custom,$a,...array_map(fn($item)=>$item->id(),array_filter($service->itemsForMenu($other),fn($item)=>$item->parentId()===null && !in_array($item->id(),[$b,$hidden,$custom,$a],true)))]);
    $siblings=$repository->siblingRows($other,null); $orders=array_map(fn($row)=>(int)$row['sort_order'],$siblings); $assert($orders===range(0,count($orders)-1),'Reorder did not rewrite positions.'); $service->reorderSiblings($other,null,array_map(fn($row)=>(int)$row['id'],$siblings)); $assert(true,'Exact no-op reorder was accepted.');
    $rejects(fn()=> $service->reorderSiblings($other,null,[$a,$a]),'Duplicate reorder permutation was accepted.');
    $rejects(fn()=> $service->reorderSiblings($other,null,[$a]),'Missing reorder sibling was accepted.'); $rejects(fn()=> $service->reorderSiblings($other,null,[$aChild]),'Foreign-parent reorder ID was accepted.'); $rejects(fn()=> $service->reorderSiblings($other,null,[$foreign]),'Foreign-menu reorder ID was accepted.');
    $parent=$service->createItem($provider($other,null,'Delete Parent')); $desc=$service->createItem($provider($other,$parent,'Delete Child')); $outcome=$service->deleteItem($parent); $assert($outcome['deleted_items']===2 && $repository->findItem($desc)===null,'Parent deletion did not cascade descendants.');
    $db->exec("INSERT INTO themes (theme_id, name, version, type, path, is_active, metadata, created_at, updated_at) VALUES ('default','Default','1.0.0','site','themes/default',1,NULL,NOW(),NOW())"); $assigned=$service->createMenu(['name'=>'Assigned','slug'=>'assigned']); $db->exec("INSERT INTO navigation_menu_assignments (theme_id, location_key, menu_id, created_at, updated_at) VALUES ('default','primary',{$assigned},NOW(),NOW())"); $assignedItem=$service->createItem($provider($assigned,null,'Assigned Item')); $menuOutcome=$service->deleteMenu($assigned); $assert($menuOutcome['deleted_items']===1 && $menuOutcome['deleted_assignments']===1 && $repository->findItem($assignedItem)===null,'Menu deletion did not cascade items and assignments.');
    $db->beginTransaction(); $marker=$service->createMenu(['name'=>'Nested','slug'=>'nested']); $assert($db->inTransaction(),'Nested success closed caller transaction.'); $db->rollBack(); $assert($repository->findMenu($marker)===null,'Caller rollback did not remove nested work.');
    $db->beginTransaction(); $db->exec("INSERT INTO navigation_menus (name,slug,created_at,updated_at) VALUES ('Marker','marker',NOW(),NOW())"); $rejects(fn()=> $service->createItem($provider($other,999999,'Invalid nested')),'Nested invalid operation succeeded.'); $assert($db->inTransaction() && (int)$db->query("SELECT COUNT(*) FROM navigation_menus WHERE slug='marker'")->fetchColumn()===1,'Nested failure did not preserve caller work.'); $db->rollBack();
    $corrupt=$service->createMenu(['name'=>'Corrupt','slug'=>'corrupt']); $corruptA=$service->createItem($provider($corrupt,null,'Corrupt A')); $corruptB=$service->createItem($provider($corrupt,$corruptA,'Corrupt B')); $db->exec('SET FOREIGN_KEY_CHECKS = 0'); $db->exec("UPDATE navigation_items SET parent_id = {$corruptB} WHERE id = {$corruptA}"); $db->exec('SET FOREIGN_KEY_CHECKS = 1'); $rejects(fn()=> $service->createItem($provider($corrupt,$corruptA,'Blocked')),'Corrupted parent cycle was accepted.');
    $assert(!$db->inTransaction(),'Test left transaction open.'); echo "M3.6 Work Unit 2 Navigation domain passed ({$assertions} assertions)." . PHP_EOL;
} finally { $server->exec('DROP DATABASE IF EXISTS '.$quoted); }
