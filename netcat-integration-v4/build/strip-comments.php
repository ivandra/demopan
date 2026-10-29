<?php
$root = $argv[1];
$single = isset($argv[2]) ? $argv[2] : null;
$paths = array($root . '/components', $root . '/files');
foreach ($paths as $folder) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($single && $file->getPathname() !== $single) { continue; }
        if (!$file->isFile() || !preg_match('/\.(html|php|php\.txt)$/', $file->getFilename())) {
            continue;
        }
        $contents = '';
        foreach (token_get_all(file_get_contents($file->getPathname())) as $token) {
            if (is_array($token)) {
                if (in_array($token[0], array(T_COMMENT, T_DOC_COMMENT), true)) {
                    $contents .= "\n";
                    continue;
                }
                $contents .= $token[1];
            } else {
                $contents .= $token;
            }
        }
        file_put_contents($file->getPathname(), preg_replace('/^[ \t]+$/m', '', $contents));
    }
}
