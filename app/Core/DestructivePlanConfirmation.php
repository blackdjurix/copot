<?php

namespace Copot\Core;

final class DestructivePlanConfirmation
{
    public const VERSION = 1;

    public function __construct(
        private string $operationIdentity,
        private string $planIdentity,
        private string $removalSetDigest,
        private DestructiveActorAuthorizationEvidence $actorAuthorizationEvidence,
        private int $confirmationVersion = self::VERSION
    ) {
        if ($operationIdentity === '' || trim($operationIdentity) !== $operationIdentity || preg_match('/[\x00-\x1F\x7F]/', $operationIdentity) === 1
            || preg_match('/^v3-plan-sha256:[a-f0-9]{64}$/D', $planIdentity) !== 1
            || preg_match('/^[a-f0-9]{64}$/D', $removalSetDigest) !== 1
            || $confirmationVersion !== self::VERSION) { throw new \InvalidArgumentException('Destructive plan confirmation is invalid.'); }
    }
    public function operationIdentity(): string { return $this->operationIdentity; }
    public function planIdentity(): string { return $this->planIdentity; }
    public function removalSetDigest(): string { return $this->removalSetDigest; }
    public function actorAuthorizationEvidence(): DestructiveActorAuthorizationEvidence { return $this->actorAuthorizationEvidence; }
    public function confirmationVersion(): int { return $this->confirmationVersion; }
    public function bindingIdentity(): string { return 'v1-confirmation-sha256:' . hash('sha256', json_encode($this->toArray(), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)); }
    public function toArray(): array { return ['operation_identity' => $this->operationIdentity, 'plan_identity' => $this->planIdentity, 'removal_set_digest' => $this->removalSetDigest, 'actor_authorization_evidence' => $this->actorAuthorizationEvidence->toArray(), 'confirmation_version' => $this->confirmationVersion]; }
    public static function fromArray(array $data): self
    {
        $keys = ['operation_identity', 'plan_identity', 'removal_set_digest', 'actor_authorization_evidence', 'confirmation_version'];
        if (array_keys($data) !== $keys || !is_string($data['operation_identity']) || !is_string($data['plan_identity']) || !is_string($data['removal_set_digest']) || !is_int($data['confirmation_version'])) { throw new \InvalidArgumentException('Destructive plan confirmation shape is invalid.'); }
        return new self($data['operation_identity'], $data['plan_identity'], $data['removal_set_digest'], DestructiveActorAuthorizationEvidence::fromArray($data['actor_authorization_evidence']), $data['confirmation_version']);
    }
}
