<?php

namespace Copot\Core;

final class SystemHealthProducerResult
{
    private $visibilityPolicy;

    public function __construct(
        private string $source,
        private string $availability,
        private array $findings = [],
        private bool $required = false,
        private ?string $observedAt = null,
        private ?string $freshness = null,
        ?callable $visibilityPolicy = null
    ) {
        if ($source === '' || preg_match('/^[a-z0-9][a-z0-9._:-]*$/', $source) !== 1) {
            throw new \InvalidArgumentException('System Health producer source is invalid.');
        }

        SystemHealthProducerAvailability::assertValid($availability);
        foreach ($findings as $finding) {
            if (!$finding instanceof SystemHealthFinding || $finding->source() !== $source) {
                throw new \InvalidArgumentException('System Health producer findings are invalid.');
            }
        }

        $this->observedAt = SystemHealthSanitizer::metadata($observedAt);
        $this->freshness = SystemHealthSanitizer::metadata($freshness);
        $this->visibilityPolicy = $visibilityPolicy;
    }

    public static function producerError(string $source, bool $required = false): self
    {
        return new self($source, SystemHealthProducerAvailability::PRODUCER_ERROR, [], $required);
    }

    public function source(): string { return $this->source; }
    public function availability(): string { return $this->availability; }
    public function findings(): array { return $this->findings; }
    public function required(): bool { return $this->required; }
    public function observedAt(): ?string { return $this->observedAt; }
    public function freshness(): ?string { return $this->freshness; }

    public function visibleTo(mixed $viewer): bool
    {
        return $this->visibilityPolicy === null || (bool) ($this->visibilityPolicy)($viewer);
    }

    public function toArray(): array
    {
        return array_filter([
            'source' => $this->source,
            'availability' => $this->availability,
            'observed_at' => $this->observedAt,
            'freshness' => $this->freshness,
            'findings' => array_map(static fn (SystemHealthFinding $finding): array => $finding->toArray(), $this->findings),
        ], static fn ($value): bool => $value !== null);
    }

}
