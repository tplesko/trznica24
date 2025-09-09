<?php
require_once __DIR__ . '/../../lib/auth.php';
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $name = trim($_POST['name'] ?? '');
        $role = $_POST['role'] ?? 'buyer';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('Neispravan email.');
        if (strlen($password) < 6) throw new Exception('Lozinka mora imati najmanje 6 znakova.');
        if (!in_array($role, ['buyer', 'seller'])) $role = 'buyer';
        auth_register($email, $password, $name, $role);
        header('Location: ' . BASE_URL . '/');
        exit;
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="hr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Registracija</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
    <div class="container py-5" style="max-width:520px">
        <h1 class="h3 mb-3">Registracija</h1>
        <?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= h($err) ?></div><?php endforeach; ?>
        <form method="post" novalidate>
            <div class="mb-2">
                <label class="form-label">Ime i prezime</label>
                <input name="name" class="form-control" required>
            </div>
            <div class="mb-2">
                <label class="form-label">Email</label>
                <input name="email" type="email" class="form-control" required>
            </div>
            <div class="mb-2">
                <label class="form-label">Lozinka</label>
                <input name="password" type="password" class="form-control" minlength="6" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Tip računa</label>
                <select name="role" class="form-select">
                    <option value="buyer">Kupac</option>
                    <option value="seller">Prodavatelj (OPG/PG...)</option>
                </select>
            </div>
            <button class="btn btn-primary w-100">Kreiraj račun</button>
            <div class="text-center mt-3"><a href="<?= h(BASE_URL) ?>/auth/login.php">Već imaš račun? Prijava</a></div>
        </form>
    </div>
</body>

</html>