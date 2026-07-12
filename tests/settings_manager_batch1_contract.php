<?php

declare(strict_types=1);

use Copot\Core\Application;
use Copot\Core\Env;
use Copot\Core\SettingDefinition;

$basePath = dirname(__DIR__);

chdir($basePath);
require $basePath . '/bootstrap/autoload.php';
Env::load($basePath . '/.env');

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;

    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$app = new Application($basePath);
$connection = $app->database()->connection();
$connection->beginTransaction();

try {
    $definitions = $app->settings()->definitions();
    $identifiers = array_map(
        static fn (SettingDefinition $definition): string => $definition->identifier(),
        $definitions
    );
    $expectedIdentifiers = [
        'localization.date_format',
        'localization.locale',
        'localization.time_format',
        'localization.timezone',
        'site.favicon',
        'site.logo',
        'site.name',
        'site.tagline',
    ];

    $assert($identifiers === $expectedIdentifiers, 'Definition discovery ordering is not deterministic.');
    $assert(count($definitions) === 8, 'Definition discovery did not return the approved registry set.');
    $assert(
        array_reduce(
            $definitions,
            static fn (bool $valid, mixed $definition): bool => $valid && $definition instanceof SettingDefinition,
            true
        ),
        'Definition discovery exposed non-definition values.'
    );

    $siteDefinitions = $app->settings()->definitions('site');
    $siteIdentifiers = array_map(
        static fn (SettingDefinition $definition): string => $definition->identifier(),
        $siteDefinitions
    );
    $assert(
        $siteIdentifiers === ['site.favicon', 'site.logo', 'site.name', 'site.tagline'],
        'Namespace-filtered definition discovery is incorrect.'
    );

    $groups = $app->settings()->definitionGroups();
    $assert(array_keys($groups) === ['localization', 'site'], 'Definition groups are not stably ordered.');
    $assert(count($groups['localization'] ?? []) === 4, 'Localization definition group is incomplete.');
    $assert(count($groups['site'] ?? []) === 4, 'Site definition group is incomplete.');

    $unknownKey = 'batch1_unregistered_' . bin2hex(random_bytes(4));
    $connection->prepare(
        'INSERT INTO settings (namespace, setting_key, setting_value, value_type, created_at, updated_at)
        VALUES (:namespace, :setting_key, :setting_value, :value_type, NOW(), NOW())'
    )->execute([
        'namespace' => 'batch1',
        'setting_key' => $unknownKey,
        'setting_value' => 'must-not-be-discovered',
        'value_type' => 'string',
    ]);

    $afterUnknownInsert = array_map(
        static fn (SettingDefinition $definition): string => $definition->identifier(),
        $app->settings()->definitions()
    );
    $assert(
        $afterUnknownInsert === $expectedIdentifiers,
        'An unregistered stored row became an editable setting definition.'
    );
    $assert(
        $app->settings()->get('batch1', $unknownKey, 'controlled-default') === 'controlled-default',
        'An unregistered stored row bypassed registry ownership.'
    );

    $originalName = $app->settings()->get('site', 'name');
    $storedName = 'Batch 1 discovery ' . bin2hex(random_bytes(4));
    $app->settings()->set('site', 'name', $storedName);
    $assert($app->settings()->get('site', 'name') === $storedName, 'Resolved effective value did not use stored override.');
    $assert(
        array_map(
            static fn (SettingDefinition $definition): string => $definition->identifier(),
            $app->settings()->definitions()
        ) === $expectedIdentifiers,
        'Stored overrides changed the registered definition set.'
    );
    $assert($originalName !== null, 'Existing registered setting could not be resolved.');

    $beforeDiscoveryCount = (int) $connection->query('SELECT COUNT(*) FROM settings')->fetchColumn();
    $app->settings()->definitions();
    $app->settings()->definitions('localization');
    $app->settings()->definitionGroups();
    $afterDiscoveryCount = (int) $connection->query('SELECT COUNT(*) FROM settings')->fetchColumn();
    $assert($afterDiscoveryCount === $beforeDiscoveryCount, 'Definition discovery mutated settings storage.');

    echo "M3.2 Batch 1 settings definition contract passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }
}
