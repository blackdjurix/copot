<?php

namespace Copot\Core;

use PDOException;
use Throwable;

class InstallerAdministratorSetup
{
    public function __construct(
        private Database $database,
        private UserProvider $users,
        private PasswordHasher $passwords,
        private SettingsService $settings,
        private InstallerSchemaState $schema,
        private InstallationMutex $mutex
    ) {
    }

    public function install(array $input, bool $requirementsPassed): array
    {
        if (!$requirementsPassed) {
            throw new InstallationException('Installer requirements are not satisfied.');
        }

        $values = $this->validateInput($input);
        $passwordHash = $this->passwords->make($values['password']);
        $values['password'] = '';
        $input['admin_password'] = '';
        $input['admin_password_confirmation'] = '';
        $lock = $this->mutex->acquire();

        if (!$lock instanceof InstallationLock) {
            throw new InstallationException('Another installation process is already running.');
        }

        try {
            if (!$this->schema->isReady()) {
                throw new InstallationException('Database schema is not ready.');
            }

            if ($this->users->findByEmail($values['email']) instanceof User) {
                throw new InstallerValidationException(
                    ['admin_email' => 'Administrator email is already in use.'],
                    $this->submittedValues($values)
                );
            }

            if ($this->userCount() !== 0) {
                throw new InstallationException('The first administrator has already been created.');
            }

            $roleId = $this->administratorRoleId();

            if ($roleId === null) {
                throw new InstallationException('Administrator role is unavailable.');
            }

            $connection = $this->database->connection();
            $connection->beginTransaction();

            try {
                $statement = $connection->prepare(
                    'INSERT INTO users (
                        name,
                        email,
                        password_hash,
                        status,
                        created_at,
                        updated_at
                    ) VALUES (
                        :name,
                        :email,
                        :password_hash,
                        :status,
                        NOW(),
                        NOW()
                    )'
                );
                $statement->execute([
                    'name' => $values['name'],
                    'email' => $values['email'],
                    'password_hash' => $passwordHash,
                    'status' => 'active',
                ]);
                $userId = (int) $connection->lastInsertId();

                $statement = $connection->prepare(
                    'INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)'
                );
                $statement->execute([
                    'user_id' => $userId,
                    'role_id' => $roleId,
                ]);

                $this->settings->set('site', 'name', $values['site_name']);
                $this->settings->set('site', 'tagline', $values['site_tagline']);
                $this->settings->set('localization', 'timezone', $values['timezone']);
                $this->settings->set('localization', 'locale', $values['locale']);

                $connection->commit();

                return [
                    'user_id' => $userId,
                    'email' => $values['email'],
                ];
            } catch (PDOException) {
                if ($connection->inTransaction()) {
                    $connection->rollBack();
                }

                throw new InstallationException('Administrator and settings could not be saved.');
            } catch (Throwable $exception) {
                if ($connection->inTransaction()) {
                    $connection->rollBack();
                }

                throw $exception;
            }
        } catch (PDOException | SettingsException) {
            throw new InstallationException('Administrator and settings storage is unavailable.');
        } finally {
            $passwordHash = '';
            $lock->release();
        }
    }

    public function administratorExists(): bool
    {
        return $this->userCount() !== 0;
    }

    private function validateInput(array $input): array
    {
        $name = $this->stringValue($input, 'admin_name', true);
        $email = strtolower($this->stringValue($input, 'admin_email', true));
        $password = $this->stringValue($input, 'admin_password', false);
        $confirmation = $this->stringValue($input, 'admin_password_confirmation', false);
        $siteName = $this->stringValue($input, 'site_name', false);
        $siteTagline = $this->stringValue($input, 'site_tagline', false);
        $timezone = $this->stringValue($input, 'timezone', false);
        $locale = $this->stringValue($input, 'locale', false);
        $errors = [];

        $fieldMessages = [
            'admin_name' => 'Administrator name is required and must not exceed 120 characters.',
            'admin_email' => 'Enter a valid administrator email address.',
            'admin_password' => 'Administrator password must contain at least 10 characters.',
            'admin_password_confirmation' => 'Password confirmation does not match.',
            'site_name' => 'Site Name is required and must not exceed 150 characters.',
            'site_tagline' => 'Site Tagline must not exceed 255 characters.',
            'timezone' => 'Invalid timezone.',
            'locale' => 'Unsupported locale.',
        ];

        foreach ($fieldMessages as $field => $message) {
            if (array_key_exists($field, $input) && !is_string($input[$field])) {
                $errors[$field] = $message;
            }
        }

        $nameLength = $this->stringLength($name);

        if (
            $name === ''
            || $nameLength === null
            || $nameLength > 120
            || preg_match('/[\x00-\x1F\x7F]/', $name)
        ) {
            $errors['admin_name'] = 'Administrator name is required and must not exceed 120 characters.';
        }

        if (
            $email === ''
            || strlen($email) > 190
            || filter_var($email, FILTER_VALIDATE_EMAIL) === false
        ) {
            $errors['admin_email'] = 'Enter a valid administrator email address.';
        }

        $passwordLength = $this->stringLength($password);

        if ($passwordLength === null || $passwordLength < 10) {
            $errors['admin_password'] = 'Administrator password must contain at least 10 characters.';
        }

        if ($confirmation !== $password) {
            $errors['admin_password_confirmation'] = 'Password confirmation does not match.';
        }

        $this->validateSetting('site', 'name', $siteName, 'site_name', 'Site Name is required and must not exceed 150 characters.', $errors);
        $this->validateSetting('site', 'tagline', $siteTagline, 'site_tagline', 'Site Tagline must not exceed 255 characters.', $errors);
        $this->validateSetting('localization', 'timezone', $timezone, 'timezone', 'Invalid timezone.', $errors);
        $this->validateSetting('localization', 'locale', $locale, 'locale', 'Unsupported locale.', $errors);

        $values = [
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'site_name' => $siteName,
            'site_tagline' => $siteTagline,
            'timezone' => $timezone,
            'locale' => $locale,
        ];

        if ($errors !== []) {
            throw new InstallerValidationException($errors, $this->submittedValues($values));
        }

        return $values;
    }

    private function validateSetting(
        string $namespace,
        string $key,
        string $value,
        string $field,
        string $message,
        array &$errors
    ): void {
        try {
            $this->settings->validate($namespace, $key, $value);
        } catch (SettingsException) {
            $errors[$field] = $message;
        }
    }

    private function userCount(): int
    {
        return (int) $this->database->connection()->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }

    private function administratorRoleId(): ?int
    {
        $statement = $this->database->connection()->prepare(
            'SELECT id FROM roles WHERE slug = :slug LIMIT 1'
        );
        $statement->execute(['slug' => 'admin']);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    private function submittedValues(array $values): array
    {
        return [
            'admin_name' => $this->safeSubmittedValue($values['name']),
            'admin_email' => $this->safeSubmittedValue($values['email']),
            'site_name' => $this->safeSubmittedValue($values['site_name']),
            'site_tagline' => $this->safeSubmittedValue($values['site_tagline']),
            'timezone' => $this->safeSubmittedValue($values['timezone']),
            'locale' => $this->safeSubmittedValue($values['locale']),
        ];
    }

    private function safeSubmittedValue(string $value): string
    {
        return preg_match('/[\x00-\x1F\x7F]/', $value) ? '' : $value;
    }

    private function stringValue(array $input, string $key, bool $trim): string
    {
        $value = $input[$key] ?? '';

        if (!is_string($value)) {
            return "\0";
        }

        return $trim ? trim($value) : $value;
    }

    private function stringLength(string $value): ?int
    {
        $length = preg_match_all('/./us', $value);

        return is_int($length) ? $length : null;
    }
}
