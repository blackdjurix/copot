<?php

use Copot\Core\SystemHealthContext;
use Copot\Core\SystemHealthFinding;
use Copot\Core\SystemHealthFindingSeverity;
use Copot\Core\SystemHealthProducer;
use Copot\Core\SystemHealthProducerAvailability;
use Copot\Core\SystemHealthProducerResult;
use Copot\Core\User;

/**
 * Adapts existing Module diagnostics into the Core System Health pipeline.
 * This class owns no health state and does not alter Module authority.
 */
final class SiteSettingsModuleHealthProducer implements SystemHealthProducer
{
    public const SOURCE = 'webcore.modules';

    /** @var callable|null */
    private $inventoryResolver;

    public function __construct(private object $app, ?callable $inventoryResolver = null)
    {
        $this->inventoryResolver = $inventoryResolver;
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
        $visibility = static fn (mixed $viewer): bool => $viewer instanceof User && $viewer->can('modules.manage');

        try {
            $items = $this->inventoryResolver !== null
                ? ($this->inventoryResolver)($context)
                : (new ModuleInventoryBuilder(
                    new Copot\Core\ModuleDiscovery($this->app->path('modules')),
                    new Copot\Core\ModuleRepository($this->app->database())
                ))->build();

            if (!is_array($items)) {
                throw new RuntimeException('Module diagnostic evidence is invalid.');
            }
        } catch (Throwable) {
            return new SystemHealthProducerResult(
                self::SOURCE,
                SystemHealthProducerAvailability::UNAVAILABLE,
                [],
                true,
                null,
                null,
                $visibility
            );
        }

        $findings = [];
        $seen = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $name = (string) ($item['name'] ?? '');
            if (preg_match('/^[a-z0-9][a-z0-9_-]*$/', $name) !== 1) {
                continue;
            }

            $diagnostics = is_array($item['diagnostics'] ?? null) ? $item['diagnostics'] : [];
            foreach ($diagnostics as $diagnostic) {
                if (!is_array($diagnostic)) {
                    continue;
                }

                $code = strtolower((string) ($diagnostic['code'] ?? ''));
                $severity = strtolower((string) ($diagnostic['severity'] ?? ''));
                if ($code === '' || !in_array($severity, [
                    SystemHealthFindingSeverity::WARNING,
                    SystemHealthFindingSeverity::ERROR,
                    SystemHealthFindingSeverity::CRITICAL,
                ], true)) {
                    continue;
                }

                $dedupeKey = $name . '|' . $code;
                if (isset($seen[$dedupeKey])) {
                    continue;
                }
                $seen[$dedupeKey] = true;

                $safeCode = $this->slug($code);
                $findings[] = new SystemHealthFinding(
                    self::SOURCE . ':' . $name . ':' . $safeCode,
                    self::SOURCE,
                    $name,
                    'module.' . $safeCode,
                    $severity,
                    'A Module ' . $this->category($code) . ' condition requires review.'
                );
            }
        }

        return new SystemHealthProducerResult(
            self::SOURCE,
            SystemHealthProducerAvailability::READY,
            $findings,
            true,
            null,
            null,
            $visibility
        );
    }

    private function category(string $code): string
    {
        return match (true) {
            str_contains($code, 'dependency') || str_contains($code, 'dependent') => 'dependency',
            str_contains($code, 'package') || str_contains($code, 'integrity') => 'package integrity',
            str_contains($code, 'discovery') || str_contains($code, 'route_') || str_contains($code, 'listener_') => 'discovery',
            str_contains($code, 'metadata') || str_contains($code, 'stored_') => 'metadata',
            default => 'diagnostic',
        };
    }

    private function slug(string $value): string
    {
        $slug = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $value), '-'));

        return substr($slug === '' ? 'diagnostic' : $slug, 0, 72);
    }
}
