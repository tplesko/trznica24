<?php
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_seller();
$pdo = db();
$uid = user_id();


// seller_id
$stmt = $pdo->prepare('SELECT id FROM sellers WHERE user_id=?');
$stmt->execute([$uid]);
$seller = $stmt->fetch();
if (!$seller) {
    header('Location: ' . BASE_URL . '/seller/profile.php');
    exit;
}
$seller_id = (int)$seller['id'];


$types = $pdo->query('SELECT id,name FROM product_types ORDER BY name')->fetchAll();
$counties = $pdo->query('SELECT id,name FROM counties ORDER BY name')->fetchAll();
$units = ['kom' => 'Komad', 'kg' => 'Kilogram'];


$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$product = null;
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id=? AND seller_id=?');
    $stmt->execute([$id, $seller_id]);
    $product = $stmt->fetch();
    if (!$product) {
        http_response_code(404);
        die('Proizvod nije pronađen.');
    }
}


$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $type_id = $_POST['type_id'] ? (int)$_POST['type_id'] : null;
    $price_eur = (float)str_replace(',', '.', $_POST['price_eur'] ?? '0');
    $stock = (int)($_POST['stock'] ?? 0);
    $county_id = $_POST['county_id'] ? (int)$_POST['county_id'] : null;
    $city = trim($_POST['city'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $unit = $_POST['unit'] ?? 'kom';
    
    if (!in_array($unit, array_keys($units), true)) $unit = 'kom';
    if ($name === '') $errors[] = 'Naziv je obavezan.';
    if ($price_eur <= 0) $errors[] = 'Cijena mora biti veća od 0.';
    if ($stock < 0) $errors[] = 'Zaliha ne može biti negativna.';


    if (!$errors) {
        $price_cents = (int)round($price_eur * 100);
        if ($id) {
            $sql = 'UPDATE products SET name=?, description=?, type_id=?, price_cents=?, unit=?, stock=?, is_active=?, county_id=?, city=? WHERE id=? AND seller_id=?';
            $pdo->prepare($sql)->execute([$name,$description,$type_id,$price_cents,$unit,$stock,$is_active,$county_id,$city,$id,$seller_id]);
        } else {
            $sql = 'INSERT INTO products (seller_id, name, description, type_id, price_cents, unit, stock, is_active, county_id, city)
        VALUES (?,?,?,?,?,?,?,?,?,?)';
            $pdo->prepare($sql)->execute([$seller_id,$name,$description,$type_id,$price_cents,$unit,$stock,$is_active,$county_id,$city]);
            $id = (int)$pdo->lastInsertId();
        }


        // Upload slike (opcionalno)
        if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            // 1) odredi ekstenziju preko MIME-a (fileinfo je točniji)
            $allowed = ['image/jpeg' => '.jpg', 'image/png' => '.png', 'image/webp' => '.webp'];

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = $finfo ? finfo_file($finfo, $_FILES['image']['tmp_name']) : null;
            if ($finfo) finfo_close($finfo);

            // fallback ako iz nekog razloga $mime nije prepoznat
            if (!$mime) {
                $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                $map = ['jpg' => '.jpg', 'jpeg' => '.jpg', 'png' => '.png', 'webp' => '.webp'];
                $mime = array_search('.' . $ext, $map, true) ? 'image/' . $ext : null;
            }

            if (!isset($allowed[$mime])) {
                $errors[] = 'Dozvoljeni formati su JPG, PNG ili WEBP.';
            } elseif ($_FILES['image']['size'] > 5 * 1024 * 1024) {
                $errors[] = 'Slika je prevelika (maks 5 MB).';
            } else {
                $subdir = date('Y/m');

                //spremamo u PUBLIC/uploads da URL postoji
                $dir = __DIR__ . '/../../public/uploads/' . $subdir;
                if (!is_dir($dir)) {
                    mkdir($dir, 0777, true);
                }

                $filename = bin2hex(random_bytes(8)) . $allowed[$mime];
                $path = $dir . '/' . $filename;

                if (!move_uploaded_file($_FILES['image']['tmp_name'], $path)) {
                    $errors[] = 'Greška pri spremanju slike.';
                } else {
                    // URL koji browser može dohvatiti
                    $url = BASE_URL . '/uploads/' . $subdir . '/' . $filename;

                    // Postavi kao primary
                    $pdo->prepare('UPDATE product_images SET is_primary=0 WHERE product_id=?')->execute([$id]);
                    $pdo->prepare('INSERT INTO product_images (product_id, url, alt, is_primary) VALUES (?,?,?,1)')
                        ->execute([$id, $url, $name ?: 'proizvod']);
                }
            }
        }

        if (!$errors) {
            header('Location: ' . BASE_URL . '/seller/products.php');
            exit;
        }
    }
}
?>

<!doctype html>
<html lang="hr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= ($id ? 'Uredi' : 'Novi') ?> proizvod — Tržnica24</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
    <div class="container py-4" style="max-width:900px">
        <h1 class="h4 mb-3"><?= ($id ? 'Uredi proizvod' : 'Novi proizvod') ?></h1>
        <?php foreach ($errors as $e): ?><div class="alert alert-danger"><?= h($e) ?></div><?php endforeach; ?>
        <form method="post" enctype="multipart/form-data" class="card p-3" novalidate>
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">Naziv</label>
                    <input name="name" class="form-control" value="<?= h($product['name'] ?? '') ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tip</label>
                    <select name="type_id" class="form-select">
                        <option value="">- odaberi -</option>
                        <?php foreach ($types as $t): ?>
                            <option value="<?= $t['id'] ?>" <?= (($product['type_id'] ?? null) == $t['id'] ? 'selected' : '') ?>><?= h($t['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Opis</label>
                    <textarea name="description" class="form-control" rows="4"><?= h($product['description'] ?? '') ?></textarea>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Cijena (€)</label>
                    <input name="price_eur" type="number" step="0.01" min="0" class="form-control" value="<?= isset($product['price_cents']) ? number_format($product['price_cents'] / 100, 2, '.', '') : '' ?>" required>
                </div>
                <div class="col-md-3">
                <label class="form-label">Jedinica</label>
                <select name="unit" class="form-select">
                    <?php foreach($units as $k=>$label): ?>
                    <option value="<?=$k?>" <?=(($product['unit'] ?? 'kom')===$k?'selected':'')?>><?=$label?></option>
                    <?php endforeach; ?>
                </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Zaliha</label>
                    <input name="stock" type="number" step="0.01" min="0" class="form-control" value="<?= h($product['stock'] ?? 0) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Županija</label>
                    <select name="county_id" class="form-select">
                        <option value="">- odaberi -</option>
                        <?php foreach ($counties as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= (($product['county_id'] ?? null) == $c['id'] ? 'selected' : '') ?>><?= h($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Grad/Mjesto</label>
                    <input name="city" class="form-control" value="<?= h($product['city'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Slika proizvoda (JPG/PNG/WEBP, max 5MB)</label>
                    <input name="image" type="file" accept="image/*" class="form-control">
                </div>
                <div class="col-md-3 form-check mt-4">
                    <input class="form-check-input" type="checkbox" name="is_active" id="act" <?= ((int)($product['is_active'] ?? 1) ? 'checked' : '') ?>>
                    <label class="form-check-label" for="act">Aktivan</label>
                </div>
            </div>
            <div class="mt-3 d-flex gap-2">
                <a class="btn btn-outline-secondary" href="<?= h(BASE_URL) ?>/seller/products.php">Odustani</a>
                <button class="btn btn-success">Spremi</button>
            </div>
        </form>
    </div>
</body>
</html>