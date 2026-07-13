<?php

final class SettingsManagerPolicy
{
    private const SECTION_KEYS = ['identifier', 'label', 'description', 'order'];
    private const FIELD_KEYS = [
        'identifier',
        'section',
        'label',
        'description',
        'presentation',
        'required',
        'order',
        'constraints',
    ];
    private const CONSTRAINT_KEYS = ['option_source'];
    private const PRESENTATIONS = ['text', 'number', 'checkbox', 'select'];
    private const OPTION_SOURCES = ['timezone_identifiers'];

    private const DEFAULT_SECTIONS = [
        [
            'identifier' => 'site',
            'label' => 'General',
            'description' => 'Manage global site settings.',
            'order' => 10,
        ],
        [
            'identifier' => 'localization',
            'label' => 'Localization',
            'description' => 'Manage site-wide localization settings.',
            'order' => 20,
        ],
    ];

    private const DEFAULT_FIELDS = [
        [
            'identifier' => 'site.name',
            'section' => 'site',
            'label' => 'Site Name',
            'description' => 'Used as the public site name.',
            'presentation' => null,
            'required' => true,
            'order' => 10,
            'constraints' => [],
        ],
        [
            'identifier' => 'site.tagline',
            'section' => 'site',
            'label' => 'Site Tagline',
            'description' => 'Optional short description of the site.',
            'presentation' => null,
            'required' => false,
            'order' => 20,
            'constraints' => [],
        ],
        [
            'identifier' => 'localization.timezone',
            'section' => 'localization',
            'label' => 'Timezone',
            'description' => 'Controls the default application timezone.',
            'presentation' => 'select',
            'required' => true,
            'order' => 10,
            'constraints' => ['option_source' => 'timezone_identifiers'],
        ],
        [
            'identifier' => 'localization.locale',
            'section' => 'localization',
            'label' => 'Locale',
            'description' => 'Controls the configured runtime locale.',
            'presentation' => null,
            'required' => true,
            'order' => 20,
            'constraints' => [],
        ],
        [
            'identifier' => 'localization.date_format',
            'section' => 'localization',
            'label' => 'Date Format',
            'description' => 'Controls the configured default date display format.',
            'presentation' => null,
            'required' => true,
            'order' => 30,
            'constraints' => [],
        ],
        [
            'identifier' => 'localization.time_format',
            'section' => 'localization',
            'label' => 'Time Format',
            'description' => 'Controls the configured default time display format.',
            'presentation' => null,
            'required' => true,
            'order' => 40,
            'constraints' => [],
        ],
    ];

    private array $sections;
    private array $fields;

    public function __construct(array $sections, array $fields)
    {
        $this->sections = $this->validateSections($sections);
        $this->fields = $this->validateFields($fields, $this->sections);
    }

    public static function defaults(): self
    {
        return new self(self::DEFAULT_SECTIONS, self::DEFAULT_FIELDS);
    }

    public function sections(): array
    {
        return $this->sections;
    }

    public function fields(): array
    {
        return $this->fields;
    }

    private function validateSections(array $sections): array
    {
        if ($sections === []) {
            throw new LogicException('Settings Manager policy requires at least one section.');
        }

        $validated = [];
        $identifiers = [];
        $orders = [];

        foreach ($sections as $section) {
            if (!is_array($section)) {
                throw new LogicException('Settings Manager section policy must be an array.');
            }

            $this->assertExactKeys($section, self::SECTION_KEYS, 'section');
            $identifier = $section['identifier'];

            if (!is_string($identifier) || !preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $identifier)) {
                throw new LogicException('Settings Manager section identifier is invalid.');
            }

            $this->assertText($section['label'], 'section label');
            $this->assertDescription($section['description'], 'section description');
            $this->assertOrder($section['order'], 'section order');

            if (isset($identifiers[$identifier])) {
                throw new LogicException('Settings Manager section identifiers must be unique.');
            }

            if (isset($orders[$section['order']])) {
                throw new LogicException('Settings Manager section orders must be unique.');
            }

            $identifiers[$identifier] = true;
            $orders[$section['order']] = true;
            $validated[] = $section;
        }

        usort($validated, static fn (array $left, array $right): int => $left['order'] <=> $right['order']);

