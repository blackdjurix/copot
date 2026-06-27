<?php

namespace Copot\Core;

class SettingsRepository
{
    private const NAMESPACE_PATTERN = '/^[a-z][a-z0-9_-]{0,63}$/';
    private const KEY_PATTERN = '/^[a-z][a-z0-9_-]{0,127}$/';

    public function __construct(private Database $database)
    {
    }

    public function findOverride(string $namespace, string $key): ?array
    {
        $this->validateIdentifiers($namespace, $key);

        $statement = $this->database->connection()->prepare(
            'SELECT * FROM settings
            WHERE namespace = :namespace AND setting_key = :setting_key
            LIMIT 1'
        );

        $statement->execute([
            'namespace' => $namespace,
            'setting_key' => $key,
        ]);
        $override = $statement->fetch();

        return is_array($override) ? $override : null;
    }

    public function upsertOverride(
        string $namespace,
        string $key,
        string $storedValue,
        string $valueType
    ): void {
        $this->validateIdentifiers($namespace, $key);
        $this->validateValueType($valueType);

        $statement = $this->database->connection()->prepare(
            'INSERT INTO settings (
                namespace,
                setting_key,
                setting_value,
                value_type,
                created_at,
                updated_at
            ) VALUES (
                :namespace,
                :setting_key,
                :setting_value,
                :value_type,
                NOW(),
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                value_type = VALUES(value_type),
                updated_at = NOW()'
        );

        $statement->execute([
            'namespace' => $namespace,
            'setting_key' => $key,
            'setting_value' => $storedValue,
            'value_type' => $valueType,
        ]);
    }

    public function hasOverride(string $namespace, string $key): bool
    {
        $this->validateIdentifiers($namespace, $key);

        $statement = $this->database->connection()->prepare(
            'SELECT 1 FROM settings
            WHERE namespace = :namespace AND setting_key = :setting_key
            LIMIT 1'
        );

        $statement->execute([
            'namespace' => $namespace,
            'setting_key' => $key,
        ]);

        return $statement->fetchColumn() !== false;
    }

    public function allOverrides(string $namespace): array
    {
        $this->validateNamespace($namespace);

        $statement = $this->database->connection()->prepare(
            'SELECT * FROM settings
            WHERE namespace = :namespace
            ORDER BY setting_key ASC'
        );

        $statement->execute(['namespace' => $namespace]);

        return $statement->fetchAll();
    }

    public function deleteOverride(string $namespace, string $key): void
    {
        $this->validateIdentifiers($namespace, $key);

        $statement = $this->database->connection()->prepare(
            'DELETE FROM settings
            WHERE namespace = :namespace AND setting_key = :setting_key'
        );

        $statement->execute([
            'namespace' => $namespace,
            'setting_key' => $key,
        ]);
    }

    private function validateIdentifiers(string $namespace, string $key): void
    {
        $this->validateNamespace($namespace);
        $this->validateKey($key);
    }

    private function validateNamespace(string $namespace): void
    {
        if (!preg_match(self::NAMESPACE_PATTERN, $namespace)) {
            throw new SettingsException("Invalid settings namespace [{$namespace}].");
        }
    }

    private function validateKey(string $key): void
    {
        if (!preg_match(self::KEY_PATTERN, $key)) {
            throw new SettingsException("Invalid settings key [{$key}].");
        }
    }

    private function validateValueType(string $valueType): void
    {
        if ($valueType === '' || strlen($valueType) > 20) {
            throw new SettingsException('Settings value type must be a non-empty string of at most 20 characters.');
        }
    }
}
