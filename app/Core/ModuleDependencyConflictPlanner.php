<?php

namespace Copot\Core;

final class ModuleDependencyConflictPlanner
{
    private array $states;
    private array $prerequisites = [];
    private array $findings = [];
    private array $visiting = [];

    /** @param callable(string):?array $runtimeReader @param callable(string):?ModulePackageContract $contractReader @param callable(ModulePackageContract):list<array>|null $ownershipEvidence */
    public function __construct(private ModuleLifecycleStateStore $stateStore, private $runtimeReader, private $contractReader, private $ownershipEvidence = null)
    {
    }

    /** @param list<ModuleLifecycleTarget> $availableTargets */
    public function plan(ModuleLifecycleTarget $target, ModuleTransitionPlan $transition, array $availableTargets = []): ModuleDependencyConflictPlan
    {
        $this->states = [];
        foreach ($this->stateStore->all() as $state) $this->states[$state->moduleIdentity()->value()] = $state;
        $this->prerequisites = [];
        $this->findings = [];
        $this->visiting = [];
        $this->inspectIdentityConflicts($target->contract());
        if (!$transition->accepted()) {
            if ($this->findings === []) $this->add(ModuleDependencyConflictStatus::UNRESOLVABLE, $target->contract()->moduleIdentity()->value(), 'Module transition is not accepted by WU3.', [], true);
            return new ModuleDependencyConflictPlan(ModuleDependencyConflictStatus::UNRESOLVABLE, false, $target, $this->findings, [], $this->limitations());
        }
        $this->available = [];
        foreach ($availableTargets as $candidate) if ($candidate instanceof ModuleLifecycleTarget) $this->available[$candidate->contract()->moduleIdentity()->value()] = $candidate;
        $this->inspectContract($target->contract(), $target->contract()->moduleIdentity()->value());
        $status = $this->overallStatus();
        return new ModuleDependencyConflictPlan($status, $status === ModuleDependencyConflictStatus::SATISFIED, $target, $this->findings, array_values($this->prerequisites), $this->limitations());
    }

    private array $available = [];

    private function inspectContract(ModulePackageContract $contract, string $owner): void
    {
        $node = 'module:' . $owner;
        if (isset($this->visiting[$node])) { $this->add(ModuleDependencyConflictStatus::CYCLIC_DEPENDENCY, $owner, 'Dependency cycle detected.', [], true); return; }
        $this->visiting[$node] = true;
        $dependencies = $contract->dependencies();
        usort($dependencies, static fn (ModulePackageDependencyDeclaration $left, ModulePackageDependencyDeclaration $right): int => strcmp($left->target()->kind() . ':' . $left->target()->identity(), $right->target()->kind() . ':' . $right->target()->identity()));
        foreach ($dependencies as $dependency) $this->inspectDependency($dependency, $owner);
        $conflicts = $contract->conflicts();
        usort($conflicts, static fn (ModulePackageConflictDeclaration $left, ModulePackageConflictDeclaration $right): int => strcmp($left->target()->kind() . ':' . $left->target()->identity(), $right->target()->kind() . ':' . $right->target()->identity()));
        foreach ($conflicts as $conflict) $this->inspectConflict($conflict, $owner);
        if ($this->ownershipEvidence !== null) $this->inspectOwnershipEvidence(($this->ownershipEvidence)($contract), $owner);
        unset($this->visiting[$node]);
    }

    private function inspectIdentityConflicts(ModulePackageContract $contract): void
    {
        $module = $contract->moduleIdentity()->value();
        if (isset($this->states[$module]) && !$this->states[$module]->packageIdentity()->equals($contract->packageIdentity()->value())) {
            $this->add(ModuleDependencyConflictStatus::IDENTITY_CONFLICT, 'module:' . $module, 'Technical Module identity is already bound to another package identity.', [], true);
        }
        foreach ($this->states as $state) {
            if ($state->moduleIdentity()->value() !== $module && $state->packageIdentity()->equals($contract->packageIdentity()->value())) {
                $this->add(ModuleDependencyConflictStatus::IDENTITY_CONFLICT, 'package:' . $contract->packageIdentity()->value(), 'Package identity is already bound to another technical Module.', [], true);
            }
        }
    }

