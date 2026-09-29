<?php

namespace Copot\Core;

final class EmailTransport
{
    public function __construct(
        private EmailConfigurationResolver $configuration,
        private EmailCredentialBoundary $credentials,
        private SmtpTransportDriver $driver = new NativeSmtpTransportDriver(),
    ) {
    }

    public function deliver(EmailMessage $message): EmailDeliveryResult
    {
        try {
            $configuration = $this->configuration->resolve();
        } catch (\Throwable) {
            return EmailDeliveryResult::failed('configuration_incomplete');
        }

        if (!$configuration->transport()->isComplete() || !$configuration->sender()->isComplete()) {
            return EmailDeliveryResult::failed('configuration_incomplete');
        }

        if (!$configuration->credentialConfigured()) {
            return EmailDeliveryResult::failed('credential_unavailable');
        }

        $credential = $this->credentials->readForTransport();
        if ($credential === null) {
            return EmailDeliveryResult::failed('credential_unavailable');
        }

        try {
            $this->driver->deliver($message, $configuration->sender(), $configuration->transport(), $credential);

            return EmailDeliveryResult::succeeded();
        } catch (EmailMessageException) {
            return EmailDeliveryResult::failed('message_invalid');
        } catch (\Throwable) {
            return EmailDeliveryResult::failed('transport_failed');
        }
    }
}
