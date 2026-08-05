<?php

namespace Copot\Core;

final class ModuleProvisioningReconciler
{
    public function __construct(private $schemaExecutor = null, private $schemaVerifier = null)
    {
        if ($schemaExecutor !== null && !is_callable($schemaExecutor)) throw new \InvalidArgumentException('Module schema executor is invalid.');
        if ($schemaVerifier !== null && !is_callable($schemaVerifier)) throw new \InvalidArgumentException('Module schema verifier is invalid.');
    }

    public function establishBaseline(ModuleIdentity $module, ModuleProvisioningDeclaration $declaration): ModuleProvisioningReconciliationResult
    {
        return $this->run($module, $declaration, true);
    }

    public function reconcile(ModuleIdentity $module, ModuleProvisioningDeclaration $declaration): ModuleProvisioningReconciliationResult
    {
        return $this->run($module, $declaration, false);
    }

    private function run(ModuleIdentity $module, ModuleProvisioningDeclaration $declaration, bool $baseline): ModuleProvisioningReconciliationResult
    {
        try {
            if ($this->schemaExecutor !== null) ($this->schemaExecutor)($module, $declaration, $baseline);
            if ($this->schemaVerifier !== null && !($this->schemaVerifier)($module, $declaration)) throw new \RuntimeException('Module provisioning postcondition failed.');
            return new ModuleProvisioningReconciliationResult(ModuleProvisioningReconciliationResult::COMPLETED, '', ['schema_identity' => $declaration->schemaIdentity(), 'baseline' => $baseline]);
        } catch (\Throwable $e) { return new ModuleProvisioningReconciliationResult(ModuleProvisioningReconciliationResult::FAILED, $e->getMessage()); }
    }
}
