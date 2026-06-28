<?php

namespace Copot\Core;

class InstallerRequirements
{
    private string $projectRoot;

    public function __construct(string $projectRoot)
    {
        $this->projectRoot = rtrim($projectRoot, '/\\');
    }

    public function check(?bool $sessionReady = null): array
    {
        $sessionSupported = extension_loaded('session')
            && function_exists('session_start')
            && function_exists('session_status');
        $sessionPassed = $sessionSupported && $sessionReady !== false;
        $storagePath = $this->projectRoot . DIRECTORY_SEPARATOR . 'storage';
        $environmentPath = $this->projectRoot . DIRECTORY_SEPARATOR . '.env';
        $environmentDirectoryWritable = is_dir($this->projectRoot) && is_writable($this->projectRoot);
        $environmentWritable = $environmentDirectoryWritable
            && (
                !file_exists($environmentPath)
                || (is_file($environmentPath) && !is_link($environmentPath) && is_writable($environmentPath))
            );

        return [
            $this->phpRequirement(),
            $this->result('pdo', 'PDO extension', extension_loaded('PDO')),
            $this->result('pdo_mysql', 'PDO MySQL extension', extension_loaded('pdo_mysql')),
            $this->result('session', 'Session support', $sessionPassed),
            $this->result('json', 'JSON extension', extension_loaded('json')),
            $this->result('filter', 'Filter extension', extension_loaded('filter')),
            $this->result('storage', 'Writable storage', is_dir($storagePath) && is_writable($storagePath)),
            $this->result('environment', 'Writable environment configuration', $environmentWritable),
        ];
    }

    public function phpRequirement(?int $versionId = null): array
    {
        $versionId ??= PHP_VERSION_ID;

        if ($versionId < 80200) {
            return $this->result('php', 'PHP 8.2+', false, null, 'unsupported');
        }

        if ($versionId < 80300) {
            return $this->result(
                'php',
                'PHP 8.2+',
                true,
                'PHP 8.2 is supported but aging; PHP 8.3 or newer is recommended.',
                'minimum'
            );
        }

        if ($versionId < 80400) {
            return $this->result('php', 'PHP 8.2+', true, null, 'recommended');
        }

        if ($versionId < 80500) {
            return $this->result('php', 'PHP 8.2+', true, null, 'preferred');
        }

        return $this->result('php', 'PHP 8.2+', true, null, 'newer');
    }

    public function allPassed(array $requirements): bool
    {
        foreach ($requirements as $requirement) {
            if (($requirement['passed'] ?? false) !== true) {
                return false;
            }
        }

        return true;
    }

    private function result(
        string $name,
        string $label,
        bool $passed,
        ?string $warning = null,
        ?string $supportLevel = null
    ): array
    {
        return [
            'name' => $name,
            'label' => $label,
            'passed' => $passed,
            'warning' => $warning,
            'support_level' => $supportLevel,
        ];
    }
}
