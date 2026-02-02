<?php

namespace App\Services;

class ZipBuilder
{
    public function buildFromDir(string $dir, string $outFile): array
    {
        $dir = rtrim($dir, "/\\");
        if (!is_dir($dir)) {
            throw new \RuntimeException("build_path is not a directory: {$dir}");
        }

        // Если путь к zip задан относительный - пишем в zips (внутри storage)
        if (!preg_match('~^(?:/|[A-Za-z]:[\\/])~', $outFile)) {
            $outFile = (class_exists('Paths') ? \Paths::storage('zips/' . ltrim($outFile, "/\\")) : $outFile);
        }

        $zipDir = dirname($outFile);

        // Директорию под zip создаем всегда (по стандарту через Paths::ensureDir)
        if (class_exists('Paths')) {
            \Paths::ensureDir($zipDir);
        } else {
            if (!is_dir($zipDir)) {
                mkdir($zipDir, 0775, true);
            }
        }

        $zip = new \ZipArchive();
        if ($zip->open($outFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Cannot open zip file: {$outFile}");
        }

        $rootN = rtrim(str_replace('\\', '/', $dir), '/');
        $prefix = $rootN . '/';

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            /** @var \SplFileInfo $file */
            if (!$file->isFile()) continue;

            $filePath = $file->getPathname();
            $fileN = str_replace('\\', '/', $filePath);

            if (strpos($fileN, $prefix) !== 0) {
                // на всякий случай пропускаем файлы вне исходной директории
                continue;
            }

            $relPath = substr($fileN, strlen($prefix));
            $zip->addFile($filePath, $relPath);
        }

        $zip->close();

        $size = @filesize($outFile);
        return ['path' => $outFile, 'size' => $size === false ? 0 : (int)$size];
    }
}
