<?php

namespace Copot\Core;

final class ModuleApplyResult
{
    public const COMPLETED='completed'; public const FAILED='failed'; public const BLOCKED='blocked'; public const INDETERMINATE='indeterminate'; public const CLEANUP_PENDING='cleanup_pending';
    public function __construct(private string $status, private string $reason='', private ?string $operationId=null, private array $appliedPaths=[], private ?HealthGateMatrix $gates=null) {}
    public function status(): string{return $this->status;} public function reason():string{return $this->reason;} public function operationId():?string{return $this->operationId;} public function appliedPaths():array{return $this->appliedPaths;} public function gates():?HealthGateMatrix{return $this->gates;}
}
