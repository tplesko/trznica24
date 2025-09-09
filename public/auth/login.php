<?php
require_once __DIR__ . '/../../lib/auth.php';
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        auth_login($email, $password);
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
    <title>Prijava</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
    <div class="container py-5" style="max-width:420px">
        <h1 class="h3 mb-3">Prijava</h1>
        <?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= h($err) ?></div><?php endforeach; ?>
        <form method="post" novalidate>
            <div class="mb-2">
                <label class="form-label">Email</label>
                <input name="email" type="email" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Lozinka</label>
                <input name="password" type="password" class="form-control" required>
            </div>
            <button class="btn btn-primary w-100">Prijavi se</button>
            <div class="text-center mt-3"><a href="<?= h(BASE_URL) ?>/auth/register.php">Nemaš račun? Registracija</a></div>
        </form>
    </div>
</body>

</html>