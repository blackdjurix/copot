<?php

namespace Copot\Core;

final class EmailMessage
{
    public function __construct(
        private string $recipient,
        private string $subject,
        private string $body,
    ) {
        if (
            filter_var($recipient, FILTER_VALIDATE_EMAIL) === false
            || strlen($recipient) > 254
            || preg_match('/[\x00\r\n]/', $recipient)
        ) {
            throw new EmailMessageException('Email recipient is invalid.');
        }

        if ($subject === '' || strlen($subject) > 998 || preg_match('/[\x00\r\n]/', $subject)) {
            throw new EmailMessageException('Email subject is invalid.');
        }

        if (strlen($body) > 1048576 || preg_match('/\x00/', $body)) {
            throw new EmailMessageException('Email body is invalid.');
        }
    }

    public function recipient(): string
    {
        return $this->recipient;
    }

    public function subject(): string
    {
        return $this->subject;
    }

    public function body(): string
    {
        return $this->body;
    }
}
