<?php

namespace Copot\Core;

/** Result returned by an existing authorized lifecycle operation. */
final class AdoptionResolutionOperationResult
{
    public const COMPLETED = 'completed';
    public const FAILED = 'failed';
    public const RECOVERY_REQUIRED = 'recovery_required';
    public const UNRESOLVED = 'unresolved';
    public const UNAUTHORIZED = 'unauthorized';
    public const UNAVAILABLE = 'unavailable';

    private const STATES = [self::COMPLETED, self::FAILED, self::RECOVERY_REQUIRED, self::UNRESOLVED, self::UNAUTHORIZED, self::UNAVAILABLE];

    public function __construct(
        private string $operationId,
        private string $lifecycleClass,
        private string $status,
        private string $resultIdentity,
        private string $detail
    ) {
        foreach ([$operationId, $lifecycleClass, $resultIdentity, $detail] as $value) {
            if ($value === '' || trim($value) !== $value || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
                throw new \InvalidArgumentException('Adoption resolution operation evidence is invalid.');
            }
        }
        if (!in_array($status, self::STATES, true)) {
            throw new \InvalidArgumentException('Adoption resolution operation status is unsupported.');
        }
    }

    public function operationId(): string { return $this->operationId; }
    public function lifecycleClass(): string { return $this->lifecycleClass; }
    public function status(): string { return $this->status; }
    public function resultIdentity(): string { return $this->resultIdentity; }
    public function detail(): string { return $this->detail; }
    public function completed(): bool { return $this->status === self::COMPLETED; }
    public function toArray(): array
    {
        return [
            'operation_id' => $this->operationId,
            'lifecycle_class' => $this->lifecycleClass,
            'status' => $this->status,
            'result_identity' => $this->resultIdentity,
            'detail' => $this->detail,
        ];
    }
}
