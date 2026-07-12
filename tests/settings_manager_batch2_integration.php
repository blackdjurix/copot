<?php

declare(strict_types=1);

use Copot\Core\Application;
use Copot\Core\Database;
use Copot\Core\Env;
use Copot\Core\SettingDefinition;
use Copot\Core\SettingsRegistry;
use Copot\Core\SettingsRepository;
use Copot\Core\SettingsService;

$basePath = dirname(__DIR__);

require $basePath . '/bootstrap/autoload.php';
require $basePath . '/modules/settings-manager/Services/SettingsManagerPolicy.php';
require $basePath . '/modules/settings-manager/Services/SettingsField.php';
require $basePath . '/modules/settings-manager/Services/SettingsSection.php';
require $basePath . '/modules/settings-manager/Services/SettingsFieldMapper.php';
require $basePath . '/modules/settings-manager/Services/SettingsValidationException.php';
require $basePath . '/modules/settings-manager/Services/SettingsManager.php';

Env::load($basePath . '/.env');

final class RecordingSettingsService extends SettingsService
{
    public array $writes = [];
    public ?int $failAtWrite = null;
    public bool $failValidationOperationally = false;

    public function validate(string $namespace, string $key, mixed $value, ?string $type = null): void
    {
        if ($this->failValidationOperationally) {
            throw new RuntimeException('CONTROLLED_OPERATIONAL_FAILURE');
        }

        parent::validate($namespace, $key, $value, $type);
    }

    public function set(string $namespace, string $key, mixed $value, ?string $type = null): void
    {
        $this->writes[] = $namespace . '.' . $key;

        if ($this->failAtWrite === count($this->writes)) {
            throw new PDOException('CONTROLLED_STORAGE_FAILURE');
        }

        parent::set($namespace, $key, $value, $type);
    }
}

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;

    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$captureValidation = static function (callable $operation): SettingsValidationException {
    try {
        $operation();
    } catch (SettingsValidationException $exception) {
        return $exception;
    }

    throw new RuntimeException('Expected SettingsValidationException was not thrown.');
};
$productionInput = static fn (string $name, string $tagline = ''): array => [
    'site.name' => $name,
    'site.tagline' => $tagline,
    'localization.timezone' => 'UTC',
    'localization.locale' => 'en_US',
    'localization.date_format' => 'Y-m-d',
    'localization.time_format' => 'H:i',
];

$app = new Application($basePath);
$database = $app->database();
$connection = $database->connection();
$registry = SettingsRegistry::core();
$service = new RecordingSettingsService($registry, new SettingsRepository($database));
$manager = new SettingsManager($service, new SettingsFieldMapper(SettingsManagerPolicy::defaults()), $database);
$snapshot = [];

