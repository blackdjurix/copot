<?php

namespace Copot\Core;

final class SecurityPolicyService
{
    public const PASSWORD_MINIMUM_KEY = 'password_min_length';
    public const PASSWORD_MAXIMUM_KEY = 'password_max_length';
    public const IDLE_TIMEOUT_KEY = 'authenticated_idle_timeout_minutes';

    public function __construct(
        private SettingsService $settings,
        private ReauthenticationService $reauthentication,
        private SecurityEventService $securityEvents,
        private Database $database
    ) {
    }

    /** @return array{password_min_length:int,password_max_length:int,authenticated_idle_timeout_minutes:int} */
    public function values(): array
    {
        return [
            self::PASSWORD_MINIMUM_KEY => (int) $this->settings->get('security', self::PASSWORD_MINIMUM_KEY, PasswordPolicy::DEFAULT_MINIMUM_LENGTH),
            self::PASSWORD_MAXIMUM_KEY => (int) $this->settings->get('security', self::PASSWORD_MAXIMUM_KEY, PasswordPolicy::DEFAULT_MAXIMUM_LENGTH),
            self::IDLE_TIMEOUT_KEY => (int) $this->settings->get('security', self::IDLE_TIMEOUT_KEY, AuthenticatedIdleTimeoutResolver::DEFAULT_MINUTES),
        ];
    }

    /** @param array{password_min_length:int,password_max_length:int,authenticated_idle_timeout_minutes:int} $values */
    /** @return list<string> */
    public function update(int $actorUserId, array $values): array
    {
        foreach ($values as $key => $value) {
            $this->settings->validate('security', $key, $value, 'integer');
        }

        if ($values[self::PASSWORD_MINIMUM_KEY] > $values[self::PASSWORD_MAXIMUM_KEY]) {
            throw new SettingsException('Password minimum length cannot exceed password maximum length.');
        }

        $current = $this->values();
        $changed = array_values(array_filter(
            array_keys($values),
            static fn (string $key): bool => $values[$key] !== $current[$key]
        ));

        if ($changed === []) {
            return [];
        }

        $this->reauthentication->requireRecentProof();

        $connection = $this->database->connection();
        $connection->beginTransaction();
        try {
            foreach ($changed as $key) {
                $this->settings->set('security', $key, $values[$key], 'integer');
            }
            $connection->commit();
        } catch (\Throwable $failure) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
            throw $failure;
        }

        foreach ($changed as $key) {
            $this->securityEvents->recordSecurityPolicyChange($actorUserId, $key);
        }

        return $changed;
    }
}
