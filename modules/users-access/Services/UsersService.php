<?php

use Copot\Core\Database;
use Copot\Core\PasswordHasher;
use Copot\Core\PermissionChecker;

class UsersService
{
    public function __construct(
        private UsersRepository $users,
        private PasswordHasher $passwords,
        private PermissionChecker $permissions,
        private Database $database
    ) {
    }

    public function create(array $input, bool $activeCreationAuthorized = false): int
    {
        $this->rejectUnexpectedFields($input, ['name', 'email', 'password', 'password_confirmation', 'status']);
        $name = $this->stringInput($input, 'name');
        $email = strtolower(trim($this->stringInput($input, 'email')));
        $password = $this->stringInput($input, 'password', false);
        $confirmation = $this->stringInput($input, 'password_confirmation', false);
        $status = array_key_exists('status', $input) ? $this->stringInput($input, 'status') : 'inactive';
        $errors = $this->identityErrors($name, $email);
        $errors += $this->passwordErrors($password, $confirmation);

        if (!in_array($status, ['active', 'inactive'], true)) {
            $errors['status'] = 'Status must be active or inactive.';
        } elseif ($status === 'active' && !$activeCreationAuthorized) {
            $errors['status'] = 'Creating an active user is not authorized.';
        }

        if (!isset($errors['email']) && $this->users->emailExists($email)) {
            $errors['email'] = 'Email is already in use.';
        }

        if ($errors !== []) {
            throw new UsersValidationException($errors, compact('name', 'email', 'status'));
        }

        try {
            return $this->users->create($name, $email, $this->passwords->make($password), $status);
        } catch (PDOException $exception) {
            if ($this->isDuplicateKey($exception)) {
                throw new UsersValidationException(
                    ['email' => 'Email is already in use.'],
                    compact('name', 'email', 'status')
                );
            }

            throw $exception;
        }
    }

    public function updateIdentity(int $id, array $input): void
    {
        $this->rejectUnexpectedFields($input, ['name', 'email']);
        $name = $this->stringInput($input, 'name');
        $email = strtolower(trim($this->stringInput($input, 'email')));
        $errors = $this->identityErrors($name, $email);

        if (!isset($errors['email']) && $this->users->emailExists($email, $id)) {
            $errors['email'] = 'Email is already in use.';
        }

        if ($errors !== []) {
            throw new UsersValidationException($errors, compact('name', 'email'));
        }

        try {
            $this->users->updateIdentity($id, $name, $email);
        } catch (PDOException $exception) {
            if ($this->isDuplicateKey($exception)) {
                throw new UsersValidationException(
                    ['email' => 'Email is already in use.'],
                    compact('name', 'email')
                );
            }

            throw $exception;
        }
    }

    public function changePassword(int $id, array $input): void
    {
        $this->rejectUnexpectedFields($input, ['password', 'password_confirmation']);
        $password = $this->stringInput($input, 'password', false);
        $confirmation = $this->stringInput($input, 'password_confirmation', false);
        $errors = $this->passwordErrors($password, $confirmation);

        if ($errors !== []) {
            throw new UsersValidationException($errors);
        }

        $this->users->updatePasswordHash($id, $this->passwords->make($password));
    }

    public function changeStatus(int $targetId, mixed $status, int $actorId): void
    {
        if (!is_string($status) || !in_array($status, ['active', 'inactive'], true)) {
            throw new UsersValidationException(['status' => 'Status must be active or inactive.']);
        }

        $connection = $this->database->connection();
        $ownsTransaction = !$connection->inTransaction();

        if ($ownsTransaction) {
            $connection->beginTransaction();
        }

        try {
            $target = $this->users->findByIdForUpdate($targetId);

            if (!$target instanceof ManagedUser) {
                throw new UsersValidationException(['user' => 'User account is unavailable.']);
            }

            if ($status === 'inactive' && $targetId === $actorId) {
                throw new UsersValidationException(['status' => 'You cannot deactivate your own account.']);
            }

            if ($status === 'inactive' && $this->permissions->userCan($targetId, 'admin.access')) {
                throw new UsersValidationException([
                    'status' => 'Users with Admin access cannot be deactivated until administrator protection is available.',
                ]);
            }

            $this->users->updateStatus($targetId, $status);

            if ($ownsTransaction) {
                $connection->commit();
            }
        } catch (Throwable $exception) {
            if ($ownsTransaction && $connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }

    private function identityErrors(string $name, string $email): array
    {
        $errors = [];
        $nameLength = $this->stringLength($name);

        if ($name === '' || $nameLength === null || $nameLength > 120 || preg_match('/[\x00-\x1F\x7F]/', $name)) {
            $errors['name'] = 'Name is required and must not exceed 120 characters.';
        }

        if ($email === '' || strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Enter a valid email address.';
        }

        return $errors;
    }

    private function passwordErrors(string $password, string $confirmation): array
    {
        $errors = [];
        $passwordLength = $this->stringLength($password);

        if ($passwordLength === null || $passwordLength < 10 || strlen($password) > 4096) {
            $errors['password'] = 'Password must contain at least 10 characters and no more than 4096 bytes.';
        }

        if ($confirmation !== $password) {
            $errors['password_confirmation'] = 'Password confirmation does not match.';
        }

        return $errors;
    }

    private function rejectUnexpectedFields(array $input, array $allowed): void
    {
        foreach (array_keys($input) as $field) {
            if (!is_string($field) || !in_array($field, $allowed, true)) {
                throw new UsersValidationException(['form' => 'Unexpected user input was provided.']);
            }
        }
    }

    private function stringInput(array $input, string $field, bool $trim = true): string
    {
        $value = $input[$field] ?? null;

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

    private function isDuplicateKey(PDOException $exception): bool
    {
        $errorInfo = $exception->errorInfo;

        return is_array($errorInfo) && (int) ($errorInfo[1] ?? 0) === 1062;
    }
}
