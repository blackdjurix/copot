<?php

namespace Copot\Core;

final class ModuleTargetIntegrityVerifier
{
    public function verify(ModulePackageInspection $inspection, LiveTreePathGuard $guard): HealthGateMatrix
    {
        $gates=[]; $root=$inspection->contract()->ownership()->rootPath();
        foreach($inspection->manifest()->inventory() as $entry){ if(!$entry instanceof PackageInventoryEntry) continue; try{$path=$guard->destination($entry->path());$guard->verifyDestination($entry->path(),true);$size=@filesize($path);$hash=@hash_file('sha256',$path);$gates[]=is_int($size)&&$size===$entry->byteSize()&&$hash===$entry->sha256()?HealthGateResult::pass('module-file:'.$entry->path()):HealthGateResult::fail('module-file:'.$entry->path(),'Module target file identity does not match inventory.');}catch(\Throwable $e){$gates[]=HealthGateResult::fail('module-file:'.$entry->path(),$e->getMessage());} }
        $manifest=$guard->destination($root.'/module.json'); if(!is_file($manifest)||is_link($manifest)) $gates[]=HealthGateResult::fail('module-manifest','Runtime module.json is unavailable.'); else { try{$data=json_decode((string)file_get_contents($manifest),true,16,JSON_THROW_ON_ERROR);$error=ModuleManifestValidator::validate($inspection->contract()->moduleIdentity()->value(),$data);$gates[]=$error===null?HealthGateResult::pass('module-manifest'):HealthGateResult::fail('module-manifest',$error);}catch(\Throwable $e){$gates[]=HealthGateResult::fail('module-manifest',$e->getMessage());} }
        return new HealthGateMatrix($gates);
    }
}
