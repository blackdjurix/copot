<?php

namespace Copot\Core;

class Application
{
    private string $basePath;
    private Config $config;
    private Router $router;
    private View $view;
    private Database $database;
    private Session $session;
    private Auth $auth;
    private ModuleManager $modules;
    private ModuleLoader $moduleLoader;
    private ThemeManager $themes;
    private ThemeLoader $themeLoader;
    private ThemeAssets $themeAssets;
    private ViewRenderer $viewRenderer;
    private ViewResolver $viewResolver;

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, DIRECTORY_SEPARATOR);
        $this->config = new Config($this->path('config'));
        $this->router = new Router();
        $this->view = new View($this->path('resources/views'));
        $this->database = new Database($this->config);
        $this->session = new Session($this->config);
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

    public function view(): View
    {
        return $this->view;
    }

    public function database(): Database
    {
        return $this->database;
    }

    public function session(): Session
    {
        return $this->session;
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

    public function run(Request $request): Response
    {
        return $this->router->dispatch($request);
    }
}
