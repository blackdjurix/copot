<?php

namespace Copot\Core;

class Auth
{
    private ?User $user = null;

    public function __construct(
        private Config $config,
        private Session $session,
        private UserProvider $users,
        private PasswordHasher $passwords
    ) {
    }

    public function attempt(string $email, string $password): bool
    {
        $user = $this->users->findByEmail($email);

        if (!$user instanceof User || !$user->isActive()) {
            return false;
        }

        if (!$this->passwords->verify($password, $user->passwordHash())) {
            return false;
        }

        $this->session->regenerate();
        $this->session->set($this->sessionKey(), $user->id());
        $this->session->regenerateCsrfToken();
        $this->users->updateLastLogin($user->id());
        $this->user = $user;

        return true;
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
