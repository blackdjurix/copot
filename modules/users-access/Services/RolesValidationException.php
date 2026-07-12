<?php

class RolesValidationException extends RuntimeException
{
    private array $errors;
    private array $safeValues;

    public function __construct(array $errors, array $safeValues = [])
    {
        parent::__construct('Role validation failed.');
        $this->errors = $this->normalizeErrors($errors);
        $this->safeValues = $this->normalizeSafeValues($safeValues);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function safeValues(): array
    {
        return $this->safeValues;
    }

    private function normalizeErrors(array $errors): array
    {
        $normalized = [];

        foreach ($errors as $field => $message) {
            if (is_string($field) && is_string($message) && trim($field) !== '' && trim($message) !== '') {
                $normalized[$field] = trim($message);
            }
        }

        return $normalized;
    }

    private function normalizeSafeValues(array $values): array
    {
        $safe = [];

        foreach (['name', 'slug'] as $field) {
            if (isset($values[$field]) && is_string($values[$field])) {
                $safe[$field] = $values[$field];
            }
        }

        return $safe;
    }
}
