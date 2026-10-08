<?php

declare(strict_types=1);

use Copot\Core\DatabaseHealthVerifier;
final class DatabaseHealthVerifierRegressionStatement
{
    public function __construct(private array $rows = [], private mixed $column = null) {}
    public function fetchAll(...$arguments): array { return $this->rows; }
    public function fetchColumn(...$arguments): mixed { return $this->column; }
}

final class DatabaseHealthVerifierRegressionPdo extends PDO
{
    public function __construct(private array $tables) {}

    public function query($query, ...$arguments)
    {
        if (str_contains($query, 'SELECT 1')) {
            return new DatabaseHealthVerifierRegressionStatement([], 1);
        }

        if (str_contains($query, 'information_schema.tables')) {
            return new DatabaseHealthVerifierRegressionStatement($this->tables);
        }

        if (str_contains($query, 'DESCRIBE core_migration_history')) {
            return new DatabaseHealthVerifierRegressionStatement([
                'migration_id', 'sequence_number', 'target_webcore_version',
                'target_schema_identity', 'migration_checksum', 'applied_at',
            ]);
        }

        throw new RuntimeException('Unexpected health-verifier query: ' . $query);
    }
}

$basePath = dirname(__DIR__);
chdir($basePath);
require $basePath . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) { throw new RuntimeException($message); }
};

$coreTables = [
    'users', 'roles', 'permissions', 'user_roles', 'role_permissions', 'settings',
    'themes', 'content', 'media', 'media_usages', 'navigation_menus', 'navigation_items',
    'modules', 'module_permissions',
    'redirects', 'security_login_attempts', 'security_sessions', 'security_events',
    'core_migration_history',
];

$withoutModuleTaxonomy = (new DatabaseHealthVerifier())->verify(
    new DatabaseHealthVerifierRegressionPdo($coreTables)
);
$assert($withoutModuleTaxonomy->passed(), 'Missing Module-owned taxonomy tables failed Webcore database health.');

$withoutRequiredCoreTable = (new DatabaseHealthVerifier())->verify(
    new DatabaseHealthVerifierRegressionPdo(array_values(array_diff($coreTables, ['users'])))
);
$assert(!$withoutRequiredCoreTable->passed(), 'Missing required Webcore table passed database health.');
$assert(str_contains($withoutRequiredCoreTable->failureReason(), 'users'), 'Required Webcore-table failure did not identify users.');

echo "Database health verifier regression tests passed ({$assertions} assertions)." . PHP_EOL;
