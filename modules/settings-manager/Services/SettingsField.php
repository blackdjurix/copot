<?php

final class SettingsField
{
    private const VALUE_TYPES = ['string', 'integer', 'boolean', 'float'];
    private const FIELD_TYPES = ['text', 'number', 'checkbox', 'select'];

    public function __construct(
        private string $identifier,
        private string $namespace,
        private string $key,
        private string $valueType,
        private string $fieldType,
        private string $label,
        private ?string $description,
        private bool $required,
        private ?int $maximumLength,
        private array $options,
        private mixed $defaultValue
    ) {
        if (!preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $namespace)
            || !preg_match('/^[a-z][a-z0-9_-]{0,127}$/', $key)
            || $identifier !== $namespace . '.' . $key) {
            throw new LogicException('Settings field identifier is inconsistent.');
        }

        if (!in_array($valueType, self::VALUE_TYPES, true)
            || !in_array($fieldType, self::FIELD_TYPES, true)
            || trim($label) === '') {
            throw new LogicException('Settings field contract is invalid.');
        }

        if (!$this->valueMatchesType($defaultValue, $valueType)) {
            throw new LogicException('Settings field default value is incompatible with its value type.');
        }

        if ($maximumLength !== null
            && ($maximumLength < 1 || $valueType !== 'string' || $fieldType !== 'text')) {
            throw new LogicException('Settings field maximum length is invalid.');
        }

        if (($fieldType === 'select') !== ($options !== [])) {
            throw new LogicException('Settings field options are inconsistent with its field type.');
        }

        $compatibleFieldType = match ($fieldType) {
            'text' => $valueType === 'string',
            'number' => in_array($valueType, ['integer', 'float'], true),
            'checkbox' => $valueType === 'boolean',
            'select' => $valueType !== 'boolean',
        };

        if (!$compatibleFieldType) {
            throw new LogicException('Settings field type is incompatible with its value type.');
        }

        $seenOptions = [];

        foreach ($options as $option) {
            if (!$this->valueMatchesType($option, $valueType)) {
                throw new LogicException('Settings field option is incompatible with its value type.');
            }

            $identity = get_debug_type($option) . ':' . var_export($option, true);

            if (isset($seenOptions[$identity])) {
                throw new LogicException('Settings field options must be strictly unique.');
            }

            $seenOptions[$identity] = true;
        }
    }

    public function identifier(): string { return $this->identifier; }
    public function namespace(): string { return $this->namespace; }
    public function key(): string { return $this->key; }
    public function valueType(): string { return $this->valueType; }
    public function fieldType(): string { return $this->fieldType; }
    public function label(): string { return $this->label; }
    public function description(): ?string { return $this->description; }
    public function required(): bool { return $this->required; }
    public function maximumLength(): ?int { return $this->maximumLength; }
    public function options(): array { return $this->options; }
    public function defaultValue(): mixed { return $this->defaultValue; }

    private function valueMatchesType(mixed $value, string $type): bool
    {
        return match ($type) {
            'string' => is_string($value),
            'integer' => is_int($value),
            'boolean' => is_bool($value),
            'float' => is_float($value) && is_finite($value),
            default => false,
        };
    }
}