foreach (SettingsManagerPolicy::defaults()->fields() as $fieldPolicy) {
    [$namespace, $key] = explode('.', $fieldPolicy['identifier'], 2);
    $statement = $connection->prepare(
        'SELECT * FROM settings WHERE namespace = :namespace AND setting_key = :setting_key LIMIT 1'
    );
    $statement->execute(['namespace' => $namespace, 'setting_key' => $key]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    $snapshot[$fieldPolicy['identifier']] = is_array($row) ? $row : null;
}

try {
    $sections = $manager->sections();
    $identifiers = [];

    foreach ($sections as $sectionValue) {
        foreach ($sectionValue->fields() as $fieldValue) {
            $identifiers[] = $fieldValue->identifier();
        }
    }

    $assert($identifiers === [
        'site.name',
        'site.tagline',
        'localization.timezone',
        'localization.locale',
        'localization.date_format',
        'localization.time_format',
    ], 'Manager did not expose exactly the six approved production definitions.');

    $rootName = 'Batch 2 root ' . bin2hex(random_bytes(4));
    $service->writes = [];
    $manager->save($productionInput($rootName, 'root transaction'));
    $assert(!$connection->inTransaction(), 'Successful root save left a transaction active.');
    $assert($service->get('site', 'name') === $rootName, 'Successful root save did not persist.');
    $assert($service->writes === $identifiers, 'Writes did not follow deterministic policy order.');

    $beforeFailureName = $service->get('site', 'name');
    $beforeFailureTagline = $service->get('site', 'tagline');
    $service->writes = [];
    $service->failAtWrite = 2;

    try {
        $manager->save($productionInput('must roll back', 'must roll back'));
        throw new RuntimeException('Controlled later-write failure did not throw.');
    } catch (PDOException $exception) {
        $assert($exception->getMessage() === 'CONTROLLED_STORAGE_FAILURE',
            'Controlled storage failure was not propagated.');
    } finally {
        $service->failAtWrite = null;
    }

    $assert(!$connection->inTransaction(), 'Failed root save left a transaction active.');
    $assert($service->get('site', 'name') === $beforeFailureName, 'Root rollback did not restore the earlier write.');
    $assert($service->get('site', 'tagline') === $beforeFailureTagline, 'Root rollback changed a later value.');

    $connection->beginTransaction();

    $unknownInput = $productionInput('unknown check');
    $unknownInput['secret.token'] = 'blocked';
    $unknown = $captureValidation(fn () => $manager->save($unknownInput));
    $assert(count($unknown->formErrors()) === 1, 'Unknown identifier did not produce a form error.');
    $assert(!array_key_exists('secret.token', $unknown->submittedValues()),
        'Unknown identifier leaked into submitted values.');

    $priorName = $service->get('site', 'name');
    $priorTagline = $service->get('site', 'tagline');
    $invalid = $productionInput('', 'must not persist');
    $invalid['localization.locale'] = 'xx_XX';
    $aggregate = $captureValidation(fn () => $manager->save($invalid));
    $assert(array_keys($aggregate->fieldErrors()) === ['site.name', 'localization.locale'],
        'Multiple field errors were not aggregated deterministically.');
    $assert($aggregate->formErrors() === [], 'Field validation produced a form error.');
    $assert($aggregate->submittedValues()['localization.locale'] === 'xx_XX',
        'Invalid select value was not retained safely.');
    $assert($service->get('site', 'name') === $priorName, 'Validation failure changed the prior site name.');
    $assert($service->get('site', 'tagline') === $priorTagline, 'Validation failure partially persisted a valid field.');

    $optionalBefore = $service->get('site', 'tagline');
    $optionalInput = $productionInput('optional omission');
    unset($optionalInput['site.tagline']);
    $service->writes = [];
    $manager->save($optionalInput);
    $assert($service->get('site', 'tagline') === $optionalBefore,
        'Missing optional field changed its prior value.');
    $assert(!in_array('site.tagline', $service->writes, true),
        'Missing optional field was written.');
    $assert(count($service->writes) === 5, 'Missing optional field did not save exactly the submitted candidates.');

    $requiredMissing = $productionInput('required missing');
    unset($requiredMissing['site.name']);
    $service->writes = [];
    $requiredFailure = $captureValidation(fn () => $manager->save($requiredMissing));
    $assert(isset($requiredFailure->fieldErrors()['site.name']), 'Missing required field was not rejected.');
    $assert(!array_key_exists('site.name', $requiredFailure->submittedValues()),
        'Missing required field appeared in submitted values.');
    $assert($service->writes === [], 'Validation failure for a missing required field performed writes.');

    $manager->save($productionInput('empty check', ''));
    $assert($service->get('site', 'tagline') === '', 'Explicit empty string was treated as missing.');
    $assert($connection->inTransaction(), 'Nested successful save committed the caller transaction.');

    $nestedBefore = $service->get('site', 'name');
    $service->writes = [];
    $service->failAtWrite = 2;

    try {
        $manager->save($productionInput('nested rollback', 'nested rollback'));
        throw new RuntimeException('Nested controlled failure did not throw.');
    } catch (PDOException) {
        $assert(true, 'Nested controlled failure was not propagated.');
    } finally {
        $service->failAtWrite = null;
    }

    $assert($connection->inTransaction(), 'Nested failure rolled back the caller transaction.');
    $assert($service->get('site', 'name') === $nestedBefore, 'Nested rollback did not restore manager-unit writes.');

    $service->failValidationOperationally = true;

    try {
        $manager->save($productionInput('operational check'));
        throw new RuntimeException('Operational validation failure did not propagate.');
    } catch (RuntimeException $exception) {
        $assert($exception->getMessage() === 'CONTROLLED_OPERATIONAL_FAILURE',
            'Operational failure became validation feedback.');
    } finally {
        $service->failValidationOperationally = false;
    }

    $fixtureRegistry = new SettingsRegistry([
        new SettingDefinition('fixture', 'count', 'integer', 1),
        new SettingDefinition('fixture', 'ratio', 'float', 1.0),
        new SettingDefinition('fixture', 'enabled', 'boolean', true),
        new SettingDefinition('fixture', 'choice', 'integer', 1, allowedValues: [1, 2]),
    ]);
    $fixtureService = new RecordingSettingsService($fixtureRegistry, new SettingsRepository($database));
    $fixturePolicy = new SettingsManagerPolicy(
        [[
            'identifier' => 'fixture',
            'label' => 'Fixture',
            'description' => null,
            'order' => 10,
        ]],
        array_map(
            static fn (array $value): array => [
                'identifier' => 'fixture.' . $value[0],
                'section' => 'fixture',
                'label' => ucfirst($value[0]),
                'description' => null,
                'presentation' => null,
                'required' => true,
                'order' => $value[1],
                'constraints' => [],
            ],
            [['count', 10], ['ratio', 20], ['enabled', 30], ['choice', 40]]
        )
    );
    $fixtureManager = new SettingsManager(
        $fixtureService,
        new SettingsFieldMapper($fixturePolicy),
        $database
    );
    $numericFailure = $captureValidation(fn () => $fixtureManager->save([
        'fixture.count' => '0012',
        'fixture.ratio' => '1.50',
        'fixture.choice' => '9',
    ]));
    $assert($numericFailure->submittedValues()['fixture.count'] === '0012',
        'Integer display value was not preserved.');
    $assert($numericFailure->submittedValues()['fixture.ratio'] === '1.50',
        'Float display value was not preserved.');
    $assert($numericFailure->submittedValues()['fixture.enabled'] === false,
        'Missing checkbox was not normalized to false.');
    $assert($numericFailure->submittedValues()['fixture.choice'] === '9',
        'Invalid numeric select was not retained safely.');
    $assert(isset($numericFailure->fieldErrors()['fixture.choice']),
        'Invalid allowed value was not rejected.');

    if ($connection->inTransaction()) {
        $connection->rollBack();
    }

    echo "M3.2 Batch 2 integration contract passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }

    $connection->beginTransaction();

    try {
        foreach ($snapshot as $identifier => $row) {
            [$namespace, $key] = explode('.', $identifier, 2);
            $connection->prepare(
                'DELETE FROM settings WHERE namespace = :namespace AND setting_key = :setting_key'
            )->execute(['namespace' => $namespace, 'setting_key' => $key]);

            if (is_array($row)) {
                $connection->prepare(
                    'INSERT INTO settings (
                        id, namespace, setting_key, setting_value, value_type, created_at, updated_at
                    ) VALUES (
                        :id, :namespace, :setting_key, :setting_value, :value_type, :created_at, :updated_at
                    )'
                )->execute([
                    'id' => $row['id'],
                    'namespace' => $row['namespace'],
                    'setting_key' => $row['setting_key'],
                    'setting_value' => $row['setting_value'],
                    'value_type' => $row['value_type'],
                    'created_at' => $row['created_at'],
                    'updated_at' => $row['updated_at'],
                ]);
            }
        }

        $connection->commit();
    } catch (Throwable $cleanupFailure) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }

        throw new RuntimeException('Batch 2 integration cleanup failed.', 0, $cleanupFailure);
    }
}
