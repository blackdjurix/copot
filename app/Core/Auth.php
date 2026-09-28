<?php

namespace Copot\Core;

class Auth
{
    private ?User $user = null;
    private static ?string $dummyPasswordHash = null;
    private $delay;

    public function __construct(
        private Config $config,
        private Session $session,
        private UserProvider $users,
        private PasswordHasher $passwords,
        private ?FailedLoginThrottle $throttle = null,
        ?callable $delay = null,
        private ?AuthenticatedSessionRepository $authenticatedSessions = null,
        private $deviceDescriptor = null,
        private $clock = null
    ) {
        $this->delay = $delay;
    }

    public function attempt(string $email, string $password): bool
    {
        $user = $this->users->findByEmail($email);

        if (!$this->throttle instanceof FailedLoginThrottle) {
            if (!$user instanceof User || !$user->isActive()) {
                return false;
            }

            if (!$this->passwords->verify($password, $user->passwordHash())) {
                return false;
            }

            return $this->establish($user);
        }

        $targetHash = FailedLoginThrottle::targetHash($email);
        $verificationHash = $user instanceof User && $user->isActive()
            ? $user->passwordHash()
            : $this->dummyPasswordHash();
        $validPassword = $this->passwords->verify($password, $verificationHash);
        $throttleState = $this->throttle->beforeAttempt($targetHash);

        if ($throttleState->isLocked()) {
            return false;
        }

        if (!$user instanceof User || !$user->isActive() || !$validPassword) {
            $failure = $this->throttle->recordFailure($targetHash);
            $this->delay($failure->delayMilliseconds());
            return false;
        }

        $this->throttle->recordSuccess($targetHash);
        return $this->establish($user);
    }

    private function establish(User $user): bool
    {
        $record = null;

        try {
            $now = $this->now();
            if ($this->authenticatedSessions instanceof AuthenticatedSessionRepository) {
                $record = $this->authenticatedSessions->create(
                    $user->id(),
                    $this->deviceDescriptor instanceof \Closure ? (string) ($this->deviceDescriptor)() : DeviceDescriptor::fromRuntime(),
                    $now,
                    $this->idleTimeoutMinutes()
                );
            }
            $this->session->regenerate();
            $this->session->set($this->sessionKey(), $user->id());
            if ($record instanceof AuthenticatedSessionRecord) {
                $this->session->setAuthenticatedSessionIdentity($record->identity());
            }
            $this->session->beginAuthenticatedActivity($now->getTimestamp());
            $this->session->regenerateCsrfToken();
            $this->users->updateLastLogin($user->id());
            $this->user = $user;

            return true;
        } catch (\Throwable) {
            if ($record instanceof AuthenticatedSessionRecord) {
                $this->authenticatedSessions?->revoke($record->identity(), 'establishment_failed');
            }
            $this->session->clearAuthenticatedState();
            $this->user = null;

            return false;
        }
    }

    private function delay(int $milliseconds): void
    {
        if ($milliseconds === 0) {
            return;
        }

        ($this->delay ?? static function (int $value): void { usleep($value * 1000); })($milliseconds);
    }

    private function dummyPasswordHash(): string
    {
        return self::$dummyPasswordHash ??= $this->passwords->make('copot-authentication-dummy-password');
    }

    public function check(): bool
    {
        return $this->user() instanceof User;
    }

    public function id(): ?int
    {
        return $this->user()?->id();
    }

    public function durableSessionIdentity(): ?string
    {
        return $this->session->authenticatedSessionIdentity();
    }

    public function user(): ?User
    {
        $userId = $this->session->get($this->sessionKey());
        $sessionIdentity = $this->session->authenticatedSessionIdentity();

        if (!$this->user instanceof User && !is_numeric($userId)) {
            return null;
        }

        if (!$this->session->evaluateAuthenticatedActivity()) {
            if ($this->authenticatedSessions instanceof AuthenticatedSessionRepository && $sessionIdentity !== null) {
                $this->authenticatedSessions->revoke($sessionIdentity, 'idle_expired');
            }
            $this->user = null;

            return null;
        }

        if ($this->authenticatedSessions instanceof AuthenticatedSessionRepository) {
            $record = $sessionIdentity === null ? null : $this->authenticatedSessions->find($sessionIdentity);
            if (!$record instanceof AuthenticatedSessionRecord || $record->userId() !== (int) $userId || $record->isRevoked()) {
                if ($sessionIdentity !== null) {
                    $this->authenticatedSessions->revoke($sessionIdentity, 'invalid_registry_state');
                }
                $this->session->clearAuthenticatedState();
                $this->user = null;

                return null;
            }
            $this->authenticatedSessions->touchIfDue($sessionIdentity, $this->now(), $this->idleTimeoutMinutes());
        }

        if ($this->user instanceof User) {
            return $this->user;
        }

        if (!is_numeric($userId)) {
            return null;
        }

        $user = $this->users->findById((int) $userId);

        if (!$user instanceof User || !$user->isActive()) {
            if ($this->authenticatedSessions instanceof AuthenticatedSessionRepository && $sessionIdentity !== null) {
                $this->authenticatedSessions->revoke($sessionIdentity, 'inactive_user');
            }
            $this->session->clearAuthenticatedState();
            $this->user = null;

            return null;
        }

        $this->user = $user;

        return $this->user;
    }

    public function logout(): void
    {
        $identity = $this->session->authenticatedSessionIdentity();
        if ($this->authenticatedSessions instanceof AuthenticatedSessionRepository && $identity !== null) {
            $this->authenticatedSessions->revoke($identity, 'logout');
        }
        $this->session->clearAuthenticatedState();
        $this->session->regenerate();
        $this->session->regenerateCsrfToken();
        $this->user = null;
    }

    private function sessionKey(): string
    {
        return $this->config->get('auth.session_key', '_copot_user_id');
    }

    private function now(): \DateTimeImmutable
    {
        $value = $this->clock instanceof \Closure ? ($this->clock)() : null;
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }
        if (is_int($value)) {
            return (new \DateTimeImmutable('@' . $value))->setTimezone(new \DateTimeZone('UTC'));
        }

        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    private function idleTimeoutMinutes(): int
    {
        return $this->session->authenticatedIdleTimeoutMinutes();
    }
}
