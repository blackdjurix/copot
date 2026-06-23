<?php

namespace Copot\Core;

class Config
{
    private array $items = [];

    public function __construct(string $configPath)
    {
        $this->load($configPath);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = $this->items;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    public function all(): array
    {
        return $this->items;
    }

    private function load(string $configPath): void
    {
        if (!is_dir($configPath)) {
            return;
        }

        foreach (glob(rtrim($configPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.php') ?: [] as $file) {
            $name = basename($file, '.php');
            $values = require $file;

            if (is_array($values)) {
                $this->items[$name] = $values;
            }
        }
    }
}
