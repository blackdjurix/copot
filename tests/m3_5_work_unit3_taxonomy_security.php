<?php

declare(strict_types=1);

use Copot\Core\Admin\AdminErrorRenderer;
use Copot\Core\Application;
use Copot\Core\Auth;
use Copot\Core\Config;
use Copot\Core\Csrf;
use Copot\Core\Database;
use Copot\Core\Env;
use Copot\Core\InstallerSchemaRunner;
use Copot\Core\Request;
use Copot\Core\Response;
use Copot\Core\User;
use Copot\Core\View;

$basePath = dirname(__DIR__);
chdir($basePath);
session_save_path(sys_get_temp_dir());
session_id('copotm35wu3' . bin2hex(random_bytes(5)));
require $basePath . '/bootstrap/autoload.php';
Env::load($basePath . '/.env');
require_once $basePath . '/modules/taxonomy/Services/TaxonomyType.php';
require_once $basePath . '/modules/taxonomy/Services/TaxonomyTerm.php';
require_once $basePath . '/modules/taxonomy/Services/TaxonomyRepository.php';
require_once $basePath . '/modules/taxonomy/Services/TaxonomyAssignmentRepository.php';
require_once $basePath . '/modules/taxonomy/Services/Slugger.php';

final class Wu3PermissionUser extends User
{
    public function __construct(private array $grants) {}
    public function id(): int { return 930003; }
    public function name(): string { return 'WU3 Security'; }
    public function email(): string { return 'wu3-security@example.test'; }
    public function status(): string { return 'active'; }
    public function isActive(): bool { return true; }
    public function can(string $permission): bool { return in_array($permission, $this->grants, true); }
}

final class Wu3AuthSpy extends Auth
{
    public function __construct(public ?User $actor) {}
    public function check(): bool { return $this->actor !== null; }
    public function user(): ?User { return $this->actor; }
}

final class Wu3CsrfSpy extends Csrf
{
    public int $validations = 0;
    public function token(): string { return 'wu3-csrf'; }
    public function validateOrReject(Request $request, string $field = '_token'): ?Response
    {
        $this->validations++;
        return $request->post($field) === 'wu3-csrf' ? null : Response::html('RAW_CSRF_REJECT', 419);
    }
    public function reset(): void { $this->validations = 0; }
}

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$value = static fn (Response $response, string $property): mixed => (new ReflectionProperty(Response::class, $property))->getValue($response);
$status = static fn (Response $response): int => (int) $value($response, 'status');
$content = static fn (Response $response): string => (string) $value($response, 'content');

