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
                    'INSERT INTO ' . $this->database->table('users') . ' (
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
                    'INSERT INTO ' . $this->database->table('user_roles') . ' (user_id, role_id) VALUES (:user_id, :role_id)'
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
        return InstallerAdministratorValidator::validate($input);
    }

    private function userCount(): int
    {
        return (int) $this->database->connection()->query('SELECT COUNT(*) FROM ' . $this->database->table('users'))->fetchColumn();
    }

    private function administratorRoleId(): ?int
    {
        $statement = $this->database->connection()->prepare(
            'SELECT id FROM ' . $this->database->table('roles') . ' WHERE slug = :slug LIMIT 1'
        );
        $statement->execute(['slug' => 'admin']);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }

}
