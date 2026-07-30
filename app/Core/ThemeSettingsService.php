<?php

namespace Copot\Core;

use Throwable;

final class ThemeSettingsService
{
    public function __construct(private SettingsRepository $repository, private Database $database)
    {
    }

    public function fields(ThemeDefinition $theme): array
    {
        return $this->definitionSet($theme)['fields'];
    }

    public function values(ThemeDefinition $theme): array
    {
        $set = $this->definitionSet($theme);
        $service = new SettingsService($set['registry'], $this->repository);
        $values = [];
        foreach ($set['definitions'] as $key => $definition) {
            $values[$key] = $service->get($definition->namespace(), $key);
        }
        return $values;
    }

    public function save(ThemeDefinition $theme, array $submitted): array
    {
        $set = $this->definitionSet($theme);
        $service = new SettingsService($set['registry'], $this->repository);
        $values = [];
        $errors = [];

        foreach ($set['definitions'] as $key => $definition) {
            $field = $this->fieldFor($set['fields'], $key);
            if (!array_key_exists($key, $submitted) && (($field['control'] ?? null) === 'checkbox')) {
                $submitted[$key] = false;
            }
            if (!array_key_exists($key, $submitted) || is_array($submitted[$key]) || is_object($submitted[$key])) {
                $errors[$key] = 'Enter a valid value.';
                continue;
            }
            try {
                $values[$key] = $this->normalize($definition, $submitted[$key]);
                $service->validate($definition->namespace(), $key, $values[$key]);
            } catch (Throwable) {
                $errors[$key] = 'Enter a valid value.';
            }
        }
        foreach ($submitted as $key => $_) {
            if (!is_string($key) || !isset($set['definitions'][$key])) {
                $errors[(string) $key] = 'This setting is not available.';
            }
        }
        if ($errors !== []) {
            throw new ThemeSettingsValidationException($errors, $submitted);
        }

        $connection = $this->database->connection();
        try {
            $connection->beginTransaction();
            foreach ($values as $key => $value) {
                $definition = $set['definitions'][$key];
                $service->set($definition->namespace(), $key, $value);
            }
            $effective = $this->values($theme);
            if ($effective !== $values) {
                throw new SettingsException('Theme settings postcondition failed.');
            }
            $connection->commit();
            return $effective;
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) $connection->rollBack();
            throw $exception instanceof ThemeSettingsValidationException ? $exception : new SettingsException('Theme settings could not be saved.', 0, $exception);
        }
    }

    public function reset(ThemeDefinition $theme): void
    {
        $namespace = ThemeSettingsStorage::namespaceFor($theme->id());
        $connection = $this->database->connection();
        try {
            $connection->beginTransaction();
            $this->repository->deleteNamespace($namespace);
            if ($this->values($theme) !== $this->defaults($theme)) throw new SettingsException('Theme settings reset postcondition failed.');
            $connection->commit();
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) $connection->rollBack();
            throw $exception instanceof SettingsException ? $exception : new SettingsException('Theme settings could not be reset.', 0, $exception);
        }
    }

    public function defaults(ThemeDefinition $theme): array
    {
        $values = [];
        foreach ($this->definitionSet($theme)['definitions'] as $key => $definition) $values[$key] = $definition->defaultValue();
        return $values;
    }

    public function resolveMetadata(string $themeId, array $metadata): array
    {
        $set = $this->definitionSetFromMetadata($themeId, $metadata);
        $service = new SettingsService($set['registry'], $this->repository);
        $values = [];
        foreach ($set['definitions'] as $key => $definition) $values[$key] = $service->get($definition->namespace(), $key);
        return $values;
    }

    private function definitionSet(ThemeDefinition $theme): array
    {
        return $this->definitionSetFromMetadata($theme->id(), $theme->metadata());
    }

    private function definitionSetFromMetadata(string $themeId, array $metadata): array
    {
        $namespace = ThemeSettingsStorage::namespaceFor($themeId);
        $registry = new SettingsRegistry();
        $definitions = [];
        $fields = [];
        $settings = $metadata['settings'] ?? [];
        if (!is_array($settings) || !is_array($settings['sections'] ?? null)) return ['registry' => $registry, 'definitions' => [], 'fields' => []];
        foreach ($settings['sections'] as $section) {
            if (!is_array($section)) continue;
            $normalizedFields = [];
            foreach (($section['fields'] ?? []) as $field) {
                if (!is_array($field) || !is_string($field['key'] ?? null)) continue;
                $key = $field['key']; $validation = is_array($field['validation'] ?? null) ? $field['validation'] : [];
                $type = $field['type'] ?? ''; $default = $field['default'] ?? null;
                $validator = static function (mixed $value) use ($validation, $type): bool {
                    if (($validation['required'] ?? false) && $type === 'string' && trim((string) $value) === '') return false;
                    if (isset($validation['min']) && $value < $validation['min']) return false;
                    if (isset($validation['max']) && $value > $validation['max']) return false;
                    if (isset($validation['max_length']) && strlen((string) $value) > $validation['max_length']) return false;
                    if (isset($validation['format']) && $validation['format'] === 'hex_color' && !preg_match('/^#[0-9a-f]{3,4}(?:[0-9a-f]{2})?$/i', (string) $value)) return false;
                    return true;
                };
                try {
                    $definition = new SettingDefinition($namespace, $key, $type, $default, $validator, is_array($validation['allowed_values'] ?? null) ? $validation['allowed_values'] : [], ['control' => $field['control'] ?? 'text']);
                    $registry->register($definition); $definitions[$key] = $definition;
                    $normalizedFields[] = $field;
                } catch (Throwable $exception) {
                    throw new SettingsException('Theme settings metadata is invalid.', 0, $exception);
                }
            }
            if ($normalizedFields !== []) $fields[] = ['id' => (string) ($section['id'] ?? ''), 'label' => (string) ($section['label'] ?? ''), 'description' => $section['description'] ?? null, 'fields' => $normalizedFields];
        }
        usort($fields, static fn ($a, $b) => $a['id'] <=> $b['id']);
        return ['registry' => $registry, 'definitions' => $definitions, 'fields' => $fields];
    }

    private function normalize(SettingDefinition $definition, mixed $value): mixed
    {
        return match ($definition->type()) {
            'string' => is_string($value) ? $value : throw new SettingsException('Invalid string.'),
            'integer' => is_int($value) ? $value : (is_string($value) && preg_match('/^-?(0|[1-9][0-9]*)$/D', $value) ? (int) $value : throw new SettingsException('Invalid integer.')),
            'float' => is_float($value) || is_int($value) ? (float) $value : (is_string($value) && is_numeric($value) ? (float) $value : throw new SettingsException('Invalid number.')),
            'boolean' => is_bool($value) ? $value : (is_string($value) && in_array(strtolower($value), ['0','1','false','true'], true) ? in_array(strtolower($value), ['1','true'], true) : throw new SettingsException('Invalid boolean.')),
            default => throw new SettingsException('Unsupported Theme setting type.'),
        };
    }

    private function fieldFor(array $sections, string $key): array
    {
        foreach ($sections as $section) foreach (($section['fields'] ?? []) as $field) if (($field['key'] ?? null) === $key) return $field;
        return [];
    }
}
