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
        ?callable $delay = null
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
        $this->session->regenerate();
        $this->session->set($this->sessionKey(), $user->id());
        $this->session->regenerateCsrfToken();
        $this->users->updateLastLogin($user->id());
        $this->user = $user;

        return true;
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

    public function user(): ?User
    {
        if ($this->user instanceof User) {
            return $this->user;
        }

        $userId = $this->session->get($this->sessionKey());

        if (!is_numeric($userId)) {
            return null;
        }

        $user = $this->users->findById((int) $userId);

        if (!$user instanceof User || !$user->isActive()) {
            $this->session->remove($this->sessionKey());
            $this->user = null;

            return null;
        }

        $this->user = $user;

        return $this->user;
    }

    public function logout(): void
    {
        $this->session->remove($this->sessionKey());
        $this->session->regenerate();
        $this->session->regenerateCsrfToken();
        $this->user = null;
    }

    private function sessionKey(): string
    {
        return $this->config->get('auth.session_key', '_copot_user_id');
    }
}
