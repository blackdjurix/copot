<?php

namespace Copot\Core;

class SettingsRegistry
{
    private const NAMESPACE_PATTERN = '/^[a-z][a-z0-9_-]{0,63}$/';
    private const KEY_PATTERN = '/^[a-z][a-z0-9_-]{0,127}$/';

    private array $definitions = [];

    public function __construct(array $definitions = [])
    {
        foreach ($definitions as $definition) {
            if (!$definition instanceof SettingDefinition) {
                throw new SettingsException('Settings registry accepts SettingDefinition instances only.');
            }

            $this->register($definition);
        }
    }

    public static function core(): self
    {
        return new self([
            new SettingDefinition(
                'site',
                'name',
                'string',
                'copot',
                static function (string $value): bool {
                    $length = self::stringLength($value);

                    return trim($value) !== '' && $length !== null && $length <= 150;
                },
                metadata: ['max_length' => 150]
            ),
            new SettingDefinition(
                'site',
                'tagline',
                'string',
                '',
                static function (string $value): bool {
                    $length = self::stringLength($value);

                    return $length !== null && $length <= 255;
                },
                metadata: ['max_length' => 255]
            ),
            new SettingDefinition(
                'localization',
                'timezone',
                'string',
                'UTC',
                static fn (string $value): bool => in_array($value, timezone_identifiers_list(), true)
            ),
            new SettingDefinition(
                'localization',
                'locale',
                'string',
                'en_US',
                allowedValues: ['en_US', 'id_ID']
            ),
            new SettingDefinition(
                'localization',
                'date_format',
                'string',
                'Y-m-d',
                allowedValues: ['Y-m-d', 'd/m/Y', 'm/d/Y', 'd M Y']
            ),
            new SettingDefinition(
                'localization',
                'time_format',
                'string',
                'H:i',
                allowedValues: ['H:i', 'h:i A']
            ),
        ]);
    }

    public function register(SettingDefinition $definition): void
    {
        $identifier = $definition->identifier();

        if (isset($this->definitions[$identifier])) {
            throw new SettingsException("Duplicate setting definition [{$identifier}].");
        }

        $this->definitions[$identifier] = $definition;
    }

    public function find(string $namespace, string $key): ?SettingDefinition
    {
        $this->validateIdentifiers($namespace, $key);

        return $this->definitions[$namespace . '.' . $key] ?? null;
    }

    public function has(string $namespace, string $key): bool
    {
        return $this->find($namespace, $key) instanceof SettingDefinition;
    }

    public function all(string $namespace): array
    {
        $this->validateNamespace($namespace);

        return array_values(array_filter(
            $this->definitions,
            static fn (SettingDefinition $definition): bool => $definition->namespace() === $namespace
        ));
    }

    public function namespaces(): array
    {
        $namespaces = [];

        foreach ($this->definitions as $definition) {
            $namespaces[$definition->namespace()] = true;
        }

        return array_keys($namespaces);
    }

    private function validateIdentifiers(string $namespace, string $key): void
    {
        $this->validateNamespace($namespace);

        if (!preg_match(self::KEY_PATTERN, $key)) {
            throw new SettingsException("Invalid settings key [{$key}].");
        }
    }

    private function validateNamespace(string $namespace): void
    {
        if (!preg_match(self::NAMESPACE_PATTERN, $namespace)) {
            throw new SettingsException("Invalid settings namespace [{$namespace}].");
        }
    }

    private static function stringLength(string $value): ?int
    {
        $length = preg_match_all('/./us', $value);

        return is_int($length) ? $length : null;
    }
}
