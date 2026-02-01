<?php

abstract class Controller
{
    protected function view(string $path, array $data = []): void
    {
        extract($data);
        require __DIR__ . '/../Views/layout.php';
    }

    protected function redirect(string $url): void
    {
        if (!headers_sent()) {
            header('Location: ' . $url, true, 302);
            exit;
        }

        echo '<meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES) . '">';
        echo '<script>location.href=' . json_encode($url) . ';</script>';
        exit;
    }
	
	
}
