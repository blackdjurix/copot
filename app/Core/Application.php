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

    public function run(Request $request): Response
    {
        return $this->router->dispatch($request);
    }
}
