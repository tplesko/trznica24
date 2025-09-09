<?php
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';

require_seller();

$pdo = db();
$uid = user_id();

$st = $pdo->prepare('SELECT id FROM sellers WHERE user_id = ?');
$st->execute([$uid]);
$seller_id = (int)$st->fetchColumn();
if (!$seller_id) {
    http_response_code(403);
    die('Nema prodavatelja.');
}

$order_id   = (int)($_POST['order_id'] ?? 0);
$new_status = $_POST['status'] ?? 'pending';
$allowed    = ['pending', 'approved', 'shipped', 'delivered', 'cancelled'];
if (!$order_id || !in_array($new_status, $allowed, true)) {
    http_response_code(400);
    die('Neispravan zahtjev.');
}

$chk = $pdo->prepare("SELECT o.status
                      FROM orders o
                      WHERE o.id = ?
                        AND EXISTS (
                          SELECT 1
                          FROM order_items oi
                          JOIN products p ON p.id = oi.product_id
                          WHERE oi.order_id = o.id AND p.seller_id = ?
                        )");
$chk->execute([$order_id, $seller_id]);
$current = $chk->fetchColumn();

if ($current === false) {
    http_response_code(403);
    die('Nije dopušteno.');
}

if ($current === 'cancelled') {
    http_response_code(400);
    die('Narudžba je otkazana i status se više ne može mijenjati.');
}

$upd = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?');
$upd->execute([$new_status, $order_id]);

header('Location: ' . BASE_URL . '/seller/orders.php');
