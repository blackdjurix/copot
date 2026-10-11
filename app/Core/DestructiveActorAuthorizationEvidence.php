<?php

namespace Copot\Core;

/** Opaque authenticated actor/session capability reference; credentials are never accepted or persisted. */
final class DestructiveActorAuthorizationEvidence
{
    public function __construct(
        private string $actorIdentity,
        private string $capabilityReference,
        private ?string $sessionIdentity = null
    ) {
        foreach ([$actorIdentity, $capabilityReference, $sessionIdentity] as $value) {
            if ($value !== null && ($value === '' || trim($value) !== $value || preg_match('/[\x00-\x1F\x7F]/', $value) === 1)) { throw new \InvalidArgumentException('Actor authorization evidence is invalid.'); }
        }
    }
    public function actorIdentity(): string { return $this->actorIdentity; }
    public function capabilityReference(): string { return $this->capabilityReference; }
    public function sessionIdentity(): ?string { return $this->sessionIdentity; }
    public function toArray(): array { return ['actor_identity' => $this->actorIdentity, 'capability_reference' => $this->capabilityReference, 'session_identity' => $this->sessionIdentity]; }
    public static function fromArray(mixed $data): self
    {
        $keys = ['actor_identity', 'capability_reference', 'session_identity'];
        if (!is_array($data) || array_keys($data) !== $keys || !is_string($data['actor_identity']) || !is_string($data['capability_reference']) || ($data['session_identity'] !== null && !is_string($data['session_identity']))) { throw new \InvalidArgumentException('Actor authorization evidence shape is invalid.'); }
        return new self($data['actor_identity'], $data['capability_reference'], $data['session_identity']);
    }
}
