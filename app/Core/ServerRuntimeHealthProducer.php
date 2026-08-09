<?php

namespace Copot\Core;

final class ServerRuntimeHealthProducer implements SystemHealthProducer
{
    public const SOURCE = 'webcore.server-runtime';
    private $visibilityPolicy;

    public function __construct(
        private ServerRuntimeHealthEvidenceSource $evidenceSource,
        ?callable $visibilityPolicy = null
    ) {
        $this->visibilityPolicy = $visibilityPolicy;
    }

    public function source(): string { return self::SOURCE; }
    public function required(): bool { return true; }

    public function report(SystemHealthContext $context): SystemHealthProducerResult
    {
        $evidence = $this->evidenceSource->collect($context);
        if (!$evidence instanceof ServerRuntimeHealthEvidence) {
            throw new \RuntimeException('Server/runtime health evidence is invalid.');
        }

        $findings = [];
        $requirements = $evidence->requirements();
        $complete = $evidence->complete() && $requirements !== [];
        foreach ($requirements as $requirement) {
            if (!is_array($requirement)
                || !is_string($requirement['name'] ?? null)
                || preg_match('/^[a-z0-9][a-z0-9._:-]*$/', $requirement['name']) !== 1
                || !is_string($requirement['label'] ?? null)
                || !is_bool($requirement['passed'] ?? null)
            ) {
                throw new \RuntimeException('Server/runtime requirement evidence is invalid.');
            }
            if (!$requirement['passed']) {
                $name = $requirement['name'];
                $findings[] = new SystemHealthFinding(
                    self::SOURCE . ':' . $name,
                    self::SOURCE,
                    $name,
                    'runtime.' . $name,
                    SystemHealthFindingSeverity::ERROR,
                    'A required Copot runtime capability is unavailable.',
                    $requirement['label']
                );
            }
        }

        if (!$complete) {
            $findings[] = new SystemHealthFinding(
                self::SOURCE . ':evidence-unavailable',
                self::SOURCE,
                null,
                'runtime.evidence-unavailable',
                SystemHealthFindingSeverity::ERROR,
                'Required Copot runtime evidence is unavailable.'
            );
        }

        return new SystemHealthProducerResult(
            self::SOURCE,
            $complete ? SystemHealthProducerAvailability::READY : SystemHealthProducerAvailability::UNAVAILABLE,
            $findings,
            true,
            $evidence->observedAt(),
            $evidence->freshness(),
            $this->visibilityPolicy
        );
    }
}
