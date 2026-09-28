<?php

namespace Copot\Core;

final class ReauthenticationResult
{
    private function __construct(private string $outcome)
    {
    }

    public static function success(): self { return new self('success'); }
    public static function unauthenticated(): self { return new self('unauthenticated'); }
    public static function invalidPassword(): self { return new self('invalid_password'); }
    public function succeeded(): bool { return $this->outcome === 'success'; }
    public function outcome(): string { return $this->outcome; }
}
