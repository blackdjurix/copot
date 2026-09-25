<?php

namespace Copot\Core;

/** @immutable */
final class PackageTargetRequirements
{
    /** @var array<string, PackageTargetRequirement> */
    private array $requirements;

    /** @param list<PackageTargetRequirement> $requirements */
    public function __construct(array $requirements = [])
    {
        $this->requirements = [];
        foreach ($requirements as $requirement) {
            if (!$requirement instanceof PackageTargetRequirement) {
                throw new \InvalidArgumentException('Target requirements must contain requirement values.');
            }

            if (isset($this->requirements[$requirement->key()])) {
                throw new \InvalidArgumentException('Target requirements contain a duplicate requirement.');
            }

            $this->requirements[$requirement->key()] = $requirement;
        }

        ksort($this->requirements, SORT_STRING);
    }

    /** @return list<PackageTargetRequirement> */
    public function requirements(): array
    {
        return array_values($this->requirements);
    }

    public function isEmpty(): bool
    {
        return $this->requirements === [];
    }

    /** Stable identity for the declaration, not an evaluation result. */
    public function identity(): string
    {
        return hash('sha256', json_encode($this->toArray(), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    public function toArray(): array
    {
        return array_map(
            static fn (PackageTargetRequirement $requirement): array => $requirement->toArray(),
            $this->requirements()
        );
    }

    /** @param mixed $value */
    public static function fromArray(mixed $value): self
    {
        if (!is_array($value) || array_keys($value) !== array_keys(array_values($value))) {
            throw new \InvalidArgumentException('Target requirements must be an ordered list.');
        }

        $requirements = [];
        foreach ($value as $entry) {
            if (!is_array($entry) || array_keys($entry) !== ['kind', 'owner', 'identity', 'operator', 'value', 'mandatory']) {
                throw new \InvalidArgumentException('Target requirement entry is invalid.');
            }

            if (!is_string($entry['kind']) || !is_string($entry['owner']) || !is_string($entry['identity'])
                || !is_string($entry['operator']) || ($entry['value'] !== null && !is_string($entry['value']))
                || !is_bool($entry['mandatory'])) {
                throw new \InvalidArgumentException('Target requirement entry types are invalid.');
            }

            $requirements[] = new PackageTargetRequirement(
                $entry['kind'],
                $entry['owner'],
                $entry['identity'],
                $entry['operator'],
                $entry['value'],
                $entry['mandatory']
            );
        }

        return new self($requirements);
    }
}
