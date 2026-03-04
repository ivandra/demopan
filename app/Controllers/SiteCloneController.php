<?php

class SiteCloneController extends Controller
{
    private function requireAuth(): void
    {
        if (empty($_SESSION['user_id'])) {
            $this->redirect('/login');
        }
    }

    private function loadSite(int $id): ?array
    {
        $st = DB::pdo()->prepare("SELECT * FROM sites WHERE id = ? LIMIT 1");
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function cloneForm(): void
    {
        $this->requireAuth();

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) die('bad id');

        $site = $this->loadSite($id);
        if (!$site) die('not found');

        // Берём список поддоменов из site_subdomains (источник истины для UI)
        $st = DB::pdo()->prepare("SELECT label, fqdn, enabled FROM site_subdomains WHERE site_id = ? ORDER BY label ASC");
        $st->execute([$id]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        $labels = [];
        foreach ($rows as $r) {
            $lb = (string)($r['label'] ?? '');
            if ($lb === '') continue;
            if ($lb === '_default') continue; // _default не показываем как чекбокс, он всегда копируется
            $labels[] = [
                'label' => $lb,
                'fqdn' => (string)($r['fqdn'] ?? ''),
                'enabled' => (int)($r['enabled'] ?? 1),
            ];
        }

        $defaultNewDomain = preg_replace('~^clone\.~i', '', (string)($site['domain'] ?? ''));
        if ($defaultNewDomain === (string)($site['domain'] ?? '')) {
            $defaultNewDomain = 'clone.' . $defaultNewDomain;
        }

        $this->view('sites/clone', [
            'site' => $site,
            'labels' => $labels,
            'defaultNewDomain' => $defaultNewDomain,
            'defaultSameVps' => 1,     // как ты просил: дефолт “same VPS”
            'defaultResetState' => 1,  // галка “reset provisioning state”
        ]);
    }

    public function cloneDo(): void
    {
        $this->requireAuth();

        $srcSiteId = (int)($_GET['id'] ?? 0);
        if ($srcSiteId <= 0) die('bad id');

        $srcSite = $this->loadSite($srcSiteId);
        if (!$srcSite) die('not found');

        $newDomain = trim((string)($_POST['new_domain'] ?? ''));
        if ($newDomain === '') die('Новый домен не указан');

        // чекбоксы
        $sameVps = (int)($_POST['same_vps'] ?? 0) === 1;
        $resetState = (int)($_POST['reset_state'] ?? 0) === 1;

        // выбранные labels
        $labels = $_POST['labels'] ?? [];
        if (!is_array($labels)) $labels = [];

        // _default всегда включаем
        $labels[] = '_default';

        require_once __DIR__ . '/../Services/SiteCloner.php';

        $cloner = new SiteCloner();
        $newSiteId = $cloner->cloneSite(
            $srcSiteId,
            $newDomain,
            $labels,
            [
                'same_vps' => $sameVps ? 1 : 0,
                'reset_state' => $resetState ? 1 : 0,
            ]
        );

        // После клона логично попасть на страницу редактирования/просмотра
        $this->redirect('/sites/edit?id=' . (int)$newSiteId);
    }
}