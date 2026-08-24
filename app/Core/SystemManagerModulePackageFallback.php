<?php

namespace Copot\Core;

/**
 * Bounded System Manager delivery for Module packages while the broader
 * Modules UX re-homing remains incomplete. Orchestration is Core-owned.
 */
final class SystemManagerModulePackageFallback
{
    public function __construct(private object $app)
    {
    }

    public function preflight(?array $upload, bool $moduleManagerOperational): ?array
    {
        if ($moduleManagerOperational) return $this->rejected('');
        $result = (new ModulePackageOperator($this->app))->preflightUpload($upload ?? []);
        $result['action'] = 'Module lifecycle';
        $result['package_type'] = ModulePackageContract::MODULE_PACKAGE_TYPE;
        if (($result['classification'] ?? null) === ModuleTransitionPlan::INSTALL) {
            $result['guidance'] = 'Module package preflight passed. Open the Modules area after completion to review the installed Module and available actions.';
        }
        return $result;
    }

    public function execute(?array $upload, bool $moduleManagerOperational, string $requestedAction): ?array
    {
        if ($moduleManagerOperational || strcasecmp(trim($requestedAction), 'Module lifecycle') !== 0) return $this->rejected('');
        $classification = (new ModulePackageOperator($this->app))->executeUpload($upload ?? []);
        return [
            'accepted' => true,
            'status' => 'completed',
            'reason' => '',
            'action' => 'Module lifecycle',
            'classification' => $classification,
            'package_type' => ModulePackageContract::MODULE_PACKAGE_TYPE,
            'module' => '',
            'title' => '',
        ];
    }

    private function rejected(string $module): array
    {
        return [
            'accepted' => false,
            'status' => 'rejected',
            'reason' => 'Module lifecycle is handled through the canonical System Manager Modules area.',
            'action' => null,
            'package_type' => ModulePackageContract::MODULE_PACKAGE_TYPE,
            'module' => $module,
        ];
    }
}
