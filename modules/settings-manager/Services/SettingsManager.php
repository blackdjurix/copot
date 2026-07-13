<?php

use Copot\Core\Database;
use Copot\Core\SettingsException;
use Copot\Core\SettingsService;

final class SettingsManager
{
    private const SAVEPOINT_PREFIX = 'settings_manager_save_';
    private static int $savepointCounter = 0;

    public function __construct(
        private SettingsService $settings,
        private SettingsFieldMapper $mapper,
        private Database $database
    ) {
    }

    public function sections(): array
    {
        return $this->mapper->sections($this->settings->definitions());
    }

    public function save(array $submitted): void
    {
        $fields = [];

        foreach ($this->sections() as $section) {
            foreach ($section->fields() as $field) {
                $fields[$field->identifier()] = $field;
            }
        }

        $fieldErrors = [];
        $formErrors = [];
        $submittedValues = [];
        $candidates = [];
        $unknown = array_values(array_diff(array_keys($submitted), array_keys($fields)));
        sort($unknown, SORT_STRING);

        if ($unknown !== []) {
            $formErrors[] = 'The submitted settings contain unknown or uneditable fields.';
        }

        foreach ($fields as $identifier => $field) {
            $present = array_key_exists($identifier, $submitted);

            if (!$present && $field->fieldType() === 'checkbox') {
                $candidate = false;
            } elseif (!$present) {
                if ($field->required()) {
                    $fieldErrors[$identifier][] = 'This setting is missing.';
                }

                continue;
            } else {
                $candidate = $submitted[$identifier];
            }

            if (!$this->isSafeSubmittedValue($candidate)) {
                $fieldErrors[$identifier][] = 'The submitted value is invalid.';
                continue;
            }

            if ($field->fieldType() === 'checkbox') {
                $normalized = $this->normalizeBoolean($candidate);

                if ($normalized === null) {
                    $submittedValues[$identifier] = $candidate;
                    $fieldErrors[$identifier][] = 'The submitted value is invalid.';
                    continue;
                }

                $candidate = $normalized;
            }

            $submittedValues[$identifier] = $candidate;
            $candidates[$identifier] = $field;

            try {
                $this->settings->validate($field->namespace(), $field->key(), $candidate, $field->valueType());
            } catch (SettingsException) {
                $fieldErrors[$identifier][] = 'The submitted value is invalid.';
            }
        }

        if ($fieldErrors !== [] || $formErrors !== []) {
            throw new SettingsValidationException($fieldErrors, $formErrors, $submittedValues);
        }

        $connection = $this->database->connection();
        $ownsTransaction = !$connection->inTransaction();
        $savepoint = null;

        if ($ownsTransaction) {
            $connection->beginTransaction();
        } else {
            $savepoint = $this->nextSavepoint();
            $connection->exec('SAVEPOINT ' . $savepoint);
        }

        try {
            foreach ($candidates as $identifier => $field) {
                $this->settings->set(
                    $field->namespace(),
                    $field->key(),
                    $submittedValues[$identifier],
                    $field->valueType()
                );
            }

            if ($ownsTransaction) {
                $connection->commit();
            } else {
                $connection->exec('RELEASE SAVEPOINT ' . $savepoint);
            }
        } catch (Throwable $failure) {
            try {
                if ($ownsTransaction) {
                    if ($connection->inTransaction()) {
                        $connection->rollBack();
                    }
                } elseif ($connection->inTransaction()) {
                    $connection->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
                    $connection->exec('RELEASE SAVEPOINT ' . $savepoint);
                }
            } catch (Throwable) {
                throw new RuntimeException('Settings Manager transaction cleanup failed.', 0, $failure);
            }

            throw $failure;
        }
    }

    private function nextSavepoint(): string
    {
        self::$savepointCounter++;

        return self::SAVEPOINT_PREFIX . self::$savepointCounter . '_' . bin2hex(random_bytes(6));
    }

    private function isSafeSubmittedValue(mixed $value): bool
    {
        return is_string($value)
            || is_int($value)
            || is_bool($value)
            || (is_float($value) && is_finite($value));
    }

    private function normalizeBoolean(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (!is_string($value) || !in_array($value, ['1', '0', 'true', 'false'], true)) {
            return null;
        }

        return in_array($value, ['1', 'true'], true);
    }
}
