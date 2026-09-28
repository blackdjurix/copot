<?php

namespace Copot\Core;

final class SelfSessionService
{
    public function __construct(private AuthenticatedSessionRepository $sessions)
    {
    }

    /** @return list<array{session: AuthenticatedSessionRecord, current: bool}> */
    public function listOwn(int $userId, string $currentIdentity): array
    {
        return array_map(
            static fn (AuthenticatedSessionRecord $session): array => [
                'session' => $session,
                'current' => hash_equals($session->identity(), $currentIdentity),
            ],
            $this->sessions->forUser($userId)
        );
    }

    public function revokeOwn(int $userId, string $identity): bool
    {
        return $this->sessions->revoke($identity, 'revoked_by_user', $userId);
    }

    public function revokeOtherSessions(int $userId, string $currentIdentity): int
    {
        $current = $this->sessions->find($currentIdentity);
        if (!$current instanceof AuthenticatedSessionRecord || $current->userId() !== $userId || $current->isRevoked()) {
            return 0;
        }

        return $this->sessions->revokeOthers($userId, $currentIdentity);
    }
}
