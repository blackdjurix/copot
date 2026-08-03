<?php

final class SubmissionValueValidator
{
    /** @param FormField[] $fields */
    public function values(array $fields, array $input): array
    {
        $byKey = []; foreach ($fields as $field) $byKey[$field->key()] = $field;
        $result = [];
        foreach ($input as $key => $value) {
            if (!is_string($key) || !isset($byKey[$key])) throw new InvalidArgumentException('Submission contains an unknown field.');
            if (isset($result[$key])) throw new InvalidArgumentException('Submission contains a duplicate field.');
            $result[$key] = $this->value($byKey[$key], $value);
        }
        foreach ($byKey as $key => $field) {
            if (!isset($result[$key])) {
                if ($field->required()) throw new InvalidArgumentException('A required field is missing.');
                $result[$key] = $this->value($field, null);
            }
        }
        return $result;
    }

    private function value(FormField $field, mixed $value): array
    {
        if ($field->type() === 'checkbox') {
            if (!is_bool($value) && !in_array($value, [0, 1, '0', '1'], true) && $value !== null) throw new InvalidArgumentException('Checkbox value is invalid.');
            $normalized = ($value === true || $value === 1 || $value === '1') ? '1' : '0';
            if ($field->required() && $normalized !== '1') throw new InvalidArgumentException('A required checkbox must be checked.');
            return $this->snapshot($field, $normalized, null);
        }
        if (!is_string($value)) { if ($value === null && !$field->required()) return $this->snapshot($field, '', null); throw new InvalidArgumentException('Submission value is invalid.'); }
        $controlPattern = $field->type() === 'textarea' ? '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/' : '/[\x00-\x1F\x7F]/';
        if (preg_match($controlPattern, $value) === 1) throw new InvalidArgumentException('Submission value contains controls.');
        $normalized = $field->type() === 'textarea' ? str_replace(["\r\n", "\r"], "\n", $value) : trim($value);
        if ($field->required() && $normalized === '') throw new InvalidArgumentException('A required field is missing.');
        if (strlen($normalized) > FormDefinitionValidator::MAX_VALUE_LENGTH) throw new InvalidArgumentException('Submission value is too long.');
        if ($field->minLength() !== null && strlen($normalized) < $field->minLength()) throw new InvalidArgumentException('Submission value is too short.');
        if ($field->maxLength() !== null && strlen($normalized) > $field->maxLength()) throw new InvalidArgumentException('Submission value is too long.');
        if ($field->type() === 'email') { $normalized = strtolower($normalized); if ($normalized !== '' && (strlen($normalized) > 190 || filter_var($normalized, FILTER_VALIDATE_EMAIL) === false)) throw new InvalidArgumentException('Email value is invalid.'); }
        if ($field->type() === 'select') { foreach ($field->options() as $option) if ($option->value() === $normalized) return $this->snapshot($field, $normalized, $option->label()); throw new InvalidArgumentException('Select value is invalid.'); }
        return $this->snapshot($field, $normalized, null);
    }
    private function snapshot(FormField $field, string $text, ?string $label): array { return ['form_field_id' => $field->id()->value(), 'field_key' => $field->key(), 'field_label' => $field->label(), 'field_type' => $field->type(), 'value_text' => $text, 'value_label' => $label]; }
}
