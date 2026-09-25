<?php

namespace Copot\Core;

/**
 * One immutable, target-owned requirement declaration.
 *
 * This is deliberately a representation only. It does not inspect or
 * evaluate a database, schema, migration ledger, or runtime.
 */
final class PackageTargetRequirement
{
    public const DATABASE = 'database';
    public const SCHEMA = 'schema';
    public const CAPABILITY = 'capability';

    public const PRESENT = 'present';
    public const MINIMUM_VERSION = 'minimum_version';
    public const EXACT_IDENTITY = 'exact_identity';

    public function __construct(
        private string $kind,
        private string $owner,
        private string $identity,
        private string $operator,
        private ?string $value = null,
        private bool $mandatory = true
    ) {
        if (!in_array($kind, [self::DATABASE, self::SCHEMA, self::CAPABILITY], true)) {
            throw new \InvalidArgumentException('Target requirement kind is unsupported.');
        }

        self::assertToken($owner, 'Target requirement owner');
        self::assertToken($identity, 'Target requirement identity');

        if (!in_array($operator, [self::PRESENT, self::MINIMUM_VERSION, self::EXACT_IDENTITY], true)) {
            throw new \InvalidArgumentException('Target requirement operator is unsupported.');
        }

        if ($operator === self::PRESENT && $value !== null) {
            throw new \InvalidArgumentException('Presence requirements cannot declare a value.');
        }

        if ($operator !== self::PRESENT && ($value === null || $value === '' || trim($value) !== $value)) {
            throw new \InvalidArgumentException('Valued target requirements require a non-empty value.');
        }

        if ($operator === self::MINIMUM_VERSION) {
            PackageVersion::assertValid($value ?? '');
        }

        if ($kind === self::DATABASE && $operator !== self::MINIMUM_VERSION) {
            throw new \InvalidArgumentException('Database requirements must declare a minimum version.');
        }

        if ($kind === self::SCHEMA && $operator === self::MINIMUM_VERSION) {
            throw new \InvalidArgumentException('Schema requirements cannot declare a version range.');
        }
    }

    public function kind(): string { return $this->kind; }
    public function owner(): string { return $this->owner; }
    public function identity(): string { return $this->identity; }
    public function operator(): string { return $this->operator; }
    public function value(): ?string { return $this->value; }
    public function mandatory(): bool { return $this->mandatory; }

    public function key(): string
    {
        return implode(':', [$this->kind, $this->owner, $this->identity]);
    }

    public function toArray(): array
    {
        return [
            'kind' => $this->kind,
            'owner' => $this->owner,
            'identity' => $this->identity,
            'operator' => $this->operator,
            'value' => $this->value,
            'mandatory' => $this->mandatory,
        ];
    }

    private static function assertToken(string $value, string $label): void
    {
        if ($value === '' || trim($value) !== $value || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            throw new \InvalidArgumentException($label . ' is invalid.');
        }
    }
}
