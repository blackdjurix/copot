<?php

namespace Copot\Core;

class Session
{
    public function __construct(private Config $config)
    {
    }

    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name($this->config->get('session.name', 'COPOTSESSID'));

        session_set_cookie_params([
            'lifetime' => $this->config->get('session.lifetime', 120) * 60,
            'path' => $this->config->get('session.path', '/'),
            'secure' => $this->config->get('session.secure', false),
            'httponly' => $this->config->get('session.http_only', true),
            'samesite' => $this->config->get('session.same_site', 'Lax'),
        ]);

        session_start();
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
}
