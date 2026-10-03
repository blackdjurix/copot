<?php

namespace Copot\Core;

final class CurrentUserPasswordChangeResult
{
    private function __construct(
        private string $status,
        private string $code,
        private array $errors = [],
    ) {
    }

    public static function success(): self
    {
        return new self('success', 'password_changed');
    }

    public static function failure(string $code, array $errors = []): self
    {
        return new self('failure', $code, $errors);
    }

    public function status(): string
    {
        return $this->status;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function succeeded(): bool
    {
        return $this->status === 'success';
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
