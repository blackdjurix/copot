<?php

namespace Copot\Core\BackupRecovery;

final class RecoveryStateVerificationResult
{
    /** @param array<string, string> $identities @param array<string, string> $failures */
    public function __construct(private bool $passed, private array $identities = [], private array $failures = []) {}
    public function passed(): bool { return $this->passed; }
    /** @return array<string, string> */
    public function identities(): array { return $this->identities; }
    /** @return array<string, string> */
    public function failures(): array { return $this->failures; }
    /** @param array<string, string> $identities */
    public static function success(array $identities): self { return new self(true, $identities); }
    /** @param array<string, string> $failures */
    public static function failure(array $failures): self { return new self(false, [], $failures); }
}
