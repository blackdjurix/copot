<?php

namespace Copot\Core\BackupRecovery;

final class DatabaseVerificationResult
{
    /** @param array<string, string> $identities */
    public function __construct(private bool $valid, private array $identities = [], private ?string $failure = null)
    {
    }

    public function isValid(): bool { return $this->valid; }
    /** @return array<string, string> */
    public function identities(): array { return $this->identities; }
    public function failure(): ?string { return $this->failure; }

    /** @param array<string, string> $identities */
    public static function valid(array $identities): self { return new self(true, $identities); }
    public static function failed(string $failure): self { return new self(false, [], $failure); }
}
