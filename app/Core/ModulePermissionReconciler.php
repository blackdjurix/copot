<?php

namespace Copot\Core;

final class ModulePermissionReconciler
{
    public function __construct(private $reader, private $upsert, private $ownerReader = null)
    {
        if (!is_callable($reader) || !is_callable($upsert) || ($ownerReader !== null && !is_callable($ownerReader))) throw new \InvalidArgumentException('Module permission reconciliation adapters are invalid.');
    }

    public function reconcile(ModuleIdentity $module, ModuleProvisioningDeclaration $declaration): ModulePermissionReconciliationResult
    {
        try {
            $existing = ($this->reader)($module); if (!is_array($existing)) throw new \RuntimeException('Module permission metadata is invalid.');
            $bySlug = []; foreach ($existing as $row) { if (isset($row['permission_slug'])) $bySlug[(string) $row['permission_slug']] = $row; }
            $added = []; $changed = []; $preserved = array_keys($bySlug);
            foreach ($declaration->permissions() as $permission) {
                if ($this->ownerReader !== null) { $owner = ($this->ownerReader)($permission->slug()); if ($owner !== null && $owner !== $module->value()) throw new \RuntimeException('Module permission ownership conflict.'); }
                $old = $bySlug[$permission->slug()] ?? null;
                ($this->upsert)($module, $permission);
                if ($old === null) $added[] = $permission->slug(); else { $changed[] = $permission->slug(); $preserved = array_values(array_diff($preserved, [$permission->slug()])); }
            }
            return new ModulePermissionReconciliationResult(ModulePermissionReconciliationResult::COMPLETED, '', $added, $changed, $preserved);
        } catch (\Throwable $e) { return new ModulePermissionReconciliationResult(ModulePermissionReconciliationResult::FAILED, $e->getMessage()); }
    }
}
