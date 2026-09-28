<?php

namespace Copot\Core;

final class SelfSessionService
{
    public function __construct(
        private AuthenticatedSessionRepository $sessions,
        private ReauthenticationService $reauthentication,
        private ?SecurityEventService $securityEvents = null
    )
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
        $this->reauthentication->requireRecentProof();

        $revoked = $this->sessions->revoke($identity, 'revoked_by_user', $userId);
        if ($revoked) {
            $this->securityEvents?->recordSessionRevoked($userId, $identity, 'revoked_by_user');
        }

        return $revoked;
    }

    public function revokeOtherSessions(int $userId, string $currentIdentity): int
    {
        $this->reauthentication->requireRecentProof();

        $current = $this->sessions->find($currentIdentity);
        if (!$current instanceof AuthenticatedSessionRecord || $current->userId() !== $userId || $current->isRevoked()) {
            return 0;
        }

        $revoked = $this->sessions->revokeOthers($userId, $currentIdentity);
        $this->securityEvents?->recordSignOutOthers($userId, $currentIdentity, $revoked);

        return $revoked;
    }
}
