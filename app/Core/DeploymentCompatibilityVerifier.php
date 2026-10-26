<?php

namespace Copot\Core;

/**
 * Verifies the deployment boundary required by Webcore without assuming a
 * particular web-server configuration language or routing file.
 */
final class DeploymentCompatibilityVerifier
{
    /** @return array{app_root:string,public_root:string,base_path:string,entrypoint:string} */
    public static function verify(DeploymentContext $deployment, bool $requireEntrypoint = true): array
    {
        $appRoot = $deployment->appRoot();
        $publicRoot = $deployment->publicRoot();
        $entrypoint = $deployment->publicPath('index.php');
        $bootstrap = $deployment->path('bootstrap/autoload.php');

        if ($requireEntrypoint) {
            if (!is_file($entrypoint) || !is_readable($entrypoint)) {
                throw new \RuntimeException('Deployment public entrypoint is unavailable.');
            }
            if (!is_file($bootstrap) || !is_readable($bootstrap)) {
                throw new \RuntimeException('Deployment private bootstrap is unavailable.');
            }
        }
        if (self::samePath($appRoot, $publicRoot) || self::isInside($appRoot, $publicRoot)) {
            throw new \RuntimeException('Deployment roots do not preserve the private APP_ROOT boundary.');
        }

        return [
            'app_root' => $appRoot,
            'public_root' => $publicRoot,
            'base_path' => $deployment->basePath(),
            'entrypoint' => $entrypoint,
        ];
    }

    private static function samePath(string $left, string $right): bool
    {
        return self::identityPath($left) === self::identityPath($right);
    }

    private static function isInside(string $path, string $directory): bool
    {
        $path = self::identityPath($path);
        $directory = rtrim(self::identityPath($directory), '/') . '/';
        return str_starts_with($path . '/', $directory);
    }

    private static function identityPath(string $path): string
    {
        $path = str_replace('\\', '/', rtrim($path, '/\\'));
        return DIRECTORY_SEPARATOR === '\\' ? strtolower($path) : $path;
    }
}
