<?php

declare(strict_types=1);

use Copot\Core\Config;
use Copot\Core\Database;
use Copot\Core\Env;
use Copot\Core\InstallerSchemaRunner;

$basePath = dirname(__DIR__);
chdir($basePath);
require $basePath . '/bootstrap/autoload.php';
Env::load($basePath . '/.env');
require_once $basePath . '/modules/taxonomy/Services/TaxonomyType.php';
require_once $basePath . '/modules/taxonomy/Services/TaxonomyTerm.php';
require_once $basePath . '/modules/taxonomy/Services/TaxonomyAssignmentRepository.php';
require_once $basePath . '/modules/taxonomy/Services/TaxonomyRepository.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};
$rejects = static function (callable $operation, string $message) use ($assert): void {
    try { $operation(); } catch (RuntimeException) { $assert(true, $message); return; }
    $assert(false, $message);
};

$host = (string) Env::get('DB_HOST', '127.0.0.1');
$port = (int) Env::get('DB_PORT', '3306');
$user = (string) Env::get('DB_USERNAME', 'root');
$password = (string) Env::get('DB_PASSWORD', '');
$name = 'copot_m35_wu2_' . bin2hex(random_bytes(6));
$quoted = '`' . str_replace('`', '``', $name) . '`';
$config = ['host' => $host, 'port' => $port, 'database' => $name, 'username' => $user, 'password' => $password];
$server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
$server->exec('CREATE DATABASE ' . $quoted . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

try {
    (new InstallerSchemaRunner($basePath . '/database/schema.sql'))->install($config);
    $_ENV['DB_DATABASE'] = $name;
    putenv('DB_DATABASE=' . $name);
    $database = new Database(new Config($basePath . '/config'));
    $db = $database->connection();
    $repository = new TaxonomyRepository($database);
    $assignments = new TaxonomyAssignmentRepository($database);
    $category = (int) $db->query("SELECT id FROM taxonomy_types WHERE slug = 'category'")->fetchColumn();
    $tag = (int) $db->query("SELECT id FROM taxonomy_types WHERE slug = 'tag'")->fetchColumn();
    $payload = static fn (int $type, string $slug, mixed $parent = null): array => [
        'taxonomy_type_id' => $type, 'parent_id' => $parent, 'name' => ucfirst(str_replace('-', ' ', $slug)),
        'slug' => $slug, 'description' => null, 'sort_order' => 0,
    ];
    $row = static function (PDO $db, int $id): ?array {
        $statement = $db->prepare('SELECT taxonomy_type_id, parent_id, name, slug, description, sort_order FROM taxonomy_terms WHERE id = :id');
        $statement->execute(['id' => $id]);
        $value = $statement->fetch();
        return is_array($value) ? $value : null;
    };

    $root = $repository->createTerm($payload($category, 'wu2-root'));
    $assert($root > 0 && !$db->inTransaction(), 'Root create failed or left a transaction open.');
    $child = $repository->createTerm($payload($category, 'wu2-child', $root));
    $assert((int) $row($db, $child)['parent_id'] === $root, 'Child category create failed.');
    $other = $repository->createTerm($payload($category, 'wu2-other'));
    $repository->updateTerm($child, $payload($category, 'wu2-child', $other));
    $assert((int) $row($db, $child)['parent_id'] === $other, 'Valid reparent failed.');
    $repository->updateTerm($child, $payload($category, 'wu2-child', ''));
    $assert($row($db, $child)['parent_id'] === null, 'Parent removal failed.');

    $count = (int) $db->query('SELECT COUNT(*) FROM taxonomy_terms')->fetchColumn();
    $rejects(fn () => $repository->createTerm($payload($category, 'stale-parent', 999999999)), 'Stale parent was accepted.');
    $assert((int) $db->query('SELECT COUNT(*) FROM taxonomy_terms')->fetchColumn() === $count, 'Rejected create left a row.');
    foreach ([0, -1, true, false, [], new stdClass(), 1.5, '1.5', '1e2', ' ', '-2'] as $index => $invalid) {
        $rejects(fn () => $repository->createTerm($payload($category, 'malformed-' . $index, $invalid)), 'Malformed parent was accepted.');
    }
    $tagTerm = $repository->createTerm($payload($tag, 'wu2-tag'));
    $rejects(fn () => $repository->createTerm($payload($category, 'wrong-parent', $tagTerm)), 'Wrong-type parent was accepted.');
    $rejects(fn () => $repository->createTerm($payload($tag, 'tag-parent', $root)), 'Tag parent was accepted.');

    $repository->updateTerm($child, $payload($category, 'wu2-child', $root));
    $before = $row($db, $root);
    $rejects(fn () => $repository->updateTerm($root, $payload($category, 'wu2-root', $root)), 'Self-parent was accepted.');
    $rejects(fn () => $repository->updateTerm($root, $payload($category, 'wu2-root', $child)), 'Direct descendant was accepted.');
    $deep = $repository->createTerm($payload($category, 'wu2-deep', $child));
    $rejects(fn () => $repository->updateTerm($root, $payload($category, 'wu2-root', $deep)), 'Deep descendant was accepted.');
    $assert($row($db, $root) === $before, 'Rejected update changed the target row.');
    $rejects(fn () => $repository->updateTerm($root, $payload($tag, 'wu2-root')), 'Type mutation was accepted.');

    $cycleA = $repository->createTerm($payload($category, 'cycle-a'));
    $cycleB = $repository->createTerm($payload($category, 'cycle-b', $cycleA));
    $db->exec("UPDATE taxonomy_terms SET parent_id = {$cycleB} WHERE id = {$cycleA}");
    $rejects(fn () => $repository->createTerm($payload($category, 'cycle-entry', $cycleA)), 'Persisted cycle was accepted.');
    $broken = $repository->createTerm($payload($category, 'broken'));
    $db->exec('SET FOREIGN_KEY_CHECKS = 0');
    $db->exec("UPDATE taxonomy_terms SET parent_id = 999999998 WHERE id = {$broken}");
    $db->exec('SET FOREIGN_KEY_CHECKS = 1');
    $rejects(fn () => $repository->createTerm($payload($category, 'broken-entry', $broken)), 'Unresolved ancestor was accepted.');

    $chainParent = null;
    $insert = $db->prepare('INSERT INTO taxonomy_terms (taxonomy_type_id, parent_id, name, slug, sort_order, created_at, updated_at) VALUES (:type, :parent, :name, :slug, 0, NOW(), NOW())');
    for ($i = 0; $i < 1001; $i++) {
        $insert->execute(['type' => $category, 'parent' => $chainParent, 'name' => 'Depth ' . $i, 'slug' => 'depth-' . $i]);
        $chainParent = (int) $db->lastInsertId();
    }
    $rejects(fn () => $repository->createTerm($payload($category, 'too-deep', $chainParent)), 'Traversal bound did not fail closed.');

    $db->beginTransaction();
    $nested = $repository->createTerm($payload($category, 'nested-success'));
    $assert($db->inTransaction(), 'Nested success closed caller transaction.');
    $db->rollBack();
    $assert($row($db, $nested) === null, 'Caller rollback did not remove nested work.');
    $db->beginTransaction();
    $db->exec("INSERT INTO taxonomy_terms (taxonomy_type_id, name, slug, sort_order, created_at, updated_at) VALUES ({$tag}, 'Caller marker', 'caller-marker', 0, NOW(), NOW())");
    $rejects(fn () => $repository->createTerm($payload($tag, 'nested-invalid', $root)), 'Nested invalid mutation succeeded.');
    $assert($db->inTransaction(), 'Nested validation failure closed caller transaction.');
    $assert((int) $db->query("SELECT COUNT(*) FROM taxonomy_terms WHERE slug = 'caller-marker'")->fetchColumn() === 1, 'Nested failure removed caller work.');
    $db->rollBack();

    $assigned = $repository->createTerm($payload($tag, 'assigned-tag'));
    $assignments->assign('content', 8001, $assigned);
    $rejects(fn () => $repository->deleteTermIfUnused($assigned, $assignments), 'Assigned deletion succeeded.');
    $parent = $repository->createTerm($payload($category, 'delete-parent'));
    $leaf = $repository->createTerm($payload($category, 'delete-leaf', $parent));
    $rejects(fn () => $repository->deleteTermIfUnused($parent, $assignments), 'Parent deletion succeeded.');
    $assignments->assign('content', 8002, $parent);
    $rejects(fn () => $repository->deleteTermIfUnused($parent, $assignments), 'Assigned parent deletion succeeded.');
    $repository->deleteTermIfUnused($leaf, $assignments);
    $assert($row($db, $leaf) === null, 'Unused leaf was not deleted.');
    $unusedTag = $repository->createTerm($payload($tag, 'unused-tag'));
    $repository->deleteTermIfUnused($unusedTag, $assignments);
    $assert($row($db, $unusedTag) === null, 'Unused tag was not deleted.');
    $rejects(fn () => $repository->deleteTermIfUnused(999999997, $assignments), 'Stale deletion target was accepted.');
    $assert(!$db->inTransaction(), 'Suite left a transaction open.');

    echo "M3.5 Work Unit 2 Taxonomy domain passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    $server->exec('DROP DATABASE IF EXISTS ' . $quoted);
}
