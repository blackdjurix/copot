<?php

namespace Copot\Core;

final class AuthenticatedIdleTimeoutResolver
{
    public const NAMESPACE = 'security';
    public const KEY = 'authenticated_idle_timeout_minutes';
    public const DEFAULT_MINUTES = 120;
    public const MINIMUM_MINUTES = 1;
    public const MAXIMUM_MINUTES = 43200;

    public function __construct(
        private SettingsService $settings,
        private Config $config
    ) {
    }

    public function resolve(): int
    {
        $runtime = $this->settings->read(self::NAMESPACE, self::KEY);

        if ($runtime->storageReadable()) {
            return $this->validMinutes($runtime->value())
                ?? self::DEFAULT_MINUTES;
        }

        return $this->validMinutes(
            $this->config->get('security.authenticated_idle_timeout_minutes')
        ) ?? self::DEFAULT_MINUTES;
    }

    private function validMinutes(mixed $value): ?int
    {
        if (is_string($value) && preg_match('/^[0-9]+$/', $value) === 1) {
            $value = filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
        }

        return is_int($value)
            && $value >= self::MINIMUM_MINUTES
            && $value <= self::MAXIMUM_MINUTES
            ? $value
            : null;
    }
}
