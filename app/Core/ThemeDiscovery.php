<?php

namespace Copot\Core;

use JsonException;
use stdClass;

class ThemeDiscovery
{
    private const MAX_METADATA_BYTES = 262144;
    private const MAX_SCREENSHOT_BYTES = 8388608;
    private const SUPPORTED_CAPABILITIES = ['module_view_overrides', 'navigation_locations'];
    private const SCREENSHOT_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];
    private const SETTING_TYPES = ['string', 'integer', 'float', 'boolean'];
    private const SETTING_CONTROLS = ['text', 'number', 'checkbox', 'select', 'color'];
    private const SETTING_FORMATS = ['hex_color'];

    public function __construct(private string $themesPath)
    {
    }

    public function discover(): array
    {
        $catalog = $this->discoverCatalog();

        if ($catalog['errors'] !== []) {
            throw new ThemeException('Theme discovery failed.');
        }

        return $catalog['themes'];
    }

    /**
     * Discover all healthy themes while retaining bounded diagnostics for
     * individual invalid or unavailable entries.
     *
     * @return array{themes: list<ThemeDefinition>, errors: list<array{theme: ?string, status: string, code: string, message: string}>}
     */
    public function discoverCatalog(): array
    {
        $themesPath = realpath($this->themesPath);

        if ($themesPath === false || !is_dir($themesPath)) {
            return [
                'themes' => [],
                'errors' => [$this->failure(null, 'unavailable', 'themes_path_unavailable')],
            ];
        }

        $themes = [];
        $errors = [];
        $directories = glob(rtrim($themesPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [];

        foreach ($directories as $directory) {
            $themePath = realpath($directory);
            $themeIdentifier = $this->safeThemeIdentifier(basename($directory));

            if ($themePath === false || !is_dir($themePath)) {
                $errors[] = $this->failure($themeIdentifier, 'unavailable', 'theme_path_unavailable');
                continue;
            }

            if (!$this->isInsideDirectory($themePath, $themesPath)) {
                $errors[] = $this->failure($themeIdentifier, 'invalid', 'theme_path_escape');
                continue;
            }

            $themeJson = $themePath . DIRECTORY_SEPARATOR . 'theme.json';

            if (!is_file($themeJson)) {
                continue;
            }

            try {
                $theme = $this->loadTheme($themePath, $themeJson);
            } catch (\Throwable) {
                $errors[] = $this->failure($themeIdentifier, 'invalid', 'invalid_definition');
                continue;
            }

            if (isset($themes[$theme->id()])) {
                $errors[] = $this->failure($theme->id(), 'invalid', 'duplicate_theme_id');
                continue;
            }

            $themes[$theme->id()] = $theme;
        }

        ksort($themes);

        usort($errors, static function (array $left, array $right): int {
            return [(string) ($left['theme'] ?? ''), $left['code']] <=> [(string) ($right['theme'] ?? ''), $right['code']];
        });

        return ['themes' => array_values($themes), 'errors' => $errors];
    }

    private function failure(?string $theme, string $status, string $code): array
    {
        return [
            'theme' => $theme,
            'status' => $status,
            'code' => $code,
            'message' => $status === 'unavailable'
                ? 'Theme is unavailable.'
                : 'Theme definition is invalid.',
        ];
    }

    private function safeThemeIdentifier(string $value): ?string
    {
        return preg_match('/^[a-z0-9_-]+$/', $value) === 1 ? $value : null;
    }

    private function loadTheme(string $themePath, string $themeJson): ThemeDefinition
    {
        $metadataSize = filesize($themeJson);
        if ($metadataSize === false || $metadataSize > self::MAX_METADATA_BYTES) {
            throw new ThemeException('theme.json exceeds the metadata size limit.');
        }

        $contents = file_get_contents($themeJson);

        if ($contents === false || strlen($contents) > self::MAX_METADATA_BYTES) {
            throw new ThemeException('Unable to read theme.json.');
        }

        try {
            $metadataObject = json_decode($contents, false, 512, JSON_THROW_ON_ERROR);
            $metadata = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ThemeException('theme.json contains invalid JSON.');
        }

        if (!$metadataObject instanceof stdClass || !is_array($metadata)) {
            throw new ThemeException('theme.json must contain valid JSON object metadata.');
        }

        $metadata = $this->normalizeMetadata($metadata, $metadataObject, $themePath);

        $this->validateLayout($themePath, $metadata['entry']['layout'], $metadata['id']);

        return new ThemeDefinition(
            id: $metadata['id'],
            name: $metadata['name'],
            version: $metadata['version'],
            type: $metadata['type'],
            path: $themePath,
            layout: $metadata['entry']['layout'],
            description: $metadata['description'],
            author: $metadata['author'],
            supports: $metadata['supports'],
            screenshot: $metadata['screenshot'],
            settings: $metadata['settings'],
            metadata: $metadata
        );
    }

    private function normalizeMetadata(array $metadata, stdClass $metadataObject, string $themePath): array
    {
        foreach (['id', 'name', 'version', 'type'] as $field) {
            if (!$this->hasRequiredString($metadata, $field)) {
                throw new ThemeException("Missing required field [{$field}].");
            }
        }

        if (!isset($metadataObject->entry) || !$metadataObject->entry instanceof stdClass || !isset($metadata['entry']) || !is_array($metadata['entry'])) {
            throw new ThemeException('Missing required field [entry.layout].');
        }

        if (!$this->hasRequiredString($metadata['entry'], 'layout')) {
            throw new ThemeException('Missing required field [entry.layout].');
        }

        $this->validateOptionalString($metadata, 'description');
        $this->validateOptionalString($metadata, 'author');
        $this->validateScreenshot($themePath, $metadata);
        $this->validateSupports($metadataObject, $metadata);
        $metadata['settings'] = $this->normalizeSettings($metadata);

        $metadata['id'] = trim($metadata['id']);
        $metadata['name'] = trim($metadata['name']);
        $metadata['version'] = trim($metadata['version']);
        $metadata['type'] = trim($metadata['type']);
        $metadata['entry']['layout'] = trim($metadata['entry']['layout']);
        $metadata['description'] = $this->optionalString($metadata, 'description');
        $metadata['author'] = $this->optionalString($metadata, 'author');
        $metadata['supports'] = isset($metadata['supports']) ? $metadata['supports'] : [];
        $metadata['screenshot'] = $metadata['screenshot'] ?? null;

        if (!preg_match('/^[a-z0-9-]+$/', $metadata['id'])) {
            throw new ThemeException('Theme ID must be a lowercase slug using only letters, numbers, and hyphens.');
        }

        if ($metadata['type'] !== 'frontend') {
            throw new ThemeException("Unsupported theme type [{$metadata['type']}].");
        }

        if (!$this->isSafeRelativePath($metadata['entry']['layout'])) {
            throw new ThemeException('Theme layout path must be a safe relative path inside the theme folder.');
        }

        return $metadata;
    }

    private function validateLayout(string $themePath, string $layout, string $themeId): void
    {
        $layoutPath = $themePath . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $layout);

        if (!is_file($layoutPath)) {
            throw new ThemeException("Default layout [{$layout}] for theme [{$themeId}] was not found.");
        }

        $resolvedLayout = realpath($layoutPath);

        if ($resolvedLayout === false || !$this->isInsideDirectory($resolvedLayout, $themePath)) {
            throw new ThemeException("Default layout [{$layout}] for theme [{$themeId}] is outside the theme folder.");
        }
    }

    private function isSafeRelativePath(string $path): bool
    {
        $path = str_replace('\\', '/', $path);

        if ($path === '' || str_starts_with($path, '/') || str_contains($path, "\0") || preg_match('/^[A-Za-z]:\//', $path)) {
            return false;
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
        }

        return true;
    }

    private function hasRequiredString(array $metadata, string $field): bool
    {
        return isset($metadata[$field])
            && is_string($metadata[$field])
            && trim($metadata[$field]) !== '';
    }

    private function validateOptionalString(array $metadata, string $field): void
    {
        if (array_key_exists($field, $metadata) && !is_string($metadata[$field])) {
            throw new ThemeException("Optional field [{$field}] must be a string.");
        }
    }

    private function optionalString(array $metadata, string $field): ?string
    {
        if (!array_key_exists($field, $metadata)) {
            return null;
        }

        $value = trim($metadata[$field]);

        return $value === '' ? null : $value;
    }

    private function validateSupports(stdClass $metadataObject, array &$metadata): void
    {
        if (!property_exists($metadataObject, 'supports')) {
            return;
        }

        if (!$metadataObject->supports instanceof stdClass || !isset($metadata['supports']) || !is_array($metadata['supports'])) {
            throw new ThemeException('Optional field [supports] must be a JSON object.');
        }

        $unknown = array_diff(array_keys($metadata['supports']), self::SUPPORTED_CAPABILITIES);
        if ($unknown !== []) {
            throw new ThemeException('Theme supports contains an unsupported capability.');
        }

        $normalized = [];
        if (array_key_exists('module_view_overrides', $metadata['supports'])) {
            if (!is_bool($metadata['supports']['module_view_overrides'])) {
                throw new ThemeException('Theme supports.module_view_overrides must be boolean.');
            }
            $normalized['module_view_overrides'] = $metadata['supports']['module_view_overrides'];
        }

        if (array_key_exists('navigation_locations', $metadata['supports'])) {
            $locations = $metadata['supports']['navigation_locations'];
            if (!is_array($locations)) {
                throw new ThemeException('Theme supports.navigation_locations must be an array.');
            }

            $seen = [];
            foreach ($locations as $location) {
                if (!is_string($location) || preg_match('/^[a-z][a-z0-9._-]*$/D', $location) !== 1 || isset($seen[$location])) {
                    throw new ThemeException('Theme supports.navigation_locations contains an invalid or duplicate location.');
                }
                $seen[$location] = true;
            }

            sort($locations, SORT_STRING);
            $normalized['navigation_locations'] = array_values($locations);
        }

        $metadata['supports'] = $normalized;
    }

    private function validateScreenshot(string $themePath, array &$metadata): void
    {
        if (!array_key_exists('screenshot', $metadata)) {
            $metadata['screenshot'] = null;

            return;
        }

        if (!is_string($metadata['screenshot']) || trim($metadata['screenshot']) === '') {
            throw new ThemeException('Theme screenshot must be a non-empty relative path.');
        }

        $screenshot = $this->normalizeRelativePath($metadata['screenshot']);
        if (!$this->isSafeRelativePath($screenshot)) {
            throw new ThemeException('Theme screenshot path must be a safe relative path inside the theme folder.');
        }

        $extension = strtolower((string) pathinfo($screenshot, PATHINFO_EXTENSION));
        if (!in_array($extension, self::SCREENSHOT_EXTENSIONS, true)) {
            throw new ThemeException('Theme screenshot format is unsupported.');
        }

        $screenshotPath = $themePath . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $screenshot);
        if (!is_file($screenshotPath) || is_dir($screenshotPath)) {
            throw new ThemeException('Theme screenshot file was not found.');
        }

        $screenshotSize = filesize($screenshotPath);
        if ($screenshotSize === false || $screenshotSize > self::MAX_SCREENSHOT_BYTES) {
            throw new ThemeException('Theme screenshot exceeds the size limit.');
        }

        $resolvedScreenshot = realpath($screenshotPath);
        if ($resolvedScreenshot === false || !$this->isInsideDirectory($resolvedScreenshot, $themePath)) {
            throw new ThemeException('Theme screenshot path is outside the theme folder.');
        }

        $mime = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = $mime === false ? false : finfo_file($mime, $resolvedScreenshot);
        if ($mime !== false) {
            finfo_close($mime);
        }
        $expectedMime = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
        ][$extension] ?? null;
        if ($expectedMime === null || $detectedMime !== $expectedMime) {
            throw new ThemeException('Theme screenshot MIME type is invalid.');
        }

        $metadata['screenshot'] = $screenshot;
    }

    private function normalizeSettings(array $metadata): array
    {
        if (!array_key_exists('settings', $metadata)) {
            return [];
        }

        $settings = $metadata['settings'];
        if (!is_array($settings) || array_is_list($settings) || array_diff(array_keys($settings), ['version', 'sections']) !== []
            || array_diff(['version', 'sections'], array_keys($settings)) !== []) {
            throw new ThemeException('Theme settings must contain exactly version and sections.');
        }

        if ($settings['version'] !== 1 || !is_array($settings['sections']) || $settings['sections'] === [] || !array_is_list($settings['sections'])) {
            throw new ThemeException('Theme settings version or sections are invalid.');
        }

        $sections = [];
        $sectionIds = [];
        $fieldKeys = [];

        foreach ($settings['sections'] as $section) {
            $allowedSectionKeys = ['id', 'label', 'description', 'fields'];
            if (!is_array($section) || array_is_list($section) || array_diff(array_keys($section), $allowedSectionKeys) !== []
                || array_diff(['id', 'label', 'fields'], array_keys($section)) !== []) {
                throw new ThemeException('Theme settings section shape is invalid.');
            }

            $this->validateIdentifier($section['id'], 'section ID', 63);
            $this->validateLabel($section['label'], 'section label');
            $this->validateOptionalDescription($section['description'] ?? null, 'section description');
            if (!is_array($section['fields']) || $section['fields'] === [] || !array_is_list($section['fields'])) {
                throw new ThemeException('Theme settings section fields are invalid.');
            }
            if (isset($sectionIds[$section['id']])) {
                throw new ThemeException('Theme settings section IDs must be unique.');
            }

            $fields = [];
            foreach ($section['fields'] as $field) {
                $fields[] = $this->normalizeSettingField($field, $fieldKeys);
            }
            usort($fields, static fn (array $left, array $right): int => $left['key'] <=> $right['key']);

            $sectionIds[$section['id']] = true;
            $sections[] = [
                'id' => $section['id'],
                'label' => trim($section['label']),
                'description' => isset($section['description']) ? trim($section['description']) : null,
                'fields' => $fields,
            ];
        }

        usort($sections, static fn (array $left, array $right): int => $left['id'] <=> $right['id']);

        return ['version' => 1, 'sections' => $sections];
    }

    private function normalizeSettingField(mixed $field, array &$fieldKeys): array
    {
        $allowedKeys = ['key', 'label', 'description', 'type', 'control', 'default', 'validation'];
        if (!is_array($field) || array_is_list($field) || array_diff(array_keys($field), $allowedKeys) !== []
            || array_diff(['key', 'label', 'type', 'control', 'default'], array_keys($field)) !== []) {
            throw new ThemeException('Theme setting field shape is invalid.');
        }

        $this->validateIdentifier($field['key'], 'field key', 127);
        $this->validateLabel($field['label'], 'field label');
        $this->validateOptionalDescription($field['description'] ?? null, 'field description');
        if (!is_string($field['type']) || !in_array($field['type'], self::SETTING_TYPES, true)) {
            throw new ThemeException('Theme setting type is unsupported.');
        }
        if (!is_string($field['control']) || !in_array($field['control'], self::SETTING_CONTROLS, true)) {
            throw new ThemeException('Theme setting control is unsupported.');
        }
        $this->validateControlCompatibility($field['type'], $field['control']);
        $default = $field['control'] === 'color' ? strtolower($field['default']) : $field['default'];
        $this->validateSettingDefault($default, $field['type']);
        $validation = $this->normalizeValidation($field['validation'] ?? [], $field['type'], $field['control'], $default);

        if (isset($fieldKeys[$field['key']])) {
            throw new ThemeException('Theme setting field keys must be unique.');
        }
        $fieldKeys[$field['key']] = true;

        return [
            'key' => $field['key'],
            'label' => trim($field['label']),
            'description' => isset($field['description']) ? trim($field['description']) : null,
            'type' => $field['type'],
            'control' => $field['control'],
            'default' => $default,
            'validation' => $validation,
        ];
    }

    private function normalizeValidation(mixed $validation, string $type, string $control, mixed $default): array
    {
        if (!is_array($validation) || array_diff(array_keys($validation), ['required', 'allowed_values', 'min', 'max', 'max_length', 'format']) !== []) {
            throw new ThemeException('Theme setting validation metadata is invalid.');
        }

        if (array_key_exists('required', $validation) && !is_bool($validation['required'])) {
            throw new ThemeException('Theme setting required validation must be boolean.');
        }
        if (array_key_exists('allowed_values', $validation)) {
            $values = $validation['allowed_values'];
            if (!is_array($values) || $values === [] || !array_is_list($values)) {
                throw new ThemeException('Theme setting allowed_values must be a non-empty list.');
            }
            $seen = [];
            foreach ($values as $value) {
                if (!$this->isScalarSettingValue($value, $type) || in_array($value, $seen, true)) {
                    throw new ThemeException('Theme setting allowed_values contains an invalid or duplicate value.');
                }
                $seen[] = $value;
            }
            if (!in_array($default, $values, true)) {
                throw new ThemeException('Theme setting default is not an allowed value.');
            }
        }
        foreach (['min', 'max'] as $bound) {
            if (array_key_exists($bound, $validation)
                && (!in_array($type, ['integer', 'float'], true) || !is_int($validation[$bound]) && !is_float($validation[$bound]) || is_bool($validation[$bound]))) {
                throw new ThemeException('Theme setting numeric bounds are invalid.');
            }
        }
        if (isset($validation['min'], $validation['max']) && $validation['min'] > $validation['max']) {
            throw new ThemeException('Theme setting minimum cannot exceed maximum.');
        }
        if (array_key_exists('min', $validation) && $default < $validation['min']
            || array_key_exists('max', $validation) && $default > $validation['max']) {
            throw new ThemeException('Theme setting default is outside numeric bounds.');
        }
        if (array_key_exists('max_length', $validation)
            && ($type !== 'string' || !is_int($validation['max_length']) || $validation['max_length'] < 1)) {
            throw new ThemeException('Theme setting max_length is invalid.');
        }
        if (array_key_exists('max_length', $validation) && strlen($default) > $validation['max_length']) {
            throw new ThemeException('Theme setting default exceeds max_length.');
        }
        if (($validation['required'] ?? false) && $type === 'string' && trim($default) === '') {
            throw new ThemeException('Theme required setting default must be non-empty.');
        }
        if (array_key_exists('format', $validation)) {
            if (!is_string($validation['format']) || !in_array($validation['format'], self::SETTING_FORMATS, true) || $type !== 'string') {
                throw new ThemeException('Theme setting format is unsupported.');
            }
            if ($validation['format'] === 'hex_color' && !preg_match('/^#[0-9a-f]{3,4}(?:[0-9a-f]{2})?$/i', (string) $default)) {
                throw new ThemeException('Theme setting hex_color default is invalid.');
            }
        }
        if ($control === 'select' && !array_key_exists('allowed_values', $validation)) {
            throw new ThemeException('Theme select settings require allowed_values.');
        }
        if ($control === 'color' && (($validation['format'] ?? null) !== 'hex_color')) {
            throw new ThemeException('Theme color settings require the hex_color format.');
        }

        $normalized = [];
        foreach (['required', 'allowed_values', 'min', 'max', 'max_length', 'format'] as $key) {
            if (array_key_exists($key, $validation)) {
                $normalized[$key] = $validation[$key];
            }
        }

        return $normalized;
    }

    private function validateControlCompatibility(string $type, string $control): void
    {
        $compatible = [
            'string' => ['text', 'select', 'color'],
            'integer' => ['number'],
            'float' => ['number'],
            'boolean' => ['checkbox'],
        ];
        if (!in_array($control, $compatible[$type], true)) {
            throw new ThemeException('Theme setting type and control are incompatible.');
        }
    }

    private function validateSettingDefault(mixed $default, string $type): void
    {
        $valid = match ($type) {
            'string' => is_string($default),
            'integer' => is_int($default),
            'float' => is_float($default),
            'boolean' => is_bool($default),
        };
        if (!$valid) {
            throw new ThemeException('Theme setting default type is invalid.');
        }
    }

    private function isScalarSettingValue(mixed $value, string $type): bool
    {
        return match ($type) {
            'string' => is_string($value),
            'integer' => is_int($value),
            'float' => is_float($value),
            'boolean' => is_bool($value),
        };
    }

    private function validateIdentifier(mixed $value, string $subject, int $maxLength): void
    {
        if (!is_string($value) || preg_match('/^[a-z][a-z0-9_-]{0,' . ($maxLength - 1) . '}$/', $value) !== 1) {
            throw new ThemeException("Theme {$subject} is invalid.");
        }
    }

    private function validateLabel(mixed $value, string $subject): void
    {
        if (!is_string($value) || trim($value) === '') {
            throw new ThemeException("Theme {$subject} must be non-empty.");
        }
    }

    private function validateOptionalDescription(mixed $value, string $subject): void
    {
        if ($value !== null && !is_string($value)) {
            throw new ThemeException("Theme {$subject} must be a string or null.");
        }
    }

    private function normalizeRelativePath(string $path): string
    {
        return implode('/', array_map(static fn (string $segment): string => trim($segment), explode('/', str_replace('\\', '/', trim($path)))));
    }

    private function isInsideDirectory(string $path, string $directory): bool
    {
        $directory = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        return str_starts_with($path, $directory);
    }
}
