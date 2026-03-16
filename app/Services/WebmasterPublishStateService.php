<?php

class WebmasterPublishStateService
{
    public function getState(int $siteId): array
    {
        $siteId = (int)$siteId;

        $state = [
            'written_cnt'   => 0,
            'written_at'    => '',
            'deploy_at'     => '',
            'needs_deploy'  => 0,
            'ok'            => 0,
            'title'         => '',
            'message'       => '',
        ];

        if ($siteId <= 0) {
            return $state;
        }

        $site = DB::withReconnect(function(PDO $pdo) use ($siteId) {
            $st = $pdo->prepare("
                SELECT *
                FROM sites
                WHERE id = ?
                LIMIT 1
            ");
            $st->execute([$siteId]);
            return $st->fetch(PDO::FETCH_ASSOC) ?: null;
        });

        if (!$site) {
            return $state;
        }

        $wm = DB::withReconnect(function(PDO $pdo) use ($siteId) {
            $st = $pdo->prepare("
                SELECT
                    MAX(file_written_at) AS last_file_written_at,
                    SUM(CASE WHEN file_written = 1 THEN 1 ELSE 0 END) AS written_cnt
                FROM webmaster_hosts
                WHERE site_id = ?
            ");
            $st->execute([$siteId]);
            return $st->fetch(PDO::FETCH_ASSOC) ?: [];
        });

        $writtenAt  = (string)($wm['last_file_written_at'] ?? '');
        $writtenCnt = (int)($wm['written_cnt'] ?? 0);
        $deployAt   = (string)($site['fp_files_last_ok'] ?? '');

        $writtenTs = $writtenAt !== '' ? (int)strtotime($writtenAt) : 0;
        $deployTs  = $deployAt !== '' ? (int)strtotime($deployAt) : 0;

        $state['written_cnt'] = $writtenCnt;
        $state['written_at']  = $writtenAt;
        $state['deploy_at']   = $deployAt;

        if ($writtenCnt <= 0 || $writtenTs <= 0) {
            return $state;
        }

        if ($deployTs < $writtenTs) {
            $state['needs_deploy'] = 1;
            $state['title'] = 'Verify-файлы записаны, но еще не опубликованы на VPS';
            $state['message'] = 'После шага 1 нужно выполнить публикацию, иначе Яндекс не увидит verification-файлы по URL домена или поддоменов.';
            return $state;
        }

        $state['ok'] = 1;
        $state['title'] = 'Verify-файлы уже опубликованы';
        $state['message'] = 'Файлы подтверждения записаны и уже выгружены на VPS. Можно запускать проверку верификации в Яндексе.';

        return $state;
    }
}