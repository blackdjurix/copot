<?php

namespace Copot\Core;

class InstallerDatabaseValidator
{
    private const HOST_PATTERN = '/^[A-Za-z0-9._:-]{1,255}$/';
    private const DATABASE_PATTERN = '/^[A-Za-z0-9_-]{1,64}$/';

    public function validate(array $input): array
    {
        $host = $this->stringValue($input, 'host', true);
        $portValue = $this->stringValue($input, 'port', true);
        $database = $this->stringValue($input, 'database', true);
        $username = $this->stringValue($input, 'username', true);
        $password = $this->stringValue($input, 'password', false);
        $errors = [];

        if ($host === '' || !preg_match(self::HOST_PATTERN, $host)) {
            $errors['host'] = 'Enter a valid database host.';
        }

        $port = filter_var($portValue, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 65535],
        ]);

        if (!is_int($port) || !preg_match('/^[0-9]+$/', $portValue)) {
            $errors['port'] = 'Enter a database port between 1 and 65535.';
        }

        if ($database === '' || !preg_match(self::DATABASE_PATTERN, $database)) {
            $errors['database'] = 'Enter a valid database name using letters, numbers, underscores, or hyphens.';
        }

        if ($username === '' || strlen($username) > 128 || preg_match('/[\x00-\x1F\x7F]/', $username)) {
            $errors['username'] = 'Enter a valid database username.';
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $password)) {
            $errors['password'] = 'The database password contains unsupported control characters.';
        }

        $submittedValues = [
            'host' => $this->safeSubmittedValue($host),
            'port' => $this->safeSubmittedValue($portValue),
            'database' => $this->safeSubmittedValue($database),
            'username' => $this->safeSubmittedValue($username),
        ];

        if ($errors !== []) {
            throw new InstallerValidationException($errors, $submittedValues);
        }

        return [
            'host' => $host,
            'port' => $port,
            'database' => $database,
            'username' => $username,
            'password' => $password,
        ];
    }

    private function stringValue(
        array $input,
        string $key,
        bool $trim
    ): string {
        $value = $input[$key] ?? '';

        if (!is_string($value)) {
            return "\0";
        }

        return $trim ? trim($value) : $value;
    }

    private function safeSubmittedValue(string $value): string
    {
        return preg_match('/[\x00-\x1F\x7F]/', $value) ? '' : $value;
    }
}
