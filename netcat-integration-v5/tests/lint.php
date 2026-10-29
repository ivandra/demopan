<?php
// Parse templates without executing stored PHP or requiring a configured CMS.
$root = dirname(__DIR__) . '/files';
$result = array('passed'=>array(), 'failed'=>array());
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($files as $file) {
    if (!$file->isFile() || !preg_match('/\.(html|php|php\.txt)$/', $file->getFilename())) { continue; }
    $relative = substr($file->getPathname(), strlen($root)+1);
    try {
        $tokens = token_get_all(file_get_contents($file->getPathname()), TOKEN_PARSE);
        foreach ($tokens as $token) {
            if (is_array($token) && in_array($token[0], array(T_COMMENT,T_DOC_COMMENT), true)) {
                throw new ParseError('Unexpected PHP comment');
            }
        }
        $result['passed'][] = $relative;
    } catch (ParseError $error) {
        $result['failed'][] = array('file'=>$relative,'error'=>$error->getMessage());
    }
}
echo json_encode($result, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE), "\n";
exit($result['failed'] ? 1 : 0);
