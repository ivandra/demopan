<?php

class TemplateService
{
    private function storageRoot(): string
    {
        if (defined('STORAGE_ROOT')) return rtrim(STORAGE_ROOT, '/\\');
        if (defined('APP_ROOT')) return rtrim(APP_ROOT, '/\\') . '/storage';

        // fallback (на всякий)
        $root = realpath(__DIR__ . '/../../storage');
        return rtrim($root ?: (__DIR__ . '/../../storage'), '/\\');
    }

    public function listTemplates(): array
    {
        $base = $this->storageRoot() . '/templates';
        if (!is_dir($base)) return [];

        $items = scandir($base);
        if ($items === false) return [];

        $templates = [];
        foreach ($items as $name) {
            if ($name === '.' || $name === '..') continue;
            $dir = $base . '/' . $name;
            if (is_dir($dir)) $templates[] = $name;
        }

        sort($templates);
        return $templates;
    }

    public function copyTemplate(string $templateName, string $targetDir): void
    {
        $src = $this->storageRoot() . '/templates/' . $templateName;
        if (!is_dir($src)) {
            throw new RuntimeException("Template not found: {$templateName}");
        }

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }

        $this->rcopy($src, $targetDir);
    }

    private function rcopy(string $src, string $dst): void
    {
        $dir = opendir($src);
        if ($dir === false) return;

        @mkdir($dst, 0775, true);

        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') continue;

            $srcPath = $src . '/' . $file;
            $dstPath = $dst . '/' . $file;

            if (is_dir($srcPath)) {
                $this->rcopy($srcPath, $dstPath);
            } else {
                @copy($srcPath, $dstPath);
            }
        }
        closedir($dir);
    }
}
