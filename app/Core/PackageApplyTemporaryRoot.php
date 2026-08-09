<?php

namespace Copot\Core;

final class PackageApplyTemporaryRoot
{
    public static function forProject(string $projectRoot, ?string $installationId = null): string
    {
        $canonicalProjectRoot = realpath($projectRoot);
        if ($canonicalProjectRoot === false || !is_dir($canonicalProjectRoot) || is_link($projectRoot)) {
            throw new \InvalidArgumentException('Project root is invalid for package apply temporary storage.');
        }

        $systemTemporaryRoot = realpath(sys_get_temp_dir());
        if ($systemTemporaryRoot === false || !is_dir($systemTemporaryRoot) || is_link($systemTemporaryRoot)) {
            throw new \RuntimeException('System temporary root is unavailable for package apply.');
        }

        $namespace = $systemTemporaryRoot . DIRECTORY_SEPARATOR . 'copot-package-apply';
        if (!file_exists($namespace) && !mkdir($namespace, 0700)) {
            throw new \RuntimeException('Package apply temporary namespace could not be created.');
        }
        if (is_link($namespace) || !is_dir($namespace) || !is_writable($namespace)) {
            throw new \RuntimeException('Package apply temporary namespace is invalid.');
        }
        @chmod($namespace, 0700);

        $canonicalNamespace = realpath($namespace);
        if ($canonicalNamespace === false || $canonicalNamespace !== $namespace) {
            throw new \RuntimeException('Package apply temporary namespace identity is invalid.');
        }

        $projectIdentity = hash('sha256', str_replace('\\', '/', $canonicalProjectRoot) . '|' . ($installationId ?? 'default'));
        $projectNamespace = $canonicalNamespace . DIRECTORY_SEPARATOR . $projectIdentity;
        if (!file_exists($projectNamespace) && !mkdir($projectNamespace, 0700)) {
            throw new \RuntimeException('Project package apply temporary namespace could not be created.');
        }
        if (is_link($projectNamespace) || !is_dir($projectNamespace) || !is_writable($projectNamespace)) {
            throw new \RuntimeException('Project package apply temporary namespace is invalid.');
        }
        @chmod($projectNamespace, 0700);

        $canonicalProjectNamespace = realpath($projectNamespace);
        if ($canonicalProjectNamespace === false || dirname($canonicalProjectNamespace) !== $canonicalNamespace) {
            throw new \RuntimeException('Project package apply temporary namespace identity is invalid.');
        }

        return $canonicalProjectNamespace;
    }
}