    private function inspectDependency(ModulePackageDependencyDeclaration $dependency, string $owner): void
    {
        $target = $dependency->target();
        $key = $target->kind() . ':' . $target->identity();
        if (($target->kind() === ModulePackageTarget::MODULE && $target->identity() === $owner) || ($target->kind() === ModulePackageTarget::PACKAGE && $target->identity() === $this->currentPackageIdentity($owner))) {
            $this->add(ModuleDependencyConflictStatus::CYCLIC_DEPENDENCY, $key, 'Self-dependency is unsupported.', [], true); return;
        }
        $state = $this->resolveState($target);
        if ($state === null) {
            $candidate = $target->kind() === ModulePackageTarget::MODULE ? ($this->available[$target->identity()] ?? null) : $this->candidatePackage($target->identity());
            if ($candidate instanceof ModuleLifecycleTarget && $dependency->versionConstraint()->supports($candidate->contract()->packageVersion())) {
                $this->prerequisites[$candidate->contract()->moduleIdentity()->value()] = $candidate;
                $this->add(ModuleDependencyConflictStatus::RESOLUTION_REQUIRED, $key, 'Dependency is missing and requires an explicit prerequisite installation.', [$candidate->contract()->moduleIdentity()->value()], true);
            } else $this->add(ModuleDependencyConflictStatus::MISSING_DEPENDENCY, $key, 'Required dependency is not installed.', [], true);
            return;
        }
        $runtime = ($this->runtimeReader)($state->moduleIdentity()->value());
        if (!is_array($runtime) || ($runtime['status'] ?? null) !== 'enabled') { $this->add(ModuleDependencyConflictStatus::DEPENDENCY_DISABLED, $key, 'Required dependency is not enabled.', [], true); return; }
        if (!$dependency->versionConstraint()->supports($state->packageVersion())) {
            $candidate = $this->available[$state->moduleIdentity()->value()] ?? null;
            if ($candidate instanceof ModuleLifecycleTarget && $candidate->contract()->packageIdentity()->equals($state->packageIdentity()->value()) && $dependency->versionConstraint()->supports($candidate->contract()->packageVersion()) && PackageVersion::compare($candidate->contract()->packageVersion(), $state->packageVersion()) > 0) {
                $this->prerequisites[$state->moduleIdentity()->value()] = $candidate;
                $this->add(ModuleDependencyConflictStatus::RESOLUTION_REQUIRED, $key, 'Dependency requires a forward update before the target transition.', [$state->moduleIdentity()->value()], true);
            } else $this->add(ModuleDependencyConflictStatus::INCOMPATIBLE_DEPENDENCY, $key, 'Installed dependency version does not satisfy the declared constraint.', [], true);
            return;
        }
        $dependencyContract = ($this->contractReader)($state->moduleIdentity()->value());
        if (!$dependencyContract instanceof ModulePackageContract) { $this->add(ModuleDependencyConflictStatus::UNRESOLVABLE, $key, 'Installed dependency contract evidence is unavailable.', [], true); return; }
        $this->inspectContract($dependencyContract, $state->moduleIdentity()->value());
    }

    private function inspectConflict(ModulePackageConflictDeclaration $conflict, string $owner): void
    {
        $state = $this->resolveState($conflict->target());
        if ($state === null) return;
        $constraint = $conflict->versionConstraint();
        if ($constraint === null || $constraint->supports($state->packageVersion())) $this->add(ModuleDependencyConflictStatus::DECLARED_CONFLICT, $conflict->target()->kind() . ':' . $conflict->target()->identity(), 'Declared conflict affects an installed Module.', [], true);
    }

    private function inspectOwnershipEvidence(array $evidence, string $owner): void
    {
        foreach ($evidence as $item) {
            if (!is_array($item) || !isset($item['type'], $item['identity'], $item['message'])) { $this->add(ModuleDependencyConflictStatus::UNRESOLVABLE, $owner, 'Authoritative ownership evidence is malformed.', [], true); continue; }
            $status = match ($item['type']) { 'owned_path' => ModuleDependencyConflictStatus::OWNED_PATH_CONFLICT, 'schema' => ModuleDependencyConflictStatus::SCHEMA_OWNERSHIP_CONFLICT, 'permission' => ModuleDependencyConflictStatus::PERMISSION_OWNERSHIP_CONFLICT, default => ModuleDependencyConflictStatus::UNRESOLVABLE };
            $this->add($status, (string) $item['identity'], (string) $item['message'], [], true);
        }
    }

    private function resolveState(ModulePackageTarget $target): ?ModuleLifecycleState
    {
        if ($target->kind() === ModulePackageTarget::MODULE) return $this->states[$target->identity()] ?? null;
        foreach ($this->states as $state) if ($state->packageIdentity()->equals($target->identity())) return $state;
        return null;
    }

    private function candidatePackage(string $identity): ?ModuleLifecycleTarget
    {
        foreach ($this->available as $candidate) if ($candidate->contract()->packageIdentity()->equals($identity)) return $candidate;
        return null;
    }

    private function currentPackageIdentity(string $module): ?string { return $this->states[$module]?->packageIdentity()->value(); }
    private function add(string $status, string $identity, string $message, array $prerequisites, bool $operator): void { $this->findings[] = new ModuleDependencyConflictFinding($status, $identity, $message, $prerequisites, $operator); }
    private function overallStatus(): string
    {
        if ($this->findings === []) return ModuleDependencyConflictStatus::SATISFIED;
        foreach ($this->findings as $finding) if (in_array($finding->status(), [ModuleDependencyConflictStatus::CYCLIC_DEPENDENCY, ModuleDependencyConflictStatus::UNRESOLVABLE, ModuleDependencyConflictStatus::MISSING_DEPENDENCY, ModuleDependencyConflictStatus::INCOMPATIBLE_DEPENDENCY], true)) return ModuleDependencyConflictStatus::UNRESOLVABLE;
        return ModuleDependencyConflictStatus::RESOLUTION_REQUIRED;
    }
    private function limitations(): array { return $this->ownershipEvidence === null ? ['Cross-Module path, schema/migration, and permission/provisioning ownership conflicts require authoritative ownership evidence and are not inferred.'] : []; }
}
