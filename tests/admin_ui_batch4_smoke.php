<?php

declare(strict_types=1);

$basePath = dirname(__DIR__);

$assertions = 0;

$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;

    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}" . PHP_EOL);
        exit(1);
    }
};

$readFile = static function (string $path) use ($assert): string {
    $assert(is_file($path), "Required file is missing [{$path}].");

    return (string) file_get_contents($path);
};

$render = static function (string $path, array $data): string {
    extract($data, EXTR_SKIP);
    ob_start();

    try {
        require $path;

        return (string) ob_get_clean();
    } catch (Throwable $throwable) {
        ob_end_clean();

        throw $throwable;
    }
};

$contentFormFile = $basePath . '/modules/content/views/admin/form.php';
$contentListFile = $basePath . '/modules/content/views/admin/list.php';
$taxonomyFormFile = $basePath . '/modules/taxonomy/views/admin/form.php';
$taxonomyTermsFile = $basePath . '/modules/taxonomy/views/admin/terms.php';
$taxonomyTypesFile = $basePath . '/modules/taxonomy/views/admin/types.php';
$cssFile = $basePath . '/public/admin-assets/css/admin.css';

$contentFormSource = $readFile($contentFormFile);
$contentListSource = $readFile($contentListFile);
$taxonomyFormSource = $readFile($taxonomyFormFile);
$taxonomyTermsSource = $readFile($taxonomyTermsFile);
$taxonomyTypesSource = $readFile($taxonomyTypesFile);
$css = $readFile($cssFile);

$adminUrl = static fn (string $path): string => '/dapur/' . ltrim($path, '/');

$contentItem = new class {
    public function id(): int { return 7; }
    public function title(): string { return 'Example'; }
    public function type(): string { return 'article'; }
    public function slug(): string { return 'example'; }
    public function status(): string { return 'draft'; }
    public function updatedAt(): string { return '2026-07-01 09:00:00'; }
    public function isArchived(): bool { return false; }
};

$taxonomyType = new class {
    public function name(): string { return 'Categories'; }
    public function slug(): string { return 'categories'; }
    public function description(): string { return 'Content categories'; }
    public function isHierarchical(): bool { return true; }
};

$taxonomyTerm = new class {
    public function id(): int { return 3; }
    public function name(): string { return 'News'; }
    public function slug(): string { return 'news'; }
    public function description(): string { return 'News content'; }
    public function sortOrder(): int { return 0; }
};

$contentForm = $render($contentFormFile, [
    'errors' => ['Title is required.'],
    'formAction' => '/dapur/content',
    'csrfToken' => 'csrf-token',
    'content' => [
        'type' => 'article',
        'title' => '',
        'slug' => 'example',
        'excerpt' => '',
        'body' => '',
        'status' => 'draft',
    ],
    'canPublish' => true,
    'taxonomy' => [
        'available' => true,
        'categories' => [$taxonomyTerm],
        'tags' => [],
    ],
    'selectedTaxonomy' => [
        'category_ids' => [3],
        'tag_ids' => [],
    ],
    'submitLabel' => 'Save content',
]);

$contentList = $render($contentListFile, [
    'canCreate' => true,
    'canUpdate' => true,
    'canPublish' => true,
    'canDelete' => true,
    'taxonomyAvailable' => true,
    'contents' => [$contentItem],
    'taxonomyTerms' => [
        7 => [
            'categories' => [$taxonomyTerm],
            'tags' => [],
        ],
    ],
    'csrfToken' => 'csrf-token',
    'adminUrl' => $adminUrl,
]);

$contentEmpty = $render($contentListFile, [
    'canCreate' => true,
    'canUpdate' => true,
    'canPublish' => true,
    'canDelete' => true,
    'taxonomyAvailable' => false,
    'contents' => [],
    'taxonomyTerms' => [],
    'csrfToken' => 'csrf-token',
    'adminUrl' => $adminUrl,
]);

$taxonomyForm = $render($taxonomyFormFile, [
    'errors' => ['Name is required.'],
    'formAction' => '/dapur/taxonomy/categories',
    'csrfToken' => 'csrf-token',
    'term' => [
        'name' => '',
        'slug' => '',
        'description' => '',
        'sort_order' => 0,
    ],
    'submitLabel' => 'Save term',
    'type' => $taxonomyType,
    'adminUrl' => $adminUrl,
]);

$taxonomyTerms = $render($taxonomyTermsFile, [
    'type' => $taxonomyType,
    'error' => null,
    'canCreate' => true,
    'canUpdate' => true,
    'canDelete' => true,
    'terms' => [$taxonomyTerm],
    'usageCounts' => [3 => 0],
    'csrfToken' => 'csrf-token',
    'adminUrl' => $adminUrl,
]);

$taxonomyTermsEmpty = $render($taxonomyTermsFile, [
    'type' => $taxonomyType,
    'error' => null,
    'canCreate' => true,
    'canUpdate' => true,
    'canDelete' => true,
    'terms' => [],
    'usageCounts' => [],
    'csrfToken' => 'csrf-token',
    'adminUrl' => $adminUrl,
]);

$taxonomyTypes = $render($taxonomyTypesFile, [
    'types' => [$taxonomyType],
    'adminUrl' => $adminUrl,
]);

$taxonomyTypesEmpty = $render($taxonomyTypesFile, [
    'types' => [],
    'adminUrl' => $adminUrl,
]);

