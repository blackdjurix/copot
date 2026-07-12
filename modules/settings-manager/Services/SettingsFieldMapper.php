<?php

use Copot\Core\SettingDefinition;

final class SettingsFieldMapper
{
    public function __construct(private SettingsManagerPolicy $policy)
    {
    }

    public function sections(array $definitions): array
    {
        $registered = [];

        foreach ($definitions as $definition) {
            if (!$definition instanceof SettingDefinition) {
                throw new LogicException('Settings field mapping accepts registered definitions only.');
            }

            if (isset($registered[$definition->identifier()])) {
                throw new LogicException('Registered setting identifiers must be unique.');
            }

            $registered[$definition->identifier()] = $definition;
        }

        $fieldsBySection = [];

        foreach ($this->policy->fields() as $fieldPolicy) {
            $identifier = $fieldPolicy['identifier'];

            if (!isset($registered[$identifier])) {
                throw new LogicException('Settings Manager policy references an unregistered definition.');
            }

            $field = $this->mapField($registered[$identifier], $fieldPolicy);
            $fieldsBySection[$fieldPolicy['section']][] = $field;
        }

        $sections = [];

        foreach ($this->policy->sections() as $sectionPolicy) {
            $fields = $fieldsBySection[$sectionPolicy['identifier']] ?? [];

            if ($fields === []) {
                continue;
            }

            $sections[] = new SettingsSection(
                $sectionPolicy['identifier'],
                $sectionPolicy['label'],
                $sectionPolicy['description'],
                $fields
            );
        }

        return $sections;
    }

    private function mapField(SettingDefinition $definition, array $policy): SettingsField
    {
        $type = $definition->type();

        if ($type === 'json') {
            throw new LogicException('JSON settings are not supported by generic Settings Manager fields.');
        }

        if (!in_array($type, ['string', 'integer', 'boolean', 'float'], true)) {
            throw new LogicException('Settings Manager definition type is unsupported.');
        }

        $metadata = $definition->metadata();
        $unknownMetadata = array_diff(array_keys($metadata), ['max_length']);

        if ($unknownMetadata !== []) {
            throw new LogicException('Settings Manager definition metadata is unsupported.');
        }

        $maximumLength = $metadata['max_length'] ?? null;

        if ($maximumLength !== null && (!is_int($maximumLength) || $maximumLength < 1)) {
            throw new LogicException('Settings Manager max_length metadata is invalid.');
        }

        $allowedValues = $definition->allowedValues();
        $this->validateAllowedValues($type, $allowedValues);
        $constraints = $policy['constraints'];
        $optionSource = $constraints['option_source'] ?? null;
        $options = $allowedValues;

        if ($optionSource === 'timezone_identifiers') {
            if ($type !== 'string' || $allowedValues !== []) {
                throw new LogicException('Timezone option source is incompatible with its definition.');
            }

            $options = array_values(array_filter(
                timezone_identifiers_list(),
                static fn (string $timezone): bool => $timezone !== 'UTC'
            ));
            sort($options, SORT_STRING);
            array_unshift($options, 'UTC');
        }

        $inferred = match ($type) {
            'string' => $options === [] ? 'text' : 'select',
            'integer', 'float' => $options === [] ? 'number' : 'select',
            'boolean' => $allowedValues === [] ? 'checkbox' : throw new LogicException(
                'Boolean settings with allowed values are unsupported.'
            ),
        };
        $fieldType = $policy['presentation'] ?? $inferred;

        if ($fieldType !== $inferred) {
            throw new LogicException('Settings Manager presentation override is incompatible with its definition.');
        }

        if ($maximumLength !== null && !($type === 'string' && $fieldType === 'text')) {
            throw new LogicException('Settings Manager max_length metadata applies to string text fields only.');
        }

        return new SettingsField(
            $definition->identifier(),
            $definition->namespace(),
            $definition->key(),
            $type,
            $fieldType,
            $policy['label'],
            $policy['description'],
            $policy['required'],
            $maximumLength,
            $options,
            $definition->defaultValue()
        );
    }

    private function validateAllowedValues(string $type, array $values): void
    {
        $seen = [];

        foreach ($values as $value) {
            $compatible = match ($type) {
                'string' => is_string($value),
                'integer' => is_int($value),
                'float' => is_float($value),
                'boolean' => is_bool($value),
                default => false,
            };

            if (!$compatible || !is_scalar($value)) {
                throw new LogicException('Settings Manager allowed value is incompatible with its definition.');
            }

            $identity = get_debug_type($value) . ':' . var_export($value, true);

            if (isset($seen[$identity])) {
                throw new LogicException('Settings Manager allowed values must be strictly unique.');
            }

            $seen[$identity] = true;
        }
    }
}
