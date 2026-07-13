<?php

declare(strict_types=1);

use Copot\Core\SettingDefinition;
use Copot\Core\SettingsRegistry;

$basePath = dirname(__DIR__);

require $basePath . '/bootstrap/autoload.php';
require $basePath . '/modules/settings-manager/Services/SettingsManagerPolicy.php';
require $basePath . '/modules/settings-manager/Services/SettingsField.php';
require $basePath . '/modules/settings-manager/Services/SettingsSection.php';
require $basePath . '/modules/settings-manager/Services/SettingsFieldMapper.php';
require $basePath . '/modules/settings-manager/Services/SettingsValidationException.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;

    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$expectLogic = static function (callable $operation, string $message) use ($assert): void {
    try {
        $operation();
    } catch (LogicException) {
        $assert(true, $message);
        return;
    }

    throw new RuntimeException($message);
};
$section = static fn (string $id, int $order = 10): array => [
    'identifier' => $id,
    'label' => strtoupper($id),
    'description' => null,
    'order' => $order,
];
$field = static fn (
    string $id,
    string $sectionId,
    int $order = 10,
    ?string $presentation = null,
    array $constraints = []
): array => [
    'identifier' => $id,
    'section' => $sectionId,
    'label' => $id . ' label',
    'description' => null,
    'presentation' => $presentation,
    'required' => true,
    'order' => $order,
    'constraints' => $constraints,
];

$defaults = SettingsManagerPolicy::defaults();
$assert(count($defaults->sections()) === 2, 'Default policy section count is incorrect.');
$assert(count($defaults->fields()) === 6, 'Default policy field count is incorrect.');
$assert(
    array_column($defaults->fields(), 'identifier') === [
        'site.name',
        'site.tagline',
        'localization.timezone',
        'localization.locale',
        'localization.date_format',
        'localization.time_format',
    ],
    'Default policy field ordering is incorrect.'
);

$validSections = [$section('test')];
$validFields = [$field('test.value', 'test')];

$expectLogic(fn () => new SettingsManagerPolicy([], $validFields), 'Empty section policy was accepted.');
$expectLogic(fn () => new SettingsManagerPolicy($validSections, []), 'Empty field policy was accepted.');
$expectLogic(fn () => new SettingsManagerPolicy([['identifier' => 'test']], $validFields),
    'Missing section policy keys were accepted.');
$unknownSection = $validSections;
$unknownSection[0]['unknown'] = true;
$expectLogic(fn () => new SettingsManagerPolicy($unknownSection, $validFields),
    'Unknown section policy key was accepted.');
$wrongSection = $validSections;
$wrongSection[0]['order'] = '10';
$expectLogic(fn () => new SettingsManagerPolicy($wrongSection, $validFields),
    'Wrong section policy value type was accepted.');
$missingField = $validFields;
unset($missingField[0]['label']);
$expectLogic(fn () => new SettingsManagerPolicy($validSections, $missingField),
    'Missing field policy key was accepted.');
$unknownField = $validFields;
$unknownField[0]['unknown'] = true;
$expectLogic(fn () => new SettingsManagerPolicy($validSections, $unknownField),
    'Unknown field policy key was accepted.');
$wrongField = $validFields;
$wrongField[0]['required'] = 1;
$expectLogic(fn () => new SettingsManagerPolicy($validSections, $wrongField),
    'Wrong field policy value type was accepted.');
$unknownConstraint = $validFields;
$unknownConstraint[0]['constraints'] = ['future' => true];
$expectLogic(fn () => new SettingsManagerPolicy($validSections, $unknownConstraint),
    'Unknown constraint policy key was accepted.');
$nullConstraint = $validFields;
$nullConstraint[0]['constraints'] = ['option_source' => null];
$expectLogic(fn () => new SettingsManagerPolicy($validSections, $nullConstraint),
    'Invalid null option source was accepted.');
$duplicateSections = [$section('test', 10), $section('other', 20), $section('test', 30)];
$expectLogic(fn () => new SettingsManagerPolicy($duplicateSections, $validFields),
    'Duplicate section identifier was accepted.');
$duplicateSectionOrder = [$section('test', 10), $section('other', 10)];
$expectLogic(fn () => new SettingsManagerPolicy($duplicateSectionOrder, $validFields),
    'Duplicate section order was accepted.');
$duplicateFields = [$field('test.value', 'test', 10), $field('test.value', 'test', 20)];
$expectLogic(fn () => new SettingsManagerPolicy($validSections, $duplicateFields),
    'Duplicate field identifier was accepted.');
$duplicateFieldOrder = [$field('test.value', 'test', 10), $field('test.other', 'test', 10)];
$expectLogic(fn () => new SettingsManagerPolicy($validSections, $duplicateFieldOrder),
    'Duplicate field order was accepted.');
