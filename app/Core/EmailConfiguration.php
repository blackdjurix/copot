<?php

namespace Copot\Core;

final class EmailConfiguration
{
    public function __construct(
        private EmailTransportConfiguration $transport,
        private EmailSenderIdentity $sender,
        private bool $credentialConfigured,
    ) {
    }

    public function transport(): EmailTransportConfiguration
    {
        return $this->transport;
    }

    public function sender(): EmailSenderIdentity
    {
        return $this->sender;
    }

    public function credentialConfigured(): bool
    {
        return $this->credentialConfigured;
    }

    public function state(): string
    {
        return $this->isConfigured() ? 'configured' : 'incomplete';
    }

    public function isConfigured(): bool
    {
        return $this->transport->isComplete()
            && $this->sender->isComplete()
            && $this->credentialConfigured;
    }

    /** @return array{state: string, transport: array{host: string, port: int, security: string, username: ?string}, sender: array{email: string, name: string}, credential: array{configured: bool}} */
    public function toReadModel(): array
    {
        return [
            'state' => $this->state(),
            'transport' => [
                'host' => $this->transport->host(),
                'port' => $this->transport->port(),
                'security' => $this->transport->security(),
                'username' => $this->transport->username(),
            ],
            'sender' => [
                'email' => $this->sender->email(),
                'name' => $this->sender->name(),
            ],
            'credential' => [
                'configured' => $this->credentialConfigured,
            ],
        ];
    }
}
