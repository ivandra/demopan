<?php

class RegistrarContactsController extends Controller
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

        $rows = DB::pdo()->query("SELECT * FROM registrar_contacts ORDER BY id DESC")->fetchAll();
        $this->view('registrar_contacts/index', compact('rows'));
    }

    public function createForm(): void
    {
        $this->requireAuth();
        $this->view('registrar_contacts/create', []);
    }

public function store(): void
{
    $this->requireAuth();

    $data  = $this->collectContactFromPost();
    $error = $this->validateContact($data);

    if ($error !== '') {
        $this->view('registrar_contacts/create', ['error' => $error, 'data' => $data]);
        return;
    }

    $stmt = DB::pdo()->prepare("
        INSERT INTO registrar_contacts
        (label, first_name, last_name, organization, address1, address2, city, state_province, postal_code, country, phone, email)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $data['label'],
        $data['first_name'],
        $data['last_name'],
        $data['organization'],
        $data['address1'],
        $data['address2'],
        $data['city'],
        $data['state_province'],  // важно: ключ должен быть таким
        $data['postal_code'],     // важно: ключ должен быть таким
        $data['country'],
        $data['phone'],
        $data['email'],
    ]);

    $this->redirect('/registrar/contacts');
}


    public function editForm(): void
    {
        $this->requireAuth();

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) die('bad id');

        $stmt = DB::pdo()->prepare("SELECT * FROM registrar_contacts WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) die('not found');

        $this->view('registrar_contacts/edit', compact('row'));
    }

    public function update(): void
{
    $this->requireAuth();

    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) die('bad id');

    $data  = $this->collectContactFromPost();
    $error = $this->validateContact($data);

    if ($error !== '') {
        $stmt = DB::pdo()->prepare("SELECT * FROM registrar_contacts WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        $this->view('registrar_contacts/edit', compact('row', 'error'));
        return;
    }

    $stmt = DB::pdo()->prepare("
        UPDATE registrar_contacts
        SET
            label=?,
            first_name=?,
            last_name=?,
            organization=?,
            address1=?,
            address2=?,
            city=?,
            state_province=?,
            postal_code=?,
            country=?,
            phone=?,
            email=?
        WHERE id=?
    ");

    $stmt->execute([
        $data['label'],
        $data['first_name'],
        $data['last_name'],
        $data['organization'],
        $data['address1'],
        $data['address2'],
        $data['city'],
        $data['state_province'],
        $data['postal_code'],
        $data['country'],
        $data['phone'],
        $data['email'],
        $id
    ]);

    $this->redirect('/registrar/contacts');
}


    public function delete(): void
    {
        $this->requireAuth();

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) die('bad id');

        DB::pdo()->prepare("DELETE FROM registrar_contacts WHERE id=?")->execute([$id]);

        $this->redirect('/registrar/contacts');
    }

    // -------- helpers --------

    private function collectContactFromPost(): array
{
    return [
        'label'          => trim((string)($_POST['label'] ?? 'default')),
        'first_name'     => trim((string)($_POST['first_name'] ?? '')),
        'last_name'      => trim((string)($_POST['last_name'] ?? '')),
        'organization'   => trim((string)($_POST['organization'] ?? '')),
        'address1'       => trim((string)($_POST['address1'] ?? '')),
        'address2'       => trim((string)($_POST['address2'] ?? '')),
        'city'           => trim((string)($_POST['city'] ?? '')),
        'state_province' => trim((string)($_POST['state_province'] ?? '')), // вместо state
        'postal_code'    => trim((string)($_POST['postal_code'] ?? '')),    // вместо zip
        'country'        => strtoupper(trim((string)($_POST['country'] ?? ''))),
        'phone'          => trim((string)($_POST['phone'] ?? '')),
        'email'          => trim((string)($_POST['email'] ?? '')),
    ];
}


    private function validateContact(array $d): string
{
    $required = ['first_name','last_name','address1','city','postal_code','country','phone','email'];
    foreach ($required as $k) {
        if (!isset($d[$k]) || trim((string)$d[$k]) === '') {
            return "Заполните обязательное поле: {$k}";
        }
    }

    if (!preg_match('~^[A-Z]{2}$~', (string)$d['country'])) {
        return "country должен быть 2-буквенный код (например US, RU).";
    }
	

    if (!filter_var((string)$d['email'], FILTER_VALIDATE_EMAIL)) {
        return "Некорректный email.";
    }

   if (!preg_match('~^\+\d{1,3}\.\d{4,14}$~', $d['phone'])) {
    return "Телефон для Namecheap должен быть в формате +7.9991234567 (точка обязательна).";
}


    return '';
}

}
