<?php

namespace Copot\Core;

interface SmtpTransportDriver
{
    public function deliver(
        EmailMessage $message,
        EmailSenderIdentity $sender,
        EmailTransportConfiguration $transport,
        string $credential
    ): void;
}
