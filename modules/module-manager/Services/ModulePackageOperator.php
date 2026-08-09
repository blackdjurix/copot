<?php

use Copot\Core\ModulePackageIntakeInspector;
use Copot\Core\ModulePackageLibrary;
use Copot\Core\ZipIntakeService;
use Copot\Core\ModuleLifecycleStateStore;
use Copot\Core\ModuleLifecycleStateInspector;
use Copot\Core\ModuleLifecycleTarget;
use Copot\Core\ModuleTransitionPlanner;
use Copot\Core\ModuleDependencyConflictPlanner;
use Copot\Core\CommittedLifecycleStateStore;
use Copot\Core\RuntimeCompatibilityContext;
use Copot\Core\ModuleApplyCoordinator;
use Copot\Core\ModuleLifecycleOperationStore;
use Copot\Core\ModuleTargetIntegrityVerifier;
use Copot\Core\InstallationMutex;
use Copot\Core\PackageOwnedFileApplier;
use Copot\Core\LiveTreePathGuard;
use Copot\Core\LiveFileActivationCapability;
use Copot\Core\PackageApplyTemporaryRoot;
use Copot\Core\ModuleMigrationLedger;
use Copot\Core\ModuleMigrationReconciler;
use Copot\Core\ModuleMigrationRegistry;
use Copot\Core\ModuleProvisioningReconciler;
use Copot\Core\ModulePermissionReconciler;
use Copot\Core\ModuleRepository;
use Copot\Core\ModuleDiscovery;

final class ModulePackageOperator
{
    private ModulePackageLibrary $library;
    public function __construct(private object $app) { $this->library = new ModulePackageLibrary($app->path('storage')); }

