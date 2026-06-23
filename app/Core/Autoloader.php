<?php
namespace Copot\Core;
class Autoloader
{
    private string $prefix;
    private string $basePath;
    public function __construct(string $prefix, string $basePath)
    {
        $this->prefix = trim($prefix, '\\') . '\\';
        $this->basePath = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }
    public function register(): void
    {
        spl_autoload_register([$this, 'load']);
    }
    public function load(string $class): void
    {
        if (strncmp($class, $this->prefix, strlen($this->prefix)) !== 0) {
            return;
        }
        $relativeClass = substr($class, strlen($this->prefix));
        $file = $this->basePath . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
}
