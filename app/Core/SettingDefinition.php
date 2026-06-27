<?php

namespace Copot\Core;

use JsonException;

class SettingDefinition
{
    private const NAMESPACE_PATTERN = '/^[a-z][a-z0-9_-]{0,63}$/';
    private const KEY_PATTERN = '/^[a-z][a-z0-9_-]{0,127}$/';
    private const TYPES = ['string', 'integer', 'boolean', 'float', 'json'];

    private $validator;

    public function __construct(
        private string $namespace,
        private string $key,
        private string $type,
        private mixed $default,
        ?callable $validator = null,
        private array $allowedValues = [],
        private array $metadata = []
    ) {
        if (!preg_match(self::NAMESPACE_PATTERN, $namespace)) {
            throw new SettingsException("Invalid settings namespace [{$namespace}].");
        }

        if (!preg_match(self::KEY_PATTERN, $key)) {
            throw new SettingsException("Invalid settings key [{$key}].");
        }

        if (!in_array($type, self::TYPES, true)) {
            throw new SettingsException("Unsupported settings type [{$type}].");
        }

        $this->validator = $validator;
        $this->assertDefaultType();
        $this->validate($default);
    }

    public function namespace(): string
    {
        return $this->namespace;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function identifier(): string
    {
        return $this->namespace . '.' . $this->key;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function defaultValue(): mixed
    {
        return $this->default;
    }

    public function allowedValues(): array
    {
        return $this->allowedValues;
    }

    public function metadata(): array
    {
        return $this->metadata;
    }

    public function validate(mixed $value): void
    {
        if ($this->allowedValues !== [] && !in_array($value, $this->allowedValues, true)) {
            throw new SettingsException("Invalid value for setting [{$this->identifier()}].");
        }

        if ($this->validator !== null && !($this->validator)($value)) {
            throw new SettingsException("Invalid value for setting [{$this->identifier()}].");
        }
    }

    private function assertDefaultType(): void
    {
        $valid = match ($this->type) {
            'string' => is_string($this->default),
            'integer' => is_int($this->default),
            'boolean' => is_bool($this->default),
            'float' => is_float($this->default),
            'json' => $this->isJsonCompatible($this->default),
        };

        if (!$valid) {
            throw new SettingsException("Invalid default type for setting [{$this->identifier()}].");
        }
    }

    private function isJsonCompatible(mixed $value): bool
    {
        try {
            json_encode($value, JSON_THROW_ON_ERROR);

            return true;
        } catch (JsonException) {
            return false;
        }
    }
}
