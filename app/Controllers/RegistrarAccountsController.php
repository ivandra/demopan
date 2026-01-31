<?php

class RegistrarAccountsController extends Controller
{
    private function requireAuth(): void
    {
        if (empty($_SESSION['user_id'])) {
            $this->redirect('/login');
        }
    }

    public function index(): void
    {
        $this->requireAuth();

        $rows = DB::pdo()->query("SELECT * FROM registrar_accounts ORDER BY id DESC")->fetchAll();
        $this->view('registrar_accounts/index', compact('rows'));
    }

    public function createForm(): void
    {
        $this->requireAuth();
        $this->view('registrar_accounts/create', []);
    }

    public function store(): void
    {
        $this->requireAuth();

        require_once __DIR__ . '/../Services/Crypto.php';

        $provider   = trim((string)($_POST['provider'] ?? 'namecheap'));
        $isSandbox  = (int)(($_POST['is_sandbox'] ?? '1') === '1');
        $clientIp   = trim((string)($_POST['client_ip'] ?? ''));
        $apiUser    = trim((string)($_POST['api_user'] ?? ''));
        $username   = trim((string)($_POST['username'] ?? ''));
        $apiKey     = trim((string)($_POST['api_key'] ?? ''));

        if ($provider === '') $provider = 'namecheap';

        if ($clientIp === '' || $apiUser === '' || $username === '' || $apiKey === '') {
            $error = 'Заполните все поля (client_ip, api_user, username, api_key).';
            $this->view('registrar_accounts/create', compact('error'));
            return;
        }

        $apiKeyEnc = Crypto::encrypt($apiKey);

        $stmt = DB::pdo()->prepare("
            INSERT INTO registrar_accounts
            (provider, is_sandbox, client_ip, api_user, username, api_key_enc, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$provider, $isSandbox, $clientIp, $apiUser, $username, $apiKeyEnc]);

        $this->redirect('/registrar/accounts');
    }

    public function editForm(): void
    {
        $this->requireAuth();

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) die('bad id');

        $stmt = DB::pdo()->prepare("SELECT * FROM registrar_accounts WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) die('not found');

        $this->view('registrar_accounts/edit', compact('row'));
    }

    public function update(): void
    {
        $this->requireAuth();

        require_once __DIR__ . '/../Services/Crypto.php';

        $id        = (int)($_GET['id'] ?? 0);
        if ($id <= 0) die('bad id');

        $provider  = trim((string)($_POST['provider'] ?? 'namecheap'));
        $isSandbox = (int)(($_POST['is_sandbox'] ?? '1') === '1');
        $clientIp  = trim((string)($_POST['client_ip'] ?? ''));
        $apiUser   = trim((string)($_POST['api_user'] ?? ''));
        $username  = trim((string)($_POST['username'] ?? ''));
        $apiKey    = trim((string)($_POST['api_key'] ?? ''));

        if ($provider === '') $provider = 'namecheap';

        if ($clientIp === '' || $apiUser === '' || $username === '') {
            $error = 'Заполните обязательные поля (client_ip, api_user, username).';
            // перечитаем row для формы
            $stmt = DB::pdo()->prepare("SELECT * FROM registrar_accounts WHERE id=?");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            $this->view('registrar_accounts/edit', compact('row', 'error'));
            return;
        }

        if ($apiKey !== '') {
            $apiKeyEnc = Crypto::encrypt($apiKey);
            $stmt = DB::pdo()->prepare("
                UPDATE registrar_accounts
                SET provider=?, is_sandbox=?, client_ip=?, api_user=?, username=?, api_key_enc=?, updated_at=NOW()
                WHERE id=?
            ");
            $stmt->execute([$provider, $isSandbox, $clientIp, $apiUser, $username, $apiKeyEnc, $id]);
        } else {
            $stmt = DB::pdo()->prepare("
                UPDATE registrar_accounts
                SET provider=?, is_sandbox=?, client_ip=?, api_user=?, username=?, updated_at=NOW()
                WHERE id=?
            ");
            $stmt->execute([$provider, $isSandbox, $clientIp, $apiUser, $username, $id]);
        }

        $this->redirect('/registrar/accounts');
    }

    public function delete(): void
    {
        $this->requireAuth();

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) die('bad id');

        DB::pdo()->prepare("DELETE FROM registrar_accounts WHERE id=?")->execute([$id]);

        $this->redirect('/registrar/accounts');
    }
}
