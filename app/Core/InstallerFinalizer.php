<?php

namespace Copot\Core;

class InstallerFinalizer
{
    private const REQUIRED_SETTINGS = [
        ['site', 'name'],
        ['site', 'tagline'],
        ['localization', 'timezone'],
        ['localization', 'locale'],
    ];
    private const BASELINE_MODULES = InstallerModuleSelection::MANDATORY_MODULES;

    public function __construct(
        private Database $database,
        private InstallerSchemaState $schema,
        private SettingsService $settings,
        private SettingsRepository $settingsRepository,
        private ThemeDiscovery $themeDiscovery,
        private ThemeManager $themes,
        private ModuleManager $modules,
        private InstallationState $installationState,
        private CommittedLifecycleStateStore $committedLifecycleState,
        private InstallationMutex $mutex
    ) {
    }

    public function finalize(): array
    {
        $lock = $this->mutex->acquire();

        if (!$lock instanceof InstallationLock) {
            throw new InstallationException('Another installation process is already running.');
        }

        try {
            if ($this->installationState->isInstalled()) {
                throw new InstallationException('Installation has already been finalized.');
            }

            if (!$this->schema->isReady()) {
                throw new InstallationException('Database schema is not ready.');
            }

            $this->validateFirstAdministrator();
            $this->validateInitialSettings();
            $this->activateDefaultTheme();
            $this->enableBaselineModules();
            $this->committedLifecycleState->commit(
                $this->installationState,
                new CommittedLifecycleState(
                    Version::CURRENT,
                    'copot-v' . Version::CURRENT,
                    null,
                    PackageContract::CURRENT_MANIFEST_CONTRACT_VERSION,
                    'canonical-current',
                    CoreMigrationStateIdentity::fromRecords([]),
                    new \DateTimeImmutable('now')
                )
            );

            return [
                'version' => Version::CURRENT,
                'theme' => 'default',
                'modules' => self::BASELINE_MODULES,
            ];
        } finally {
            $lock->release();
        }
    }

    private function validateFirstAdministrator(): void
    {
        $userCount = (int) $this->database->connection()
            ->query('SELECT COUNT(*) FROM ' . $this->database->table('users'))
            ->fetchColumn();

        if ($userCount !== 1) {
            throw new InstallationException('Exactly one first administrator is required.');
        }

        $statement = $this->database->connection()->prepare(
            'SELECT COUNT(*)
            FROM ' . $this->database->table('users') . ' users
            INNER JOIN ' . $this->database->table('user_roles') . ' user_roles ON user_roles.user_id = users.id
            INNER JOIN ' . $this->database->table('roles') . ' roles ON roles.id = user_roles.role_id
            WHERE roles.slug = :role AND users.status = :status'
        );
        $statement->execute([
            'role' => 'admin',
            'status' => 'active',
        ]);

        if ((int) $statement->fetchColumn() !== 1) {
            throw new InstallationException('The first administrator is not ready.');
        }
    }

    private function validateInitialSettings(): void
    {
        foreach (self::REQUIRED_SETTINGS as [$namespace, $key]) {
            $override = $this->settingsRepository->findOverride($namespace, $key);

            if (
                !is_array($override)
                || ($override['value_type'] ?? null) !== 'string'
                || !isset($override['setting_value'])
                || !is_string($override['setting_value'])
            ) {
                throw new InstallationException('Initial settings are not ready.');
            }

            try {
                $this->settings->validate($namespace, $key, $override['setting_value'], 'string');
            } catch (SettingsException) {
                throw new InstallationException('Initial settings are not valid.');
            }
        }
    }

    private function activateDefaultTheme(): void
    {
        $defaultTheme = null;

        foreach ($this->themeDiscovery->discoverCatalog()['themes'] as $theme) {
            if ($theme->id() === 'default') {
                $defaultTheme = $theme;
                break;
            }
        }

        if (!$defaultTheme instanceof ThemeDefinition) {
            throw new InstallationException('Default theme is unavailable.');
        }

        $this->themes->register($defaultTheme);
        $this->themes->activate('default');
    }

    private function enableBaselineModules(): void
    {
        $installed = [];

        foreach ($this->modules->installed() as $module) {
            if (isset($module['name']) && is_string($module['name'])) {
                $installed[$module['name']] = $module;
            }
        }

        foreach (self::BASELINE_MODULES as $moduleName) {
            if (!isset($installed[$moduleName])) {
                $this->modules->install($moduleName);
                $installed[$moduleName] = ['status' => 'disabled'];
            }

            if (($installed[$moduleName]['status'] ?? null) !== 'enabled') {
                $this->modules->enable($moduleName);
            }
        }
    }
}
