<?php
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_seller();
$pdo = db();
$uid = user_id();

// Dohvati seller_id
$stmt = $pdo->prepare('SELECT id, display_name FROM sellers WHERE user_id=?');
$stmt->execute([$uid]);
$seller = $stmt->fetch();
if (!$seller) {
    header('Location: ' . BASE_URL . '/seller/profile.php?edit=1');
    exit;
}

$sql = "SELECT p.id, p.name, p.price_cents, p.unit, p.stock, p.is_active, p.created_at,
        (SELECT url FROM product_images WHERE product_id=p.id AND is_primary=1 LIMIT 1) AS image_url
        FROM products p WHERE p.seller_id=? ORDER BY p.created_at DESC";

$itemsStmt = $pdo->prepare($sql);
$itemsStmt->execute([$seller['id']]);
$items = $itemsStmt->fetchAll();
?>
<!doctype html>
<html lang="hr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Moji proizvodi — Tržnica24</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
    <?php require_once __DIR__ . '/../partials/nav.php'; ?>

    <div class="container py-4">
        <div class="d-flex align-items-center mb-3">
            <h1 class="h4 mb-0 flex-grow-1">Moji proizvodi</h1>
            <a class="btn btn-success" href="<?= h(BASE_URL) ?>/seller/product_form.php">+ Novi proizvod</a>
        </div>

        <?php if (!$items): ?>
            <div class="alert alert-info">Još nemaš proizvoda. Dodaj prvi!</div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($items as $p): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100">
                            <img src="<?=h($p['image_url'] ?: 'https://via.placeholder.com/600x400?text=Proizvod')?>" class="card-img-top" alt="" style="height:180px; object-fit:cover;">
                            <div class="card-body d-flex flex-column">
                                <h6 class="card-title mb-1"><?= h($p['name'] ?? 'Bez naziva') ?></h6>
                                <div class="small text-muted mb-2">
                                    Zaliha: <?=$p['stock']?> · Cijena: <?=number_format(($p['price_cents']??0)/100,2)?> €/<?=h($p['unit'] ?? 'kom')?>
                                    <?= $p['is_active'] ? '' : '<span class="badge text-bg-secondary ms-1">Neaktivan</span>' ?>
                                </div>
                                <div class="mt-auto d-flex gap-2">
                                    <a class="btn btn-outline-primary btn-sm" href="<?= h(BASE_URL) ?>/seller/product_form.php?id=<?= $p['id'] ?>">Uredi</a>
                                    <a class="btn btn-outline-danger btn-sm" href="<?= h(BASE_URL) ?>/seller/product_delete.php?id=<?= $p['id'] ?>" onclick="return confirm('Obrisati proizvod?')">Obriši</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
<?php require_once __DIR__ . '/../partials/nav.php'; ?>

</html>