$expectLogic(fn () => new SettingsManagerPolicy($validSections, [$field('other.value', 'other')]),
    'Unknown field section reference was accepted.');

$mapper = new SettingsFieldMapper($defaults);
$coreDefinitions = SettingsRegistry::core();
$definitions = [];

foreach ($coreDefinitions->namespaces() as $namespace) {
    array_push($definitions, ...$coreDefinitions->all($namespace));
}

$mapped = $mapper->sections(array_reverse($definitions));
$assert(array_map(static fn (SettingsSection $value): string => $value->identifier(), $mapped) === ['site', 'localization'],
    'Section order depends on definition input order.');
$assert(array_map(static fn (SettingsField $value): string => $value->identifier(), $mapped[0]->fields())
    === ['site.name', 'site.tagline'], 'Site field order is not deterministic.');
$assert(array_map(static fn (SettingsField $value): string => $value->identifier(), $mapped[1]->fields())
    === ['localization.timezone', 'localization.locale', 'localization.date_format', 'localization.time_format'],
    'Localization field order is not deterministic.');
$allMapped = array_merge($mapped[0]->fields(), $mapped[1]->fields());
$assert(count($allMapped) === 6, 'Production mapping did not expose exactly six fields.');
$assert(!in_array('site.logo', array_map(static fn (SettingsField $value): string => $value->identifier(), $allMapped), true),
    'Logo descriptor leaked into generic fields.');
$assert(!in_array('site.favicon', array_map(static fn (SettingsField $value): string => $value->identifier(), $allMapped), true),
    'Favicon descriptor leaked into generic fields.');
$assert($mapped[0]->label() === 'General' && $mapped[0]->fields()[0]->label() === 'Site Name',
    'Policy labels were synthesized instead of preserved.');
$assert($mapped[0]->fields()[0]->maximumLength() === 150, 'max_length metadata was not mapped.');
$assert($mapped[1]->fields()[0]->fieldType() === 'select'
    && $mapped[1]->fields()[0]->options()[0] === 'UTC', 'Timezone option source was not mapped.');

$fixtureSections = [$section('fixture')];
$scalarDefinitions = [
    new SettingDefinition('fixture', 'text', 'string', 'value'),
    new SettingDefinition('fixture', 'string_select', 'string', 'one', allowedValues: ['one', 'two']),
    new SettingDefinition('fixture', 'integer', 'integer', 1),
    new SettingDefinition('fixture', 'integer_select', 'integer', 1, allowedValues: [1, 2]),
    new SettingDefinition('fixture', 'float', 'float', 1.5),
    new SettingDefinition('fixture', 'float_select', 'float', 1.5, allowedValues: [1.5, 2.5]),
    new SettingDefinition('fixture', 'boolean', 'boolean', false),
];
$scalarFields = [];

foreach ($scalarDefinitions as $index => $definition) {
    $scalarFields[] = $field($definition->identifier(), 'fixture', ($index + 1) * 10);
}

$scalarMapped = (new SettingsFieldMapper(new SettingsManagerPolicy($fixtureSections, $scalarFields)))
    ->sections(array_reverse($scalarDefinitions))[0]->fields();
$assert(array_map(static fn (SettingsField $value): string => $value->fieldType(), $scalarMapped)
    === ['text', 'select', 'number', 'select', 'number', 'select', 'checkbox'],
    'Core scalar types did not map to the locked field contract.');

$expectLogic(function () use ($fixtureSections, $field): void {
    $definition = new SettingDefinition('fixture', 'json', 'json', []);
    (new SettingsFieldMapper(new SettingsManagerPolicy($fixtureSections, [$field('fixture.json', 'fixture')])))
        ->sections([$definition]);
}, 'Generic JSON definition was accepted.');
$expectLogic(function () use ($fixtureSections, $field): void {
    $definition = new SettingDefinition('fixture', 'bad_meta', 'string', 'x', metadata: ['future' => true]);
    (new SettingsFieldMapper(new SettingsManagerPolicy($fixtureSections, [$field('fixture.bad_meta', 'fixture')])))
        ->sections([$definition]);
}, 'Unknown definition metadata was accepted.');
$expectLogic(function () use ($fixtureSections, $field): void {
    $definition = new SettingDefinition('fixture', 'bad_max', 'string', 'x', metadata: ['max_length' => '10']);
    (new SettingsFieldMapper(new SettingsManagerPolicy($fixtureSections, [$field('fixture.bad_max', 'fixture')])))
        ->sections([$definition]);
}, 'Malformed max_length metadata was accepted.');
$expectLogic(function () use ($fixtureSections, $field): void {
    $definition = new SettingDefinition('fixture', 'override', 'integer', 1);
    $policy = new SettingsManagerPolicy($fixtureSections, [$field('fixture.override', 'fixture', 10, 'text')]);
    (new SettingsFieldMapper($policy))->sections([$definition]);
}, 'Incompatible presentation override was accepted.');
$expectLogic(function () use ($fixtureSections, $field): void {
    $definition = new SettingDefinition('fixture', 'boolean_select', 'boolean', false, allowedValues: [false, true]);
    $policy = new SettingsManagerPolicy($fixtureSections, [$field('fixture.boolean_select', 'fixture')]);
    (new SettingsFieldMapper($policy))->sections([$definition]);
}, 'Boolean allowed-values definition was accepted.');
$expectLogic(function () use ($fixtureSections, $field): void {
    $definition = new SettingDefinition('fixture', 'registered', 'string', 'x');
    $policy = new SettingsManagerPolicy($fixtureSections, [$field('fixture.missing', 'fixture')]);
    (new SettingsFieldMapper($policy))->sections([$definition]);
}, 'Policy identifier absent from definitions was accepted.');

