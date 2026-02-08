<?php

class SubdomainsController extends Controller
{
    private function requireAuth(): void
    {
        if (empty($_SESSION['user_id'])) $this->redirect('/login');
    }

    public function index(): void
    {
        $this->requireAuth();

        $rows = DB::pdo()->query("SELECT * FROM subdomain_catalog ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
        $this->view('subdomains/index', ['rows' => $rows]);
    }

    public function bulkAdd(): void
    {
        $this->requireAuth();

        $raw = trim((string)($_POST['labels'] ?? ''));
        if ($raw === '') $this->redirect('/subdomains');

        $parts = preg_split('~[,\s]+~u', $raw);
        $labels = [];

        foreach ($parts as $p) {
            $p = strtolower(trim((string)$p));
            if ($p === '' || $p === '_default') continue;

            // label без точек
            if (!preg_match('~^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$~', $p)) continue;

            $labels[$p] = true;
        }

        if (empty($labels)) $this->redirect('/subdomains');

        $pdo = DB::pdo();
        $stmt = $pdo->prepare("INSERT IGNORE INTO subdomain_catalog(label,is_active) VALUES(?,1)");

        foreach (array_keys($labels) as $l) {
            $stmt->execute([$l]);
        }

        $this->redirect('/subdomains');
    }

    public function delete(): void
    {
        $this->requireAuth();

        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            DB::pdo()->prepare("DELETE FROM subdomain_catalog WHERE id=?")->execute([$id]);
        }
        $this->redirect('/subdomains');
    }

    public function toggle(): void
    {
        $this->requireAuth();

        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            DB::pdo()->prepare("UPDATE subdomain_catalog SET is_active = IF(is_active=1,0,1) WHERE id=?")->execute([$id]);
        }
        $this->redirect('/subdomains');
    }
}
