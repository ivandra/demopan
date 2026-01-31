<?php

class FastpanelServerController extends Controller
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

        $rows = DB::pdo()->query("SELECT * FROM fastpanel_servers ORDER BY id DESC")->fetchAll();
        $this->view('servers/index', ['servers' => $rows]);
    }

    public function createForm(): void
    {
        $this->requireAuth();

        $this->view('servers/create', [
            'defaultVerifyTls' => (int)(config('fastpanel.default_verify_tls', false) ? 1 : 0),
        ]);
    }

    public function store(): void
    {
        $this->requireAuth();

        require_once __DIR__ . '/../Services/Crypto.php';

        $title     = trim((string)($_POST['title'] ?? ''));
        $host      = trim((string)($_POST['host'] ?? ''));
        $username  = trim((string)($_POST['username'] ?? ''));
        $password  = (string)($_POST['password'] ?? '');
        $verifyTls = (int)($_POST['verify_tls'] ?? 0);

        if ($title === '' || $host === '' || $username === '' || $password === '') {
            die('title/host/username/password required');
        }

        // normalize host (remove scheme + trailing slash)
        $host = preg_replace('~^https?://~i', '', $host);
        $host = rtrim($host, '/');

        $passwordEnc = Crypto::encrypt($password);

        $stmt = DB::pdo()->prepare(
            "INSERT INTO fastpanel_servers (title, host, username, password_enc, verify_tls)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$title, $host, $username, $passwordEnc, $verifyTls]);

        $this->redirect('/servers');
    }

    public function editForm(): void
    {
        $this->requireAuth();

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) die('bad id');

        $server = $this->loadServer($id);
        $this->view('servers/edit', ['server' => $server]);
    }

    public function update(): void
    {
        $this->requireAuth();

        require_once __DIR__ . '/../Services/Crypto.php';

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) die('bad id');

        $server = $this->loadServer($id);

        $title     = trim((string)($_POST['title'] ?? ''));
        $host      = trim((string)($_POST['host'] ?? ''));
        $username  = trim((string)($_POST['username'] ?? ''));
        $password  = (string)($_POST['password'] ?? '');
        $verifyTls = (int)($_POST['verify_tls'] ?? 0);

        if ($title === '' || $host === '' || $username === '') {
            die('title/host/username required');
        }

        $host = preg_replace('~^https?://~i', '', $host);
        $host = rtrim($host, '/');

        // password can be left empty to keep current
        $passwordEnc = (string)$server['password_enc'];
        if ($password !== '') {
            $passwordEnc = Crypto::encrypt($password);
        }

        $stmt = DB::pdo()->prepare(
            "UPDATE fastpanel_servers
             SET title=?, host=?, username=?, password_enc=?, verify_tls=?
             WHERE id=?"
        );
        $stmt->execute([$title, $host, $username, $passwordEnc, $verifyTls, $id]);

        $this->redirect('/servers');
    }

    public function delete(): void
    {
        $this->requireAuth();

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) die('bad id');

        $stmt = DB::pdo()->prepare("DELETE FROM fastpanel_servers WHERE id=?");
        $stmt->execute([$id]);

        $this->redirect('/servers');
    }

    public function test(): void
    {
        $this->requireAuth();

        require_once __DIR__ . '/../Services/Crypto.php';
        require_once __DIR__ . '/../Services/FastpanelClient.php';

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) die('bad id');

        $server = $this->loadServer($id);

        $password = Crypto::decrypt((string)$server['password_enc']);

        $client = new FastpanelClient(
            (string)$server['host'],
            (bool)$server['verify_tls'],
            (int)config('fastpanel.timeout', 30)
        );

        try {
            $client->login((string)$server['username'], $password);
            $me = $client->me();

            header('Content-Type: text/plain; charset=utf-8');
            echo "OK\n";
            echo "host: " . $server['host'] . "\n";
            echo "user: " . ($me['username'] ?? '[n/a]') . "\n";
            echo "roles: " . (is_array($me['roles'] ?? null) ? implode(',', $me['roles']) : '[n/a]') . "\n";
        } catch (Throwable $e) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            echo "ERROR: " . $e->getMessage();
        }
    }

    private function loadServer(int $id): array
    {
        $stmt = DB::pdo()->prepare("SELECT * FROM fastpanel_servers WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) die('server not found');
        return $row;
    }
}
