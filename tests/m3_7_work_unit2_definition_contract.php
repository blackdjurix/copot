<?php

declare(strict_types=1);

use Copot\Core\ThemeDiscovery;

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

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-m37-wu2-' . bin2hex(random_bytes(5));
$writeTheme = static function (string $root, string $name, array $manifest): string {
    $themePath = $root . DIRECTORY_SEPARATOR . $name;
    mkdir($themePath . DIRECTORY_SEPARATOR . 'layouts', 0777, true);
    file_put_contents($themePath . DIRECTORY_SEPARATOR . 'layouts' . DIRECTORY_SEPARATOR . 'app.php', '<?php echo "theme";');
    file_put_contents($themePath . DIRECTORY_SEPARATOR . 'theme.json', json_encode($manifest, JSON_THROW_ON_ERROR));

    return $themePath;
};
$removeDirectory = static function (string $path) use (&$removeDirectory): void {
    if (!is_dir($path)) {
        return;
    }

    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $child = $path . DIRECTORY_SEPARATOR . $entry;
        is_dir($child) ? $removeDirectory($child) : unlink($child);
    }

    rmdir($path);
};
$validManifest = static function (string $id): array {
    return [
        'id' => $id,
        'name' => 'Contract Theme',
        'version' => '1.0.0',
        'type' => 'frontend',
        'entry' => ['layout' => 'layouts/app.php'],
    ];
};
$assertInvalid = static function (array $manifest, string $message) use ($root, $writeTheme, $assert): void {
    $name = 'invalid-' . bin2hex(random_bytes(3));
    $writeTheme($root, $name, $manifest);
    $catalog = (new ThemeDiscovery($root))->discoverCatalog();
    $assert(count($catalog['errors']) >= 1, $message . ' was accepted.');
    $assert(in_array('invalid_definition', array_column($catalog['errors'], 'code'), true), $message . ' produced an uncontrolled diagnostic.');
    $assert(!str_contains(json_encode($catalog['errors'], JSON_THROW_ON_ERROR), $root), $message . ' leaked its path.');
    $remove = static function (string $path) use (&$remove): void {
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $child = $path . DIRECTORY_SEPARATOR . $entry;
            is_dir($child) ? $remove($child) : unlink($child);
        }
        rmdir($path);
    };
    $remove($root . DIRECTORY_SEPARATOR . $name);
};

mkdir($root, 0777, true);

