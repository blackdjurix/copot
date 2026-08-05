<?php

declare(strict_types=1);

use Copot\Core\InstallationMutex;
use Copot\Core\LiveFileActivationCapability;
use Copot\Core\LiveTreePathGuard;
use Copot\Core\ModuleApplyCoordinator;
use Copot\Core\ModuleApplyResult;
use Copot\Core\ModuleDependencyConflictPlan;
use Copot\Core\ModuleDependencyConflictStatus;
use Copot\Core\ModuleIdentity;
use Copot\Core\ModuleLifecycleOperationRecord;
use Copot\Core\ModuleLifecycleOperationStore;
use Copot\Core\ModuleLifecycleState;
use Copot\Core\ModuleLifecycleStateStore;
use Copot\Core\ModuleLifecycleTarget;
use Copot\Core\ModuleMigrationReconciliationResult;
use Copot\Core\ModulePackageContract;
use Copot\Core\ModulePackageIntakeInspector;
use Copot\Core\ModulePackageManifestReader;
use Copot\Core\ModulePackageOwnership;
use Copot\Core\ModulePackageTarget;
use Copot\Core\ModulePermissionReconciliationResult;
use Copot\Core\ModuleProvisioningReconciliationResult;
use Copot\Core\ModuleTransitionPlan;
use Copot\Core\ModuleTargetIntegrityVerifier;
use Copot\Core\PackageCompatibility;
use Copot\Core\PackageIdentity;
use Copot\Core\PackageOwnedFileApplier;
use Copot\Core\PackageRuntimeCompatibility;
use Copot\Core\PackageVersion;
use Copot\Core\StagedPayload;
use Copot\Core\ZipIntakeService;

