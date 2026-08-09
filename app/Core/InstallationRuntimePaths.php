<?php

namespace Copot\Core;

final class InstallationRuntimePaths
{
    public function __construct(private string $root)
    {
        if (trim($root) === '' || str_contains($root, "\0")) throw new \InvalidArgumentException('Installation runtime root is invalid.');
    }

    public static function forInstallation(string $installationId, ?string $systemRoot = null): self
    {
        if (preg_match('/\Ainst_[a-f0-9]{32}\z/', $installationId) !== 1) throw new \InvalidArgumentException('Installation identity is invalid.');
        $root = rtrim($systemRoot ?? sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'copot-runtime' . DIRECTORY_SEPARATOR . $installationId;
        foreach ([$root, $root . DIRECTORY_SEPARATOR . 'cache', $root . DIRECTORY_SEPARATOR . 'temp', $root . DIRECTORY_SEPARATOR . 'locks', $root . DIRECTORY_SEPARATOR . 'package-staging'] as $path) {
            if (!is_dir($path) && !mkdir($path, 0700, true) && !is_dir($path)) throw new \RuntimeException('Installation runtime directory could not be created.');
            if (is_link($path) || !is_writable($path)) throw new \RuntimeException('Installation runtime directory is invalid.');
            @chmod($path, 0700);
        }
        return new self(realpath($root) ?: throw new \RuntimeException('Installation runtime root could not be resolved.'));
    }

    public function root(): string { return $this->root; }
    public function cache(): string { return $this->root . DIRECTORY_SEPARATOR . 'cache'; }
    public function temp(): string { return $this->root . DIRECTORY_SEPARATOR . 'temp'; }
    public function locks(): string { return $this->root . DIRECTORY_SEPARATOR . 'locks'; }
    public function packageStaging(): string { return $this->root . DIRECTORY_SEPARATOR . 'package-staging'; }
}
