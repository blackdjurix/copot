<?php

final class FormDefinitionValidator
{
    public const FORM_STATES = ['draft', 'published', 'disabled'];
    public const FIELD_TYPES = ['text', 'email', 'textarea', 'select', 'checkbox'];
    public const MAX_FIELDS = 50;
    public const MAX_OPTIONS = 100;
    public const MAX_VALUE_LENGTH = 4000;

    public function definition(mixed $name, mixed $status, mixed $fields): array
    {
        $name = $this->text($name, 150, 'Form name');
        $this->state($status);
        if (!is_array($fields)) throw new InvalidArgumentException('Field definitions are invalid.');
        if (count($fields) > self::MAX_FIELDS) throw new InvalidArgumentException('Too many form fields.');
        $keys = []; $orders = []; $normalized = [];
        foreach ($fields as $field) {
            if (!is_array($field)) throw new InvalidArgumentException('Field definition is invalid.');
            $item = $this->field($field);
            if (isset($keys[$item['field_key']]) || isset($orders[$item['sort_order']])) throw new InvalidArgumentException('Field keys and sort orders must be unique within a form.');
            $keys[$item['field_key']] = true; $orders[$item['sort_order']] = true; $normalized[] = $item;
        }
        usort($normalized, static fn (array $a, array $b): int => $a['sort_order'] <=> $b['sort_order']);
        return ['name' => $name, 'status' => $status, 'fields' => $normalized];
    }

    public function state(mixed $status): string
    {
        if (!is_string($status) || !in_array($status, self::FORM_STATES, true)) throw new InvalidArgumentException('Form status is invalid.');
        return $status;
    }

    public function field(array $field): array
    {
        $key = $field['field_key'] ?? null; $type = $field['field_type'] ?? null;
        if (!is_string($key) || preg_match('/^[a-z][a-z0-9_]{0,99}$/D', $key) !== 1) throw new InvalidArgumentException('Field key is invalid.');
        if (!is_string($type) || !in_array($type, self::FIELD_TYPES, true)) throw new InvalidArgumentException('Field type is invalid.');
        $label = $this->text($field['label'] ?? null, 150, 'Field label');
        $sort = $this->nonNegativeInt($field['sort_order'] ?? null, 'Field sort order'); if ($sort > 1000000) throw new InvalidArgumentException('Field sort order is invalid.');
        $required = $this->boolean($field['is_required'] ?? null);
        $min = $this->nullableLength($field['min_length'] ?? null, 'Minimum length');
        $max = $this->nullableLength($field['max_length'] ?? null, 'Maximum length');
        if ($min !== null && $max !== null && $min > $max) throw new InvalidArgumentException('Minimum length cannot exceed maximum length.');
        if (in_array($type, ['select', 'checkbox'], true) && ($min !== null || $max !== null)) throw new InvalidArgumentException('Length constraints are not valid for this field type.');
        $options = $field['options'] ?? [];
        if (!is_array($options)) throw new InvalidArgumentException('Field options are invalid.');
        if ($type !== 'select' && $options !== []) throw new InvalidArgumentException('Only select fields may have options.');
        if ($type === 'select' && $options === []) throw new InvalidArgumentException('Select fields require options.');
        if (count($options) > self::MAX_OPTIONS) throw new InvalidArgumentException('Too many select options.');
        $values = []; $orders = []; $normalizedOptions = [];
        foreach ($options as $option) {
            if (!is_array($option)) throw new InvalidArgumentException('Select option is invalid.');
            $value = $this->text($option['option_value'] ?? null, 100, 'Option value');
            if (preg_match('/\s/', $value) === 1) throw new InvalidArgumentException('Option value must not contain whitespace.');
            $optionLabel = $this->text($option['option_label'] ?? null, 150, 'Option label');
            $optionOrder = $this->nonNegativeInt($option['sort_order'] ?? null, 'Option sort order'); if ($optionOrder > 1000000) throw new InvalidArgumentException('Option sort order is invalid.');
            if (isset($values[$value]) || isset($orders[$optionOrder])) throw new InvalidArgumentException('Option values and sort orders must be unique.');
            $values[$value] = true; $orders[$optionOrder] = true; $normalizedOptions[] = compact('value', 'optionLabel', 'optionOrder');
        }
        usort($normalizedOptions, static fn (array $a, array $b): int => $a['optionOrder'] <=> $b['optionOrder']);
        return ['field_key' => $key, 'label' => $label, 'field_type' => $type, 'sort_order' => $sort, 'is_required' => $required ? 1 : 0, 'min_length' => $min, 'max_length' => $max, 'options' => array_map(static fn (array $o): array => ['option_value' => $o['value'], 'option_label' => $o['optionLabel'], 'sort_order' => $o['optionOrder']], $normalizedOptions)];
    }

    private function text(mixed $value, int $max, string $label): string
    {
        if (!is_string($value)) throw new InvalidArgumentException($label . ' is invalid.');
        $value = trim($value);
        if ($value === '' || strlen($value) > $max || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) throw new InvalidArgumentException($label . ' is invalid.');
        return $value;
    }
    private function nonNegativeInt(mixed $value, string $label): int { if (!is_int($value) || $value < 0) throw new InvalidArgumentException($label . ' is invalid.'); return $value; }
    private function nullableLength(mixed $value, string $label): ?int { if ($value === null) return null; if (!is_int($value) || $value < 0 || $value > self::MAX_VALUE_LENGTH) throw new InvalidArgumentException($label . ' is invalid.'); return $value; }
    private function boolean(mixed $value): bool { if (!is_bool($value) && !in_array($value, [0, 1], true)) throw new InvalidArgumentException('Required state is invalid.'); return $value === true || $value === 1; }
}
