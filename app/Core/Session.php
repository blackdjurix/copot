<?php

namespace Copot\Core;

class Session
{
    private const AUTH_ACTIVITY_KEY = '_copot_authenticated_last_active_at';

    public function __construct(
        private Config $config,
        private ?InstallationIdentity $installation = null,
        private ?AuthenticatedIdleTimeoutResolver $idleTimeout = null,
        private $clock = null
    )
    {
    }

    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name($this->cookieName());

        $carrierMinutes = (int) $this->config->get('session.lifetime', 120);

        if ($this->idleTimeout instanceof AuthenticatedIdleTimeoutResolver) {
            $carrierMinutes = max($carrierMinutes, $this->idleTimeout->resolve());
        }

        $lifetimeSeconds = $carrierMinutes * 60;
        ini_set('session.gc_maxlifetime', (string) $lifetimeSeconds);

        session_set_cookie_params([
            'lifetime' => $lifetimeSeconds,
            'path' => $this->cookiePath(),
            'secure' => $this->config->get('session.secure', false),
            'httponly' => $this->config->get('session.http_only', true),
            'samesite' => $this->config->get('session.same_site', 'Lax'),
        ]);

        session_start();
    }

    public function cookieName(): string
    {
        $base = (string) $this->config->get('session.name', 'COPOTSESSID');
        return $this->installation === null
            ? $base
            : $base . '_' . substr(hash('sha256', $this->installation->value()), 0, 16);
    }

    public function cookiePath(): string
    {
        return (string) $this->config->get('session.path', '/');
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $_SESSION);
    }

    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public function destroy(): void
    {
        $_SESSION = [];
        session_destroy();
    }

    public function csrfToken(): string
    {
        $key = $this->config->get('session.csrf_key', '_copot_csrf_token');

        if (!$this->has($key)) {
            $this->regenerateCsrfToken();
        }

        return $this->get($key);
    }

    public function regenerateCsrfToken(): string
    {
        $key = $this->config->get('session.csrf_key', '_copot_csrf_token');
        $token = bin2hex(random_bytes(32));

        $this->set($key, $token);

        return $token;
    }

    public function validateCsrf(?string $token): bool
    {
        $key = $this->config->get('session.csrf_key', '_copot_csrf_token');
        $storedToken = $this->get($key);

        return is_string($token)
            && is_string($storedToken)
            && hash_equals($storedToken, $token);
    }

    public function beginAuthenticatedActivity(?int $now = null): void
    {
        $this->set(self::AUTH_ACTIVITY_KEY, $now ?? $this->now());
    }

    public function evaluateAuthenticatedActivity(?int $now = null): bool
    {
        $current = $now ?? $this->now();
        $lastActive = $this->get(self::AUTH_ACTIVITY_KEY);

        if (!is_int($lastActive) && !(is_string($lastActive) && ctype_digit($lastActive))) {
            $this->beginAuthenticatedActivity($current);

            return true;
        }

        $lastActive = (int) $lastActive;
        $timeout = $this->idleTimeout?->resolve() ?? AuthenticatedIdleTimeoutResolver::DEFAULT_MINUTES;

        if ($lastActive + ($timeout * 60) <= $current) {
            $this->clearAuthenticatedState();

            return false;
        }

        $this->beginAuthenticatedActivity($current);

        return true;
    }

    public function clearAuthenticatedState(): void
    {
        $this->remove($this->config->get('auth.session_key', '_copot_user_id'));
        $this->remove(self::AUTH_DURABLE_SESSION_KEY);
        $this->remove(self::AUTH_ACTIVITY_KEY);
    }

    private const AUTH_DURABLE_SESSION_KEY = '_copot_authenticated_session_identity';

    public function authenticatedSessionIdentity(): ?string
    {
        $identity = $this->get(self::AUTH_DURABLE_SESSION_KEY);

        return is_string($identity) ? $identity : null;
    }

    public function setAuthenticatedSessionIdentity(string $identity): void
    {
        $this->set(self::AUTH_DURABLE_SESSION_KEY, $identity);
    }

    public function authenticatedActivityExpired(?int $now = null): bool
    {
        $lastActive = $this->get(self::AUTH_ACTIVITY_KEY);
        if (!is_int($lastActive) && !(is_string($lastActive) && ctype_digit($lastActive))) {
            return false;
        }

        $timeout = $this->idleTimeout?->resolve() ?? AuthenticatedIdleTimeoutResolver::DEFAULT_MINUTES;

        return (int) $lastActive + ($timeout * 60) <= ($now ?? $this->now());
    }

    public function authenticatedIdleTimeoutMinutes(): int
    {
        return $this->idleTimeout?->resolve() ?? AuthenticatedIdleTimeoutResolver::DEFAULT_MINUTES;
    }

    private function now(): int
    {
        return $this->clock instanceof \Closure ? (int) ($this->clock)() : time();
    }
}
