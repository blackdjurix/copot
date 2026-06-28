<?php

namespace Copot\Core;

use PDO;
use PDOException;

class InstallerSchemaRunner
{
    private const MAX_SCHEMA_BYTES = 1048576;

    public function __construct(private string $schemaPath, private int $timeoutSeconds = 5)
    {
        if ($timeoutSeconds < 1 || $timeoutSeconds > 30) {
            throw new InstallationException('Schema installation configuration is invalid.');
        }
    }

    public function install(array $configuration): int
    {
        $schema = $this->readSchema();
        $statements = $this->statements($schema);

        if ($statements === []) {
            throw new InstallationException('Database schema is invalid.');
        }

        try {
            $connection = new PDO($this->dsn($configuration), $configuration['username'], $configuration['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => $this->timeoutSeconds,
            ]);

            foreach ($statements as $statement) {
                $connection->exec($statement);
            }
        } catch (PDOException) {
            throw new InstallationException('Database schema installation failed. Use a clean empty database before retrying.');
        }

        return count($statements);
    }

    public function statements(string $schema): array
    {
        $statements = [];
        $buffer = '';
        $length = strlen($schema);
        $state = 'normal';

        for ($index = 0; $index < $length; $index++) {
            $character = $schema[$index];
            $next = $index + 1 < $length ? $schema[$index + 1] : '';

            if ($state === 'line-comment') {
                if ($character === "\n" || $character === "\r") {
                    $buffer .= $character;
                    $state = 'normal';
                }

                continue;
            }

            if ($state === 'block-comment') {
                if ($character === '*' && $next === '/') {
                    $buffer .= ' ';
                    $state = 'normal';
                    $index++;
                }

                continue;
            }

            if ($state !== 'normal') {
                $buffer .= $character;

                if ($character === '\\' && $next !== '') {
                    $buffer .= $next;
                    $index++;
                    continue;
                }

                if ($character === $state) {
                    if ($next === $state) {
                        $buffer .= $next;
                        $index++;
                    } else {
                        $state = 'normal';
                    }
                }

                continue;
            }

            if ($character === '-' && $next === '-' && ($index + 2 >= $length || ctype_space($schema[$index + 2]))) {
                $state = 'line-comment';
                $index++;
                continue;
            }

            if ($character === '#') {
                $state = 'line-comment';
                continue;
            }

            if ($character === '/' && $next === '*') {
                $state = 'block-comment';
                $index++;
                continue;
            }

            if ($character === "'" || $character === '"' || $character === '`') {
                $state = $character;
                $buffer .= $character;
                continue;
            }

            if ($character === ';') {
                $statement = trim($buffer);

                if ($statement !== '') {
                    $this->validateStatement($statement);
                    $statements[] = $statement;
                }

                $buffer = '';
                continue;
            }

            $buffer .= $character;
        }

        if ($state === 'line-comment') {
            $state = 'normal';
        }

        if ($state !== 'normal' || trim($buffer) !== '') {
            throw new InstallationException('Database schema is invalid.');
        }

        return $statements;
    }

    private function readSchema(): string
    {
        if (
            is_link($this->schemaPath)
            || !is_file($this->schemaPath)
            || !is_readable($this->schemaPath)
        ) {
            throw new InstallationException('Database schema is unavailable.');
        }

        $size = @filesize($this->schemaPath);

        if (!is_int($size) || $size < 1 || $size > self::MAX_SCHEMA_BYTES) {
            throw new InstallationException('Database schema is invalid.');
        }

        $schema = @file_get_contents($this->schemaPath);

        if (!is_string($schema)) {
            throw new InstallationException('Database schema is unavailable.');
        }

        return $schema;
    }

    private function validateStatement(string $statement): void
    {
        if (!preg_match('/\A(?:CREATE\s+TABLE|INSERT\s+INTO)\b/i', $statement)) {
            throw new InstallationException('Database schema contains an unsupported statement.');
        }
    }

    private function dsn(array $configuration): string
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

        return sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $configuration['host'],
            $configuration['port'],
            $configuration['database']
        );
    }
}
