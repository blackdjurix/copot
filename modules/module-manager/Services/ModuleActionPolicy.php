<?php

final class ModuleActionPolicy
{
    private const ACTIONS = ['install', 'enable', 'disable', 'uninstall'];

    public function evaluate(array $item): array
    {
        $lifecycle = (string) ($item['lifecycle_state'] ?? 'not_installed');
        $discovery = (string) ($item['discovery_state'] ?? 'missing');
        $diagnostics = is_array($item['diagnostics'] ?? null) ? $item['diagnostics'] : [];
        $diagnosticCodes = array_values(array_map(
            static fn (array $diagnostic): string => (string) ($diagnostic['code'] ?? ''),
            $diagnostics
        ));
        $actions = [];
        $denialReasons = [];

        foreach (self::ACTIONS as $action) {
            $actions[$action] = [
                'visible' => true,
                'enabled' => false,
            ];
            $denialReasons[$action] = [];
        }

        if ($lifecycle !== 'not_installed') {
            $actions['install']['visible'] = false;
        }

        if ($lifecycle !== 'installed_disabled') {
            $actions['enable']['visible'] = false;
        }

        if ($lifecycle !== 'installed_enabled') {
            $actions['disable']['visible'] = false;
        }

        if ($lifecycle === 'not_installed') {
            $actions['uninstall']['visible'] = false;
        }

        if ($lifecycle === 'not_installed') {
            if ($discovery === 'valid') {
                $actions['install']['enabled'] = true;
            } else {
                $denialReasons['install'] = $this->discoveryBlockers($discovery, $diagnosticCodes);
            }
        } else {
            $denialReasons['install'] = ['already_installed'];
        }

        if (in_array('invalid_stored_status', $diagnosticCodes, true)) {
            $denialReasons['enable'] = ['invalid_stored_status'];
            $denialReasons['disable'] = ['invalid_stored_status'];
            $denialReasons['uninstall'] = ['invalid_stored_status'];

            return [
                'available_actions' => $actions,
                'denial_reasons' => $denialReasons,
            ];
        }

        if ($lifecycle === 'installed_disabled') {
            $enableBlockers = [];

            if ($discovery !== 'valid') {
                $enableBlockers = array_merge(
                    $enableBlockers,
                    $this->discoveryBlockers($discovery, $diagnosticCodes)
                );
            }

            foreach ([
                'route_file_missing',
                'listener_file_missing',
                'self_dependency',
                'duplicate_dependency',
                'unsupported_version_constraint',
                'dependency_missing',
                'dependency_disabled',
                'dependency_cycle',
            ] as $code) {
                if (in_array($code, $diagnosticCodes, true)) {
                    $enableBlockers[] = $code;
                }
            }

            $enableBlockers = array_values(array_unique($enableBlockers));

            if ($enableBlockers === []) {
                $actions['enable']['enabled'] = true;
            } else {
                $denialReasons['enable'] = $enableBlockers;
            }
        } elseif ($lifecycle === 'installed_enabled') {
            $denialReasons['enable'] = ['already_enabled'];
        } else {
            $denialReasons['enable'] = ['not_installed'];
        }

        if ($lifecycle === 'installed_enabled') {
            $disableBlockers = $this->dependentBlockers($diagnosticCodes);

            if ($disableBlockers === []) {
                $actions['disable']['enabled'] = true;
            } else {
                $denialReasons['disable'] = $disableBlockers;
            }
        } elseif ($lifecycle === 'installed_disabled') {
            $denialReasons['disable'] = ['already_disabled'];
        } else {
            $denialReasons['disable'] = ['not_installed'];
        }

        if ($lifecycle === 'installed_disabled') {
            $uninstallBlockers = $this->dependentBlockers($diagnosticCodes);

            if ($uninstallBlockers === []) {
                $actions['uninstall']['enabled'] = true;
            } else {
                $denialReasons['uninstall'] = $uninstallBlockers;
            }
        } elseif ($lifecycle === 'installed_enabled') {
            $denialReasons['uninstall'] = ['enabled_module'];
        } else {
            $denialReasons['uninstall'] = ['not_installed'];
        }

        return [
            'available_actions' => $actions,
            'denial_reasons' => $denialReasons,
        ];
    }

    private function discoveryBlockers(string $discovery, array $diagnosticCodes): array
    {
        $blockers = match ($discovery) {
            'malformed' => ['malformed_discovery'],
            'invalid_metadata' => ['invalid_metadata'],
            default => ['discovery_missing'],
        };

        foreach ($diagnosticCodes as $code) {
            if (in_array($code, ['route_file_missing', 'listener_file_missing'], true)) {
                continue;
            }

            if (!in_array($code, $blockers, true) && $code !== '') {
                $blockers[] = $code;
            }
        }

        return $blockers;
    }

    private function dependentBlockers(array $diagnosticCodes): array
    {
        $blockers = [];

        foreach (['enabled_dependent', 'dependent_safety_unknown'] as $code) {
            if (in_array($code, $diagnosticCodes, true)) {
                $blockers[] = $code;
            }
        }

        return $blockers;
    }
}
