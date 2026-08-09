<?php

namespace Copot\Core;

final class RuntimeParticipant
{
    public const REGISTERED = 'REGISTERED';
    public const ACTIVE = 'ACTIVE';
    public const STALE = 'STALE';
    public const DETACHED = 'DETACHED';
    public const INCOMPATIBLE = 'INCOMPATIBLE';

    public function __construct(private array $data)
    {
        if (!is_string($data['runtime_id'] ?? null) || preg_match('/\Art_[a-f0-9]{32}\z/', $data['runtime_id']) !== 1) {
            throw new \InvalidArgumentException('Runtime identity is invalid.');
        }
        if (!is_string($data['installation_id'] ?? null)) {
            throw new \InvalidArgumentException('Runtime installation identity is invalid.');
        }
        if (!in_array($data['state'] ?? null, [self::REGISTERED, self::ACTIVE, self::STALE, self::DETACHED, self::INCOMPATIBLE], true)) {
            throw new \InvalidArgumentException('Runtime lifecycle state is invalid.');
        }
    }

    public function toArray(): array { return $this->data; }
    public function runtimeId(): string { return $this->data['runtime_id']; }
    public function installationId(): string { return $this->data['installation_id']; }
    public function state(): string { return $this->data['state']; }
    public function lastSeenAt(): string { return $this->data['last_seen_at']; }
}
