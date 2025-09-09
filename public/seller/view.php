<?php
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';

$pdo = db();
$id  = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// profil prodavatelja
$stmt = $pdo->prepare("SELECT s.*, c.name AS county_name
                       FROM sellers s
                       LEFT JOIN counties c ON c.id=s.county_id
                       WHERE s.id=?");
$stmt->execute([$id]);
$seller = $stmt->fetch();
if (!$seller) {
    http_response_code(404);
    die('Prodavatelj nije pronađen.');
}

// proizvodi tog prodavatelja (samo aktivni)
$q = $pdo->prepare("SELECT p.id, p.name, p.price_cents, p.unit, p.city,
                   (SELECT url FROM product_images WHERE product_id=p.id AND is_primary=1 LIMIT 1) AS image_url
                   FROM products p
                   WHERE p.seller_id=? AND p.is_active=1
                   ORDER BY p.created_at DESC");
$q->execute([$id]);
$products = $q->fetchAll();
?>
<!doctype html>
<html lang="hr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= h($seller['display_name']) ?> — Tržnica24</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
    <?php require_once __DIR__ . '/../partials/nav.php'; ?>

    <div class="container py-4" style="max-width:1100px">
        <div class="card mb-4">
            <div class="card-body">
                <h1 class="h4 mb-1"><?= h($seller['display_name']) ?> <span class="badge text-bg-success ms-2"><?= h($seller['legal_type']) ?></span></h1>
                <div class="text-muted small mb-2">
                    <?= h($seller['county_name'] ?? '') ?>
                    <?= $seller['city'] ? '· ' . h($seller['city']) : '' ?>
                    <?= $seller['address'] ? '· ' . h($seller['address']) : '' ?>
                </div>
                <div><?= nl2br(h($seller['about'] ?? '')) ?></div>
            </div>
        </div>

        <h2 class="h5 mb-3">Proizvodi</h2>
        <?php if (!$products): ?>
            <div class="alert alert-info">Nema objavljenih proizvoda.</div>
        <?php else: ?>
            <div class="row g-3" id="prodGrid">
                <?php foreach ($products as $p): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100">
                            <img src="<?= h($p['image_url'] ?: 'https://via.placeholder.com/600x400?text=Proizvod') ?>"
                                class="card-img-top" alt="" style="height:220px;object-fit:cover;">
                            <div class="card-body d-flex flex-column">
                                <h6 class="card-title mb-1"><?= h($p['name']) ?></h6>
                                <div class="small text-muted mb-2">
                                    <?= h($seller['county_name'] ?? '') ?> <?= $p['city'] ? '· ' . h($p['city']) : '' ?>
                                </div>
                                <div class="fw-bold mb-2"><?= number_format($p['price_cents'] / 100, 2) ?> €/<?= h($p['unit'] ?? 'kom') ?></div>
                                <button class="btn btn-sm btn-success mt-auto" data-add="<?= $p['id'] ?>">Dodaj u košaricu</button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateCartBadge(count) {
            const b = document.getElementById('cartCount');
            if (!b) return;
            b.textContent = count;
            b.classList.toggle('d-none', !count);
        }
        document.getElementById('prodGrid')?.addEventListener('click', async (e) => {
            const btn = e.target.closest('[data-add]');
            if (!btn) return;
            const product_id = Number(btn.getAttribute('data-add'));
            const res = await fetch('<?= h(BASE_URL) ?>/api/cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    product_id,
                    qty: 1
                })
            });
            if (res.status === 401) {
                location.href = '<?= h(BASE_URL) ?>/auth/login.php';
                return;
            }
            const data = await res.json();
            if (data.ok) {
                btn.textContent = 'Dodano ✓';
                updateCartBadge(data.count);
                setTimeout(() => btn.textContent = 'Dodaj u košaricu', 900);
            } else {
                alert(data.error || 'Greška');
            }
        });
    </script>
</body>

</html>