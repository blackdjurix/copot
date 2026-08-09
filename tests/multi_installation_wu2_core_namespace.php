<?php

declare(strict_types=1);

use Copot\Core\Config;
use Copot\Core\CoreMigrationLedger;
use Copot\Core\CoreSchemaCompatibilityResolver;
use Copot\Core\CoreSchemaGenerationStore;
use Copot\Core\CoreSchemaMaterializer;
use Copot\Core\Database;
use Copot\Core\DatabaseTableNames;
use Copot\Core\ThemeRepository;

$basePath = dirname(__DIR__);
chdir($basePath);
require $basePath . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$host = (string) Copot\Core\Env::get('DB_HOST', '127.0.0.1');
$port = (int) Copot\Core\Env::get('DB_PORT', '3306');
$username = (string) Copot\Core\Env::get('DB_USERNAME', 'root');
$password = (string) Copot\Core\Env::get('DB_PASSWORD', '');
$server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$databaseName = 'copot_wu2_' . bin2hex(random_bytes(6));
$server->exec('CREATE DATABASE `' . $databaseName . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

$configFor = static function (string $namespace) use ($basePath, $host, $port, $databaseName, $username, $password): Config {
    $config = new Config($basePath . '/config');
    $reflection = new ReflectionClass($config);
    $property = $reflection->getProperty('items');
    $property->setAccessible(true);
    $items = $property->getValue($config);
    $items['database']['connections']['mysql']['host'] = $host;
    $items['database']['connections']['mysql']['port'] = $port;
    $items['database']['connections']['mysql']['database'] = $databaseName;
    $items['database']['connections']['mysql']['username'] = $username;
    $items['database']['connections']['mysql']['password'] = $password;
    $items['database']['connections']['mysql']['namespace'] = $namespace;
    $property->setValue($config, $items);

    return $config;
};

$materializer = new CoreSchemaMaterializer();
$schemaPath = $basePath . '/database/schema.sql';
$emptyTables = new DatabaseTableNames();
$alphaTables = new DatabaseTableNames('alpha');
$betaTables = new DatabaseTableNames('beta');

$assert($emptyTables->table('users') === 'users', 'Empty namespace changed the legacy users table name.');
$assert(str_contains(implode("\n", $materializer->statements($schemaPath, $emptyTables)), 'CREATE TABLE users'), 'Empty namespace canonical Core schema did not preserve users.');
$assert($alphaTables->table('users') === 'alpha_users', 'Non-empty namespace did not resolve users deterministically.');
$assert($alphaTables->table('modules') === 'modules', 'WU2 incorrectly claimed the Module table namespace.');
$assert($materializer->generationIdentity($schemaPath) === $materializer->generationIdentity($schemaPath), 'Core schema generation identity was not deterministic.');
$fullStatements = (new Copot\Core\InstallerSchemaRunner($schemaPath))->statements((string) file_get_contents($schemaPath));
$fullNamespaced = $materializer->namespaceStatements($fullStatements, $alphaTables);
$assert(str_contains(implode("\n", $fullNamespaced), 'CREATE TABLE alpha_users'), 'Full schema namespace pass omitted the Core namespace.');
$assert(str_contains(implode("\n", $fullNamespaced), 'CREATE TABLE modules'), 'Full schema namespace pass incorrectly altered Module ownership.');
$assert(str_contains(implode("\n", $materializer->statements($schemaPath, $alphaTables)), 'CREATE TABLE alpha_users'), 'Namespaced canonical Core schema omitted users.');
$assert(str_contains(implode("\n", $materializer->statements($schemaPath, $alphaTables)), 'REFERENCES alpha_users'), 'Namespaced foreign-key references were not rewritten.');

$connections = [];
try {
    foreach (['alpha' => $alphaTables, 'beta' => $betaTables] as $namespace => $tables) {
        $database = new Database($configFor($namespace));
        $connection = $database->connection();
        $materializer->install($connection, $schemaPath, $tables);
        $connections[$namespace] = [$database, $connection];
    }

    $alpha = $connections['alpha'][1];
    $beta = $connections['beta'][1];
    $alpha->exec("INSERT INTO alpha_themes (theme_id, name, version, type, path, is_active, metadata, created_at, updated_at) VALUES ('alpha-theme', 'Alpha', '1.0.0', 'frontend', 'themes/alpha', 0, NULL, NOW(), NOW())");
    $assert((int) $alpha->query("SELECT COUNT(*) FROM alpha_themes WHERE theme_id = 'alpha-theme'")->fetchColumn() === 1, 'Alpha Core schema did not persist its namespaced row.');
    $assert((int) $beta->query("SELECT COUNT(*) FROM beta_themes WHERE theme_id = 'alpha-theme'")->fetchColumn() === 0, 'Independent namespaced Core schemas collided.');

    $alphaLedger = new CoreMigrationLedger($alphaTables);
    $betaLedger = new CoreMigrationLedger($betaTables);
    $migration = new Copot\Core\CoreMigrationDescriptor('wu2.test', 1, '0.13.0', null, '0.13.1', 'wu2-schema', Copot\Core\CoreMigrationDescriptor::TRANSACTIONAL, 'wu2-checksum', static function (PDO $connection): void {});
    $alphaLedger->record($alpha, $migration);
    $assert(count($alphaLedger->records($alpha)) === 1, 'Alpha migration ledger did not use its namespace.');
    $assert($betaLedger->records($beta) === [], 'Beta migration ledger observed Alpha history.');

    $alphaStore = new CoreSchemaGenerationStore($alphaTables);
    $betaStore = new CoreSchemaGenerationStore($betaTables);
    $generation = $materializer->generationIdentity($schemaPath);
    $alphaStore->record($alpha, $generation, 'compatible');
    $betaStore->record($beta, $generation, 'compatible');
    $alphaState = (new CoreSchemaCompatibilityResolver($alphaStore, $alphaLedger))->resolve($alpha);
    $betaState = (new CoreSchemaCompatibilityResolver($betaStore, $betaLedger))->resolve($beta);
    $assert($alphaState['schema_generation'] === $generation && $betaState['schema_generation'] === $generation, 'Schema-generation metadata was not stored per namespace.');
    $assert($alphaState['migration_identity'] !== $betaState['migration_identity'], 'Schema generation was conflated with migration history.');

    $alphaTheme = (new ThemeRepository($connections['alpha'][0]))->findByThemeId('alpha-theme');
    $betaTheme = (new ThemeRepository($connections['beta'][0]))->findByThemeId('alpha-theme');
    $assert(is_array($alphaTheme) && $betaTheme === null, 'ThemeRepository bypassed the authoritative Core naming boundary.');
} finally {
    $server->exec('DROP DATABASE IF EXISTS `' . $databaseName . '`');
}

echo "WU2 Core namespace focused tests passed ({$assertions} assertions)." . PHP_EOL;
