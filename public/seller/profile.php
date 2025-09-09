<?php
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_seller();

$pdo = db();
$uid = user_id();

// Dohvati profil
$stmt = $pdo->prepare('SELECT s.*, c.name AS county_name
                       FROM sellers s
                       LEFT JOIN counties c ON c.id = s.county_id
                       WHERE s.user_id = ?');
$stmt->execute([$uid]);
$seller = $stmt->fetch();

$legalTypes = ['OPG', 'PG', 'FIZ', 'OBRT'];
$counties = $pdo->query('SELECT id,name FROM counties ORDER BY name')->fetchAll();

$errors = [];

// Ako POST -> spremi i PRG redirect na VIEW
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $legal_type   = $_POST['legal_type'] ?? 'OPG';
    $display_name = trim($_POST['display_name'] ?? '');
    $about        = trim($_POST['about'] ?? '');
    $county_id    = $_POST['county_id'] !== '' ? (int)$_POST['county_id'] : null;
    $city         = trim($_POST['city'] ?? '');
    $address      = trim($_POST['address'] ?? '');

    if (!in_array($legal_type, $legalTypes, true)) $legal_type = 'OPG';
    if ($display_name === '') $errors[] = 'Naziv prikaza je obavezan.';

    if (!$errors) {
        if ($seller) {
            $sql = 'UPDATE sellers SET legal_type=?, display_name=?, about=?, county_id=?, city=?, address=? WHERE user_id=?';
            $pdo->prepare($sql)->execute([$legal_type, $display_name, $about, $county_id, $city, $address, $uid]);
        } else {
            $sql = 'INSERT INTO sellers (user_id, legal_type, display_name, about, county_id, city, address) VALUES (?,?,?,?,?,?,?)';
            $pdo->prepare($sql)->execute([$uid, $legal_type, $display_name, $about, $county_id, $city, $address]);
        }
        header('Location: ' . BASE_URL . '/seller/profile.php?saved=1'); // PRG
        exit;
    }
    // Ako ima grešaka, ostani u edit modu
    $mode = 'edit';
} else {
    // GET: ako nema profila -> odmah u edit; inače view
    $mode = $seller ? 'view' : 'edit';
}

// Ako smo u VIEW modu, dohvatimo proizvode tog sellera
$products = [];
if ($mode === 'view') {
    $sid = (int)$seller['id'];
    $q = $pdo->prepare("SELECT p.id, p.name, p.price_cents, p.unit, p.stock, p.is_active, p.created_at,
   (SELECT url FROM product_images WHERE product_id=p.id AND is_primary=1 LIMIT 1) AS image_url
   FROM products p WHERE p.seller_id=? ORDER BY p.created_at DESC");

    $q->execute([$sid]);
    $products = $q->fetchAll();
}
?>
<!doctype html>
<html lang="hr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Moj profil — Tržnica24</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
    <?php require_once __DIR__ . '/../partials/nav.php'; ?>

    <div class="container py-4" style="max-width:1100px">
        <div class="d-flex align-items-center mb-3">
            <h1 class="h4 mb-0 flex-grow-1">Moj profil</h1>
            <?php if ($mode === 'view'): ?>
                <a class="btn btn-outline-primary" href="<?= h(BASE_URL) ?>/seller/profile.php?edit=1">Uredi profil</a>
                <a class="btn btn-success ms-2" href="<?= h(BASE_URL) ?>/seller/product_form.php">+ Novi proizvod</a>
            <?php endif; ?>
        </div>

        <?php foreach ($errors as $e): ?>
            <div class="alert alert-danger"><?= h($e) ?></div>
        <?php endforeach; ?>

        <?php if ($mode === 'edit' || isset($_GET['edit'])): ?>
            <?php // EDIT MODE 
            ?>
            <form method="post" class="card p-3" novalidate>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Pravni oblik</label>
                        <select name="legal_type" class="form-select">
                            <?php foreach ($legalTypes as $lt): ?>
                                <option value="<?= $lt ?>" <?= (($seller['legal_type'] ?? 'OPG') === $lt ? 'selected' : '') ?>><?= $lt ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Naziv prikaza (OPG/PG...)</label>
                        <input name="display_name" class="form-control" value="<?= h($seller['display_name'] ?? '') ?>" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">O nama</label>
                        <textarea name="about" class="form-control" rows="4"><?= h($seller['about'] ?? '') ?></textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Županija</label>
                        <select name="county_id" class="form-select">
                            <option value="">- odaberi -</option>
                            <?php foreach ($counties as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= (($seller['county_id'] ?? null) == $c['id'] ? 'selected' : '') ?>><?= h($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Grad/Mjesto</label>
                        <input name="city" class="form-control" value="<?= h($seller['city'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Adresa</label>
                        <input name="address" class="form-control" value="<?= h($seller['address'] ?? '') ?>">
                    </div>
                </div>
                <div class="mt-3 d-flex gap-2">
                    <a class="btn btn-outline-secondary" href="<?= h(BASE_URL) ?>/seller/profile.php">Odustani</a>
                    <button class="btn btn-success">Spremi</button>
                </div>
            </form>

        <?php else: ?>
            <?php // VIEW MODE 
            ?>
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="mb-1"><?= h($seller['display_name']) ?> <span class="badge text-bg-success ms-2"><?= h($seller['legal_type']) ?></span></h5>
                    <div class="text-muted small mb-2">
                        <?= h($seller['county_name'] ?? '') ?> <?= $seller['city'] ? '· ' . h($seller['city']) : '' ?> <?= $seller['address'] ? '· ' . h($seller['address']) : '' ?>
                    </div>
                    <div><?= nl2br(h($seller['about'] ?? '')) ?></div>
                </div>
            </div>

            <h2 class="h5 mb-3">Objavljeni proizvodi</h2>
            <?php if (!$products): ?>
                <div class="alert alert-info">Još nemaš proizvoda. Klikni “Novi proizvod”.</div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($products as $p): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card h-100">
                                <img src="<?=h($p['image_url'] ?: 'https://via.placeholder.com/600x400?text=Proizvod')?>" class="card-img-top" alt="" style="height:180px; object-fit:cover;">                                <div class="card-body d-flex flex-column">
                                    <h6 class="card-title mb-1"><?= h($p['name'] ?? 'Bez naziva') ?></h6>
                                    <div class="small text-muted mb-2">
                                        Zaliha: <?=$p['stock']?> · Cijena: <?=number_format(($p['price_cents']??0)/100,2)?> €/<?=h($p['unit'] ?? 'kom')?>
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
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
<?php require_once __DIR__ . '/../partials/nav.php'; ?>

</html>