<?php

namespace Copot\Core;

final class MigrationCompatibilityResult
{
    public const COMPATIBLE = 'compatible';
    public const UNSUPPORTED = 'unsupported_state';
    public const DOWNGRADE = 'downgrade_unsupported';

    private function __construct(private string $code, private string $reason) {}
    public static function compatible(): self { return new self(self::COMPATIBLE, ''); }
    public static function unsupported(string $reason): self { return new self(self::UNSUPPORTED, self::sanitize($reason)); }
    public static function downgrade(): self { return new self(self::DOWNGRADE, 'Downgrade and reverse migration are unsupported.'); }
    public function code(): string { return $this->code; }
    public function reason(): string { return $this->reason; }
    private static function sanitize(string $reason): string { $reason = preg_replace('/\s+/', ' ', trim($reason)) ?? ''; return strlen($reason) > 240 ? substr($reason, 0, 240) : $reason; }
}
