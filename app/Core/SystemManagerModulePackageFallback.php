<?php

namespace Copot\Core;

/**
 * Bounded System Manager delivery for Module packages while Module Manager is
 * unavailable. Module lifecycle planning and application remain owned by the
 * existing ModulePackageOperator.
 */
final class SystemManagerModulePackageFallback
{
    public function __construct(
        private object $app,
        private SystemManagerPackageUpload $uploads
    ) {}

    public function preflight(?array $upload, bool $moduleManagerOperational): ?array
    {
        $inspection = $this->inspect($upload);
        if (!$inspection instanceof ModulePackageInspection) return null;

        $contract = $inspection->contract();
        if ($moduleManagerOperational) {
            return $this->rejected($contract->moduleIdentity()->value());
        }

        return [
            'accepted' => true,
            'status' => 'ready',
            'reason' => '',
            'action' => 'Module lifecycle',
            'package_type' => ModulePackageContract::MODULE_PACKAGE_TYPE,
            'module' => $contract->moduleIdentity()->value(),
            'title' => $contract->title(),
        ];
    }

    public function execute(?array $upload, bool $moduleManagerOperational, string $requestedAction): ?array
    {
        $inspection = $this->inspect($upload);
        if (!$inspection instanceof ModulePackageInspection) return null;

        $contract = $inspection->contract();
        if ($moduleManagerOperational || strcasecmp(trim($requestedAction), 'Module lifecycle') !== 0) {
            return $this->rejected($contract->moduleIdentity()->value());
        }

        require_once $this->app->path('modules/module-manager/Services/ModulePackageOperator.php');
        $operator = new \ModulePackageOperator($this->app);
        $candidate = $operator->registerUpload($upload ?? []);
        $classification = $operator->execute((string) ($candidate['candidate_key'] ?? ''));

        return [
            'accepted' => true,
            'status' => 'completed',
            'reason' => '',
            'action' => 'Module lifecycle',
            'classification' => $classification,
            'package_type' => ModulePackageContract::MODULE_PACKAGE_TYPE,
            'module' => $contract->moduleIdentity()->value(),
            'title' => $contract->title(),
        ];
    }

    private function inspect(?array $upload): ?ModulePackageInspection
    {
        try {
            $path = $this->uploads->sourcePath($upload);
            $inspection = (new ModulePackageIntakeInspector(
                new ZipIntakeService($this->app->path(), null, null, null, null, $this->app->installationIdentity()->value())
            ))->inspect($path);
            $inspection->livePayload()->cleanup();
            return $inspection;
        } catch (\Throwable) {
            return null;
        }
    }

    private function rejected(string $module): array
    {
        return [
            'accepted' => false,
            'status' => 'rejected',
            'reason' => 'Module packages must be handled through Module Manager.',
            'action' => null,
            'package_type' => ModulePackageContract::MODULE_PACKAGE_TYPE,
            'module' => $module,
        ];
    }
}
