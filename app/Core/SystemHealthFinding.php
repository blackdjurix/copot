<?php

namespace Copot\Core;

final class SystemHealthFinding
{
    public function __construct(
        private string $identity,
        private string $source,
        private ?string $target,
        private string $code,
        private string $severity,
        private string $summary,
        private ?string $detail = null,
        private ?string $recommendedAction = null,
        private ?string $actionTarget = null,
        private ?string $observedAt = null,
        private ?string $freshness = null
    ) {
        foreach (['identity' => $identity, 'source' => $source, 'code' => $code] as $label => $value) {
            if ($value === '' || trim($value) !== $value || preg_match('/^[a-z0-9][a-z0-9._:-]*$/', $value) !== 1) {
                throw new \InvalidArgumentException("System Health finding {$label} is invalid.");
            }
        }

        SystemHealthFindingSeverity::assertValid($severity);
        $this->summary = SystemHealthSanitizer::summary($summary);
        $this->detail = SystemHealthSanitizer::detail($detail);
        $this->recommendedAction = $recommendedAction === null ? null : SystemHealthSanitizer::summary($recommendedAction);
        $this->target = self::safeTarget($target);
        $this->actionTarget = self::safeActionTarget($actionTarget);
        $this->observedAt = SystemHealthSanitizer::metadata($observedAt);
        $this->freshness = SystemHealthSanitizer::metadata($freshness);
    }

    public function identity(): string { return $this->identity; }
    public function source(): string { return $this->source; }
    public function target(): ?string { return $this->target; }
    public function code(): string { return $this->code; }
    public function severity(): string { return $this->severity; }
    public function summary(): string { return $this->summary; }
    public function detail(): ?string { return $this->detail; }
    public function recommendedAction(): ?string { return $this->recommendedAction; }
    public function actionTarget(): ?string { return $this->actionTarget; }

    public function toArray(): array
    {
        return array_filter([
            'identity' => $this->identity,
            'source' => $this->source,
            'target' => $this->target,
            'code' => $this->code,
            'severity' => $this->severity,
            'summary' => $this->summary,
            'detail' => $this->detail,
            'recommended_action' => $this->recommendedAction,
            'action_target' => $this->actionTarget,
            'observed_at' => $this->observedAt,
            'freshness' => $this->freshness,
        ], static fn ($value): bool => $value !== null);
    }

    private static function safeActionTarget(?string $value): ?string
    {
        if ($value === null || preg_match('/\A(?:\/[A-Za-z0-9._~!$&\'()*+,;=:@%\/?#-]*)\z/', $value) !== 1) {
            return null;
        }

        return substr($value, 0, 300);
    }

    private static function safeTarget(?string $value): ?string
    {
        if ($value === null || preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._:-]{0,119}$/', $value) !== 1) {
            return null;
        }

        return $value;
    }
}
