<?php

namespace Copot\Core;

final class EmailSenderIdentity
{
    public function __construct(
        private string $email,
        private string $name,
    ) {
    }

    public function email(): string
    {
        return $this->email;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function isComplete(): bool
    {
        return $this->email !== '' && $this->name !== '';
    }
}
