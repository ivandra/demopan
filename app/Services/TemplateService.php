<?php

require_once __DIR__ . '/../Core/Paths.php';

class TemplateService
{
    public function listTemplates(): array
    {
        $base = Paths::storage('templates');
        if (!is_dir($base)) return [];

        $out = [];
        foreach (scandir($base) ?: [] as $name) {
            if ($name === '.' || $name === '..') continue;
            $full = rtrim($base, "/\\") . '/' . $name;
            if (is_dir($full)) $out[] = $name;
        }

        sort($out);
        return $out;
    }

    public function copyTemplate(string $templateName, string $targetDir): void
    {
        $templateName = trim($templateName);
        if ($templateName === '' || preg_match('~[^a-z0-9_\-]~i', $templateName)) {
            throw new RuntimeException('Bad template name: ' . $templateName);
        }

        $src = rtrim(Paths::storage('templates'), "/\\") . '/' . $templateName;
        if (!is_dir($src)) {
            throw new RuntimeException('Template not found: ' . $src);
        }

        if (!is_dir($targetDir)) {
            if (!@mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
                throw new RuntimeException('Cannot create target dir: ' . $targetDir);
            }
        }

        $this->rcopy($src, $targetDir);
    }

    private function rcopy(string $src, string $dst): void
    {
        $src = rtrim($src, "/\\");
        $dst = rtrim($dst, "/\\");

        if (!is_dir($dst)) {
            if (!@mkdir($dst, 0775, true) && !is_dir($dst)) {
                throw new RuntimeException('Cannot create dir: ' . $dst);
            }
        }

        $items = scandir($src);
        if ($items === false) return;

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;

            $s = $src . '/' . $item;
            $d = $dst . '/' . $item;

            if (is_link($s)) {
                continue;
            }

            if (is_dir($s)) {
                $this->rcopy($s, $d);
            } else {
                if (!@copy($s, $d)) {
                    throw new RuntimeException('Copy failed: ' . $s . ' -> ' . $d);
                }
            }
        }
    }
}
