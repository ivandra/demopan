<?php

namespace App\Services;

class ZipBuilder
{
    public function buildFromDir(string $dir, string $outFile): array
    {
        $dir = rtrim($dir, '/');
        if (!is_dir($dir)) {
            throw new \RuntimeException("build_path is not a directory: {$dir}");
        }

        $zipDir = dirname($outFile);
        if (!is_dir($zipDir)) {
            mkdir($zipDir, 0775, true);
        }

        $zip = new \ZipArchive();
        if ($zip->open($outFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Cannot open zip file: {$outFile}");
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            /** @var \SplFileInfo $file */
            if (!$file->isFile()) continue;

            $filePath = $file->getRealPath();
            $relPath = substr($filePath, strlen(realpath($dir)) + 1);
            $relPath = str_replace('\\', '/', $relPath);

            $zip->addFile($filePath, $relPath);
        }

        $zip->close();

        $size = filesize($outFile);
        return ['path' => $outFile, 'size' => $size];
    }
}
