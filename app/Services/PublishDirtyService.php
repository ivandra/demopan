<?php

class PublishDirtyService
{
    private function ensureColumns(): void
    {
        DB::withReconnect(function(PDO $pdo) {
            $sqls = [
                "ALTER TABLE sites ADD COLUMN publish_dirty TINYINT(1) NOT NULL DEFAULT 0",
                "ALTER TABLE sites ADD COLUMN publish_dirty_at DATETIME DEFAULT NULL",
                "ALTER TABLE sites ADD COLUMN publish_dirty_message VARCHAR(255) NOT NULL DEFAULT ''",
            ];
            foreach ($sqls as $sql) {
                try { $pdo->exec($sql); } catch (Throwable $e) {}
            }
        });
    }

    public function markDirty(int $siteId, string $message = ''): void
    {
        $this->ensureColumns();
        DB::withReconnect(function(PDO $pdo) use ($siteId, $message) {
            $st = $pdo->prepare("UPDATE sites SET publish_dirty=1, publish_dirty_at=NOW(), publish_dirty_message=:msg WHERE id=:id LIMIT 1");
            $st->execute([
                ':id' => $siteId,
                ':msg' => trim($message) !== '' ? trim($message) : 'Есть локальные изменения. Требуется выгрузка на VPS.',
            ]);
        });
    }

    public function clearDirty(int $siteId): void
    {
        $this->ensureColumns();
        DB::withReconnect(function(PDO $pdo) use ($siteId) {
            $st = $pdo->prepare("UPDATE sites SET publish_dirty=0, publish_dirty_at=NULL, publish_dirty_message='' WHERE id=:id LIMIT 1");
            $st->execute([':id' => $siteId]);
        });
    }

    public function getDirtySites(int $limit = 10): array
    {
        $this->ensureColumns();
        return DB::withReconnect(function(PDO $pdo) use ($limit) {
            $limit = max(1, min($limit, 50));
            $st = $pdo->query("SELECT id, domain, publish_dirty_at, publish_dirty_message FROM sites WHERE publish_dirty=1 ORDER BY COALESCE(publish_dirty_at, updated_at) DESC LIMIT " . (int)$limit);
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        });
    }
}