try {
    $default = (new ThemeDiscovery($basePath . '/themes'))->discover()[0];
    $assert($default->screenshot() === null && $default->settings() === [], 'Existing default Theme optional WU2 descriptors changed unexpectedly.');
    $assert($default->supports() === ['module_view_overrides' => true, 'navigation_locations' => ['primary']], 'Existing default Theme capabilities changed unexpectedly.');

    $manifest = $validManifest('contract-theme');
    $manifest['description'] = '  A normalized description.  ';
    $manifest['author'] = '  Copot  ';
    $manifest['screenshot'] = 'screenshots/PREVIEW.WEBP';
    $manifest['supports'] = [
        'navigation_locations' => ['footer', 'primary'],
        'module_view_overrides' => false,
    ];
    $manifest['vendor_extension'] = ['retained' => true];
    $manifest['settings'] = [
        'version' => 1,
        'sections' => [
            [
                'id' => 'zeta',
                'label' => ' Zeta ',
                'description' => ' Zeta description ',
                'fields' => [
                    ['key' => 'enabled', 'label' => 'Enabled', 'type' => 'boolean', 'control' => 'checkbox', 'default' => true, 'validation' => ['required' => true]],
                    ['key' => 'accent', 'label' => 'Accent', 'description' => null, 'type' => 'string', 'control' => 'color', 'default' => '#AABBCC', 'validation' => ['format' => 'hex_color']],
                ],
            ],
            [
                'id' => 'brand',
                'label' => 'Brand',
                'description' => null,
                'fields' => [
                    ['key' => 'mode', 'label' => 'Mode', 'description' => null, 'type' => 'string', 'control' => 'select', 'default' => 'light', 'validation' => ['allowed_values' => ['light', 'dark']]],
                    ['key' => 'size', 'label' => 'Size', 'description' => null, 'type' => 'integer', 'control' => 'number', 'default' => 2, 'validation' => ['min' => 1, 'max' => 4]],
                    ['key' => 'title', 'label' => 'Title', 'type' => 'string', 'control' => 'text', 'default' => 'Copot', 'validation' => ['required' => true, 'max_length' => 20]],
                ],
            ],
        ],
    ];
    $themePath = $writeTheme($root, 'contract-theme', $manifest);
    mkdir($themePath . DIRECTORY_SEPARATOR . 'screenshots', 0777, true);
    file_put_contents($themePath . DIRECTORY_SEPARATOR . 'screenshots' . DIRECTORY_SEPARATOR . 'PREVIEW.WEBP', base64_decode('UklGRiIAAABXRUJQVlA4IBgAAAAwAQCdASoBAAEADsADAA==', true));

    $definition = (new ThemeDiscovery($root))->discover()[0];
    $assert($definition->description() === 'A normalized description.' && $definition->author() === 'Copot', 'Optional metadata was not normalized.');
    $assert($definition->screenshot() === 'screenshots/PREVIEW.WEBP', 'Contained screenshot was not normalized.');
    $assert($definition->supports() === ['module_view_overrides' => false, 'navigation_locations' => ['footer', 'primary']], 'Capabilities were not normalized deterministically.');
    $assert(array_column($definition->settings()['sections'], 'id') === ['brand', 'zeta'], 'Settings sections were not deterministically ordered.');
    $assert(array_column($definition->settings()['sections'][0]['fields'], 'key') === ['mode', 'size', 'title'], 'Settings fields were not deterministically ordered.');
    $assert($definition->settings()['sections'][0]['fields'][0]['validation']['allowed_values'] === ['light', 'dark'], 'Allowed values were not retained.');
    $assert($definition->settings()['sections'][1]['fields'][0]['default'] === '#aabbcc', 'Color defaults were not normalized.');
    $assert($definition->metadata()['vendor_extension'] === ['retained' => true], 'Unknown top-level metadata was not preserved.');

    $invalid = $validManifest('invalid');
    $invalid['supports'] = ['unsupported_capability' => true];
    $assertInvalid($invalid, 'unsupported capability');
    $invalid = $validManifest('invalid');
    $invalid['supports'] = ['navigation_locations' => ['primary', 'primary']];
    $assertInvalid($invalid, 'duplicate capability declaration');
    $invalid = $validManifest('invalid');
    $invalid['screenshot'] = '../outside.png';
    $assertInvalid($invalid, 'escaping screenshot');
    $invalid = $validManifest('invalid');
    $invalid['screenshot'] = 'preview.gif';
    $assertInvalid($invalid, 'unsupported screenshot extension');
    $invalid = $validManifest('invalid');
    $invalid['settings'] = ['version' => 1, 'sections' => [['id' => 'one', 'label' => 'One', 'description' => null, 'fields' => []]]];
    $assertInvalid($invalid, 'empty settings section');
    $invalid = $validManifest('invalid');
    $invalid['settings'] = ['version' => 1, 'sections' => [
        ['id' => 'one', 'label' => 'One', 'description' => null, 'fields' => [['key' => 'same', 'label' => 'Same', 'description' => null, 'type' => 'string', 'control' => 'text', 'default' => 'a', 'validation' => []]]],
        ['id' => 'two', 'label' => 'Two', 'description' => null, 'fields' => [['key' => 'same', 'label' => 'Same', 'description' => null, 'type' => 'string', 'control' => 'text', 'default' => 'b', 'validation' => []]]],
    ]];
    $assertInvalid($invalid, 'duplicate field key');
    $invalid = $validManifest('invalid');
    $invalid['settings'] = [
        'version' => 1,
        'sections' => [[
            'id' => 'one', 'label' => 'One', 'description' => null,
            'fields' => [['key' => 'flag', 'label' => 'Flag', 'description' => null, 'type' => 'boolean', 'control' => 'text', 'default' => true, 'validation' => []]],
        ]],
    ];
    $assertInvalid($invalid, 'incompatible setting control');
    $invalid = $validManifest('invalid');
    $invalid['settings'] = [
        'version' => 1,
        'sections' => [[
            'id' => 'one', 'label' => 'One', 'description' => null,
            'fields' => [['key' => 'size', 'label' => 'Size', 'description' => null, 'type' => 'integer', 'control' => 'number', 'default' => 2, 'validation' => ['min' => 4, 'max' => 1]]],
        ]],
    ];
    $assertInvalid($invalid, 'incoherent numeric bounds');
    $invalid = $validManifest('invalid');
    $invalid['settings'] = [
        'version' => 1,
        'sections' => [[
            'id' => 'one', 'label' => 'One', 'description' => null,
            'fields' => [['key' => 'color', 'label' => 'Color', 'description' => null, 'type' => 'string', 'control' => 'color', 'default' => 'red', 'validation' => ['format' => 'hex_color']]],
        ]],
    ];
    $assertInvalid($invalid, 'invalid color default');

    echo "M3.7 Work Unit 2 definition contract passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    $removeDirectory($root);
}
