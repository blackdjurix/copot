<?php

namespace Copot\Core;

use PDO;
use PDOException;

class InstallerDatabaseProbe
{
    public function __construct(private int $timeoutSeconds = 5)
    {
        if ($timeoutSeconds < 1 || $timeoutSeconds > 30) {
            throw new InstallationException('Database connection timeout is invalid.');
        }
    }

    public function test(array $configuration): array
    {
        foreach (['host', 'port', 'database', 'username', 'password'] as $field) {
            if (!array_key_exists($field, $configuration)) {
                throw new InstallationException('Database configuration is incomplete.');
            }
        }

        if (
            !is_string($configuration['host'])
            || !is_int($configuration['port'])
            || !is_string($configuration['database'])
            || !is_string($configuration['username'])
            || !is_string($configuration['password'])
        ) {
            throw new InstallationException('Database configuration is invalid.');
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $configuration['host'],
            $configuration['port'],
            $configuration['database']
        );

        try {
            $connection = new PDO($dsn, $configuration['username'], $configuration['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => $this->timeoutSeconds,
            ]);
            $server = $this->validateServerVersion((string) $connection->getAttribute(PDO::ATTR_SERVER_VERSION));
            $statement = $connection->prepare(
                'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = :database'
            );
            $statement->execute(['database' => $configuration['database']]);
            $objectCount = (int) $statement->fetchColumn();
        } catch (PDOException) {
            throw new InstallationException('Database connection could not be verified.');
        }

        if ($objectCount !== 0) {
            throw new InstallationException('Database must be empty before installation.');
        }

        return $server;
    }

    public function validateServerVersion(string $serverVersion): array
    {
        $isMariaDb = stripos($serverVersion, 'mariadb') !== false;
        $pattern = $isMariaDb
            ? '/(\d+\.\d+(?:\.\d+)?)(?=[^0-9]*-MariaDB)/i'
            : '/(\d+\.\d+(?:\.\d+)?)/';

        if (!preg_match($pattern, $serverVersion, $matches)) {
            throw new InstallationException('Database server version could not be verified.');
        }

        $version = $matches[1];
        $minimum = $isMariaDb ? '10.4.32' : '8.0.0';

        if (version_compare($version, $minimum, '<')) {
            throw new InstallationException('Database server version is not supported.');
        }

        if ($isMariaDb) {
            if (version_compare($version, '10.6.0', '<')) {
                $supportLevel = 'legacy';
                $warning = 'MariaDB 10.4.32 through 10.5 is supported for compatibility but is legacy and end-of-life; MariaDB 10.11 or newer is recommended.';
            } elseif (version_compare($version, '10.11.0', '<')) {
                $supportLevel = 'supported';
                $warning = 'This MariaDB version is supported; MariaDB 10.11 or newer is recommended for production.';
            } elseif (version_compare($version, '11.4.0', '<')) {
                $supportLevel = 'recommended';
                $warning = null;
            } else {
                $supportLevel = 'preferred';
                $warning = null;
            }
        } else {
            $supportLevel = version_compare($version, '8.4.0', '<') ? 'minimum' : 'recommended';
            $warning = $supportLevel === 'minimum'
                ? 'MySQL 8.0 is supported; MySQL 8.4 LTS or newer is recommended for production.'
                : null;
        }

        return [
            'vendor' => $isMariaDb ? 'MariaDB' : 'MySQL',
            'version' => $version,
            'support_level' => $supportLevel,
            'warning' => $warning,
        ];
    }
}
