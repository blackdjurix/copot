<?php

namespace Copot\Core;

use JsonException;
use PDOException;

class SettingsService
{
    private const TYPES = ['string', 'integer', 'boolean', 'float', 'json'];
    private bool $storageReadable = true;

    public function __construct(
        private SettingsRegistry $registry,
        private SettingsRepository $repository
    ) {
    }

    public function get(string $namespace, string $key, mixed $default = null): mixed
    {
        $definition = $this->registry->find($namespace, $key);

        if (!$definition instanceof SettingDefinition) {
            return $default;
        }

        if (!$this->storageReadable) {
            return $definition->defaultValue();
        }

        try {
            $override = $this->repository->findOverride($namespace, $key);
        } catch (PDOException) {
            $this->storageReadable = false;

            return $definition->defaultValue();
        }

        if ($override === null) {
            return $definition->defaultValue();
        }

        try {
            return $this->deserializeOverride($definition, $override);
        } catch (SettingsException) {
            return $definition->defaultValue();
        }
    }

    public function set(
        string $namespace,
        string $key,
        mixed $value,
        ?string $type = null
    ): void {
        [$definition, $storedValue] = $this->prepareValue($namespace, $key, $value, $type);

        $this->repository->upsertOverride(
            $namespace,
            $key,
            $storedValue,
            $definition->type()
        );
    }

    public function validate(
        string $namespace,
        string $key,
        mixed $value,
        ?string $type = null
    ): void {
        $this->prepareValue($namespace, $key, $value, $type);
    }

    public function has(string $namespace, string $key): bool
    {
        return $this->registry->has($namespace, $key);
    }

    public function all(string $namespace): array
    {
        $values = [];

        foreach ($this->registry->all($namespace) as $definition) {
            $values[$definition->key()] = $this->get($namespace, $definition->key());
        }

        return $values;
    }

    public function delete(string $namespace, string $key): void
    {
        $this->requireDefinition($namespace, $key);
        $this->repository->deleteOverride($namespace, $key);
    }

    private function requireDefinition(string $namespace, string $key): SettingDefinition
    {
        $definition = $this->registry->find($namespace, $key);

        if (!$definition instanceof SettingDefinition) {
            throw new SettingsException("Unknown setting definition [{$namespace}.{$key}].");
        }

        return $definition;
    }

    private function prepareValue(
        string $namespace,
        string $key,
        mixed $value,
        ?string $type
    ): array {
        $definition = $this->requireDefinition($namespace, $key);

        if ($type !== null && $type !== $definition->type()) {
            throw new SettingsException("Setting type does not match definition [{$definition->identifier()}].");
        }

        [$effectiveValue, $storedValue] = $this->serializeValue($definition->type(), $value);
        $definition->validate($effectiveValue);

        return [$definition, $storedValue];
    }

    private function serializeValue(string $type, mixed $value): array
    {
        $this->assertSupportedType($type);

        return match ($type) {
            'string' => $this->serializeString($value),
            'integer' => $this->serializeInteger($value),
            'boolean' => $this->serializeBoolean($value),
            'float' => $this->serializeFloat($value),
            'json' => $this->serializeJson($value),
        };
    }

    private function deserializeOverride(SettingDefinition $definition, array $override): mixed
    {
        $storedType = $override['value_type'] ?? null;
        $storedValue = $override['setting_value'] ?? null;

        if (!is_string($storedType) || !is_string($storedValue)) {
            throw new SettingsException("Corrupted stored setting [{$definition->identifier()}].");
        }

        $this->assertSupportedType($storedType);

        if ($storedType !== $definition->type()) {
            throw new SettingsException("Stored type mismatch for setting [{$definition->identifier()}].");
        }

        $value = match ($storedType) {
            'string' => $storedValue,
            'integer' => $this->deserializeInteger($storedValue),
            'boolean' => $this->deserializeBoolean($storedValue),
            'float' => $this->deserializeFloat($storedValue),
            'json' => $this->deserializeJson($storedValue),
        };

        $definition->validate($value);

        return $value;
    }

    private function serializeString(mixed $value): array
    {
        if (!is_string($value)) {
            throw new SettingsException('String setting value must be a string.');
        }

        return [$value, $value];
    }

    private function serializeInteger(mixed $value): array
    {
        if (is_int($value)) {
            return [$value, (string) $value];
        }

        if (!is_string($value) || !preg_match('/^[+-]?[0-9]+$/', $value)) {
            throw new SettingsException('Integer setting value must be an integer or valid integer string.');
        }

        $negative = str_starts_with($value, '-');
        $digits = ltrim($value, '+-');
        $digits = ltrim($digits, '0');
        $digits = $digits === '' ? '0' : $digits;
        $canonical = $negative && $digits !== '0' ? '-' . $digits : $digits;
        $integer = filter_var($canonical, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);

        if (!is_int($integer)) {
            throw new SettingsException('Integer setting value is outside the supported integer range.');
        }

        return [$integer, (string) $integer];
    }

    private function serializeBoolean(mixed $value): array
    {
        if (is_bool($value)) {
            return [$value, $value ? '1' : '0'];
        }

        if (!is_string($value) || !in_array($value, ['1', '0', 'true', 'false'], true)) {
            throw new SettingsException('Boolean setting value must be a boolean or a controlled boolean string.');
        }

        $boolean = in_array($value, ['1', 'true'], true);

        return [$boolean, $boolean ? '1' : '0'];
    }

    private function serializeFloat(mixed $value): array
    {
        if (!is_int($value) && !is_float($value) && !(is_string($value) && is_numeric($value))) {
            throw new SettingsException('Float setting value must be an integer, float, or finite numeric string.');
        }

        $float = (float) $value;

        if (!is_finite($float)) {
            throw new SettingsException('Float setting value must be finite.');
        }

        try {
            $stored = json_encode($float, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        } catch (JsonException) {
            throw new SettingsException('Float setting value could not be serialized.');
        }

        return [$float, $stored];
    }

    private function serializeJson(mixed $value): array
    {
        try {
            $stored = json_encode($value, JSON_THROW_ON_ERROR);
            $decoded = json_decode($stored, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new SettingsException('JSON setting value must be JSON-compatible.');
        }

        return [$decoded, $stored];
    }

    private function deserializeInteger(string $value): int
    {
        [$integer] = $this->serializeInteger($value);

        return $integer;
    }

    private function deserializeBoolean(string $value): bool
    {
        if (!in_array($value, ['1', '0'], true)) {
            throw new SettingsException('Stored boolean setting is not canonical.');
        }

        return $value === '1';
    }

    private function deserializeFloat(string $value): float
    {
        [$float] = $this->serializeFloat($value);

        return $float;
    }

    private function deserializeJson(string $value): mixed
    {
        try {
            return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new SettingsException('Stored JSON setting is malformed.');
        }
    }

    private function assertSupportedType(string $type): void
    {
        if (!in_array($type, self::TYPES, true)) {
            throw new SettingsException("Unsupported settings type [{$type}].");
        }
    }
}
