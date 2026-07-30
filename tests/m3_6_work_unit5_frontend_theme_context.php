<?php

declare(strict_types=1);

use Copot\Core\Diagnostics;
use Copot\Core\FrontendThemeContext;
use Copot\Core\FrontendThemeContextContributor;
use Copot\Core\FrontendThemeContextRegistry;

$basePath = dirname(__DIR__);
require $basePath . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

final class M36Wu5ContextContributor implements FrontendThemeContextContributor
{
    public function __construct(private string $key, private array $fragment, private bool $fail = false)
    {
    }

    public function contextKey(): string { return $this->key; }

    public function contribute(FrontendThemeContext $context): array
    {
        if ($this->fail) {
            throw new RuntimeException('expected contributor failure');
        }

        return $this->fragment + ['theme_id' => $context->themeId()];
    }
}

$logRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-wu5-context-' . bin2hex(random_bytes(4));
mkdir($logRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs', 0777, true);

try {
    $registry = new FrontendThemeContextRegistry(new Diagnostics($logRoot));
    $registry->register(new M36Wu5ContextContributor('first', ['value' => 'one']));
    $registry->register(new M36Wu5ContextContributor('first', ['value' => 'two']));
    $registry->register(new M36Wu5ContextContributor('failed', [], true));
    $registry->register(new M36Wu5ContextContributor('second', ['value' => 'two']));
    $assert(!$registry->isFrozen(), 'Context registry froze before bootstrap completion.');
    $registry->freeze();
    $assert($registry->isFrozen(), 'Context registry did not freeze.');
    $composed = $registry->compose([
        'theme_id' => 'default',
        'metadata' => ['supports' => ['navigation_locations' => ['primary']]],
    ]);
    $assert($composed['first']['value'] === 'one', 'First duplicate context key did not win deterministically.');
    $assert(!isset($composed['failed']) && $composed['second']['theme_id'] === 'default', 'Contributor failure or context metadata handling regressed.');
    try {
        $registry->register(new M36Wu5ContextContributor('late', []));
        $assert(false, 'Frozen registry accepted a contributor.');
    } catch (RuntimeException) {
        $assert(true, 'Frozen registry rejected late registration.');
    }
    echo "M3.6 WU5 frontend Theme context passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($logRoot, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); }
    rmdir($logRoot);
}
