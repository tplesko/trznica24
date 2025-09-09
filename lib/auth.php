<?php
require_once __DIR__ . '/db.php';
session_start();

function auth_register(string $email, string $password, string $name = '', string $role = 'buyer'): array
{
    $pdo = db();
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        throw new Exception("Email je već registriran.");
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $pdo->prepare("INSERT INTO users (email, password_hash, role, name) VALUES (?, ?, ?, ?)")
        ->execute([$email, $hash, $role, $name]);
    $id = (int)$pdo->lastInsertId();
    $stmt = $pdo->prepare("SELECT id, email, role, name FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $_SESSION['user'] = $stmt->fetch();
    return $_SESSION['user'];
}

function auth_login(string $email, string $password): array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id, email, password_hash, role, name FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $u = $stmt->fetch();
    if (!$u || !password_verify($password, $u['password_hash'])) {
        throw new Exception('Neispravni podaci za prijavu.');
    }
    $_SESSION['user'] = ['id' => $u['id'], 'email' => $u['email'], 'role' => $u['role'], 'name' => $u['name']];
    return $_SESSION['user'];
}

function auth_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function user()
{
    return $_SESSION['user'] ?? null;
}

function require_login(): void
{
    if (!user()) {
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
    }
}
function user_id(): ?int
{
    return user()['id'] ?? null;
}
function is_seller(): bool
{
    return (user()['role'] ?? 'buyer') ===
        'seller';
}
function require_seller(): void
{
    require_login();
    if (!is_seller()) {
        http_response_code(403);
        die('Ova stranica je dostupna samo prodavateljima.');
    }
}
