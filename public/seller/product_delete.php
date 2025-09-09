<?php
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_seller();
$pdo = db();
$uid = user_id();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    header('Location: ' . BASE_URL . '/seller/products.php');
    exit;
}

// seller_id
$stmt = $pdo->prepare('SELECT id FROM sellers WHERE user_id=?');
$stmt->execute([$uid]);
$seller = $stmt->fetch();
if (!$seller) {
    header('Location: ' . BASE_URL . '/seller/profile.php');
    exit;
}
$seller_id = (int)$seller['id'];

// pokupi URL-ove slika (za kasnije brisanje s diska)
$imgStmt = $pdo->prepare('SELECT url FROM product_images WHERE product_id=?');
$imgStmt->execute([$id]);
$urls = $imgStmt->fetchAll(PDO::FETCH_COLUMN);

$pdo->beginTransaction();
try {
    $pdo->prepare('DELETE FROM product_images WHERE product_id=?')->execute([$id]);
    $aff = $pdo->prepare('DELETE FROM products WHERE id=? AND seller_id=?')->execute([$id, $seller_id]);
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    die('Greška pri brisanju.');
}

// obriši fajlove s diska (samo one koji su u public/uploads)
foreach ($urls as $url) {
    $prefix = BASE_URL . '/uploads/';
    if (strpos($url, $prefix) === 0) {
        $relative = substr($url, strlen(BASE_URL)); // /uploads/...
        $path = __DIR__ . '/../../public' . $relative;
        if (is_file($path)) @unlink($path);
    }
}

header('Location: ' . BASE_URL . '/seller/products.php');
exit;
