<?php

namespace Copot\Core;

final class InstallerAdministratorValidator
{
    public static function validate(array $input): array
    {
        $name = self::stringValue($input, 'admin_name', true);
        $email = strtolower(self::stringValue($input, 'admin_email', true));
        $password = self::stringValue($input, 'admin_password', false);
        $confirmation = self::stringValue($input, 'admin_password_confirmation', false);
        $siteName = self::stringValue($input, 'site_name', false);
        $siteTagline = self::stringValue($input, 'site_tagline', false);
        $timezone = self::stringValue($input, 'timezone', false);
        $locale = self::stringValue($input, 'locale', false);
        $errors = [];

        $fieldMessages = [
            'admin_name' => 'Administrator name is required and must not exceed 120 characters.',
            'admin_email' => 'Enter a valid administrator email address.',
            'admin_password' => 'Administrator password must contain at least 10 characters.',
            'admin_password_confirmation' => 'Password confirmation does not match.',
            'site_name' => 'Site Name is required and must not exceed 150 characters.',
            'site_tagline' => 'Site Tagline must not exceed 255 characters.',
            'timezone' => 'Invalid timezone.',
            'locale' => 'Unsupported locale.',
        ];

        foreach ($fieldMessages as $field => $message) {
            if (array_key_exists($field, $input) && !is_string($input[$field])) {
                $errors[$field] = $message;
            }
        }

        $nameLength = self::stringLength($name);

        if (
            $name === ''
            || $nameLength === null
            || $nameLength > 120
            || preg_match('/[\x00-\x1F\x7F]/', $name)
        ) {
            $errors['admin_name'] = $fieldMessages['admin_name'];
        }

        if (
            $email === ''
            || strlen($email) > 190
            || filter_var($email, FILTER_VALIDATE_EMAIL) === false
        ) {
            $errors['admin_email'] = $fieldMessages['admin_email'];
        }

        $passwordLength = self::stringLength($password);

        if ($passwordLength === null || $passwordLength < 10) {
            $errors['admin_password'] = $fieldMessages['admin_password'];
        }

        if ($confirmation !== $password) {
            $errors['admin_password_confirmation'] = $fieldMessages['admin_password_confirmation'];
        }

        self::validateSetting('site', 'name', $siteName, 'site_name', $fieldMessages['site_name'], $errors);
        self::validateSetting('site', 'tagline', $siteTagline, 'site_tagline', $fieldMessages['site_tagline'], $errors);
        self::validateSetting('localization', 'timezone', $timezone, 'timezone', $fieldMessages['timezone'], $errors);
        self::validateSetting('localization', 'locale', $locale, 'locale', $fieldMessages['locale'], $errors);

        $values = [
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'site_name' => $siteName,
            'site_tagline' => $siteTagline,
            'timezone' => $timezone,
            'locale' => $locale,
        ];

        if ($errors !== []) {
            throw new InstallerValidationException($errors, self::submittedValues($values));
        }

        return $values;
    }

    private static function validateSetting(
        string $namespace,
        string $key,
        string $value,
        string $field,
        string $message,
        array &$errors
    ): void {
        try {
            SettingsRegistry::core()->find($namespace, $key)?->validate($value);
        } catch (SettingsException) {
            $errors[$field] = $message;
        }
    }

    private static function submittedValues(array $values): array
    {
        return [
            'admin_name' => self::safeSubmittedValue($values['name']),
            'admin_email' => self::safeSubmittedValue($values['email']),
            'site_name' => self::safeSubmittedValue($values['site_name']),
            'site_tagline' => self::safeSubmittedValue($values['site_tagline']),
            'timezone' => self::safeSubmittedValue($values['timezone']),
            'locale' => self::safeSubmittedValue($values['locale']),
        ];
    }

    private static function safeSubmittedValue(string $value): string
    {
        return preg_match('/[\x00-\x1F\x7F]/', $value) ? '' : $value;
    }

    private static function stringValue(array $input, string $key, bool $trim): string
    {
        $value = $input[$key] ?? '';

        if (!is_string($value)) {
            return "\0";
        }

        return $trim ? trim($value) : $value;
    }

    private static function stringLength(string $value): ?int
    {
        $length = preg_match_all('/./us', $value);

        return is_int($length) ? $length : null;
    }
}
