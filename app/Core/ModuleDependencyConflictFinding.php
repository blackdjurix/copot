<?php

namespace Copot\Core;

final class ModuleDependencyConflictFinding
{
    public function __construct(
        private string $status,
        private string $affectedIdentity,
        private string $message,
        private array $requiredPrerequisites = [],
        private bool $operatorActionRequired = true
    ) {
        $allowed = [ModuleDependencyConflictStatus::MISSING_DEPENDENCY, ModuleDependencyConflictStatus::DEPENDENCY_DISABLED, ModuleDependencyConflictStatus::INCOMPATIBLE_DEPENDENCY, ModuleDependencyConflictStatus::DECLARED_CONFLICT, ModuleDependencyConflictStatus::IDENTITY_CONFLICT, ModuleDependencyConflictStatus::OWNED_PATH_CONFLICT, ModuleDependencyConflictStatus::SCHEMA_OWNERSHIP_CONFLICT, ModuleDependencyConflictStatus::PERMISSION_OWNERSHIP_CONFLICT, ModuleDependencyConflictStatus::CYCLIC_DEPENDENCY, ModuleDependencyConflictStatus::RESOLUTION_REQUIRED, ModuleDependencyConflictStatus::UNRESOLVABLE];
        if (!in_array($status, $allowed, true) || $affectedIdentity === '' || $message === '') throw new \InvalidArgumentException('Module dependency/conflict finding is invalid.');
        sort($requiredPrerequisites, SORT_STRING);
    }
    public function status(): string { return $this->status; }
    public function affectedIdentity(): string { return $this->affectedIdentity; }
    public function message(): string { return $this->message; }
    public function requiredPrerequisites(): array { return $this->requiredPrerequisites; }
    public function operatorActionRequired(): bool { return $this->operatorActionRequired; }
    public function toArray(): array { return ['status' => $this->status, 'affected_identity' => $this->affectedIdentity, 'message' => $this->message, 'required_prerequisites' => $this->requiredPrerequisites, 'operator_action_required' => $this->operatorActionRequired]; }
}