$base = dirname(__DIR__); chdir($base); require $base . '/bootstrap/autoload.php';
$assertions = 0; $assert = static function(bool $ok,string $message) use (&$assertions):void{$assertions++;if(!$ok)throw new RuntimeException($message);};
$root=sys_get_temp_dir().DIRECTORY_SEPARATOR.'copot-module-wu6-'.bin2hex(random_bytes(6));$live=$root.DIRECTORY_SEPARATOR.'live';$stage=$root.DIRECTORY_SEPARATOR.'stage';$storage=$root.DIRECTORY_SEPARATOR.'storage';mkdir($live,0700,true);mkdir($storage,0700,true);
$remove=static function(string $path)use(&$remove):void{if(is_file($path)||is_link($path)){@unlink($path);return;}if(!is_dir($path))return;foreach(scandir($path)?:[]as $entry)if($entry!=='.'&&$entry!=='..')$remove($path.DIRECTORY_SEPARATOR.$entry);@rmdir($path);};
$archive=static function(array $files,array $manifest)use($root):string{$path=$root.DIRECTORY_SEPARATOR.'package-'.bin2hex(random_bytes(4)).'.zip';$zip=new ZipArchive();if($zip->open($path,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)throw new RuntimeException('Fixture archive failed.');foreach($files as $name=>$contents)$zip->addFromString($name,$contents);$zip->addFromString('.copot/package.json',json_encode($manifest,JSON_THROW_ON_ERROR));$zip->close();return $path;};
$module=new ModuleIdentity('content');$moduleJson=json_encode(['name'=>'content','title'=>'Content','version'=>'1.0.0'],JSON_THROW_ON_ERROR);$files=['modules/content/module.json'=>$moduleJson,'modules/content/routes.php'=>'<?php return [];'];
$contract=new ModulePackageContract(ModulePackageContract::MODULE_PACKAGE_TYPE,1,new PackageIdentity('copot-content-package'),$module,'Content Module Package','1.0.0','content-release-1',new PackageCompatibility('0.12.0'),new PackageRuntimeCompatibility('8.0.0',['sqlite'=>'3.0'],['json']),new ModulePackageOwnership($module,'modules/content'),[],[],new \Copot\Core\ModuleMigrationDeclaration($module),new \Copot\Core\ModuleProvisioningDeclaration());$manifest=$contract->toArray();$manifest['inventory']=[];foreach($files as $path=>$contents)$manifest['inventory'][]=['path'=>$path,'byte_size'=>strlen($contents),'sha256'=>hash('sha256',$contents),'ownership'=>'package_owned'];
$inspection=(new ModulePackageIntakeInspector(new ZipIntakeService($live,$stage)))->inspect($archive($files,$manifest));$target=new ModuleLifecycleTarget($inspection->contract(),hash('sha256','package-identity'));$transition=ModuleTransitionPlan::allow(ModuleTransitionPlan::INSTALL,$target,null,false);$conflicts=new ModuleDependencyConflictPlan(ModuleDependencyConflictStatus::SATISFIED,true,$target);
$operations=new ModuleLifecycleOperationStore($storage);$states=new ModuleLifecycleStateStore($storage);$coordinator=new ModuleApplyCoordinator(new InstallationMutex($storage),$operations,new PackageOwnedFileApplier(new LiveTreePathGuard($live),new LiveFileActivationCapability(true,true),$root.DIRECTORY_SEPARATOR.'apply-temp'),$states,new ModuleTargetIntegrityVerifier(),$live);$pdo=new PDO('sqlite::memory:',options:[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$migrationState=hash('sha256','fresh');$migrationCalls=0;$apply=$coordinator->execute($inspection,$transition,$conflicts,$pdo,static function()use(&$migrationCalls,$migrationState):ModuleMigrationReconciliationResult{$migrationCalls++;return new ModuleMigrationReconciliationResult(ModuleMigrationReconciliationResult::COMPLETED,[],'',$migrationState);},static fn():ModuleProvisioningReconciliationResult=>new ModuleProvisioningReconciliationResult(ModuleProvisioningReconciliationResult::COMPLETED),static fn():ModulePermissionReconciliationResult=>new ModulePermissionReconciliationResult(ModulePermissionReconciliationResult::COMPLETED),static fn():bool=>true);
$assert($apply->status()===ModuleApplyResult::COMPLETED&&$migrationCalls===1,'Valid Module INSTALL did not complete.');$state=$states->read($module);$assert($state instanceof ModuleLifecycleState&&!$state->enabled()&&$state->packageIdentity()->equals('copot-content-package')&&$state->migrationStateIdentity()===$migrationState,'INSTALL committed incorrect disabled Module state.');$assert(is_file($live.DIRECTORY_SEPARATOR.'modules'.DIRECTORY_SEPARATOR.'content'.DIRECTORY_SEPARATOR.'routes.php'),'Owned Module file was not applied.');$assert(!is_file($live.DIRECTORY_SEPARATOR.'.copot'.DIRECTORY_SEPARATOR.'package.json'),'Package metadata entered the live payload.');
$enabledState=new ModuleLifecycleState($state->packageIdentity(),$module,'1.0.0','content-release-1',1,$migrationState,hash('sha256','old-integrity'),true,'repair',new DateTimeImmutable('now'));$states->write($enabledState);$repair=ModuleTransitionPlan::allow(ModuleTransitionPlan::REPAIR,$target,$enabledState,true);$repaired=$coordinator->execute($inspection,$repair,$conflicts,$pdo,static fn():ModuleMigrationReconciliationResult=>new ModuleMigrationReconciliationResult(ModuleMigrationReconciliationResult::NOOP,[],'',$migrationState),static fn():ModuleProvisioningReconciliationResult=>new ModuleProvisioningReconciliationResult(ModuleProvisioningReconciliationResult::COMPLETED),static fn():ModulePermissionReconciliationResult=>new ModulePermissionReconciliationResult(ModulePermissionReconciliationResult::COMPLETED));$assert($repaired->status()===ModuleApplyResult::COMPLETED&&$states->read($module)?->enabled(),'REPAIR did not preserve enabled state.');
$committed=$states->read($module);$committedIdentity=hash('sha256',json_encode($committed->toArray(),JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));$pending=new ModuleLifecycleOperationRecord('cleanup-test',$module,ModuleTransitionPlan::REPAIR,'copot-content-package','1.0.0','content-release-1',str_repeat('a',64),$inspection->livePayload()->stagingPath(),str_repeat('b',64),ModuleLifecycleOperationRecord::CLEANUP_PENDING,2,'modules/content/routes.php',$migrationState,$committedIdentity,gmdate(DATE_ATOM),gmdate(DATE_ATOM),'cleanup');$operations->create($pending);$retry=$coordinator->execute($inspection,$repair,$conflicts,$pdo,static function()use(&$migrationCalls):ModuleMigrationReconciliationResult{$migrationCalls++;return new ModuleMigrationReconciliationResult(ModuleMigrationReconciliationResult::FAILED,[],'must not rerun');},static fn():ModuleProvisioningReconciliationResult=>new ModuleProvisioningReconciliationResult(ModuleProvisioningReconciliationResult::FAILED,'must not rerun'),static fn():ModulePermissionReconciliationResult=>new ModulePermissionReconciliationResult(ModulePermissionReconciliationResult::FAILED,'must not rerun'));$assert($retry->status()===ModuleApplyResult::COMPLETED&&$migrationCalls===1,'Cleanup retry reran migration or failed to complete.');$assert($operations->read()===null,'Cleanup operation was not cleared.');
$inspection->livePayload()->cleanup();$remove($root);echo "WU6 Module apply/finalization focused tests passed ({$assertions} assertions).".PHP_EOL;
