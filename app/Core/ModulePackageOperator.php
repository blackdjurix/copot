<?php

namespace Copot\Core;

class ModulePackageOperator
{
    private ModulePackageLibrary $library;

    public function __construct(private object $app)
    {
        $this->library = new ModulePackageLibrary($app->path('storage'));
    }

    public function registerUpload(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) throw new \InvalidArgumentException('A local Module package ZIP is required.');
        if ((int) ($file['size'] ?? 0) < 1 || (int) $file['size'] > 64 * 1024 * 1024) throw new \InvalidArgumentException('The Module package ZIP exceeds the upload limit.');
        if (strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION)) !== 'zip') throw new \InvalidArgumentException('The Module package must be a ZIP archive.');
        $inspection = $this->inspect((string) $file['tmp_name']);
        try { return $this->library->register($inspection); } finally { $inspection->livePayload()->cleanup(); }
    }

    public function enrich(array $items): array
    {
        $states = null;
        try { $states = new ModuleLifecycleStateStore($this->app->path('storage')); } catch (\Throwable) { }
        $latest = [];
        foreach ($this->library->all() as $candidate) { $name = (string) ($candidate['technical_module_identity'] ?? ''); if ($name === '' || (isset($latest[$name]) && PackageVersion::compare((string) $candidate['package_version'], (string) $latest[$name]['package_version']) <= 0)) continue; $latest[$name] = $candidate; }
        foreach ($items as &$item) { $name = (string) ($item['name'] ?? ''); $candidate = $latest[$name] ?? null; $state = null; if ($states instanceof ModuleLifecycleStateStore) { try { $state = $states->read($name); if ($state instanceof ModuleLifecycleState) { $item['lifecycle_state'] = $state->enabled() ? 'installed_enabled' : 'installed_disabled'; $item['version'] = $state->packageVersion(); $item['lifecycle_managed'] = true; } } catch (\Throwable) { $item['lifecycle_managed'] = false; } } if (is_array($candidate)) { $item['available_package_version'] = $candidate['package_version']; $item['available_package_release'] = $candidate['release_identity']; $item['available_package_candidate'] = $candidate['candidate_key']; $item['available_package_dependencies'] = $this->packageTargets($candidate['contract']['dependencies'] ?? []); $item['available_package_conflicts'] = $this->packageTargets($candidate['contract']['conflicts'] ?? []); try { $plan = $this->planCandidate($candidate); $item['lifecycle_action'] = $plan['transition']->classification(); if (!$plan['transition']->accepted() || !$plan['conflicts']->accepted()) $item['lifecycle_blocker'] = $plan['transition']->reason() ?: 'Module dependency or conflict resolution is required.'; } catch (\Throwable) { $item['lifecycle_blocker'] = 'Module lifecycle planning is unavailable.'; } if (($item['lifecycle_state'] ?? 'not_installed') === 'not_installed' && ($item['discovery_state'] ?? '') === 'missing') $item['discovery_state'] = 'ready'; } } unset($item);
        foreach ($latest as $name => $candidate) if (!$this->has($items, $name)) { $item = ['name' => $name, 'title' => $candidate['title'], 'version' => '', 'lifecycle_state' => 'not_installed', 'discovery_state' => 'ready', 'available_package_version' => $candidate['package_version'], 'available_package_release' => $candidate['release_identity'], 'available_package_candidate' => $candidate['candidate_key'], 'available_package_dependencies' => $this->packageTargets($candidate['contract']['dependencies'] ?? []), 'available_package_conflicts' => $this->packageTargets($candidate['contract']['conflicts'] ?? []), 'available_actions' => [], 'denial_reasons' => []]; try { $plan = $this->planCandidate($candidate); $item['lifecycle_action'] = $plan['transition']->classification(); if (!$plan['transition']->accepted() || !$plan['conflicts']->accepted()) $item['lifecycle_blocker'] = $plan['transition']->reason() ?: 'Module dependency or conflict resolution is required.'; } catch (\Throwable) { $item['lifecycle_blocker'] = 'Module lifecycle planning is unavailable.'; } $items[] = $item; }
        usort($items, static fn (array $a, array $b): int => strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''))); return $items;
    }

    public function preflightUpload(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) throw new \InvalidArgumentException('A local Module package ZIP is required.');
        $inspection = $this->inspect((string) $file['tmp_name']);
        try { [$transition, $conflicts] = $this->planInspection($inspection); $accepted = $transition->accepted() && $conflicts->accepted(); return ['accepted' => $accepted, 'status' => $accepted ? 'ready' : 'blocked', 'reason' => $accepted ? '' : ($transition->reason() ?: 'Module dependency or conflict resolution is required.'), 'classification' => $transition->classification(), 'module' => $inspection->contract()->moduleIdentity()->value(), 'title' => $inspection->contract()->title(), 'next_action' => $accepted ? 'Apply Module lifecycle' : 'Review the blocking evidence']; }
        finally { $inspection->livePayload()->cleanup(); }
    }

    public function executeUpload(array $file): string
    {
        return (string) ($this->executeUploadResult($file)['classification'] ?? '');
    }

    public function executeUploadResult(array $file): array
    {
        $candidate = $this->registerUpload($file);
        $classification = $this->execute((string) ($candidate['candidate_key'] ?? ''));
        return [
            'accepted' => true,
            'status' => 'completed',
            'reason' => '',
            'classification' => $classification,
            'module' => (string) ($candidate['technical_module_identity'] ?? ''),
            'title' => (string) ($candidate['title'] ?? ''),
            'next_action' => 'Review Module state',
            'guidance' => 'Module lifecycle completed. Review the canonical Modules area for the resulting state and next eligible action.',
        ];
    }

    public function execute(string $candidateKey): string
    {
        $candidate = $this->library->find($candidateKey); if (!is_array($candidate)) throw new \InvalidArgumentException('Module package candidate was not found.');
        $inspection = $this->inspect($this->library->archive($candidate));
        try {
            [$transition, $conflicts, $states, $repo] = $this->planInspection($inspection);
            if (!$transition->accepted() || !$conflicts->accepted()) throw new \RuntimeException($transition->reason() ?: 'Module dependency or conflict resolution is required.');
            $storage = $this->app->path('storage'); $live = $this->app->path(); $tables = $this->app->database()->tables();
            $ledger = new ModuleMigrationLedger($storage, $tables); $migrations = new ModuleMigrationReconciler($ledger, $tables); $permissions = new ModulePermissionReconciler(fn ($module): array => $repo->permissionsFor($module->value()), function ($module, $permission) use ($repo): void { $repo->upsertPermissionMetadata($module->value(), $permission->slug(), $permission->name()); });
            $coordinator = new ModuleApplyCoordinator(new InstallationMutex($storage), new ModuleLifecycleOperationStore($storage), new PackageOwnedFileApplier(new LiveTreePathGuard($live), LiveFileActivationCapability::current(), PackageApplyTemporaryRoot::forProject($live, $this->app->installationIdentity()->value())), $states, new ModuleTargetIntegrityVerifier(), $live, $this->app->runtimeRegistry());
            $result = $coordinator->execute($inspection, $transition, $conflicts, $this->app->database()->connection(), function ($connection, $transition, $inspection, $operationId) use ($migrations) { $module = $inspection->contract()->moduleIdentity(); if ($transition->classification() === ModuleTransitionPlan::INSTALL) return $migrations->freshBaseline($module, 'canonical-current', static function (): void {}); $registry = new ModuleMigrationRegistry($module, $inspection->contract()->migrationDeclaration()); $catalog = DatabaseTableOwnershipCatalog::current(); return $migrations->reconcile($connection, $module, $inspection->contract()->packageVersion(), $registry, (string) ($transition->currentState()?->migrationStateIdentity() ?? ''), null, function (ModuleMigrationDescriptor $migration) use ($catalog, $module, $connection, $operationId, $inspection, $transition): AuthorizedMigrationContext { $authorization = new MigrationAuthorizationContext($this->app->installationIdentity(), $this->app->database()->tables(), $operationId, $transition->classification(), DatabaseTableOwner::module($module), $migration->id(), $migration->checksum(), (string) ($transition->currentState()?->packageVersion() ?? $inspection->contract()->packageVersion()), $migration->targetPackageVersion(), true, $migration->schemaSurface(), $catalog->extensions()); return new AuthorizedMigrationContext($connection, $authorization, $catalog); }, $transition->currentState()?->packageVersion()); }, fn ($transition, $inspection) => (new ModuleProvisioningReconciler(null, null, $this->app->database()->tables()))->reconcile($inspection->contract()->moduleIdentity(), $inspection->contract()->provisioningDeclaration()), fn ($transition, $inspection) => $permissions->reconcile($inspection->contract()->moduleIdentity(), $inspection->contract()->provisioningDeclaration()));
            if ($result->status() !== ModuleApplyResult::COMPLETED) throw new \RuntimeException($result->reason() ?: 'Module lifecycle operation did not complete.'); return $transition->classification();
        } finally { $inspection->livePayload()->cleanup(); }
    }

    private function planCandidate(array $candidate): array
    {
        $inspection = $this->inspect($this->library->archive($candidate));
        try { [$transition, $conflicts] = $this->planInspection($inspection); return ['transition' => $transition, 'conflicts' => $conflicts]; } finally { $inspection->livePayload()->cleanup(); }
    }

    private function planInspection(ModulePackageInspection $inspection): array
    {
        $storage = $this->app->path('storage'); $states = new ModuleLifecycleStateStore($storage); $repo = new ModuleRepository($this->app->database()); $discovery = new ModuleDiscovery($this->app->path('modules'));
        $reader = static function (string $name) use ($repo, $discovery, $states): ?array { foreach ($discovery->discover() as $definition) if ($definition instanceof ModuleDefinition && $definition->name() === $name) { $row = $repo->findByName($name); $state = $states->read($name); return ['name' => $name, 'version' => $definition->version(), 'status' => (($row['status'] ?? null) === 'enabled' || ($row === null && $state?->enabled())) ? 'enabled' : 'disabled']; } return null; };
        $target = new ModuleLifecycleTarget($inspection->contract(), $inspection->livePayload()->archiveSha256()); $dbVersion = (string) $this->app->database()->connection()->query('SELECT VERSION()')->fetchColumn(); preg_match('/(\d+\.\d+(?:\.\d+)?)/', $dbVersion, $match); $runtime = new RuntimeCompatibilityContext(PHP_VERSION, ['mysql' => $match[1] ?? '0.0.0'], get_loaded_extensions()); $transition = (new ModuleTransitionPlanner(new CommittedLifecycleStateStore($storage), $runtime))->plan((new ModuleLifecycleStateInspector($states, $reader))->inspect($inspection->contract()->moduleIdentity()), $target); $conflicts = (new ModuleDependencyConflictPlanner($states, $reader, static fn (string $name): ?ModulePackageContract => null))->plan($target, $transition); return [$transition, $conflicts, $states, $repo];
    }

    private function inspect(string $path): ModulePackageInspection
    {
        return (new ModulePackageIntakeInspector(new ZipIntakeService($this->app->path(), null, null, null, null, $this->app->installationIdentity()->value())))->inspect($path);
    }

    private function has(array $items, string $name): bool
    {
        foreach ($items as $item) if (($item['name'] ?? null) === $name) return true;
        return false;
    }

    private function packageTargets(mixed $declarations): array
    {
        if (!is_array($declarations)) return [];
        $targets = [];
        foreach ($declarations as $declaration) {
            if (!is_array($declaration) || !is_array($declaration['target'] ?? null)) continue;
            $target = $declaration['target'];
            $identity = (string) ($target['target_identity'] ?? '');
            if ($identity !== '') $targets[] = $identity;
        }
        return array_values(array_unique($targets));
    }
}
