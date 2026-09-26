<?php

namespace Copot\Core;

/** Immutable WU4 orchestration/audit result; it is not a lifecycle operation. */
final class AdoptionOrchestrationResult
{
    public const READY = 'ready';
    public const SUSPENDED = 'suspended';
    public const BLOCKED = 'blocked';
    public const STALE = 'stale';

    /** @param list<AdoptionResolutionOperationResult> $operations */
    public function __construct(
        private string $state,
        private string $orchestrationIdentity,
        private string $compatibilityIdentity,
        private string $classification,
        private array $operations,
        private string $detail,
        private ?string $targetIdentity = null,
        private ?string $installationIdentity = null,
        private ?string $namespaceIdentity = null
    ) {
        if (!in_array($state, [self::READY, self::SUSPENDED, self::BLOCKED, self::STALE], true)) {
            throw new \InvalidArgumentException('Adoption orchestration state is unsupported.');
        }
        if ($orchestrationIdentity === '' || $compatibilityIdentity === '' || $classification === '' || $detail === '') {
            throw new \InvalidArgumentException('Adoption orchestration result identity is invalid.');
        }
        $identities = [$targetIdentity, $installationIdentity, $namespaceIdentity];
        if (count(array_filter($identities, static fn (?string $identity): bool => $identity !== null)) > 0) {
            foreach ($identities as $identity) {
                if ($identity === null || $identity === '' || trim($identity) !== $identity || preg_match('/[\x00-\x1F\x7F]/', $identity) === 1) {
                    throw new \InvalidArgumentException('Adoption orchestration boundary identity is invalid.');
                }
            }
        }
        foreach ($operations as $operation) {
            if (!$operation instanceof AdoptionResolutionOperationResult) {
                throw new \InvalidArgumentException('Adoption orchestration operations are invalid.');
            }
        }
    }

    public function state(): string { return $this->state; }
    public function ready(): bool { return $this->state === self::READY; }
    public function orchestrationIdentity(): string { return $this->orchestrationIdentity; }
    public function compatibilityIdentity(): string { return $this->compatibilityIdentity; }
    public function classification(): string { return $this->classification; }
    public function targetIdentity(): ?string { return $this->targetIdentity; }
    public function installationIdentity(): ?string { return $this->installationIdentity; }
    public function namespaceIdentity(): ?string { return $this->namespaceIdentity; }
    /** @return list<AdoptionResolutionOperationResult> */
    public function operations(): array { return $this->operations; }
    public function detail(): string { return $this->detail; }
    public function toArray(): array
    {
        return [
            'state' => $this->state,
            'orchestration_identity' => $this->orchestrationIdentity,
            'compatibility_identity' => $this->compatibilityIdentity,
            'classification' => $this->classification,
            'target_identity' => $this->targetIdentity,
            'installation_identity' => $this->installationIdentity,
            'namespace_identity' => $this->namespaceIdentity,
            'operations' => array_map(static fn (AdoptionResolutionOperationResult $operation): array => $operation->toArray(), $this->operations),
            'detail' => $this->detail,
        ];
    }
    public function identity(): string
    {
        return hash('sha256', json_encode($this->toArray(), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