        return $validated;
    }

    private function validateFields(array $fields, array $sections): array
    {
        if ($fields === []) {
            throw new LogicException('Settings Manager policy requires at least one field.');
        }

        $sectionIdentifiers = array_fill_keys(
            array_map(static fn (array $section): string => $section['identifier'], $sections),
            true
        );
        $validated = [];
        $identifiers = [];
        $orders = [];

        foreach ($fields as $field) {
            if (!is_array($field)) {
                throw new LogicException('Settings Manager field policy must be an array.');
            }

            $this->assertExactKeys($field, self::FIELD_KEYS, 'field');
            $identifier = $field['identifier'];
            $section = $field['section'];

            if (!is_string($identifier) || !preg_match('/^[a-z][a-z0-9_-]{0,63}\.[a-z][a-z0-9_-]{0,127}$/', $identifier)) {
                throw new LogicException('Settings Manager field identifier is invalid.');
            }

            if (!is_string($section) || !isset($sectionIdentifiers[$section])) {
                throw new LogicException('Settings Manager field references an unknown section.');
            }

            if (!str_starts_with($identifier, $section . '.')) {
                throw new LogicException('Settings Manager field identifier does not belong to its section.');
            }

            $this->assertText($field['label'], 'field label');
            $this->assertDescription($field['description'], 'field description');

            if ($field['presentation'] !== null
                && (!is_string($field['presentation']) || !in_array($field['presentation'], self::PRESENTATIONS, true))) {
                throw new LogicException('Settings Manager field presentation is invalid.');
            }

            if (!is_bool($field['required'])) {
                throw new LogicException('Settings Manager field required state must be boolean.');
            }

            $this->assertOrder($field['order'], 'field order');
            $this->validateConstraints($field['constraints']);

            if (isset($identifiers[$identifier])) {
                throw new LogicException('Settings Manager field identifiers must be unique.');
            }

            $orderKey = $section . ':' . $field['order'];

            if (isset($orders[$orderKey])) {
                throw new LogicException('Settings Manager field orders must be unique within a section.');
            }

            $identifiers[$identifier] = true;
            $orders[$orderKey] = true;
            $validated[] = $field;
        }

        $sectionOrder = [];

        foreach ($sections as $section) {
            $sectionOrder[$section['identifier']] = $section['order'];
        }

        usort($validated, static function (array $left, array $right) use ($sectionOrder): int {
            $sectionComparison = $sectionOrder[$left['section']] <=> $sectionOrder[$right['section']];

            return $sectionComparison !== 0 ? $sectionComparison : $left['order'] <=> $right['order'];
        });

        return $validated;
    }

    private function validateConstraints(mixed $constraints): void
    {
        if (!is_array($constraints)) {
            throw new LogicException('Settings Manager field constraints must be an array.');
        }

        $keys = array_keys($constraints);
        $unknown = array_diff($keys, self::CONSTRAINT_KEYS);

        if ($unknown !== []) {
            throw new LogicException('Settings Manager field constraint key is unsupported.');
        }

        if (array_key_exists('option_source', $constraints)
            && (!is_string($constraints['option_source'])
                || !in_array($constraints['option_source'], self::OPTION_SOURCES, true))) {
            throw new LogicException('Settings Manager field option source is invalid.');
        }
    }

    private function assertExactKeys(array $value, array $expected, string $subject): void
    {
        $actual = array_keys($value);
        sort($actual, SORT_STRING);
        $expectedKeys = $expected;
        sort($expectedKeys, SORT_STRING);

        if ($actual !== $expectedKeys) {
            throw new LogicException("Settings Manager {$subject} policy keys are invalid.");
        }
    }

    private function assertText(mixed $value, string $subject): void
    {
        if (!is_string($value) || trim($value) === '') {
            throw new LogicException("Settings Manager {$subject} must be a non-empty string.");
        }
    }

    private function assertDescription(mixed $value, string $subject): void
    {
        if ($value !== null && !is_string($value)) {
            throw new LogicException("Settings Manager {$subject} must be a string or null.");
        }
    }

    private function assertOrder(mixed $value, string $subject): void
    {
        if (!is_int($value) || $value < 1) {
            throw new LogicException("Settings Manager {$subject} must be a positive integer.");
        }
    }
}
