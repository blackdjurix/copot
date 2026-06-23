<?php

namespace Copot\Core;

class View
{
    public function __construct(private string $viewPath)
    {
    }

    public function render(string $view, array $data = []): string
    {
        $view = trim(str_replace('.', '/', $view), '/\\');
        $file = rtrim($this->viewPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $view . '.php';

        if (!is_file($file)) {
            throw new \RuntimeException("View [{$view}] was not found.");
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $file;

        return (string) ob_get_clean();
    }
}
