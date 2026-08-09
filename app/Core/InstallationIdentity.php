<?php

namespace Copot\Core;

final class InstallationIdentity
{
    public function __construct(private string $value)
    {
        if (preg_match('/\Ainst_[a-f0-9]{32}\z/', $value) !== 1) {
            throw new \InvalidArgumentException('Installation identity is invalid.');
        }
    }

    public static function generate(): self
    {
        return new self('inst_' . bin2hex(random_bytes(16)));
    }

    public function value(): string
    {
        return $this->value;
    }
}
