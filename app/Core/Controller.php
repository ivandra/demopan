<?php

// app/Core/Controller.php
// Paths bootstrap должен быть подключен в public/index.php (или другом bootstrap) ДО загрузки контроллеров.

abstract class Controller
{
    protected function view(string $path, array $data = []): void
    {
        extract($data);

        $layout = Paths::appRoot() . '/app/Views/layout.php';
        require $layout;
    }

    protected function redirect(string $url): void
    {
        if (!headers_sent()) {
            header('Location: ' . $url, true, 302);
            exit;
        }

        echo '<meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">';
        exit;
    }
}
