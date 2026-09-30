<?php

namespace Copot\Core;

final class EmailOperatorService
{
    private const CONFIGURATION_TYPES = [
        'smtp_host' => 'string',
        'smtp_port' => 'integer',
        'smtp_security' => 'string',
        'smtp_username' => 'string',
        'sender_email' => 'string',
        'sender_name' => 'string',
    ];

    public function __construct(
        private SettingsService $settings,
        private EmailConfigurationResolver $configuration,
        private EmailCredentialBoundary $credentials,
        private EmailTransport $transport,
        private ReauthenticationService $reauthentication,
    ) {
    }

    public function read(User $operator): EmailOperatorResult
    {
        if (!$this->authorized($operator)) {
            return EmailOperatorResult::failure('forbidden');
        }

        try {
            return EmailOperatorResult::success('state_read', $this->configuration->resolve()->toReadModel());
        } catch (\Throwable) {
            return EmailOperatorResult::failure('configuration_unavailable');
        }
    }

    public function updateConfiguration(User $operator, array $values): EmailOperatorResult
    {
        if (!$this->authorized($operator)) {
            return EmailOperatorResult::failure('forbidden');
        }

        $normalized = [];
        foreach ($values as $key => $value) {
            $key = str_starts_with((string) $key, 'email.') ? substr((string) $key, 6) : (string) $key;
            $normalized[$key] = $value;
        }

        if (array_diff(array_keys($normalized), array_keys(self::CONFIGURATION_TYPES)) !== []) {
            return EmailOperatorResult::failure('invalid_configuration');
        }

        try {
            foreach ($normalized as $key => $value) {
                $this->settings->validate('email', $key, $value, self::CONFIGURATION_TYPES[$key]);
            }

            foreach ($normalized as $key => $value) {
                $this->settings->set('email', $key, $value, self::CONFIGURATION_TYPES[$key]);
            }

            return EmailOperatorResult::success('configuration_updated', $this->configuration->resolve()->toReadModel());
        } catch (SettingsException) {
            return EmailOperatorResult::failure('invalid_configuration');
        } catch (\Throwable) {
            return EmailOperatorResult::failure('configuration_update_failed');
        }
    }

    public function replaceCredential(User $operator, string $secret): EmailOperatorResult
    {
        if (!$this->authorized($operator)) {
            return EmailOperatorResult::failure('forbidden');
        }

        try {
            $this->reauthentication->requireRecentProof();
        } catch (ReauthenticationRequiredException) {
            return EmailOperatorResult::failure('reauthentication_required');
        }

        try {
            $this->credentials->replace($secret);

            return EmailOperatorResult::success('credential_updated', $this->configuration->resolve()->toReadModel());
        } catch (EmailCredentialException) {
            return EmailOperatorResult::failure('credential_update_failed');
        } catch (\Throwable) {
            return EmailOperatorResult::failure('credential_update_failed');
        }
    }

    public function sendTest(User $operator, string $recipient): EmailOperatorResult
    {
        if (!$this->authorized($operator)) {
            return EmailOperatorResult::failure('forbidden');
        }

        try {
            $result = $this->transport->deliver(new EmailMessage($recipient, 'COPOT Email test', 'This is a controlled COPOT Email test message.'));
        } catch (EmailMessageException) {
            return EmailOperatorResult::failure('invalid_recipient');
        } catch (\Throwable) {
            return EmailOperatorResult::failure('test_delivery_failed');
        }

        return $result->isSuccessful()
            ? EmailOperatorResult::success('test_delivery_succeeded')
            : EmailOperatorResult::failure($result->code() === 'credential_unavailable' ? 'credential_unavailable' : 'test_delivery_failed');
    }

    private function authorized(User $operator): bool
    {
        return $operator->isActive()
            && $operator->can('admin.access')
            && $operator->can('email.manage');
    }
}
