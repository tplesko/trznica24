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
    return is_array($data) ? $data : $_POST;
}

if ($method === 'GET') {
    header('Content-Type: application/json; charset=utf-8');

    $sql = "SELECT ci.id AS item_id, ci.product_id, ci.qty,
                   p.name, p.price_cents, p.unit, p.stock,
                   (SELECT url FROM product_images WHERE product_id=p.id AND is_primary=1 LIMIT 1) AS image_url
            FROM cart_items ci
            JOIN products p ON p.id = ci.product_id
            WHERE ci.user_id = ? AND ci.qty > 0";
    $st = $pdo->prepare($sql);
    $st->execute([$uid]);

    $items = $st->fetchAll();

    $total = 0;
    foreach ($items as $it) {
        $total += (int) round(((int)$it['price_cents']) * (float)$it['qty']);
    }

    echo json_encode(['items' => $items, 'total_cents' => $total], JSON_UNESCAPED_UNICODE);
    exit;
}


$data = json_input();

if ($method === 'POST') {
    $pid = (int)($data['product_id'] ?? 0);
    $qty = (float)($data['qty'] ?? 1);
    if ($pid <= 0 || $qty <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Neispravni podaci']);
        exit;
    }

    $st = $pdo->prepare("SELECT id, stock, is_active FROM products WHERE id=?");
    $st->execute([$pid]);
    $p = $st->fetch();
    if (!$p || !(int)$p['is_active']) {
        http_response_code(404);
        echo json_encode(['error' => 'Proizvod nije dostupan']);
        exit;
    }

    $st = $pdo->prepare("SELECT qty FROM cart_items WHERE user_id=? AND product_id=?");
    $st->execute([$uid, $pid]);
    $cur = (float)$st->fetchColumn();
    $newQty = $cur + $qty;
    if ($newQty > (float)$p['stock']) $newQty = (float)$p['stock'];

    if ($newQty <= 0) {
        $pdo->prepare("DELETE FROM cart_items WHERE user_id=? AND product_id=?")->execute([$uid, $pid]);
    } else {
        $pdo->prepare("INSERT INTO cart_items (user_id, product_id, qty) VALUES (?,?,?)
                   ON DUPLICATE KEY UPDATE qty=VALUES(qty)")
            ->execute([$uid, $pid, $newQty]);
    }
    $st = $pdo->prepare(
    "SELECT COUNT(*)
        FROM cart_items ci
        JOIN products p ON p.id = ci.product_id
        WHERE ci.user_id = ? AND ci.qty > 0"
    );
    $st->execute([$uid]);
    $count = (int)$st->fetchColumn();
    echo json_encode(['ok'=>true,'count'=>$count]); exit;

}

if ($method === 'PUT') {
    $pid = isset($data['product_id']) ? (int)$data['product_id'] : 0;
    $itemId = isset($data['item_id']) ? (int)$data['item_id'] : 0;
    $qty = max(0.0, (float)($data['qty'] ?? 0));
    if (!$pid && !$itemId) {
        http_response_code(400);
        echo json_encode(['error' => 'Nedostaje item_id ili product_id']);
        exit;
    }

    if ($pid) {
        $st = $pdo->prepare("SELECT stock FROM products WHERE id=?");
        $st->execute([$pid]);
        $stock = (float)$st->fetchColumn();
        if ($qty > $stock) $qty = $stock;
        if ($qty <= 0) {
            $pdo->prepare("DELETE FROM cart_items WHERE user_id=? AND product_id=?")->execute([$uid, $pid]);
        } else {
            $pdo->prepare("UPDATE cart_items SET qty=? WHERE user_id=? AND product_id=?")->execute([$qty, $uid, $pid]);
        }
    } else {
        $st = $pdo->prepare("SELECT ci.product_id, p.stock FROM cart_items ci JOIN products p ON p.id=ci.product_id WHERE ci.id=? AND ci.user_id=?");
        $st->execute([$itemId, $uid]);
        $row = $st->fetch();
        if (!$row) {
            http_response_code(404);
            echo json_encode(['error' => 'Stavka nije pronađena']);
            exit;
        }
        $stock = (float)$row['stock'];
        if ($qty > $stock) $qty = $stock;
        if ($qty <= 0) {
            $pdo->prepare("DELETE FROM cart_items WHERE id=? AND user_id=?")->execute([$itemId, $uid]);
        } else {
            $pdo->prepare("UPDATE cart_items SET qty=? WHERE id=? AND user_id=?")->execute([$qty, $itemId, $uid]);
        }
    }
    $st = $pdo->prepare(
    "SELECT COUNT(*)
        FROM cart_items ci
        JOIN products p ON p.id = ci.product_id
        WHERE ci.user_id = ? AND ci.qty > 0"
    );
    $st->execute([$uid]);
    $count = (int)$st->fetchColumn();
    echo json_encode(['ok'=>true,'count'=>$count]); exit;
}

if ($method === 'DELETE') {
    $pid = isset($data['product_id']) ? (int)$data['product_id'] : 0;
    $itemId = isset($data['item_id']) ? (int)$data['item_id'] : 0;
    if ($pid) {
        $pdo->prepare("DELETE FROM cart_items WHERE user_id=? AND product_id=?")->execute([$uid, $pid]);
    } elseif ($itemId) {
        $pdo->prepare("DELETE FROM cart_items WHERE id=? AND user_id=?")->execute([$itemId, $uid]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Nedostaje item_id ili product_id']);
        exit;
    }
    $st = $pdo->prepare(
    "SELECT COUNT(*)
        FROM cart_items ci
        JOIN products p ON p.id = ci.product_id
        WHERE ci.user_id = ? AND ci.qty > 0"
    );
    $st->execute([$uid]);
    $count = (int)$st->fetchColumn();
    echo json_encode(['ok'=>true,'count'=>$count]); exit;
}

http_response_code(405);
echo json_encode(['error' => 'Metoda nije podržana']);
