<?php

namespace Copot\Core;

final class LegacyBoundaryEvidence
{
    public const NONE = 'none';
    public const EXACT_MATCH_PROVEN = 'exact_match_proven';
    public const GENUINE_LEGACY = 'genuine_legacy';
    public const UNCOMMITTED = 'uncommitted';
    public const PARTIAL_COMMIT = 'partial_commit';
    public const UNPROVABLE_PROVENANCE = 'unprovable_provenance';
    public const FILESYSTEM_DRIFT = 'filesystem_drift';
    public const BROADER_CONVERGENCE = 'broader_convergence';

    private const STATES = [
        self::NONE, self::EXACT_MATCH_PROVEN, self::GENUINE_LEGACY,
        self::UNCOMMITTED, self::PARTIAL_COMMIT, self::UNPROVABLE_PROVENANCE,
        self::FILESYSTEM_DRIFT, self::BROADER_CONVERGENCE,
    ];

    public function __construct(private string $state, private string $evidenceIdentity, private string $detail)
    {
        if (!in_array($state, self::STATES, true)) {
            throw new \InvalidArgumentException('Legacy boundary evidence state is unsupported.');
        }
        foreach ([$evidenceIdentity, $detail] as $value) {
            if ($value === '' || trim($value) !== $value || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
                throw new \InvalidArgumentException('Legacy boundary evidence is invalid.');
            }
        }
    }

    public static function none(): self { return new self(self::NONE, 'none', 'No genuine Legacy Reconciliation evidence.'); }
    public static function exactMatch(string $identity, string $detail = 'Historical exact-match proof gates passed.'): self { return new self(self::EXACT_MATCH_PROVEN, $identity, $detail); }
    public static function legacy(string $state, string $identity, string $detail): self { return new self($state, $identity, $detail); }
    public static function fromLegacyClassification(LegacyClassificationResult $classification): self
    {
        $identity = 'legacy-classification:' . $classification->classification();
        if ($classification->classification() === LegacyClassification::UNKNOWN_OR_UNPROVABLE) {
            return self::legacy(self::UNPROVABLE_PROVENANCE, $identity, $classification->reason() !== '' ? $classification->reason() : 'Existing legacy authority could not prove runtime provenance.');
        }

        return new self(self::NONE, $identity, $classification->reason() !== '' ? $classification->reason() : 'Existing legacy authority produced recognized evidence.');
    }

    public function state(): string { return $this->state; }
    public function evidenceIdentity(): string { return $this->evidenceIdentity; }
    public function detail(): string { return $this->detail; }
    public function requiresLegacy(): bool { return !in_array($this->state, [self::NONE, self::EXACT_MATCH_PROVEN], true); }
    public function toArray(): array { return ['state' => $this->state, 'evidence_identity' => $this->evidenceIdentity, 'detail' => $this->detail]; }
}
