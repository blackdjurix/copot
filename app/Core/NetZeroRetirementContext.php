<?php

namespace Copot\Core;

/** Trusted, independently captured evidence used to evaluate one retirement attempt. */
final class NetZeroRetirementContext
{
    public function __construct(
        private string $operationId,
        private string $runtimeRoot,
        private string $acceptedTargetRoot,
        private string $acceptedTargetIdentity,
        private array $expectedState,
        private $currentState,
        private string $migrationPlanIdentity,
        private string $reason = 'forensic net-zero effective mutation'
    ) {
        if ($operationId === '' || trim($operationId) !== $operationId) throw new \InvalidArgumentException('Net-zero operation identity is invalid.');
        foreach ([$runtimeRoot, $acceptedTargetRoot] as $root) if ($root === '' || str_contains($root, "\0")) throw new \InvalidArgumentException('Net-zero target root is invalid.');
        self::hash($acceptedTargetIdentity, 'Accepted target identity');
        self::hash($migrationPlanIdentity, 'Migration plan identity');
        foreach (['migration_ledger', 'schema', 'capability', 'committed_state', 'persistent_state', 'operator_state'] as $key) {
            if (!isset($expectedState[$key]) || !is_string($expectedState[$key])) throw new \InvalidArgumentException('Net-zero baseline state is incomplete.');
            self::hash($expectedState[$key], 'Net-zero baseline state');
        }
        if (!is_callable($currentState)) throw new \InvalidArgumentException('Net-zero current-state provider is invalid.');
        if ($reason === '' || strlen($reason) > 1024 || preg_match('/[\x00-\x1F\x7F]/', $reason) === 1) throw new \InvalidArgumentException('Net-zero retirement reason is invalid.');
    }

    public function operationId(): string { return $this->operationId; }
    public function runtimeRoot(): string { return $this->runtimeRoot; }
    public function acceptedTargetRoot(): string { return $this->acceptedTargetRoot; }
    public function acceptedTargetIdentity(): string { return $this->acceptedTargetIdentity; }
    public function migrationPlanIdentity(): string { return $this->migrationPlanIdentity; }
    public function expectedState(): array { return $this->expectedState; }
    public function currentState(): array { $state = ($this->currentState)(); return is_array($state) ? $state : []; }
    public function reason(): string { return $this->reason; }

    private static function hash(string $value, string $label): void
    {
        if (preg_match('/^[a-f0-9]{64}$/D', strtolower($value)) !== 1) throw new \InvalidArgumentException($label . ' is invalid.');
    }
}
