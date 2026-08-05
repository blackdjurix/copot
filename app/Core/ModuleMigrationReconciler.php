<?php

namespace Copot\Core;

use PDO;

final class ModuleMigrationReconciler
{
    public function __construct(private ModuleMigrationLedger $ledger) {}

    public function freshBaseline(ModuleIdentity $module, string $baselineIdentity, callable $establish): ModuleMigrationReconciliationResult
    {
        try { ($establish)($module); $this->ledger->initializeBaseline($module, $baselineIdentity); return new ModuleMigrationReconciliationResult(ModuleMigrationReconciliationResult::COMPLETED, [], '', $this->ledger->stateIdentity($module)); }
        catch (\Throwable $e) { return new ModuleMigrationReconciliationResult(ModuleMigrationReconciliationResult::FAILED, [], $e->getMessage()); }
    }

    public function reconcile(PDO $connection, ModuleIdentity $module, string $targetPackageVersion, ModuleMigrationRegistry $registry, string $expectedStateIdentity, ?ModuleDependencyConflictPlan $conflicts = null): ModuleMigrationReconciliationResult
    {
        if ($registry->owner()->value() !== $module->value()) return new ModuleMigrationReconciliationResult(ModuleMigrationReconciliationResult::FAILED, [], 'Module migration owner mismatch.');
        if ($conflicts !== null && !$conflicts->accepted()) return new ModuleMigrationReconciliationResult(ModuleMigrationReconciliationResult::FAILED, [], 'Dependency/conflict plan is not accepted.');
        try {
            $current = $this->ledger->read($module); if ($current['baseline_identity'] === null) throw new \RuntimeException('Module migration baseline is not established.');
            if ($expectedStateIdentity !== '' && $this->ledger->stateIdentity($module) !== $expectedStateIdentity) throw new \RuntimeException('Module migration state identity is inconsistent.');
            $records = $current['records']; $byId = []; foreach ($records as $r) $byId[$r->migrationId()] = $r;
            $applied = [];
            foreach ($registry->migrations() as $migration) {
                if (PackageVersion::compare($targetPackageVersion, $migration->targetPackageVersion()) < 0) continue;
                if (!$migration->appliesTo($migration->targetPackageVersion())) throw new \RuntimeException('Module migration source compatibility is invalid.');
                if (isset($byId[$migration->id()])) { $record = $byId[$migration->id()]; if ($record->checksum() !== $migration->checksum() || $record->executableIdentity() !== $migration->executableSource()) throw new \RuntimeException('Applied Module migration was modified.'); continue; }
                if (!$migration->checkPrecondition($connection)) return new ModuleMigrationReconciliationResult(ModuleMigrationReconciliationResult::FAILED, $applied, 'Module migration precondition failed.');
                try {
                    if ($migration->transactionMode() === ModuleMigrationDescriptor::TRANSACTIONAL) { if ($connection->inTransaction()) throw new \RuntimeException('Transactional Module migration cannot join an external transaction.'); $connection->beginTransaction(); try { $migration->execute($connection); if (!$migration->checkPostcondition($connection)) throw new \RuntimeException('Module migration postcondition failed.'); $this->ledger->record($migration, $module); $connection->commit(); } catch (\Throwable $e) { if ($connection->inTransaction()) $connection->rollBack(); throw $e; } }
                    else { $migration->execute($connection); if (!$migration->checkPostcondition($connection)) throw new \RuntimeException('Module migration postcondition failed.'); try { $this->ledger->record($migration, $module); } catch (\Throwable $e) { return new ModuleMigrationReconciliationResult(ModuleMigrationReconciliationResult::INDETERMINATE, $applied, $e->getMessage()); } }
                    $applied[] = $migration->id();
                } catch (\Throwable $e) { return new ModuleMigrationReconciliationResult(ModuleMigrationReconciliationResult::FAILED, $applied, $e->getMessage()); }
            }
            return new ModuleMigrationReconciliationResult($applied === [] ? ModuleMigrationReconciliationResult::NOOP : ModuleMigrationReconciliationResult::COMPLETED, $applied, '', $this->ledger->stateIdentity($module));
        } catch (\Throwable $e) { return new ModuleMigrationReconciliationResult(ModuleMigrationReconciliationResult::FAILED, [], $e->getMessage()); }
    }
}
