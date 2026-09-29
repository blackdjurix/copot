<?php

namespace Copot\Core;

final class EmailConfigurationResolver
{
    public function __construct(
        private SettingsService $settings,
        private EmailCredentialBoundary $credentials,
    ) {
    }

    public function resolve(): EmailConfiguration
    {
        $username = (string) $this->settings->get('email', 'smtp_username', '');

        return new EmailConfiguration(
            new EmailTransportConfiguration(
                (string) $this->settings->get('email', 'smtp_host', ''),
                (int) $this->settings->get('email', 'smtp_port', 587),
                (string) $this->settings->get('email', 'smtp_security', 'tls'),
                $username === '' ? null : $username,
            ),
            new EmailSenderIdentity(
                (string) $this->settings->get('email', 'sender_email', ''),
                (string) $this->settings->get('email', 'sender_name', 'COPOT'),
            ),
            (bool) ($this->credentials->state()['configured'] ?? false),
        );
    }
}
