<?php

declare(strict_types=1);

$base = dirname(__DIR__);
chdir($base);
require $base . '/bootstrap/autoload.php';

$host = '127.0.0.1';
$port = 3306;
$user = 'root';
$password = '';
$name = 'copot_wu6_schema_' . bin2hex(random_bytes(5));
$namespace = 'wu6test';
$server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$quoted = '`' . str_replace('`', '``', $name) . '`';
$server->exec("CREATE DATABASE {$quoted} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

try {
    $configuration = ['host' => $host, 'port' => $port, 'database' => $name, 'username' => $user, 'password' => '', 'namespace' => $namespace];
    (new Copot\Core\InstallerSchemaRunner($base . '/database/schema.sql'))->install($configuration);
    $tables = new Copot\Core\DatabaseTableNames($namespace);
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4", $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    foreach (['content', 'media', 'media_usages', 'navigation_menus', 'navigation_items', 'redirects'] as $table) {
        $assert((int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '" . $tables->table($table) . "'")->fetchColumn() === 1, "Webcore baseline table [{$table}] was not materialized.");
    }
    foreach (['taxonomy_types', 'taxonomy_terms', 'taxonomy_assignments', 'navigation_menu_assignments', 'media_variants', 'forms'] as $table) {
        $assert((int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '" . $tables->moduleTable($table) . "'")->fetchColumn() === 0, "Module-owned table [{$table}] was pre-materialized.");
    }

    putenv('DB_HOST=' . $host); putenv('DB_PORT=' . $port); putenv('DB_DATABASE=' . $name); putenv('DB_USERNAME=' . $user); putenv('DB_PASSWORD='); putenv('DB_NAMESPACE=' . $namespace);
    $database = new Copot\Core\Database(new Copot\Core\Config($base . '/config'));
    $manager = new Copot\Core\ModuleManager(new Copot\Core\ModuleDiscovery($base . '/modules'), new Copot\Core\ModuleRepository($database));
    foreach (['taxonomy', 'navigation', 'media', 'form-manager'] as $module) {
        $manager->install($module);
    }
    foreach (['taxonomy_types', 'taxonomy_terms', 'taxonomy_assignments', 'navigation_menu_assignments', 'media_variants', 'forms', 'form_fields', 'form_field_options', 'form_submissions', 'form_submission_values', 'form_submission_attempts'] as $table) {
        $assert((int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '" . $tables->moduleTable($table) . "'")->fetchColumn() === 1, "Module lifecycle did not materialize [{$table}].");
    }
    $taxonomyTypes = (int) $pdo->query('SELECT COUNT(*) FROM `' . $tables->moduleTable('taxonomy_types') . '`')->fetchColumn();
    $assert($taxonomyTypes === 2, 'Taxonomy baseline seed was not materialized exactly once.');
    $definitions = $manager->discover();
    foreach ($definitions as $definition) {
        if ($definition->name() === 'taxonomy') {
            (new Copot\Core\ModuleSchemaProvisioner($database))->provision($definition, new Copot\Core\ModuleProvisioningContext($tables));
            break;
        }
    }
    $assert((int) $pdo->query('SELECT COUNT(*) FROM `' . $tables->moduleTable('taxonomy_types') . '`')->fetchColumn() === 2, 'Repeated Module schema reconciliation duplicated taxonomy seed state.');

    echo "WU6 Module schema materialization assertions: {$assertions}\n";
} finally {
    putenv('DB_HOST'); putenv('DB_PORT'); putenv('DB_DATABASE'); putenv('DB_USERNAME'); putenv('DB_PASSWORD'); putenv('DB_NAMESPACE');
    try { $server->exec("DROP DATABASE IF EXISTS {$quoted}"); } catch (Throwable) { }
}
