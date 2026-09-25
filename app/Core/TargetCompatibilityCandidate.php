<?php

namespace Copot\Core;

/**
 * An immutable, already-inspected candidate evidence snapshot.
 *
 * WU2 consumes authoritative evidence supplied by the owning inspector. It
 * deliberately performs no database, filesystem, lifecycle, or SQL work.
 */
final class TargetCompatibilityCandidate
{
    public const COHERENT = 'coherent';
    public const UNKNOWN = 'unknown';
    public const AMBIGUOUS = 'ambiguous';
    public const CONTRADICTORY = 'contradictory';
    public const UNSAFE = 'unsafe';
    public const UNSUPPORTED = 'unsupported';

    /** @var array<string, TargetRequirementEvidence> */
    private array $requirementEvidence;
    /** @var array<string, TargetCompatibleExtraState> */
    private array $extraState;

    /** @param list<TargetRequirementEvidence> $requirementEvidence
     *  @param list<TargetCompatibleExtraState> $extraState */
    public function __construct(
        array $requirementEvidence,
        array $extraState = [],
        private string $installationIdentity = self::COHERENT,
        private string $namespace = self::COHERENT,
        private string $ownership = self::COHERENT,
        private string $health = self::COHERENT,
        private string $lifecycle = self::COHERENT,
        private string $recovery = self::COHERENT
    ) {
        foreach ([$installationIdentity, $namespace, $ownership, $health, $lifecycle, $recovery] as $state) {
            if (!in_array($state, [self::COHERENT, self::UNKNOWN, self::AMBIGUOUS, self::CONTRADICTORY, self::UNSAFE, self::UNSUPPORTED], true)) {
                throw new \InvalidArgumentException('Candidate coherence state is unsupported.');
            }
        }

        $this->requirementEvidence = [];
        foreach ($requirementEvidence as $evidence) {
            if (!$evidence instanceof TargetRequirementEvidence || isset($this->requirementEvidence[$evidence->requirementKey()])) {
                throw new \InvalidArgumentException('Candidate requirement evidence is invalid or duplicated.');
            }
            $this->requirementEvidence[$evidence->requirementKey()] = $evidence;
        }
        ksort($this->requirementEvidence, SORT_STRING);

        $this->extraState = [];
        foreach ($extraState as $state) {
            if (!$state instanceof TargetCompatibleExtraState || isset($this->extraState[$state->identity()])) {
                throw new \InvalidArgumentException('Candidate extra state is invalid or duplicated.');
            }
            $this->extraState[$state->identity()] = $state;
        }
        ksort($this->extraState, SORT_STRING);
    }

    /** @return list<TargetRequirementEvidence> */
    public function requirementEvidence(): array { return array_values($this->requirementEvidence); }
    /** @return list<TargetCompatibleExtraState> */
    public function extraState(): array { return array_values($this->extraState); }
    /** @return array<string,string> */
    public function coherence(): array
    {
        return [
            'installation_identity' => $this->installationIdentity,
            'namespace' => $this->namespace,
            'ownership' => $this->ownership,
            'health' => $this->health,
            'lifecycle' => $this->lifecycle,
            'recovery' => $this->recovery,
        ];
    }
}
