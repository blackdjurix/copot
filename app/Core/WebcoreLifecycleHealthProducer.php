<?php

namespace Copot\Core;

final class WebcoreLifecycleHealthProducer implements SystemHealthProducer
{
    public const SOURCE = 'webcore.lifecycle';
    private $visibilityPolicy;

    public function __construct(
        private WebcoreLifecycleHealthEvidenceSource $evidenceSource,
        ?callable $visibilityPolicy = null
    ) {
        $this->visibilityPolicy = $visibilityPolicy;
    }

    public function source(): string
    {
        return self::SOURCE;
    }

    public function required(): bool
    {
        return true;
    }

    public function report(SystemHealthContext $context): SystemHealthProducerResult
    {
        $evidence = $this->evidenceSource->collect($context);
        if (!$evidence instanceof WebcoreLifecycleHealthEvidence) {
            throw new \RuntimeException('Webcore lifecycle health evidence is invalid.');
        }

        $findings = [];
        $installed = $evidence->installedState();
        if (!$evidence->complete() || $installed === null) {
            $findings[] = $this->finding('evidence-unavailable', SystemHealthFindingSeverity::ERROR, 'Required Webcore lifecycle evidence is unavailable.');
        } elseif ($installed->status() === InstalledStateStatus::LEGACY) {
            $findings[] = $this->finding('legacy-state', SystemHealthFindingSeverity::WARNING, 'The Webcore installation has legacy lifecycle state.', $installed->reason(), 'Complete the approved lifecycle adoption path.');
        } elseif ($installed->status() === InstalledStateStatus::INCONSISTENT) {
            $findings[] = $this->finding('inconsistent-state', SystemHealthFindingSeverity::ERROR, 'The Webcore installed state is inconsistent.', $installed->reason(), 'Review the lifecycle state before mutation.');
        } elseif ($installed->status() === InstalledStateStatus::INVALID) {
            $findings[] = $this->finding('invalid-state', SystemHealthFindingSeverity::ERROR, 'The Webcore installed state is invalid.', $installed->reason(), 'Repair the installation through the approved lifecycle boundary.');
        }

        if ($installed?->status() === InstalledStateStatus::COMMITTED && $evidence->committedState() === null) {
            $findings[] = $this->finding('committed-state-unavailable', SystemHealthFindingSeverity::ERROR, 'Committed Webcore lifecycle state is unavailable.', null, 'Restore or verify committed lifecycle state before mutation.');
        }

        foreach ([
            'database' => $evidence->databaseHealth(),
            'migration' => $evidence->migrationHealth(),
            'runtime' => $evidence->runtimeHealth(),
            'integrity' => $evidence->integrityHealth(),
        ] as $kind => $matrix) {
            if ($matrix instanceof HealthGateMatrix) {
                foreach ($matrix->gates() as $gate) {
                    if (!$gate->passed()) {
                        $findings[] = $this->gateFinding($kind, $gate);
                    }
                }
            }
        }

        $operation = $evidence->operation();
        if ($operation instanceof LifecycleOperationRecord && !$operation->isTerminal()) {
            $phase = $operation->phase();
            $severity = match ($phase) {
                LifecycleOperationRecord::INDETERMINATE => SystemHealthFindingSeverity::CRITICAL,
                LifecycleOperationRecord::BLOCKED, LifecycleOperationRecord::CLEANUP_PENDING => SystemHealthFindingSeverity::ERROR,
                default => SystemHealthFindingSeverity::WARNING,
            };
            $findings[] = $this->finding('active-operation-' . $this->slug($phase), $severity, 'A Webcore lifecycle operation is not complete.', null, 'Complete or safely recover the lifecycle operation.');
        }

        $availability = $evidence->complete() && $installed instanceof InstalledStateInspection
            ? SystemHealthProducerAvailability::READY
            : SystemHealthProducerAvailability::UNAVAILABLE;

        return new SystemHealthProducerResult(
            self::SOURCE,
            $availability,
            $findings,
            true,
            $evidence->observedAt(),
            $evidence->freshness(),
            $this->visibilityPolicy
        );
    }

    private function gateFinding(string $kind, HealthGateResult $gate): SystemHealthFinding
    {
        $name = $this->slug($gate->name());
        $severity = str_contains(strtolower($gate->name()), 'indeterminate')
            ? SystemHealthFindingSeverity::CRITICAL
            : SystemHealthFindingSeverity::ERROR;

        return $this->finding("{$kind}-gate-{$name}", $severity, 'Authoritative Webcore lifecycle evidence failed.', $gate->reason());
    }

    private function finding(string $identity, string $severity, string $summary, ?string $detail = null, ?string $action = null): SystemHealthFinding
    {
        return new SystemHealthFinding(self::SOURCE . ':' . $identity, self::SOURCE, null, 'lifecycle.' . $this->slug($identity), $severity, $summary, $detail, $action);
    }

    private function slug(string $value): string
    {
        $slug = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $value), '-'));

        return substr($slug === '' ? 'evidence' : $slug, 0, 72);
    }
}
