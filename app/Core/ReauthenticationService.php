<?php

namespace Copot\Core;

final class ReauthenticationService
{
    public const VALIDITY_WINDOW_SECONDS = 300;
    private const PROOF_KEY = '_copot_reauthentication_proof';

    public function __construct(
        private Auth $auth,
        private Session $session,
        private PasswordHasher $passwords,
        private $clock = null,
        private ?SecurityEventService $securityEvents = null
    ) {
    }

    public function reauthenticate(string $password): ReauthenticationResult
    {
        if (!$this->auth->check()) {
            $this->securityEvents?->recordReauthentication(ReauthenticationResult::unauthenticated(), null, null, $this->dateTime());

            return ReauthenticationResult::unauthenticated();
        }

        $user = $this->auth->user();
        $identity = $this->auth->durableSessionIdentity();
        if (!$user instanceof User || $identity === null) {
            $this->clearProof();
            $this->securityEvents?->recordReauthentication(ReauthenticationResult::unauthenticated(), $user?->id(), $identity, $this->dateTime());

            return ReauthenticationResult::unauthenticated();
        }

        if (!$this->passwords->verify($password, $user->passwordHash())) {
            $this->securityEvents?->recordReauthentication(ReauthenticationResult::invalidPassword(), $user->id(), $identity, $this->dateTime());

            return ReauthenticationResult::invalidPassword();
        }

        $this->session->set(self::PROOF_KEY, [
            'session_identity' => $identity,
            'authenticated_at' => $this->now(),
        ]);

        $result = ReauthenticationResult::success();
        $this->securityEvents?->recordReauthentication($result, $user->id(), $identity, $this->dateTime());

        return $result;
    }

    public function hasValidProof(): bool
    {
        if (!$this->auth->check()) {
            $this->clearProof();

            return false;
        }

        $identity = $this->auth->durableSessionIdentity();
        $proof = $this->session->get(self::PROOF_KEY);
        if ($identity === null || !is_array($proof)
            || ($proof['session_identity'] ?? null) !== $identity
            || !is_int($proof['authenticated_at'] ?? null)
            || $proof['authenticated_at'] + self::VALIDITY_WINDOW_SECONDS <= $this->now()
        ) {
            $this->clearProof();

            return false;
        }

        return true;
    }

    public function requireRecentProof(): void
    {
        if (!$this->hasValidProof()) {
            throw new ReauthenticationRequiredException('Recent re-authentication is required.');
        }
    }

    public function clearProof(): void
    {
        $this->session->remove(self::PROOF_KEY);
    }

    private function now(): int
    {
        $value = $this->clock instanceof \Closure ? ($this->clock)() : null;
        if ($value instanceof \DateTimeImmutable) {
            return $value->getTimestamp();
        }
        if (is_int($value)) {
            return $value;
        }

        return time();
    }

    private function dateTime(): \DateTimeImmutable
    {
        return (new \DateTimeImmutable('@' . $this->now()))->setTimezone(new \DateTimeZone('UTC'));
    }
}
