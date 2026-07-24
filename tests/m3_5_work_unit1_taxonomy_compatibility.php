<?php

declare(strict_types=1);

use Copot\Core\Config;
use Copot\Core\Database;
use Copot\Core\Env;
use Copot\Core\InstallerSchemaRunner;
use Copot\Core\ModuleDiscovery;
use Copot\Core\ModuleManager;
use Copot\Core\ModuleRepository;

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

$host = (string) Env::get('DB_HOST', '127.0.0.1');
$port = (int) Env::get('DB_PORT', '3306');
$username = (string) Env::get('DB_USERNAME', 'root');
$password = (string) Env::get('DB_PASSWORD', '');
$databaseName = 'copot_m35_wu1_taxonomy_' . bin2hex(random_bytes(6));
$quotedDatabase = '`' . str_replace('`', '``', $databaseName) . '`';
$configuration = [
    'host' => $host,
    'port' => $port,
    'database' => $databaseName,
    'username' => $username,
    'password' => $password,
];

$server = new PDO(
    "mysql:host={$host};port={$port};charset=utf8mb4",
    $username,
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
);
$server->exec('CREATE DATABASE ' . $quotedDatabase . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

$connection = null;

try {
    $installedStatements = (new InstallerSchemaRunner($basePath . '/database/schema.sql'))->install($configuration);
    $assert($installedStatements > 0, 'Fresh installation must execute the canonical schema.');

    $connection = new PDO(
        "mysql:host={$host};port={$port};dbname={$databaseName};charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    $tableExists = static function (string $table) use ($connection): bool {
        $statement = $connection->prepare(
            'SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = :table'
        );
        $statement->execute(['table' => $table]);

        return (int) $statement->fetchColumn() === 1;
    };

    $column = static function (string $table, string $name) use ($connection): array {
        $statement = $connection->prepare(
            'SELECT column_name, is_nullable, column_type
             FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :name'
        );
        $statement->execute(['table' => $table, 'name' => $name]);

        return $statement->fetch() ?: [];
    };

    $assert($tableExists('taxonomy_types'), 'Fresh schema must provide taxonomy_types.');
    $assert($tableExists('taxonomy_terms'), 'Fresh schema must provide taxonomy_terms.');
    $assert($tableExists('taxonomy_assignments'), 'Fresh schema must provide taxonomy_assignments.');

    $hierarchicalColumn = $column('taxonomy_types', 'is_hierarchical');
    $parentColumn = $column('taxonomy_terms', 'parent_id');
    $assert($hierarchicalColumn !== [] && str_contains((string) $hierarchicalColumn['column_type'], 'tinyint'), 'taxonomy_types.is_hierarchical must be provisioned.');
    $assert($parentColumn !== [] && $parentColumn['is_nullable'] === 'YES', 'taxonomy_terms.parent_id must be nullable.');

    $indexStatement = $connection->query(
        "SELECT index_name, GROUP_CONCAT(column_name ORDER BY seq_in_index) AS columns
         FROM information_schema.statistics
         WHERE table_schema = DATABASE() AND table_name = 'taxonomy_terms'
         GROUP BY index_name"
    );
    $indexes = [];
    foreach ($indexStatement->fetchAll() as $index) {
        $indexes[(string) $index['index_name']] = (string) $index['columns'];
    }
    $assert(($indexes['idx_taxonomy_terms_type_parent'] ?? null) === 'taxonomy_type_id,parent_id', 'Taxonomy parent index must cover type and parent_id.');

    $foreignKeyStatement = $connection->query(
        "SELECT constraint_name, referenced_table_name, delete_rule
         FROM information_schema.referential_constraints
         WHERE constraint_schema = DATABASE() AND table_name = 'taxonomy_terms'"
    );
    $foreignKeys = [];
    foreach ($foreignKeyStatement->fetchAll() as $foreignKey) {
        $foreignKeys[(string) $foreignKey['constraint_name']] = $foreignKey;
    }
    $assert(($foreignKeys['fk_taxonomy_terms_parent']['referenced_table_name'] ?? null) === 'taxonomy_terms', 'Taxonomy parent FK must reference taxonomy_terms.');
    $assert(($foreignKeys['fk_taxonomy_terms_parent']['delete_rule'] ?? null) === 'SET NULL', 'Taxonomy parent FK must preserve the baseline SET NULL rule.');

    $types = $connection->query(
        "SELECT slug, is_hierarchical FROM taxonomy_types WHERE slug IN ('category', 'tag') ORDER BY slug"
    )->fetchAll();
    $types = array_map(
        static fn (array $type): array => [
            'slug' => (string) $type['slug'],
            'is_hierarchical' => (int) $type['is_hierarchical'],
        ],
        $types
    );
    $assert($types === [
        ['slug' => 'category', 'is_hierarchical' => 1],
        ['slug' => 'tag', 'is_hierarchical' => 0],
    ], 'Fresh schema must seed category as hierarchical and tag as flat.');

    $permissionCount = (int) $connection->query(
        "SELECT COUNT(*) FROM permissions WHERE slug IN ('taxonomy.create', 'taxonomy.update', 'taxonomy.delete')"
    )->fetchColumn();
    $adminPermissionCount = (int) $connection->query(
        "SELECT COUNT(*)
         FROM role_permissions
         INNER JOIN roles ON roles.id = role_permissions.role_id
         INNER JOIN permissions ON permissions.id = role_permissions.permission_id
         WHERE roles.slug = 'admin' AND permissions.slug IN ('taxonomy.create', 'taxonomy.update', 'taxonomy.delete')"
    )->fetchColumn();
    $assert($permissionCount === 3, 'Fresh schema must provision all Taxonomy permissions.');
    $assert($adminPermissionCount === 3, 'Fresh schema must grant the baseline Taxonomy permissions to admin.');

    $temporaryConfig = new Config($basePath . '/config');
    $configReflection = new ReflectionClass($temporaryConfig);
    $configItems = $configReflection->getProperty('items');
    $configuredItems = $configItems->getValue($temporaryConfig);
    $configuredItems['database']['connections']['mysql']['host'] = $host;
    $configuredItems['database']['connections']['mysql']['port'] = $port;
    $configuredItems['database']['connections']['mysql']['database'] = $databaseName;
    $configuredItems['database']['connections']['mysql']['username'] = $username;
    $configuredItems['database']['connections']['mysql']['password'] = $password;
    $configItems->setValue($temporaryConfig, $configuredItems);
    $moduleManager = new ModuleManager(
        new ModuleDiscovery($basePath . '/modules'),
        new ModuleRepository(new Database($temporaryConfig))
    );
    $moduleManager->install('taxonomy');
    $moduleManager->enable('taxonomy');
    $module = $connection->query("SELECT status FROM modules WHERE name = 'taxonomy'")->fetchColumn();
    $assert($module === 'enabled', 'Taxonomy module must install and enable against a fresh schema.');
    $assert((int) $connection->query("SELECT COUNT(*) FROM module_permissions WHERE module_name = 'taxonomy'")->fetchColumn() === 3, 'Taxonomy module activation must register its three manifest permissions.');

    $categoryTypeId = (int) $connection->query("SELECT id FROM taxonomy_types WHERE slug = 'category'")->fetchColumn();
    $tagTypeId = (int) $connection->query("SELECT id FROM taxonomy_types WHERE slug = 'tag'")->fetchColumn();
    $now = date('Y-m-d H:i:s');
    $fixtures = [
        'category_root' => ['type_id' => $categoryTypeId, 'parent_id' => null, 'name' => 'Guides', 'slug' => 'guides'],
        'category_child' => ['type_id' => $categoryTypeId, 'parent_id' => 'category_root', 'name' => 'Getting Started', 'slug' => 'getting-started'],
        'category_descendant' => ['type_id' => $categoryTypeId, 'parent_id' => 'category_child', 'name' => 'Installation', 'slug' => 'installation'],
        'category_parent_with_children' => ['type_id' => $categoryTypeId, 'parent_id' => null, 'name' => 'Parent With Children', 'slug' => 'parent-with-children'],
        'tag_flat' => ['type_id' => $tagTypeId, 'parent_id' => null, 'name' => 'Featured', 'slug' => 'featured'],
        'tag_unassigned' => ['type_id' => $tagTypeId, 'parent_id' => null, 'name' => 'Unassigned', 'slug' => 'unassigned'],
    ];
    $insertTerm = $connection->prepare(
        'INSERT INTO taxonomy_terms (taxonomy_type_id, parent_id, name, slug, description, sort_order, created_at, updated_at)
         VALUES (:type_id, :parent_id, :name, :slug, NULL, 0, :created_at, :updated_at)'
    );
    foreach ($fixtures as $key => &$fixture) {
        $parentId = $fixture['parent_id'];
        if (is_string($parentId)) {
            $parentId = $fixtures[$parentId]['id'];
        }
        $insertTerm->execute([
            'type_id' => $fixture['type_id'],
            'parent_id' => $parentId,
            'name' => $fixture['name'],
            'slug' => $fixture['slug'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $fixture['id'] = (int) $connection->lastInsertId();
    }
    unset($fixture);

    $assert($fixtures['category_root']['parent_id'] === null, 'Category root fixture must have no parent.');
    $assert($fixtures['category_child']['id'] > 0 && $fixtures['category_descendant']['id'] > 0, 'Category child and descendant fixtures must persist.');
    $assert($fixtures['tag_flat']['parent_id'] === null, 'Flat tag fixture must use parent_id = null.');
    $assert($fixtures['category_parent_with_children']['id'] > 0, 'Parent-with-children fixture state must persist.');

    $connection->exec(
        "INSERT INTO content (type, title, slug, body, status, author_id, created_at, updated_at)
         VALUES ('article', 'Taxonomy Compatibility Fixture', 'taxonomy-compatibility-fixture', 'Fixture body', 'draft', NULL, NOW(), NOW())"
    );
    $contentId = (int) $connection->lastInsertId();
    $assignment = $connection->prepare(
        'INSERT INTO taxonomy_assignments (taxonomy_term_id, entity_type, entity_id, created_at)
         VALUES (:term_id, :entity_type, :entity_id, :created_at)'
    );
    foreach (['category_root', 'tag_flat'] as $key) {
        $assignment->execute([
            'term_id' => $fixtures[$key]['id'],
            'entity_type' => 'content',
            'entity_id' => $contentId,
            'created_at' => $now,
        ]);
    }
    $assert((int) $connection->query("SELECT COUNT(*) FROM taxonomy_assignments WHERE entity_type = 'content' AND entity_id = {$contentId}")->fetchColumn() === 2, 'Content taxonomy assignments must remain compatible.');
    $assert((int) $connection->query("SELECT COUNT(*) FROM taxonomy_assignments WHERE taxonomy_term_id = {$fixtures['tag_unassigned']['id']}")->fetchColumn() === 0, 'Unassigned term fixture must remain unassigned.');
    $assert((int) $connection->query("SELECT COUNT(*) FROM taxonomy_terms WHERE id = 999999999")->fetchColumn() === 0, 'Stale identifier fixture must resolve to no term.');

    $reconnected = new PDO(
        "mysql:host={$host};port={$port};dbname={$databaseName};charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    $assert((int) $reconnected->query("SELECT COUNT(*) FROM taxonomy_terms WHERE slug IN ('guides', 'getting-started', 'installation', 'featured')")->fetchColumn() === 4, 'Existing installation must retain the category tree and flat tag fixtures.');
    $assert((string) $reconnected->query("SELECT status FROM modules WHERE name = 'taxonomy'")->fetchColumn() === 'enabled', 'Existing installation must retain enabled Taxonomy module state.');
    $assert((int) $reconnected->query("SELECT COUNT(*) FROM taxonomy_assignments WHERE entity_type = 'content' AND entity_id = {$contentId}")->fetchColumn() === 2, 'Existing installation must retain Content taxonomy assignments.');
    $assert(glob($basePath . '/database/upgrades/*taxonomy*') === [], 'No speculative Taxonomy upgrade artifact may be required by the passing baseline.');

    echo "M3.5 Work Unit 1 Taxonomy compatibility passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    $server->exec('DROP DATABASE IF EXISTS ' . $quotedDatabase);
}