$validField = static fn (): SettingsField => new SettingsField(
    'fixture.value',
    'fixture',
    'value',
    'string',
    'text',
    'Value',
    null,
    true,
    20,
    [],
    'default'
);
$assert($validField()->identifier() === 'fixture.value', 'Valid SettingsField construction failed.');
$expectLogic(fn () => new SettingsField(
    'bad namespace.value', 'bad namespace', 'value', 'string', 'text', 'Value', null, true, null, [], 'x'
), 'Malformed SettingsField namespace was accepted.');
$expectLogic(fn () => new SettingsField(
    'fixture.bad.key', 'fixture', 'bad.key', 'string', 'text', 'Value', null, true, null, [], 'x'
), 'Malformed SettingsField key was accepted.');
$expectLogic(fn () => new SettingsField(
    'fixture.value', 'fixture', 'value', 'integer', 'number', 'Value', null, true, null, [], '1'
), 'SettingsField accepted a default with the wrong type.');
$expectLogic(fn () => new SettingsField(
    'fixture.value', 'fixture', 'value', 'integer', 'number', 'Value', null, true, 10, [], 1
), 'SettingsField accepted maximumLength on a number field.');
$expectLogic(fn () => new SettingsField(
    'fixture.value', 'fixture', 'value', 'boolean', 'checkbox', 'Value', null, true, 10, [], false
), 'SettingsField accepted maximumLength on a checkbox field.');
$expectLogic(fn () => new SettingsField(
    'fixture.value', 'fixture', 'value', 'integer', 'text', 'Value', null, true, null, [], 1
), 'SettingsField accepted an incompatible field and value type.');
$expectLogic(fn () => new SettingsField(
    'fixture.value', 'fixture', 'value', 'boolean', 'select', 'Value', null, true, null, [false, true], false
), 'SettingsField accepted a boolean select.');
$expectLogic(fn () => new SettingsField(
    'fixture.value', 'fixture', 'value', 'string', 'select', 'Value', null, true, null, ['a', 'a'], 'a'
), 'SettingsField accepted duplicate select options.');
$expectLogic(fn () => new SettingsField(
    'fixture.value', 'fixture', 'value', 'integer', 'select', 'Value', null, true, null, [1, '2'], 1
), 'SettingsField accepted an incompatible select option type.');
$assert((new SettingsField(
    'fixture.choice', 'fixture', 'choice', 'integer', 'select', 'Choice', null, true, null, [1, 2], 1
))->options() === [1, 2], 'Valid select SettingsField construction failed.');

$validValidation = new SettingsValidationException(
    ['fixture.value' => ['First error.', 'Second error.']],
    ['Form error.'],
    ['fixture.value' => 'submitted']
);
$assert(count($validValidation->fieldErrors()['fixture.value']) === 2,
    'Valid ordered validation error lists were rejected.');
$expectLogic(fn () => new SettingsValidationException(
    ['fixture.value' => ['first' => 'Error.']], [], []
), 'Associative field error messages were accepted.');
$expectLogic(fn () => new SettingsValidationException(
    ['fixture.value' => [1 => 'Error.']], [], []
), 'Sparse field error messages were accepted.');
$expectLogic(fn () => new SettingsValidationException(
    [], ['form' => 'Error.'], []
), 'Associative form errors were accepted.');
$expectLogic(fn () => new SettingsValidationException(
    [], [1 => 'Error.'], []
), 'Sparse form errors were accepted.');

foreach (['SettingsField.php', 'SettingsSection.php'] as $contractFile) {
    $source = (string) file_get_contents($basePath . '/modules/settings-manager/Services/' . $contractFile);

    foreach (['Application', 'Request', 'PDO', 'Repository', 'Router', 'View'] as $forbidden) {
        $assert(!str_contains($source, $forbidden), "{$contractFile} leaks {$forbidden} dependency.");
    }
}

echo "M3.2 Batch 2 domain contract passed ({$assertions} assertions)." . PHP_EOL;
