<?php

class ZipService
{
    public function makeZip(string $sourceDir, string $zipPath): void
    {
        $sourceDir = rtrim($sourceDir, '/');
        if (!is_dir($sourceDir)) {
            throw new RuntimeException('ZipService: sourceDir not found: ' . $sourceDir);
        }
        // Если путь к zip задан относительный - пишем в zips (внутри storage)
        if (!preg_match('~^(?:/|[A-Za-z]:[\\/])~', $zipPath)) {
            $zipPath = (class_exists('Paths') ? Paths::storage('zips/' . ltrim($zipPath, '/\\')) : $zipPath);
        }

        $zipDir = dirname($zipPath);
        if (class_exists('Paths')) {
            Paths::ensureDir($zipDir);
        } else {
            if (!is_dir($zipDir)) mkdir($zipDir, 0775, true);
        }


        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('ZipService: cannot open zip: ' . $zipPath);
        }

        $root = rtrim($sourceDir, '/');
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($it as $file) {
            /** @var SplFileInfo $file */
            if (!$file->isFile()) continue;

            $filePath = $file->getPathname();
            $rootN = rtrim(str_replace('\\', '/', $root), '/');
            $fileN = str_replace('\\', '/', $filePath);
            $prefix = $rootN . '/';
            if (strpos($fileN, $prefix) !== 0) {
                continue;
            }
            $relPath = substr($fileN, strlen($prefix));
            $relPath = str_replace('\\', '/', $relPath);

            $zip->addFile($filePath, $relPath);
        }

        $zip->close();
    }
}
