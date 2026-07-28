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
$render = static function (string $path, array $data): string {
    extract($data, EXTR_SKIP);
    ob_start();
    try {
        require $path;
        return (string) ob_get_clean();
    } catch (Throwable $exception) {
        ob_end_clean();
        throw $exception;
    }
};
$adminUrl = static fn (string $path): string => '/dapur/' . ltrim($path, '/');

$type = new class {
    public function name(): string { return 'Categories'; }
    public function slug(): string { return 'category'; }
    public function isHierarchical(): bool { return true; }
};
$tagType = new class {
    public function name(): string { return 'Tags'; }
    public function slug(): string { return 'tag'; }
    public function isHierarchical(): bool { return false; }
};
$term = static function (int $id, string $name, ?int $parentId = null): object {
    return new class($id, $name, $parentId) {
        public function __construct(private int $id, private string $name, private ?int $parentId) {}
        public function id(): int { return $this->id; }
        public function name(): string { return $this->name; }
        public function slug(): string { return strtolower($this->name); }
        public function description(): string { return '<unsafe>'; }
        public function sortOrder(): int { return 0; }
        public function parentId(): ?int { return $this->parentId; }
    };
};
$root = $term(1, 'Root <Category>');
$child = $term(2, 'Child', 1);

$types = $render($basePath . '/modules/taxonomy/views/admin/types.php', [
    'types' => [$type, $tagType], 'canCreate' => true, 'adminUrl' => $adminUrl,
]);
$categories = $render($basePath . '/modules/taxonomy/views/admin/terms.php', [
    'type' => $type, 'terms' => [$root, $child], 'usageCounts' => [1 => 0, 2 => 1],
    'canCreate' => true, 'canUpdate' => true, 'canDelete' => true,
    'csrfToken' => 'token', 'adminUrl' => $adminUrl, 'error' => null,
]);
$tags = $render($basePath . '/modules/taxonomy/views/admin/terms.php', [
    'type' => $tagType, 'terms' => [$root], 'usageCounts' => [1 => 0],
    'canCreate' => true, 'canUpdate' => true, 'canDelete' => true,
    'csrfToken' => 'token', 'adminUrl' => $adminUrl, 'error' => null,
]);
$form = $render($basePath . '/modules/taxonomy/views/admin/form.php', [
    'type' => $type, 'errors' => ['Name is required.'], 'formAction' => '/dapur/taxonomy/category',
    'csrfToken' => 'token', 'term' => ['name' => '', 'slug' => '', 'description' => '<unsafe>', 'sort_order' => 0, 'parent_id' => 1],
    'parentCandidates' => [['term' => $root, 'depth' => 0]], 'heading' => 'Create Category', 'submitLabel' => 'Create term', 'adminUrl' => $adminUrl,
]);

$assert(str_contains($types, 'category') && str_contains($types, 'tag'), 'Landing does not present only the fixed built-in types.');
$assert(str_contains($types, 'Hierarchical') && str_contains($types, 'Flat'), 'Landing does not communicate hierarchy modes.');
$assert(str_contains($categories, 'Child of Root &lt;Category&gt;'), 'Category parent information is not escaped or exposed.');
$assert(str_contains($categories, 'Has children') && str_contains($categories, 'In use'), 'Category deletion-safety states are missing.');
$assert(str_contains($tags, 'Tags are flat') && !str_contains($tags, 'parent_id'), 'Tag presentation exposes hierarchy controls.');
$assert(str_contains($form, 'for="parent_id"') && str_contains($form, 'Root category (no parent)'), 'Category parent selector lacks an associated root option.');
$assert(str_contains($form, 'aria-describedby="taxonomy-error-name"'), 'Validation error is not associated with the name field.');
$assert(str_contains($form, '&lt;unsafe&gt;'), 'Unexpected term content is not escaped.');

echo "M3.5 Work Unit 4 Taxonomy presentation tests passed ({$assertions} assertions)." . PHP_EOL;
