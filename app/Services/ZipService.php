<?php

class ZipService
{
    public function makeZip(string $sourceDir, string $zipPath): void
    {
        $sourceDir = rtrim($sourceDir, '/');
        if (!is_dir($sourceDir)) {
            throw new RuntimeException('ZipService: sourceDir not found: ' . $sourceDir);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('ZipService: cannot open zip: ' . $zipPath);
        }

        $root = realpath($sourceDir);
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($it as $file) {
            /** @var SplFileInfo $file */
            if (!$file->isFile()) continue;

            $filePath = $file->getRealPath();
            $relPath = substr($filePath, strlen($root) + 1);
            $relPath = str_replace('\\', '/', $relPath);

            $zip->addFile($filePath, $relPath);
        }

        $zip->close();
    }
}
