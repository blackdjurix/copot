<?php

namespace Copot\Core;

final class BundledCoreModuleHealthProducer implements SystemHealthProducer
{
    public const SOURCE = 'webcore.bundled-modules';
    private $visibilityPolicy;

    public function __construct(
        private BundledCoreModuleHealthEvidenceSource $evidenceSource,
        ?callable $visibilityPolicy = null
    ) {
        $this->visibilityPolicy = $visibilityPolicy;
    }

    public function source(): string { return self::SOURCE; }
    public function required(): bool { return true; }

    public function report(SystemHealthContext $context): SystemHealthProducerResult
    {
        $evidence = $this->evidenceSource->collect($context);
        if (!is_array($evidence)) throw new \RuntimeException('Bundled Core Module health evidence is invalid.');

        $findings = [];
        $complete = $evidence !== [];
        $observedAt = null;
        $freshness = null;
        foreach ($evidence as $moduleEvidence) {
            if (!$moduleEvidence instanceof BundledCoreModuleHealthEvidence) {
                throw new \RuntimeException('Bundled Core Module health evidence is invalid.');
            }
            $module = $moduleEvidence->module()->value();
            $observedAt ??= $moduleEvidence->observedAt();
            $freshness ??= $moduleEvidence->freshness();
            $complete = $complete && $moduleEvidence->complete() && $moduleEvidence->lifecycle() !== null;
            $findings = array_merge($findings, $this->lifecycleFindings($module, $moduleEvidence->lifecycle()));
            foreach ([
                'schema' => $moduleEvidence->schemaHealth(),
                'migration' => $moduleEvidence->migrationHealth(),
                'integrity' => $moduleEvidence->integrityHealth(),
            ] as $kind => $matrix) {
                if ($matrix instanceof HealthGateMatrix) {
                    foreach ($matrix->gates() as $gate) {
                        if (!$gate->passed()) $findings[] = $this->gateFinding($module, $kind, $gate);
                    }
                }
            }
        }

        if (!$complete) {
            $findings[] = $this->finding('evidence-unavailable', null, 'Required bundled Core Module lifecycle evidence is unavailable.', SystemHealthFindingSeverity::ERROR);
        }

        return new SystemHealthProducerResult(
            self::SOURCE,
            $complete ? SystemHealthProducerAvailability::READY : SystemHealthProducerAvailability::UNAVAILABLE,
            $findings,
            true,
            $observedAt,
            $freshness,
            $this->visibilityPolicy
        );
    }

    private function lifecycleFindings(string $module, ?ModuleLifecycleInspection $inspection): array
    {
        if ($inspection === null) return [];
        return match ($inspection->status()) {
            InstalledStateStatus::LEGACY => [$this->finding('lifecycle.legacy', $module, 'The bundled Core Module has legacy lifecycle state.', SystemHealthFindingSeverity::WARNING, $inspection->reason())],
            InstalledStateStatus::INCONSISTENT => [$this->finding('lifecycle.inconsistent', $module, 'The bundled Core Module lifecycle state is inconsistent.', SystemHealthFindingSeverity::ERROR, $inspection->reason())],
            InstalledStateStatus::INVALID => [$this->finding('lifecycle.invalid', $module, 'The bundled Core Module lifecycle state is invalid.', SystemHealthFindingSeverity::ERROR, $inspection->reason())],
            InstalledStateStatus::COMMITTED => $inspection->state() === null ? [$this->finding('lifecycle.committed-state-unavailable', $module, 'Committed bundled Core Module lifecycle state is unavailable.', SystemHealthFindingSeverity::ERROR)] : [],
            default => [],
        };
    }

    private function gateFinding(string $module, string $kind, HealthGateResult $gate): SystemHealthFinding
    {
        $slug = $this->slug($gate->name());
        $severity = str_contains(strtolower($gate->name()), 'indeterminate') ? SystemHealthFindingSeverity::CRITICAL : SystemHealthFindingSeverity::ERROR;
        return $this->finding("{$kind}.{$slug}", $module, 'Authoritative bundled Core Module evidence failed.', $severity, $gate->reason());
    }

    private function finding(string $code, ?string $module, string $summary, string $severity, ?string $detail = null): SystemHealthFinding
    {
        $target = $module;
        $identity = self::SOURCE . ':' . ($module === null ? $code : $module . ':' . $code);
        return new SystemHealthFinding($identity, self::SOURCE, $target, 'module.' . $this->slug($code), $severity, $summary, $detail);
    }

    private function slug(string $value): string
    {
        $slug = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $value), '-'));
        return substr($slug === '' ? 'evidence' : $slug, 0, 72);
    }
}
