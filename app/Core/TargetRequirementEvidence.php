<?php

namespace Copot\Core;

final class TargetRequirementEvidence
{
    public const SATISFIED = 'satisfied';
    public const GAP = 'gap';
    public const UNKNOWN = 'unknown';
    public const AMBIGUOUS = 'ambiguous';
    public const CONTRADICTORY = 'contradictory';
    public const UNSAFE = 'unsafe';
    public const UNSUPPORTED = 'unsupported';

    private const STATES = [
        self::SATISFIED, self::GAP, self::UNKNOWN, self::AMBIGUOUS,
        self::CONTRADICTORY, self::UNSAFE, self::UNSUPPORTED,
    ];

    public function __construct(
        private string $requirementKey,
        private string $state,
        private string $evidenceIdentity,
        private string $detail,
        private bool $mandatory = true
    ) {
        if ($requirementKey === '' || trim($requirementKey) !== $requirementKey
            || preg_match('/[\x00-\x1F\x7F]/', $requirementKey) === 1) {
            throw new \InvalidArgumentException('Target requirement evidence key is invalid.');
        }
        if (!in_array($state, self::STATES, true)) {
            throw new \InvalidArgumentException('Target requirement evidence state is unsupported.');
        }
        if ($evidenceIdentity === '' || trim($evidenceIdentity) !== $evidenceIdentity
            || preg_match('/[\x00-\x1F\x7F]/', $evidenceIdentity) === 1) {
            throw new \InvalidArgumentException('Target requirement evidence identity is invalid.');
        }
        if ($detail === '' || trim($detail) !== $detail
            || preg_match('/[\x00-\x1F\x7F]/', $detail) === 1) {
            throw new \InvalidArgumentException('Target requirement evidence detail is invalid.');
        }
    }

    public function requirementKey(): string { return $this->requirementKey; }
    public function state(): string { return $this->state; }
    public function evidenceIdentity(): string { return $this->evidenceIdentity; }
    public function detail(): string { return $this->detail; }
    public function mandatory(): bool { return $this->mandatory; }

    public function toArray(): array
    {
        return [
            'requirement_key' => $this->requirementKey,
            'state' => $this->state,
            'evidence_identity' => $this->evidenceIdentity,
            'detail' => $this->detail,
            'mandatory' => $this->mandatory,
        ];
    }
}
