<?php

namespace App\Services;

class FtpUploader
{
    public function upload(string $host, int $port, string $user, string $pass, string $localFile, string $remotePath): array
    {
        $conn = @ftp_connect($host, $port, 20);
        if (!$conn) {
            throw new \RuntimeException("FTP connect failed {$host}:{$port}");
        }

        ftp_set_option($conn, FTP_TIMEOUT_SEC, 30);

        $ok = @ftp_login($conn, $user, $pass);
        if (!$ok) {
            ftp_close($conn);
            throw new \RuntimeException("FTP login failed for user={$user}");
        }

        // пассивный режим почти всегда обязателен на VPS/панелях
        @ftp_pasv($conn, true);

        $pwd = @ftp_pwd($conn);
        $list = @ftp_nlist($conn, '.');
        if (!is_array($list)) $list = [];

        // ensure remote dir exists
        $dir = dirname($remotePath);
        $this->ensureDir($conn, $dir);

        $tmpRemote = $remotePath . '.part';
        @ftp_delete($conn, $tmpRemote);

        $res = @ftp_put($conn, $tmpRemote, $localFile, FTP_BINARY);
        if (!$res) {
            $err = error_get_last();
            ftp_close($conn);
            $extra = $err ? ($err['message'] ?? '') : '';
            throw new \RuntimeException("ftp_put failed to {$tmpRemote}. {$extra}");
        }

        // атомарная замена
        @ftp_delete($conn, $remotePath);
        $ren = @ftp_rename($conn, $tmpRemote, $remotePath);
        if (!$ren) {
            // если rename запрещен — оставим part и скажем явно
            ftp_close($conn);
            throw new \RuntimeException("ftp_rename failed ({$tmpRemote} -> {$remotePath}). Rename not permitted?");
        }

        ftp_close($conn);

        return [
            'pwd' => $pwd,
            'list' => $list,
            'remote' => $remotePath,
        ];
    }

    private function ensureDir($conn, string $dir): void
    {
        $dir = str_replace('\\', '/', $dir);
        $dir = rtrim($dir, '/');
        if ($dir === '' || $dir === '.' || $dir === '/') return;

        $parts = explode('/', ltrim($dir, '/'));
        $path = '';
        foreach ($parts as $p) {
            if ($p === '') continue;
            $path .= '/' . $p;
            @ftp_mkdir($conn, $path);
        }
    }
}
