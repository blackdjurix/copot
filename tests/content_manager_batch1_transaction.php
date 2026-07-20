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

require_once $basePath . '/modules/content/Services/Content.php';
require_once $basePath . '/modules/content/Services/ContentRepository.php';
require_once $basePath . '/modules/content/Services/ContentService.php';
require_once $basePath . '/modules/taxonomy/Services/TaxonomyType.php';
require_once $basePath . '/modules/taxonomy/Services/TaxonomyTerm.php';
require_once $basePath . '/modules/taxonomy/Services/TaxonomyAssignmentRepository.php';

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
$databaseName = 'copot_m34_content_tx_' . bin2hex(random_bytes(6));
$databaseIdentifier = '`' . str_replace('`', '``', $databaseName) . '`';
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

$server->exec('CREATE DATABASE ' . $databaseIdentifier . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

try {
    (new InstallerSchemaRunner($basePath . '/database/schema.sql'))->install($configuration);

    $_ENV['DB_DATABASE'] = $databaseName;
    putenv('DB_DATABASE=' . $databaseName);
    $database = new Database(new Config($basePath . '/config'));
    $connection = $database->connection();
    $taxonomy = new TaxonomyAssignmentRepository($database);
    $content = new ContentRepository($database);
    $service = new ContentService($database, $content, $taxonomy);

    $categoryId = (int) $connection->query(
        "SELECT id FROM taxonomy_types WHERE slug = 'category'"
    )->fetchColumn();
    $tagId = (int) $connection->query(
        "SELECT id FROM taxonomy_types WHERE slug = 'tag'"
    )->fetchColumn();
    $connection->exec(
        "INSERT INTO taxonomy_terms (taxonomy_type_id, name, slug, created_at, updated_at)
        VALUES ({$categoryId}, 'News', 'news', NOW(), NOW()),
               ({$tagId}, 'Featured', 'featured', NOW(), NOW())"
    );
    $categoryTerm = (int) $connection->query("SELECT id FROM taxonomy_terms WHERE slug = 'news'")->fetchColumn();
    $tagTerm = (int) $connection->query("SELECT id FROM taxonomy_terms WHERE slug = 'featured'")->fetchColumn();

    $taxonomy->assign('content', 9001, $categoryTerm);
    $assert(!$connection->inTransaction(), 'Standalone assignment left a transaction open.');
    $assert(
        (int) $connection->query("SELECT COUNT(*) FROM taxonomy_assignments WHERE entity_id = 9001")->fetchColumn() === 1,
        'Standalone assignment did not persist.'
    );

    $connection->beginTransaction();
    $taxonomy->assign('content', 9002, $categoryTerm);
    $assert($connection->inTransaction(), 'Joined assignment committed the caller transaction.');
    $connection->rollBack();
    $assert(
        (int) $connection->query("SELECT COUNT(*) FROM taxonomy_assignments WHERE entity_id = 9002")->fetchColumn() === 0,
        'Caller rollback did not remove joined assignment.'
    );

    $connection->beginTransaction();
    try {
        $taxonomy->syncForType('content', 9001, 'category', [999999]);
        $assert(false, 'Invalid taxonomy assignment unexpectedly succeeded.');
    } catch (InvalidArgumentException) {
        $assert($connection->inTransaction(), 'Joined taxonomy failure rolled back the caller transaction.');
    }
    $connection->rollBack();

    $contentId = $service->create([
        'type' => 'article', 'title' => 'Atomic', 'slug' => 'atomic', 'body' => 'Body', 'status' => 'draft',
        'author_id' => null,
    ], ['category_ids' => [$categoryTerm], 'tag_ids' => [$tagTerm]]);
    $assert($content->findById($contentId) !== null, 'Successful Content write did not persist.');
    $assert(
        (int) $connection->query("SELECT COUNT(*) FROM taxonomy_assignments WHERE entity_id = {$contentId}")->fetchColumn() === 2,
        'Successful Content write did not persist taxonomy assignments.'
    );

    $contentCount = (int) $connection->query('SELECT COUNT(*) FROM content')->fetchColumn();
    $assignmentCount = (int) $connection->query('SELECT COUNT(*) FROM taxonomy_assignments')->fetchColumn();
    try {
        $service->create([
            'type' => 'article', 'title' => 'Broken tag taxonomy', 'slug' => 'broken-tag-taxonomy', 'body' => 'Body', 'status' => 'draft',
            'author_id' => null,
        ], ['category_ids' => [$categoryTerm], 'tag_ids' => [999999]]);
        $assert(false, 'Tag taxonomy failure unexpectedly reported success.');
    } catch (InvalidArgumentException) {
        $assert(true, 'Tag taxonomy failure was propagated.');
    }
    $assert((int) $connection->query('SELECT COUNT(*) FROM content')->fetchColumn() === $contentCount, 'Tag taxonomy failure left Content persisted.');
    $assert((int) $connection->query('SELECT COUNT(*) FROM taxonomy_assignments')->fetchColumn() === $assignmentCount, 'Tag taxonomy failure left assignments.');

    try {
        $service->create([
            'type' => 'article', 'title' => 'Broken taxonomy', 'slug' => 'broken-taxonomy', 'body' => 'Body', 'status' => 'draft',
            'author_id' => null,
        ], ['category_ids' => [999999], 'tag_ids' => []]);
        $assert(false, 'Taxonomy failure unexpectedly reported success.');
    } catch (InvalidArgumentException $exception) {
        $assert($exception->getMessage() === 'Taxonomy assignment term does not exist.', 'Taxonomy validation did not remain controlled.');
    }
    $assert((int) $connection->query('SELECT COUNT(*) FROM content')->fetchColumn() === $contentCount, 'Taxonomy failure left Content persisted.');

    $beforeAssignments = (int) $connection->query("SELECT COUNT(*) FROM taxonomy_assignments WHERE entity_id = 9100")->fetchColumn();
    try {
        $service->create([
            'type' => 'invalid', 'title' => 'Invalid Content', 'slug' => 'invalid-content', 'body' => 'Body', 'status' => 'draft',
            'author_id' => null,
        ], ['category_ids' => [$categoryTerm], 'tag_ids' => []]);
        $assert(false, 'Invalid Content write unexpectedly succeeded.');
    } catch (InvalidArgumentException) {
        $assert(true, 'Content validation failure was not propagated.');
    }
    $assert(
        (int) $connection->query("SELECT COUNT(*) FROM taxonomy_assignments WHERE entity_id = 9100")->fetchColumn() === $beforeAssignments,
        'Content failure changed taxonomy assignments.'
    );

    $connection->beginTransaction();
    $taxonomy->syncForType('content', 9200, 'category', [$categoryTerm]);
    $assert($connection->inTransaction(), 'Joined sync committed the caller transaction.');
    $connection->rollBack();
    $assert(
        (int) $connection->query("SELECT COUNT(*) FROM taxonomy_assignments WHERE entity_id = 9200")->fetchColumn() === 0,
        'Joined sync was not controlled by the caller transaction.'
    );

    $updateTarget = $content->findById($contentId);
    $beforeUpdateAssignments = (int) $connection->query("SELECT COUNT(*) FROM taxonomy_assignments WHERE entity_id = {$contentId}")->fetchColumn();
    try {
        $service->update($contentId, [
            'type' => $updateTarget->type(),
            'title' => 'Failed taxonomy update',
            'slug' => $updateTarget->slug(),
            'body' => $updateTarget->body(),
            'status' => $updateTarget->status(),
            'author_id' => $updateTarget->authorId(),
        ], ['category_ids' => [$categoryTerm], 'tag_ids' => [999999]], $updateTarget->updatedAt());
        $assert(false, 'Taxonomy update failure unexpectedly reported success.');
    } catch (InvalidArgumentException) {
        $assert(true, 'Taxonomy update failure was propagated.');
    }
    $assert($content->findById($contentId)?->title() === $updateTarget->title(), 'Taxonomy update failure changed Content.');
    $assert((int) $connection->query("SELECT COUNT(*) FROM taxonomy_assignments WHERE entity_id = {$contentId}")->fetchColumn() === $beforeUpdateAssignments, 'Taxonomy update failure changed assignments.');

    $service->publish($contentId);
    $published = $content->findById($contentId);
    $firstPublishedAt = $published?->publishedAt();
    $assert($published?->status() === 'published', 'Draft was not published through ContentService.');
    $assert($firstPublishedAt !== null, 'Successful publish did not set published_at.');

    try {
        $service->publish($contentId);
        $assert(false, 'Repeated publish unexpectedly mutated content.');
    } catch (InvalidArgumentException) {
        $assert($content->findById($contentId)?->publishedAt() === $firstPublishedAt, 'Repeated publish changed published_at.');
    }

    $service->draft($contentId);
    $service->archive($contentId);
    $archived = $content->findById($contentId);
    $assert($archived?->status() === 'archived' && $archived->archivedAt() !== null, 'Archive did not set archived_at.');

    try {
        $service->publish($contentId);
        $assert(false, 'Archived content was published directly.');
    } catch (InvalidArgumentException) {
        $assert($content->findById($contentId)?->status() === 'archived', 'Invalid transition mutated archived content.');
    }

    try {
        $content->transition($contentId, 'archived', 'published');
        $assert(false, 'Repository lifecycle primitive bypassed transition policy.');
    } catch (InvalidArgumentException) {
        $assert($content->findById($contentId)?->status() === 'archived', 'Repository invalid transition mutated content.');
    }

    $service->restore($contentId);
    $restored = $content->findById($contentId);
    $assert($restored?->status() === 'draft' && $restored->archivedAt() === null, 'Restore did not clear archived_at.');

    usleep(1100000);
    $service->publish($contentId);
    $republished = $content->findById($contentId);
    $assert($republished?->publishedAt() !== $firstPublishedAt, 'Successful re-publish did not refresh published_at.');
    $service->draft($contentId);

    try {
        $service->restore($contentId);
        $assert(false, 'Repeated restore unexpectedly succeeded.');
    } catch (InvalidArgumentException) {
        $assert($content->findById($contentId)?->status() === 'draft', 'Repeated restore changed content.');
    }

    $stable = $content->findById($contentId);
    $stableSlug = $stable?->slug();
    $service->update($contentId, [
        'type' => $stable->type(),
        'title' => 'Atomic title changed',
        'slug' => $stableSlug,
        'body' => $stable->body(),
        'status' => $stable->status(),
        'author_id' => $stable->authorId(),
    ], [], $stable->updatedAt());
    $afterTitleEdit = $content->findById($contentId);
    $assert($afterTitleEdit?->slug() === $stableSlug, 'Title-only edit changed the existing slug.');

    try {
        $service->update($contentId, [
            'type' => $afterTitleEdit->type(),
            'title' => 'Stale overwrite',
            'slug' => $afterTitleEdit->slug(),
            'body' => $afterTitleEdit->body(),
            'status' => $afterTitleEdit->status(),
            'author_id' => $afterTitleEdit->authorId(),
        ], [], $stable->updatedAt());
        $assert(false, 'Stale Content update unexpectedly succeeded.');
    } catch (ContentStaleWriteException) {
        $assert($content->findById($contentId)?->title() === 'Atomic title changed', 'Stale update overwrote newer Content.');
    }

    try {
        $service->create([
            'type' => 'article', 'title' => 'Duplicate slug', 'slug' => $stableSlug, 'body' => 'Body', 'status' => 'draft',
            'author_id' => null,
        ]);
        $assert(false, 'Duplicate slug unexpectedly succeeded.');
    } catch (ContentDuplicateSlugException $exception) {
        $assert($exception->getMessage() === 'The content slug is already in use.', 'Duplicate slug error was not controlled.');
    }

    try {
        $duplicateUpdateId = $service->create([
            'type' => 'article', 'title' => 'Duplicate update target', 'slug' => 'duplicate-update-target', 'body' => 'Body', 'status' => 'draft',
            'author_id' => null,
        ]);
        $duplicateUpdateEntry = $content->findById($duplicateUpdateId);
        $service->update($duplicateUpdateId, [
            'type' => $duplicateUpdateEntry->type(),
            'title' => 'Duplicate slug update',
            'slug' => 'atomic',
            'body' => $duplicateUpdateEntry->body(),
            'status' => $duplicateUpdateEntry->status(),
            'author_id' => $duplicateUpdateEntry->authorId(),
        ], [], $duplicateUpdateEntry->updatedAt());
        $assert(false, 'Duplicate slug update unexpectedly succeeded.');
    } catch (ContentDuplicateSlugException $exception) {
        $assert($exception->getMessage() === 'The content slug is already in use.', 'Duplicate slug update error was not controlled.');
    }

    try {
        $service->create([
            'type' => 'article', 'title' => 'Unrelated database failure', 'slug' => 'unrelated-database-failure', 'body' => 'Body', 'status' => 'draft',
            'author_id' => 999999999,
        ]);
        $assert(false, 'Unrelated database failure unexpectedly succeeded.');
    } catch (ContentWriteException $exception) {
        $assert($exception->getMessage() === 'Content could not be saved.', 'Unrelated database failure was misclassified.');
    }

    try {
        $service->create([
            'type' => 'article', 'title' => 'Too long slug', 'slug' => str_repeat('a', 191), 'body' => 'Body', 'status' => 'draft',
            'author_id' => null,
        ]);
        $assert(false, 'Too-long slug unexpectedly succeeded.');
    } catch (InvalidArgumentException $exception) {
        $assert($exception->getMessage() === 'Content slug cannot exceed 190 characters.', 'Too-long slug validation was not centralized.');
    }

    $noTaxonomyService = new ContentService($database, $content, null);
    $noTaxonomyId = $noTaxonomyService->create([
        'type' => 'article', 'title' => 'No taxonomy create', 'slug' => 'no-taxonomy-create', 'body' => 'Body', 'status' => 'draft',
        'author_id' => null,
    ]);
    $noTaxonomyEntry = $content->findById($noTaxonomyId);
    $noTaxonomyService->update($noTaxonomyId, [
        'type' => $noTaxonomyEntry->type(),
        'title' => 'No taxonomy update',
        'slug' => $noTaxonomyEntry->slug(),
        'body' => $noTaxonomyEntry->body(),
        'status' => $noTaxonomyEntry->status(),
        'author_id' => $noTaxonomyEntry->authorId(),
    ], [], $noTaxonomyEntry->updatedAt());
    $assert($content->findById($noTaxonomyId)?->title() === 'No taxonomy update', 'Taxonomy-disabled update failed.');

    $versionEntry = $content->findById($noTaxonomyId);
    $versionBefore = $versionEntry->updatedAt();
    $noTaxonomyService->update($noTaxonomyId, [
        'type' => $versionEntry->type(),
        'title' => $versionEntry->title(),
        'slug' => $versionEntry->slug(),
        'body' => $versionEntry->body(),
        'status' => $versionEntry->status(),
        'author_id' => $versionEntry->authorId(),
    ], [], $versionBefore);
    $versionAfterNoOp = $content->findById($noTaxonomyId)->updatedAt();
    $assert($versionAfterNoOp !== $versionBefore, 'No-op update was falsely reported stale or reused its version.');
    $noTaxonomyService->update($noTaxonomyId, [
        'type' => 'article',
        'title' => 'No taxonomy sequential update',
        'slug' => 'no-taxonomy-create',
        'body' => 'Body',
        'status' => 'draft',
        'author_id' => null,
    ], [], $versionAfterNoOp);
    $versionAfterSequential = $content->findById($noTaxonomyId)->updatedAt();
    $assert($versionAfterSequential !== $versionAfterNoOp, 'Same-second sequential update reused its version.');

    echo "M3.4 Content foundation transaction/lifecycle baseline passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    $server->exec('DROP DATABASE IF EXISTS ' . $databaseIdentifier);
}
