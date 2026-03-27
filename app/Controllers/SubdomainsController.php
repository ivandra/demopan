<?php

class SubdomainsController extends Controller
{
    private function requireAuth(): void
    {
        if (empty($_SESSION['user_id'])) $this->redirect('/login');
    }

    private function ensureBrandRuColumn(): void
    {
        try {
            DB::pdo()->exec("ALTER TABLE subdomain_catalog ADD COLUMN brand_name_ru VARCHAR(255) NOT NULL DEFAULT '' AFTER brand_name");
        } catch (Throwable $e) {
        }
    }

    /**
     * Разбирает ручной ввод каталога.
     * Поддерживает строки вида:
     *   label
     *   label | Brand
     *   label | Brand | Brand_RU
     * Также понимает разделители ; и tab.
     * Если в строке только label, брендовые поля остаются пустыми.
     */
    private function parseCatalogInput(string $raw): array
    {
        $out = [];
        $lines = preg_split('~
||
~', $raw) ?: [];

        foreach ($lines as $line) {
            $line = trim((string)$line);
            if ($line === '') continue;

            if (strpos($line, '|') !== false || strpos($line, ';') !== false || strpos($line, "	") !== false) {
                $parts = preg_split('~\s*(?:\||;|	)\s*~u', $line) ?: [];
                $label = strtolower(trim((string)($parts[0] ?? '')));
                if ($label === '') continue;
                if (!preg_match('~^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$~', $label)) continue;

                $out[$label] = [
                    'label' => $label,
                    'brand_name' => trim((string)($parts[1] ?? '')),
                    'brand_name_ru' => trim((string)($parts[2] ?? '')),
                ];
                continue;
            }

            $parts = preg_split('~[,\s]+~u', $line) ?: [];
            foreach ($parts as $p) {
                $label = strtolower(trim((string)$p));
                if ($label === '') continue;
                if (!preg_match('~^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$~', $label)) continue;
                if (!isset($out[$label])) {
                    $out[$label] = [
                        'label' => $label,
                        'brand_name' => '',
                        'brand_name_ru' => '',
                    ];
                }
            }
        }

        return array_values($out);
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->ensureBrandRuColumn();

        $rows = DB::pdo()->query("SELECT * FROM subdomain_catalog ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
        $this->view('subdomains/index', ['rows' => $rows]);
    }

    public function bulkAdd(): void
    {
        $this->requireAuth();
        $this->ensureBrandRuColumn();

        $raw = trim((string)($_POST['labels'] ?? ''));
        if ($raw === '') $this->redirect('/subdomains');

        $items = $this->parseCatalogInput($raw);
        if (empty($items)) $this->redirect('/subdomains');

        $pdo = DB::pdo();
        $sql = "
            INSERT INTO subdomain_catalog(label, brand_name, brand_name_ru, is_active)
            VALUES(?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE
                brand_name = CASE
                    WHEN VALUES(brand_name) <> '' THEN VALUES(brand_name)
                    ELSE brand_name
                END,
                brand_name_ru = CASE
                    WHEN VALUES(brand_name_ru) <> '' THEN VALUES(brand_name_ru)
                    ELSE brand_name_ru
                END
        ";
        $stmt = $pdo->prepare($sql);

        foreach ($items as $item) {
            $stmt->execute([
                (string)$item['label'],
                (string)$item['brand_name'],
                (string)$item['brand_name_ru'],
            ]);
        }

        $this->redirect('/subdomains');
    }

    public function save(): void
    {
        $this->requireAuth();

        $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        $this->ensureBrandRuColumn();

        if ($id > 0) {
            $st = DB::pdo()->prepare("SELECT brand_name, brand_name_ru FROM subdomain_catalog WHERE id = ? LIMIT 1");
            $st->execute([$id]);
            $current = $st->fetch(PDO::FETCH_ASSOC) ?: [];

            $brandName = array_key_exists('brand_name', $_POST)
                ? trim((string)$_POST['brand_name'])
                : (string)($current['brand_name'] ?? '');

            $brandNameRu = array_key_exists('brand_name_ru', $_POST)
                ? trim((string)$_POST['brand_name_ru'])
                : (string)($current['brand_name_ru'] ?? '');

            DB::pdo()->prepare("
                UPDATE subdomain_catalog
                   SET brand_name = ?,
                       brand_name_ru = ?
                 WHERE id = ?
                 LIMIT 1
            ")->execute([$brandName, $brandNameRu, $id]);
        }

        $this->redirect('/subdomains');
    }

    public function delete(): void
    {
        $this->requireAuth();

        $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        if ($id > 0) {
            DB::pdo()->prepare("DELETE FROM subdomain_catalog WHERE id=?")->execute([$id]);
        }
        $this->redirect('/subdomains');
    }

    public function toggle(): void
    {
        $this->requireAuth();

        $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        if ($id > 0) {
            DB::pdo()->prepare("UPDATE subdomain_catalog SET is_active = IF(is_active=1,0,1) WHERE id=?")->execute([$id]);
        }
        $this->redirect('/subdomains');
    }
}
