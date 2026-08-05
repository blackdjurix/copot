<?php

namespace Copot\Core;

final class ModuleDependencyConflictPlan
{
    public function __construct(private string $status, private bool $accepted, private ModuleLifecycleTarget $target, array $findings = [], array $orderedPrerequisites = [], array $limitations = [])
    {
        if (!in_array($status, [ModuleDependencyConflictStatus::SATISFIED, ModuleDependencyConflictStatus::RESOLUTION_REQUIRED, ModuleDependencyConflictStatus::UNRESOLVABLE], true)) throw new \InvalidArgumentException('Module dependency/conflict plan status is invalid.');
        foreach ($findings as $finding) if (!$finding instanceof ModuleDependencyConflictFinding) throw new \InvalidArgumentException('Module dependency/conflict findings are invalid.');
        foreach ($orderedPrerequisites as $prerequisite) if (!$prerequisite instanceof ModuleLifecycleTarget) throw new \InvalidArgumentException('Module prerequisite targets are invalid.');
        usort($findings, static fn (ModuleDependencyConflictFinding $left, ModuleDependencyConflictFinding $right): int => [$left->affectedIdentity(), $left->status()] <=> [$right->affectedIdentity(), $right->status()]);
        usort($orderedPrerequisites, static fn (ModuleLifecycleTarget $left, ModuleLifecycleTarget $right): int => strcmp($left->contract()->moduleIdentity()->value(), $right->contract()->moduleIdentity()->value()));
        $this->findings = array_values($findings); $this->orderedPrerequisites = array_values($orderedPrerequisites); $this->limitations = array_values($limitations);
    }
    private array $findings = []; private array $orderedPrerequisites = []; private array $limitations = [];
    public function status(): string { return $this->status; }
    public function accepted(): bool { return $this->accepted; }
    public function target(): ModuleLifecycleTarget { return $this->target; }
    public function findings(): array { return $this->findings; }
    public function orderedPrerequisites(): array { return $this->orderedPrerequisites; }
    public function limitations(): array { return $this->limitations; }
    public function operatorActionRequired(): bool { foreach ($this->findings as $finding) if ($finding->operatorActionRequired()) return true; return false; }
}
