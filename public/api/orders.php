<?php
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
header('Content-Type: application/json; charset=utf-8');

if (!user()) {
    http_response_code(401);
    echo json_encode(['error' => 'Prijavi se']);
    exit;
}
$pdo = db();
$uid = (int)user()['id'];
$method = $_SERVER['REQUEST_METHOD'];

function json_input(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

if ($method === 'GET') {
    header('Content-Type: application/json; charset=utf-8');

    $allowed = ['pending', 'approved', 'shipped', 'delivered', 'cancelled'];

    $statusParam = trim($_GET['status'] ?? ''); 
    $statuses = array_values(array_filter(array_map('trim', explode(',', $statusParam))));
    $statuses = array_values(array_intersect($statuses, $allowed)); 

    $date_from = trim($_GET['date_from'] ?? ''); 
    $date_to   = trim($_GET['date_to']   ?? '');
    $sort      = strtolower(trim($_GET['sort'] ?? 'desc'));
    $orderDir  = $sort === 'asc' ? 'ASC' : 'DESC';

    $where = ["user_id = ?"];
    $params = [$uid];

    if ($statuses) {
        $in = implode(',', array_fill(0, count($statuses), '?'));
        $where[] = "status IN ($in)";
        array_push($params, ...$statuses);
    }
    if ($date_from !== '') {
        $where[] = "DATE(created_at) >= ?";
        $params[] = $date_from;
    }
    if ($date_to !== '') {
        $where[] = "DATE(created_at) <= ?";
        $params[] = $date_to;
    }

    $sql = "SELECT id, total_cents, status, shipping_address, shipping_phone, shipping_note, created_at
          FROM orders
          WHERE " . implode(' AND ', $where) . "
          ORDER BY created_at $orderDir";

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $orders = $st->fetchAll();

    if (!$orders) {
        echo json_encode(['orders' => []]);
        exit;
    }

    $ids = array_column($orders, 'id');
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("SELECT oi.order_id, oi.product_id, oi.qty, oi.unit_price_cents, p.name, p.unit
                       FROM order_items oi JOIN products p ON p.id=oi.product_id
                       WHERE oi.order_id IN ($in)");
    $st->execute($ids);
    $items = $st->fetchAll();
    $by = [];
    foreach ($items as $it) {
        $by[$it['order_id']][] = $it;
    }
    foreach ($orders as &$o) {
        $o['items'] = $by[$o['id']] ?? [];
    }

    echo json_encode(['orders' => $orders], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method === 'POST') {
    $in = json_input();
    $shipping_address = trim($in['shipping_address'] ?? '');
    $shipping_phone   = trim($in['shipping_phone'] ?? '');
    $shipping_note    = trim($in['shipping_note'] ?? '');

    if ($shipping_address === '' || $shipping_phone === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Adresa i telefon su obavezni']);
        exit;
    }

    $pdo->beginTransaction();
    try {
        // Zaključaj stavke u košarici
        $st = $pdo->prepare("SELECT p.id, p.stock, p.price_cents, p.is_active, ci.qty
                          FROM cart_items ci
                          JOIN products p ON p.id=ci.product_id
                          WHERE ci.user_id=? FOR UPDATE");
        $st->execute([$uid]);
        $rows = $st->fetchAll();
        if (!$rows) throw new Exception('Košarica je prazna.');

        $total = 0;
        foreach ($rows as $r) {
            if (!(int)$r['is_active']) throw new Exception('Proizvod nije dostupan.');
            $qty = (float)$r['qty'];
            if ($qty <= 0) throw new Exception('Neispravna količina.');
            if ($qty > (float)$r['stock']) throw new Exception('Nema dovoljno zaliha za jedan od proizvoda.');
            $total += (int)round($r['price_cents'] * $qty);
        }

        // Kreiraj narudžbu (status: pending)
        $pdo->prepare("INSERT INTO orders (user_id, total_cents, status, shipping_address, shipping_phone, shipping_note)
                   VALUES (?,?, 'pending', ?, ?, ?)")
            ->execute([$uid, $total, $shipping_address, $shipping_phone, $shipping_note]);
        $orderId = (int)$pdo->lastInsertId();

        // Stavi stavke + umanji zalihu
        $upd = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id=?');
        $ins = $pdo->prepare('INSERT INTO order_items (order_id, product_id, qty, unit_price_cents) VALUES (?,?,?,?)');
        foreach ($rows as $r) {
            $qty = (float)$r['qty'];
            $upd->execute([$qty, (int)$r['id']]);
            $ins->execute([$orderId, (int)$r['id'], $qty, (int)$r['price_cents']]);
        }

        // Isprazni košaricu
        $pdo->prepare('DELETE FROM cart_items WHERE user_id=?')->execute([$uid]);

        $pdo->commit();
        echo json_encode(['ok' => true, 'order_id' => $orderId]);
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage() ?: 'Greška pri narudžbi']);
        exit;
    }
}

http_response_code(405);
echo json_encode(['error' => 'Metoda nije podržana']);