// Shared module pattern adoption.
foreach ([
    'Content form' => $contentForm,
    'Content list' => $contentList,
    'Taxonomy form' => $taxonomyForm,
    'Taxonomy terms' => $taxonomyTerms,
    'Taxonomy types' => $taxonomyTypes,
] as $label => $output) {
    $assert(preg_match('/class="[^"]*\badmin-panel\b/', $output) === 1, "{$label} does not use the shared panel.");
}

$assert(str_contains($contentForm, 'class="admin-alert admin-alert--danger" role="alert"'), 'Content form errors lack shared alert semantics.');
$assert(str_contains($taxonomyForm, 'class="admin-alert admin-alert--danger" role="alert"'), 'Taxonomy form errors lack shared alert semantics.');
$assert(str_contains($contentForm, 'class="admin-field__label" for="title"'), 'Content title label is not associated with its field.');
$assert(str_contains($contentForm, '<span class="admin-visually-hidden">required</span>'), 'Content required state lacks accessible text.');
$assert(str_contains($taxonomyForm, 'class="admin-field__label" for="name"'), 'Taxonomy name label is not associated with its field.');
$assert(preg_match('/class="[^"]*\badmin-fieldset\b/', $contentForm) === 1, 'Content taxonomy does not use the shared fieldset.');
$assert(str_contains($contentForm, 'class="admin-check-list"'), 'Content taxonomy checkbox list contract is missing.');

// Tables and empty states are behaviorally consumed.
$assert(str_contains($contentList, 'class="admin-table-wrap"'), 'Content list lacks the responsive table wrapper.');
$assert(str_contains($contentList, 'class="admin-table"'), 'Content list lacks the shared table class.');
$assert(str_contains($contentList, '<th scope="col">Title</th>'), 'Content table header semantics regressed.');
$assert(preg_match('/class="[^"]*\badmin-empty-state\b/', $contentEmpty) === 1, 'Content empty state is missing.');
$assert(str_contains($taxonomyTerms, 'class="admin-table-wrap"'), 'Taxonomy terms lack the responsive table wrapper.');
$assert(str_contains($taxonomyTermsEmpty, 'class="admin-empty-state"'), 'Taxonomy terms empty state is missing.');
$assert(str_contains($taxonomyTypes, 'class="admin-table"'), 'Taxonomy types lack the shared table class.');
$assert(str_contains($taxonomyTypesEmpty, 'class="admin-empty-state"'), 'Taxonomy types empty state is missing.');

// Action semantics, permission branches, routes, and CSRF remain intact.
$assert(str_contains($contentList, 'href="/dapur/content/7/edit"'), 'Content edit URL changed unexpectedly.');
$assert(str_contains($contentList, 'action="/dapur/content/7/publish"'), 'Content publish URL changed unexpectedly.');
$assert(str_contains($contentList, 'action="/dapur/content/7/archive"'), 'Content archive URL changed unexpectedly.');
$assert(str_contains($contentList, 'name="_token" value="csrf-token"'), 'Content list CSRF rendering regressed.');
$assert(str_contains($contentList, 'class="admin-inline-form"'), 'Content row actions still require inline styling.');
$assert(str_contains($taxonomyTerms, 'action="/dapur/taxonomy/categories/3/delete"'), 'Taxonomy delete URL changed unexpectedly.');
$assert(str_contains($taxonomyTerms, 'name="_token" value="csrf-token"'), 'Taxonomy terms CSRF rendering regressed.');
$assert(str_contains($taxonomyForm, 'href="/dapur/taxonomy/categories"'), 'Taxonomy cancel URL changed unexpectedly.');
$assert(str_contains($taxonomyForm, 'class="admin-button admin-button--secondary"'), 'Taxonomy cancel action lacks the shared secondary action style.');

// CSS contracts added for module migration.
foreach ([
    '.admin-check-list',
    '.admin-check-option',
    '.admin-row-actions',
    '.admin-inline-form',
    '.admin-action-danger',
    '.admin-text-muted',
] as $selector) {
    $assert(str_contains($css, $selector), "Module Admin CSS contract [{$selector}] is missing.");
}
$assert(preg_match('/\.admin-inline-form\s*\{[^}]*display:\s*inline-flex;/s', $css) === 1, 'Inline form contract does not replace inline display styles.');
$assert(preg_match('/\.admin-table-wrap\s*\{[^}]*overflow-x:\s*auto;/s', $css) === 1, 'Responsive table overflow contract regressed.');

// Scope and regression guards.
foreach ([
    $contentFormSource,
    $contentListSource,
    $taxonomyFormSource,
    $taxonomyTermsSource,
    $taxonomyTypesSource,
] as $source) {
    $assert(!str_contains($source, 'style='), 'A migrated module view retains an inline style attribute.');
    $assert(!str_contains($source, '<style>'), 'A migrated module view contains an inline stylesheet.');
    $assert(!str_contains($source, '<script'), 'JavaScript entered Batch 4 module views.');
}
$assert(!preg_match('/theme-assets|themes\//', $css), 'Admin module patterns depend on frontend theme assets.');
$assert(!is_file($basePath . '/package.json'), 'A Node/build dependency was introduced.');

echo "Admin UI Batch 4 smoke tests passed ({$assertions} assertions)." . PHP_EOL;
echo "Note: module fixtures verify shared panel, form, table, empty-state, route, permission, and CSRF contracts." . PHP_EOL;