    public function registerUpload(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) throw new InvalidArgumentException('A local Module package ZIP is required.');
        if ((int) ($file['size'] ?? 0) < 1 || (int) $file['size'] > 64 * 1024 * 1024) throw new InvalidArgumentException('The Module package ZIP exceeds the upload limit.');
        if (strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION)) !== 'zip') throw new InvalidArgumentException('The Module package must be a ZIP archive.');
        $inspection = $this->inspect((string) $file['tmp_name']);
        try { return $this->library->register($inspection); } finally { $inspection->livePayload()->cleanup(); }
    }

    public function enrich(array $items): array
    {
        $states = null;
        try { $states = new \Copot\Core\ModuleLifecycleStateStore($this->app->path('storage')); } catch (\Throwable) { }
        $latest = [];
        foreach ($this->library->all() as $candidate) { $name = (string) ($candidate['technical_module_identity'] ?? ''); if ($name === '' || (isset($latest[$name]) && \Copot\Core\PackageVersion::compare((string) $candidate['package_version'], (string) $latest[$name]['package_version']) <= 0)) continue; $latest[$name] = $candidate; }
        foreach ($items as &$item) { $name = (string) ($item['name'] ?? ''); $candidate = $latest[$name] ?? null; $state = null; if ($states instanceof \Copot\Core\ModuleLifecycleStateStore) { try { $state = $states->read($name); if ($state instanceof \Copot\Core\ModuleLifecycleState) { $item['lifecycle_state'] = $state->enabled() ? 'installed_enabled' : 'installed_disabled'; $item['version'] = $state->packageVersion(); $item['lifecycle_managed'] = true; } } catch (\Throwable) { $item['lifecycle_managed'] = false; } } if (is_array($candidate)) { $item['available_package_version'] = $candidate['package_version']; $item['available_package_release'] = $candidate['release_identity']; $item['available_package_candidate'] = $candidate['candidate_key']; try { $plan = $this->planCandidate($candidate); $item['lifecycle_action'] = $plan['transition']->classification(); if (!$plan['transition']->accepted() || !$plan['conflicts']->accepted()) $item['lifecycle_blocker'] = $plan['transition']->reason() ?: 'Module dependency or conflict resolution is required.'; } catch (\Throwable) { $item['lifecycle_blocker'] = 'Module lifecycle planning is unavailable.'; } if (($item['lifecycle_state'] ?? 'not_installed') === 'not_installed' && ($item['discovery_state'] ?? '') === 'missing') $item['discovery_state'] = 'ready'; } } unset($item);
        foreach ($latest as $name => $candidate) if (!$this->has($items, $name)) { $item = ['name' => $name, 'title' => $candidate['title'], 'version' => '', 'lifecycle_state' => 'not_installed', 'discovery_state' => 'ready', 'available_package_version' => $candidate['package_version'], 'available_package_release' => $candidate['release_identity'], 'available_package_candidate' => $candidate['candidate_key'], 'available_actions' => [], 'denial_reasons' => []]; try { $plan = $this->planCandidate($candidate); $item['lifecycle_action'] = $plan['transition']->classification(); if (!$plan['transition']->accepted() || !$plan['conflicts']->accepted()) $item['lifecycle_blocker'] = $plan['transition']->reason() ?: 'Module dependency or conflict resolution is required.'; } catch (\Throwable) { $item['lifecycle_blocker'] = 'Module lifecycle planning is unavailable.'; } $items[] = $item; }
        usort($items, static fn (array $a, array $b): int => strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''))); return $items;
    }

    public function execute(string $candidateKey): string
    {
        $candidate = $this->library->find($candidateKey); if (!is_array($candidate)) throw new InvalidArgumentException('Module package candidate was not found.');
        $inspection = $this->inspect($this->library->archive($candidate));
        try {
            $storage = $this->app->path('storage'); $live = $this->app->path(); $states = new ModuleLifecycleStateStore($storage); $repo = new ModuleRepository($this->app->database()); $discovery = new ModuleDiscovery($this->app->path('modules'));
            $reader = static function (string $name) use ($repo, $discovery, $states): ?array { foreach ($discovery->discover() as $definition) if ($definition instanceof \Copot\Core\ModuleDefinition && $definition->name() === $name) { $row = $repo->findByName($name); $state = $states->read($name); return ['name' => $name, 'version' => $definition->version(), 'status' => (($row['status'] ?? null) === 'enabled' || ($row === null && $state?->enabled())) ? 'enabled' : 'disabled']; } return null; };
            $target = new ModuleLifecycleTarget($inspection->contract(), $inspection->livePayload()->archiveSha256()); $dbVersion = (string) $this->app->database()->connection()->query('SELECT VERSION()')->fetchColumn(); preg_match('/(\d+\.\d+(?:\.\d+)?)/', $dbVersion, $match); $runtime = new RuntimeCompatibilityContext(PHP_VERSION, ['mysql' => $match[1] ?? '0.0.0'], get_loaded_extensions()); $transition = (new ModuleTransitionPlanner(new CommittedLifecycleStateStore($storage), $runtime))->plan((new ModuleLifecycleStateInspector($states, $reader))->inspect($inspection->contract()->moduleIdentity()), $target); $conflicts = (new ModuleDependencyConflictPlanner($states, $reader, static fn (string $name): ?\Copot\Core\ModulePackageContract => null))->plan($target, $transition); if (!$transition->accepted() || !$conflicts->accepted()) throw new RuntimeException($transition->reason() ?: 'Module dependency or conflict resolution is required.');
            $tables = $this->app->database()->tables(); $ledger = new ModuleMigrationLedger($storage, $tables); $migrations = new ModuleMigrationReconciler($ledger, $tables); $permissions = new ModulePermissionReconciler(fn ($module): array => $repo->permissionsFor($module->value()), function ($module, $permission) use ($repo): void { $repo->upsertPermissionMetadata($module->value(), $permission->slug(), $permission->name()); });
            $coordinator = new ModuleApplyCoordinator(new InstallationMutex($storage), new ModuleLifecycleOperationStore($storage), new PackageOwnedFileApplier(new LiveTreePathGuard($live), LiveFileActivationCapability::current(), PackageApplyTemporaryRoot::forProject($live)), $states, new ModuleTargetIntegrityVerifier(), $live);
            $result = $coordinator->execute($inspection, $transition, $conflicts, $this->app->database()->connection(), function ($connection, $transition, $inspection) use ($migrations) { $module = $inspection->contract()->moduleIdentity(); if ($transition->classification() === \Copot\Core\ModuleTransitionPlan::INSTALL) return $migrations->freshBaseline($module, 'canonical-current', static function (): void {}); return $migrations->reconcile($connection, $module, $inspection->contract()->packageVersion(), new ModuleMigrationRegistry($module, $inspection->contract()->migrationDeclaration()), (string) ($transition->currentState()?->migrationStateIdentity() ?? ''), null); }, fn ($transition, $inspection) => (new ModuleProvisioningReconciler(null, null, $this->app->database()->tables()))->reconcile($inspection->contract()->moduleIdentity(), $inspection->contract()->provisioningDeclaration()), fn ($transition, $inspection) => $permissions->reconcile($inspection->contract()->moduleIdentity(), $inspection->contract()->provisioningDeclaration()));
            if ($result->status() !== \Copot\Core\ModuleApplyResult::COMPLETED) throw new RuntimeException($result->reason() ?: 'Module lifecycle operation did not complete.'); return $transition->classification();
        } finally { $inspection->livePayload()->cleanup(); }
    }

    private function planCandidate(array $candidate): array
    {
        $inspection = $this->inspect($this->library->archive($candidate));
        try {
            $storage = $this->app->path('storage'); $states = new ModuleLifecycleStateStore($storage); $repo = new ModuleRepository($this->app->database()); $discovery = new ModuleDiscovery($this->app->path('modules'));
            $reader = static function (string $name) use ($repo, $discovery, $states): ?array { foreach ($discovery->discover() as $definition) if ($definition instanceof \Copot\Core\ModuleDefinition && $definition->name() === $name) { $row = $repo->findByName($name); $state = $states->read($name); return ['name' => $name, 'version' => $definition->version(), 'status' => (($row['status'] ?? null) === 'enabled' || ($row === null && $state?->enabled())) ? 'enabled' : 'disabled']; } return null; };
            $target = new ModuleLifecycleTarget($inspection->contract(), $inspection->livePayload()->archiveSha256()); $dbVersion = (string) $this->app->database()->connection()->query('SELECT VERSION()')->fetchColumn(); preg_match('/(\d+\.\d+(?:\.\d+)?)/', $dbVersion, $match); $runtime = new RuntimeCompatibilityContext(PHP_VERSION, ['mysql' => $match[1] ?? '0.0.0'], get_loaded_extensions()); $transition = (new ModuleTransitionPlanner(new CommittedLifecycleStateStore($storage), $runtime))->plan((new ModuleLifecycleStateInspector($states, $reader))->inspect($inspection->contract()->moduleIdentity()), $target); $conflicts = (new ModuleDependencyConflictPlanner($states, $reader, static fn (string $name): ?\Copot\Core\ModulePackageContract => null))->plan($target, $transition); return ['transition' => $transition, 'conflicts' => $conflicts];
        } finally { $inspection->livePayload()->cleanup(); }
    }

    private function inspect(string $path): \Copot\Core\ModulePackageInspection { return (new ModulePackageIntakeInspector(new ZipIntakeService($this->app->path(), sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-module-package-staging')))->inspect($path); }
    private function has(array $items, string $name): bool { foreach ($items as $item) if (($item['name'] ?? null) === $name) return true; return false; }
}
