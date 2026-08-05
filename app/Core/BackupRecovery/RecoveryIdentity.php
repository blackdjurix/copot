<?php

namespace Copot\Core\BackupRecovery;

final class RecoveryIdentity
{
    public function __construct(private string $value)
    {
        self::assertOpaque($value, 'Recovery identity');
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    private static function assertOpaque(string $value, string $label): void
    {
        if ($value === '' || trim($value) !== $value || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            throw new RecoveryInvariantException($label . ' is invalid.');
        }
    }
}