$host = (string) Env::get('DB_HOST', '127.0.0.1');
$port = (int) Env::get('DB_PORT', '3306');
$dbUser = (string) Env::get('DB_USERNAME', 'root');
$password = (string) Env::get('DB_PASSWORD', '');
$name = 'copot_m35_wu3_' . bin2hex(random_bytes(6));
$quoted = '`' . str_replace('`', '``', $name) . '`';
$config = ['host' => $host, 'port' => $port, 'database' => $name, 'username' => $dbUser, 'password' => $password];
$server = new PDO(
    "mysql:host={$host};port={$port};charset=utf8mb4",
    $dbUser,
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
);
$server->exec('CREATE DATABASE ' . $quoted . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

try {
    (new InstallerSchemaRunner($basePath . '/database/schema.sql'))->install($config);
    $_ENV['DB_DATABASE'] = $name;
    putenv('DB_DATABASE=' . $name);

    $app = new Application($basePath);
    $app->session()->start();
    $database = new Database(new Config($basePath . '/config'));
    $repository = new TaxonomyRepository($database);
    $assignments = new TaxonomyAssignmentRepository($database);
    $db = $database->connection();
    $auth = new Wu3AuthSpy(null);
    $csrf = new Wu3CsrfSpy($app->session());

    $alternate = new class($app, $database, $auth, $csrf) {
        public function __construct(private Application $app, private Database $databaseValue, private Auth $authValue, private Csrf $csrfValue) {}
        public function config() { return $this->app->config(); }
        public function database() { return $this->databaseValue; }
        public function session() { return $this->app->session(); }
        public function csrf() { return $this->csrfValue; }
        public function auth() { return $this->authValue; }
        public function router() { return $this->app->router(); }
        public function adminNavigation() { return $this->app->adminNavigation(); }
        public function adminDashboard() { return $this->app->adminDashboard(); }
        public function adminUrl() { return $this->app->adminUrl(); }
        public function adminPageRenderer() { return $this->app->adminPageRenderer(); }
        public function adminErrors() {
            return new AdminErrorRenderer(
                new View(dirname(__DIR__) . '/resources/views'),
                $this->app->adminPageRenderer(),
                $this->app->adminUrl(),
                $this->authValue,
                $this->csrfValue,
                'admin.access'
            );
        }
    };

    (static function ($app) use ($basePath): void {
        require $basePath . '/modules/taxonomy/routes.php';
    })($alternate);

    $category = (int) $db->query("SELECT id FROM taxonomy_types WHERE slug = 'category'")->fetchColumn();
    $tag = (int) $db->query("SELECT id FROM taxonomy_types WHERE slug = 'tag'")->fetchColumn();
    $payload = static fn (int $type, string $slug, mixed $parent = null): array => [
        'taxonomy_type_id' => $type,
        'parent_id' => $parent,
        'name' => ucfirst(str_replace('-', ' ', $slug)),
        'slug' => $slug,
        'description' => null,
        'sort_order' => 0,
    ];
    $root = $repository->createTerm($payload($category, 'wu3-root'));
    $child = $repository->createTerm($payload($category, 'wu3-child', $root));
    $tagTerm = $repository->createTerm($payload($tag, 'wu3-tag'));
    $post = static fn (array $extra = []): array => array_merge([
        '_token' => 'wu3-csrf',
        'name' => 'WU3 Term',
        'slug' => '',
        'description' => '',
        'sort_order' => '0',
    ], $extra);

    foreach ([
        ['/admin/taxonomy/category', 'taxonomy.create'],
        ["/admin/taxonomy/category/{$root}", 'taxonomy.update'],
        ["/admin/taxonomy/tag/{$tagTerm}/delete", 'taxonomy.delete'],
    ] as [$path, $permission]) {
        $auth->actor = null;
        $csrf->reset();
        $response = $app->router()->dispatch(new Request('POST', $path, [], ['_token' => 'bad']));
        $assert($status($response) >= 300 && $status($response) < 400 && $csrf->validations === 0, "Guest authorization ordering failed for {$path}.");

        $auth->actor = new Wu3PermissionUser(['admin.access']);
        $csrf->reset();
        $response = $app->router()->dispatch(new Request('POST', $path, [], ['_token' => 'bad']));
        $assert($status($response) === 403 && $csrf->validations === 0, "Permission-before-CSRF failed for {$permission}.");

        $auth->actor = new Wu3PermissionUser([$permission]);
        $csrf->reset();
        $response = $app->router()->dispatch(new Request('POST', $path, [], ['_token' => 'bad']));
        $assert($status($response) === 403 && $csrf->validations === 0, "admin.access-before-CSRF failed for {$path}.");
    }

    $auth->actor = new Wu3PermissionUser(['admin.access', 'taxonomy.create', 'taxonomy.update', 'taxonomy.delete']);
    foreach ([
        ['/admin/taxonomy/category', ['name' => 'Missing CSRF']],
        ["/admin/taxonomy/category/{$root}", ['name' => 'Missing CSRF']],
        ["/admin/taxonomy/tag/{$tagTerm}/delete", []],
    ] as [$path, $body]) {
        $csrf->reset();
        $response = $app->router()->dispatch(new Request('POST', $path, [], $body));
        $assert($status($response) === 419 && $csrf->validations === 1, "Authorized CSRF rejection failed for {$path}.");
    }

    foreach (['0', '-1', '01', '1x', '1.5', '1e2', ' ', '999999999999999999999999'] as $badId) {
        $assert($status($app->router()->dispatch(new Request('GET', "/admin/taxonomy/category/{$badId}/edit"))) === 404, "Malformed ID {$badId} was not 404.");
    }
    $assert($status($app->router()->dispatch(new Request('GET', '/admin/taxonomy/other/1/edit'))) === 404, 'Unsupported type was not 404.');
    $assert($status($app->router()->dispatch(new Request('GET', '/admin/taxonomy/category/999999999/edit'))) === 404, 'Stale target was not 404.');
    $assert($status($app->router()->dispatch(new Request('GET', "/admin/taxonomy/category/{$tagTerm}/edit"))) === 404, 'Wrong-type target was not 404.');

    $response = $app->router()->dispatch(new Request('POST', '/admin/taxonomy/category', [], $post([
        'name' => 'Child via route',
        'slug' => 'child-via-route',
        'parent_id' => (string) $root,
    ])));
    $assert($status($response) >= 300 && $status($response) < 400, 'Valid category parent create failed.');
    $created = (int) $db->query("SELECT id FROM taxonomy_terms WHERE slug = 'child-via-route'")->fetchColumn();
    $assert((int) $db->query("SELECT parent_id FROM taxonomy_terms WHERE id = {$created}")->fetchColumn() === $root, 'Route did not pass parent_id to WU2 boundary.');

    foreach ([
        ['0', 'bad-parent-zero'],
        ['999999999', 'bad-parent-stale'],
        [(string) $tagTerm, 'bad-parent-type'],
    ] as [$parent, $slug]) {
        $before = (int) $db->query('SELECT COUNT(*) FROM taxonomy_terms')->fetchColumn();
        $response = $app->router()->dispatch(new Request('POST', '/admin/taxonomy/category', [], $post([
            'name' => '<script>x</script>',
            'slug' => $slug,
            'parent_id' => $parent,
        ])));
        $after = (int) $db->query('SELECT COUNT(*) FROM taxonomy_terms')->fetchColumn();
        $assert($status($response) === 422 && str_contains($content($response), '&lt;script&gt;x&lt;/script&gt;'), "Parent rejection/escaping failed for {$slug}.");
        $assert($after === $before, "Rejected parent {$slug} partially persisted.");
    }

    $assert($status($app->router()->dispatch(new Request('POST', '/admin/taxonomy/tag', [], $post([
        'slug' => 'tag-parent-reject',
        'parent_id' => (string) $root,
    ])))) === 422, 'Tag parent was not rejected.');
    $assert($status($app->router()->dispatch(new Request('POST', "/admin/taxonomy/category/{$root}", [], $post([
        'slug' => 'wu3-root',
        'parent_id' => (string) $root,
    ])))) === 422, 'Self-parent was not rejected.');
    $assert($status($app->router()->dispatch(new Request('POST', "/admin/taxonomy/category/{$root}", [], $post([
        'slug' => 'wu3-root',
        'parent_id' => (string) $child,
    ])))) === 422, 'Descendant parent was not rejected.');

    $assigned = $repository->createTerm($payload($tag, 'wu3-assigned'));
    $assignments->assign('content', 93001, $assigned);
    $assert($status($app->router()->dispatch(new Request('POST', "/admin/taxonomy/tag/{$assigned}/delete", [], ['_token' => 'wu3-csrf']))) === 409, 'Assigned delete was not 409.');

    $parentTerm = $repository->createTerm($payload($category, 'wu3-delete-parent'));
    $leaf = $repository->createTerm($payload($category, 'wu3-delete-leaf', $parentTerm));
    $assert($status($app->router()->dispatch(new Request('POST', "/admin/taxonomy/category/{$parentTerm}/delete", [], ['_token' => 'wu3-csrf']))) === 409, 'Category-with-child delete was not 409.');
    $assert($status($app->router()->dispatch(new Request('POST', "/admin/taxonomy/category/{$leaf}/delete", [], ['_token' => 'wu3-csrf']))) >= 300, 'Unused category leaf delete did not redirect.');

    $unusedTag = $repository->createTerm($payload($tag, 'wu3-unused-tag'));
    $assert($status($app->router()->dispatch(new Request('POST', "/admin/taxonomy/tag/{$unusedTag}/delete", [], ['_token' => 'wu3-csrf']))) >= 300, 'Unused tag delete did not redirect.');

    $source = (string) file_get_contents($basePath . '/modules/taxonomy/routes.php');
    $assert(!str_contains($source, "'/admin/taxonomy"), 'Literal Admin Taxonomy route dependency introduced.');
    $assert($app->adminUrl()->childUrl('taxonomy') === '/admin/taxonomy', 'Taxonomy route is not owned by AdminUrl childUrl().');

    $db->exec('RENAME TABLE taxonomy_terms TO taxonomy_terms_wu3_broken');
    $failure = $app->router()->dispatch(new Request('POST', '/admin/taxonomy/category', [], $post(['slug' => 'persistence-failure'])));
    $assert($status($failure) === 503 && !str_contains($content($failure), 'taxonomy_terms_wu3_broken'), 'Unexpected persistence failure was not sanitized.');

    echo "M3.5 Work Unit 3 Taxonomy security passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    $server->exec('DROP DATABASE IF EXISTS ' . $quoted);
}
