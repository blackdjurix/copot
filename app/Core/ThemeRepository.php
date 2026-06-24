<?php

namespace Copot\Core;

use JsonException;

class ThemeRepository
{
    public function __construct(private Database $database)
    {
    }

    public function all(): array
    {
        $statement = $this->database->connection()->query(
            'SELECT * FROM themes ORDER BY theme_id ASC'
        );

        return $statement->fetchAll();
    }

    public function findByThemeId(string $themeId): ?array
    {
        $statement = $this->database->connection()->prepare(
            'SELECT * FROM themes WHERE theme_id = :theme_id LIMIT 1'
        );

        $statement->execute(['theme_id' => $themeId]);
        $theme = $statement->fetch();

        return is_array($theme) ? $theme : null;
    }

    public function exists(string $themeId): bool
    {
        return $this->findByThemeId($themeId) !== null;
    }

    public function register(array $theme): void
    {
        $theme = $this->normalizeThemePayload($theme);
        $existing = $this->findByThemeId($theme['theme_id']);

        if ($existing === null) {
            $this->create($theme);

            return;
        }

        $this->update($theme);
    }

    public function unregister(string $themeId): void
    {
        $theme = $this->findByThemeId($themeId);

        if ($theme !== null && (int) $theme['is_active'] === 1) {
            throw new ThemeException("Active theme [{$themeId}] cannot be unregistered.");
        }

        $statement = $this->database->connection()->prepare(
            'DELETE FROM themes WHERE theme_id = :theme_id'
        );

        $statement->execute(['theme_id' => $themeId]);
    }

    public function activeFrontend(): ?array
    {
        $statement = $this->database->connection()->prepare(
            'SELECT * FROM themes WHERE type = :type AND is_active = 1 ORDER BY id ASC LIMIT 1'
        );

        $statement->execute(['type' => 'frontend']);
        $theme = $statement->fetch();

        return is_array($theme) ? $theme : null;
    }

    public function deactivateByType(string $type): void
    {
        $statement = $this->database->connection()->prepare(
            'UPDATE themes SET is_active = 0, updated_at = NOW() WHERE type = :type'
        );

        $statement->execute(['type' => $type]);
    }

    public function activate(string $themeId): void
    {
        $statement = $this->database->connection()->prepare(
            'UPDATE themes SET is_active = 1, updated_at = NOW() WHERE theme_id = :theme_id'
        );

        $statement->execute(['theme_id' => $themeId]);
    }

    private function create(array $theme): void
    {
        $statement = $this->database->connection()->prepare(
            'INSERT INTO themes (
                theme_id,
                name,
                version,
                type,
                path,
                is_active,
                metadata,
                created_at,
                updated_at
            ) VALUES (
                :theme_id,
                :name,
                :version,
                :type,
                :path,
                0,
                :metadata,
                NOW(),
                NOW()
            )'
        );

        $statement->execute([
            'theme_id' => $theme['theme_id'],
            'name' => $theme['name'],
            'version' => $theme['version'],
            'type' => $theme['type'],
            'path' => $theme['path'],
            'metadata' => $this->encodeMetadata($theme['metadata']),
        ]);
    }

    private function update(array $theme): void
    {
        $statement = $this->database->connection()->prepare(
            'UPDATE themes
            SET name = :name,
                version = :version,
                type = :type,
                path = :path,
                metadata = :metadata,
                updated_at = NOW()
            WHERE theme_id = :theme_id'
        );

        $statement->execute([
            'theme_id' => $theme['theme_id'],
            'name' => $theme['name'],
            'version' => $theme['version'],
            'type' => $theme['type'],
            'path' => $theme['path'],
            'metadata' => $this->encodeMetadata($theme['metadata']),
        ]);
    }

    private function normalizeThemePayload(array $theme): array
    {
        foreach (['theme_id', 'name', 'version', 'type', 'path'] as $field) {
            if (!isset($theme[$field]) || !is_string($theme[$field]) || trim($theme[$field]) === '') {
                throw new ThemeException("Theme payload field [{$field}] must be a non-empty string.");
            }

            $theme[$field] = trim($theme[$field]);
        }

        if (!array_key_exists('metadata', $theme)) {
            $theme['metadata'] = null;
        }

        if ($theme['metadata'] !== null && !is_array($theme['metadata'])) {
            throw new ThemeException('Theme payload field [metadata] must be an array or null.');
        }

        return $theme;
    }

    private function encodeMetadata(?array $metadata): ?string
    {
        if ($metadata === null) {
            return null;
        }

        if ($metadata === []) {
            return '{}';
        }

        try {
            return json_encode($metadata, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ThemeException('Theme metadata could not be encoded as JSON.');
        }
    }
}
