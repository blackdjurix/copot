<?php

namespace Copot\Core;

use Copot\Core\Admin\AdminDashboardRegistry;
use Copot\Core\Admin\AdminPageRenderer;
use Copot\Core\Admin\AdminUrl;

class Application
{
    private string $basePath;
    private Config $config;
    private Router $router;
    private EventDispatcher $events;
    private View $view;
    private Database $database;
    private SettingsService $settings;
    private string $siteName;
    private string $timezone;
    private string $locale;
    private SiteFormatter $formatter;
    private Session $session;
    private Csrf $csrf;
    private Auth $auth;
    private ModuleManager $modules;
    private ModuleLoader $moduleLoader;
    private ThemeManager $themes;
    private ThemeLoader $themeLoader;
    private ThemeAssets $themeAssets;
    private ViewRenderer $viewRenderer;
    private ViewResolver $viewResolver;
    private AdminNavigation $adminNavigation;
    private AdminDashboardRegistry $adminDashboard;
    private AdminUrl $adminUrl;
    private AdminPageRenderer $adminPageRenderer;

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, DIRECTORY_SEPARATOR);
        $this->config = new Config($this->path('config'));
        $this->router = new Router();
        $this->events = new SynchronousEventDispatcher();
        $this->view = new View($this->path('resources/views'));
        $this->database = new Database($this->config);
        $settingsRegistry = SettingsRegistry::core();
        $this->settings = new SettingsService(
            $settingsRegistry,
            new SettingsRepository($this->database)
        );
        $this->initializeRuntimeSettings($settingsRegistry);
        $this->session = new Session($this->config);
        $this->csrf = new Csrf($this->session);
        $this->auth = new Auth(
            $this->config,
            $this->session,
            new UserProvider($this->database),
            new PasswordHasher()
        );
        $moduleDiscovery = new ModuleDiscovery($this->path('modules'));
        $moduleRepository = new ModuleRepository($this->database);
        $this->modules = new ModuleManager($moduleDiscovery, $moduleRepository);
        $this->moduleLoader = new ModuleLoader($moduleDiscovery, $moduleRepository);
        $this->themes = new ThemeManager(
            $themeRepository = new ThemeRepository($this->database),
            $this->database,
            $this->basePath
        );
        $this->themeLoader = new ThemeLoader($themeRepository, $this->basePath);
        $this->themeAssets = new ThemeAssets($this->themeLoader);
        $this->viewRenderer = new ViewRenderer($this->themeLoader, $this->themeAssets);
        $this->viewResolver = new ViewResolver(
            $this->themeLoader,
            $this->path('resources/views'),
            $this->path('modules')
        );
        $this->adminUrl = new AdminUrl($this->config);
        $this->adminNavigation = new AdminNavigation();
        $this->adminDashboard = new AdminDashboardRegistry();
        $this->adminNavigation->add('Dashboard', $this->adminUrl->baseUrl());
        $this->adminPageRenderer = new AdminPageRenderer(
            $this->view,
            $this->adminUrl,
            $this->adminNavigation,
            (string) $this->config->get('app.name', 'Copot'),
            $this->siteName,
            $this->locale
        );
    }

    public function path(string $path = ''): string
    {
        $path = trim($path, '/\\');

        if ($path === '') {
            return $this->basePath;
        }

        return $this->basePath . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }

    public function config(): Config
    {
        return $this->config;
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function events(): EventDispatcher
    {
        return $this->events;
    }

    public function view(): View
    {
        return $this->view;
    }

    public function database(): Database
    {
        return $this->database;
    }

    public function settings(): SettingsService
    {
        return $this->settings;
    }

    public function siteName(): string
    {
        return $this->siteName;
    }

    public function timezone(): string
    {
        return $this->timezone;
    }

    public function locale(): string
    {
        return $this->locale;
    }

    public function formatter(): SiteFormatter
    {
        return $this->formatter;
    }

    public function session(): Session
    {
        return $this->session;
    }

    public function csrf(): Csrf
    {
        return $this->csrf;
    }

    public function auth(): Auth
    {
        return $this->auth;
    }

    public function modules(): ModuleManager
    {
        return $this->modules;
    }

    public function moduleLoader(): ModuleLoader
    {
        return $this->moduleLoader;
    }

    public function themes(): ThemeManager
    {
        return $this->themes;
    }

    public function themeLoader(): ThemeLoader
    {
        return $this->themeLoader;
    }

    public function themeAssets(): ThemeAssets
    {
        return $this->themeAssets;
    }

    public function viewRenderer(): ViewRenderer
    {
        return $this->viewRenderer;
    }

    public function viewResolver(): ViewResolver
    {
        return $this->viewResolver;
    }

    public function adminNavigation(): AdminNavigation
    {
        return $this->adminNavigation;
    }

    public function adminDashboard(): AdminDashboardRegistry
    {
        return $this->adminDashboard;
    }

    public function adminUrl(): AdminUrl
    {
        return $this->adminUrl;
    }

    public function adminPageRenderer(): AdminPageRenderer
    {
        return $this->adminPageRenderer;
    }

    public function run(Request $request): Response
    {
        return $this->router->dispatch($request);
    }

    private function initializeRuntimeSettings(SettingsRegistry $registry): void
    {
        $this->siteName = $this->runtimeSetting($registry, 'site', 'name');
        $this->timezone = $this->runtimeSetting($registry, 'localization', 'timezone');
        $this->locale = $this->runtimeSetting($registry, 'localization', 'locale');
        $dateFormat = $this->runtimeSetting($registry, 'localization', 'date_format');
        $timeFormat = $this->runtimeSetting($registry, 'localization', 'time_format');

        date_default_timezone_set($this->timezone);

        $this->formatter = new SiteFormatter(
            $this->locale,
            $this->timezone,
            $dateFormat,
            $timeFormat
        );
    }

    private function runtimeSetting(SettingsRegistry $registry, string $namespace, string $key): string
    {
        $definition = $registry->find($namespace, $key);

        if (!$definition instanceof SettingDefinition || !is_string($definition->defaultValue())) {
            throw new SettingsException("Missing runtime setting definition [{$namespace}.{$key}].");
        }

        try {
            $value = $this->settings->get($namespace, $key);
        } catch (SettingsException) {
            return $definition->defaultValue();
        }

        return is_string($value) ? $value : $definition->defaultValue();
    }
}
