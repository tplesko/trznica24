<?php
require_once __DIR__ . '/../../lib/db.php';
header('Content-Type: application/json; charset=utf-8');
$pdo = db();
$q = trim($_GET['q'] ?? '');
$type_id = $_GET['type_id'] ?? '';
$county_id = $_GET['county_id'] ?? '';
$city = trim($_GET['city'] ?? '');


$sql = "SELECT p.id, p.name, p.price_cents, p.unit, p.city,
               c.name AS county_name,
               s.id AS seller_id, s.display_name AS seller_name,
               (SELECT url FROM product_images WHERE product_id=p.id AND is_primary=1 LIMIT 1) AS image_url
        FROM products p
        LEFT JOIN counties c ON c.id = p.county_id
        JOIN sellers s ON s.id = p.seller_id
        WHERE p.is_active=1";

$params = [];
if ($q !== '') {
    $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if ($type_id !== '') {
    $sql .= " AND p.type_id = ?";
    $params[] = $type_id;
}
if ($county_id !== '') {
    $sql .= " AND p.county_id = ?";
    $params[] = $county_id;
}
if ($city !== '') {
    $sql .= " AND p.city LIKE ?";
    $params[] = "%$city%";
}
$sql .= " ORDER BY p.created_at DESC LIMIT 30";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
echo json_encode(['items' => $stmt->fetchAll()], JSON_UNESCAPED_UNICODE);
