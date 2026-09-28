<?php

namespace Copot\Core;

final class PasswordPolicy
{
    public const DEFAULT_MINIMUM_LENGTH = 12;
    public const DEFAULT_MAXIMUM_LENGTH = 128;

    public function __construct(private ?SettingsService $settings = null)
    {
    }

    public function minimumLength(): int
    {
        return $this->effectiveLimits()[0];
    }

    public function maximumLength(): int
    {
        return $this->effectiveLimits()[1];
    }

    public function validate(string $password, string $subject = 'Password'): ?string
    {
        $length = preg_match_all('/./us', $password);

        if (
            trim($password) === ''
            || !is_int($length)
            || $length < $this->minimumLength()
            || $length > $this->maximumLength()
        ) {
            return $subject . ' must contain between ' . $this->minimumLength() . ' and ' . $this->maximumLength() . ' characters.';
        }

        return null;
    }

    private function effectiveLimits(): array
    {
        $minimum = $this->setting('password_min_length', self::DEFAULT_MINIMUM_LENGTH);
        $maximum = $this->setting('password_max_length', self::DEFAULT_MAXIMUM_LENGTH);

        if ($minimum < 1 || $maximum < $minimum) {
            return [self::DEFAULT_MINIMUM_LENGTH, self::DEFAULT_MAXIMUM_LENGTH];
        }

        return [$minimum, $maximum];
    }

    private function setting(string $key, int $default): int
    {
        if (!$this->settings instanceof SettingsService) {
            return $default;
        }

        $value = $this->settings->get('security', $key, $default);

        return is_int($value) ? $value : $default;
    }
}
