<?php
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_seller();
function status_badge_class(string $s): string
{
  return [
    'pending'   => 'warning',   // žuta
    'approved'  => 'primary',   // plava
    'shipped'   => 'info',      // svijetloplava
    'delivered' => 'success',   // zelena
    'cancelled' => 'danger',    // crvena
  ][$s] ?? 'secondary';
}
function status_label(string $s): string
{
  return [
    'pending'   => 'Na čekanju',
    'approved'  => 'Odobreno',
    'shipped'   => 'Poslano',
    'delivered' => 'Isporučeno',
    'cancelled' => 'Otkazano',
  ][$s] ?? ucfirst($s);
}
$allowed = ['pending', 'approved', 'shipped', 'delivered', 'cancelled'];
$stParam = trim($_GET['status'] ?? '');
$statusFilter = $stParam !== '' ? array_intersect([$stParam], $allowed) : [];
$date_from = trim($_GET['date_from'] ?? '');
$date_to   = trim($_GET['date_to'] ?? '');
$sort      = strtolower(trim($_GET['sort'] ?? 'desc'));
$orderDir  = $sort === 'asc' ? 'ASC' : 'DESC';

$pdo = db();
$uid = user_id();

// seller_id
$s = $pdo->prepare('SELECT id, display_name FROM sellers WHERE user_id=?');
$s->execute([$uid]);
$seller = $s->fetch();
if (!$seller) {
  header('Location: ' . BASE_URL . '/seller/profile.php');
  exit;
}

$where = [
  "EXISTS (SELECT 1 FROM order_items oi
           JOIN products p ON p.id=oi.product_id
           WHERE oi.order_id = o.id AND p.seller_id = ?)"
];
$params = [$seller['id']];

if ($statusFilter) {
  $where[] = "o.status = ?";
  $params[] = reset($statusFilter);
}
if ($date_from !== '') {
  $where[] = "DATE(o.created_at) >= ?";
  $params[] = $date_from;
}
if ($date_to   !== '') {
  $where[] = "DATE(o.created_at) <= ?";
  $params[] = $date_to;
}

$sql = "SELECT o.id, o.created_at, o.total_cents, o.status, o.shipping_address, o.shipping_phone, o.shipping_note
        FROM orders o
        WHERE " . implode(' AND ', $where) . "
        ORDER BY o.created_at $orderDir";

$os = $pdo->prepare($sql);
$os->execute($params);
$orders = $os->fetchAll();


function fmtEur($c)
{
  return number_format($c / 100, 2);
}
?>
<!doctype html>
<html lang="hr">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Narudžbe prodavatelja — Tržnica24</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
  <?php require_once __DIR__ . '/../partials/nav.php'; ?>

  <div class="container py-4" style="max-width:1100px">
    <h1 class="h4 mb-3">Narudžbe za: <?= h($seller['display_name']) ?></h1>
    <form class="row g-2 align-items-end mb-3" method="get">
      <div class="col-sm-4 col-md-3">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
          <?php
          $opts = ['' => 'Svi', 'pending' => 'Na čekanju', 'approved' => 'Odobreno', 'shipped' => 'Poslano', 'delivered' => 'Isporučeno', 'cancelled' => 'Otkazano'];
          $cur = (string)($_GET['status'] ?? '');
          foreach ($opts as $val => $label) {
            $sel = $cur === $val ? 'selected' : '';
            echo "<option value=\"" . h($val) . "\" $sel>" . h($label) . "</option>";
          }
          ?>
        </select>
      </div>
      <div class="col-sm-4 col-md-3">
        <label class="form-label">Od datuma</label>
        <input type="date" name="date_from" value="<?= h($_GET['date_from'] ?? '') ?>" class="form-control">
      </div>
      <div class="col-sm-4 col-md-3">
        <label class="form-label">Do datuma</label>
        <input type="date" name="date_to" value="<?= h($_GET['date_to'] ?? '') ?>" class="form-control">
      </div>
      <div class="col-sm-4 col-md-2">
        <label class="form-label">Sort</label>
        <select name="sort" class="form-select">
          <option value="desc" <?= (($_GET['sort'] ?? 'desc') === 'desc' ? 'selected' : '') ?>>Najnovije</option>
          <option value="asc" <?= (($_GET['sort'] ?? '') === 'asc' ? 'selected' : '')  ?>>Najstarije</option>
        </select>
      </div>
      <div class="col-sm-4 col-md-1">
        <button class="btn btn-success w-100">Primijeni</button>
      </div>
    </form>

    <?php if (!$orders): ?>
      <div class="alert alert-info">Još nema narudžbi.</div>
      <?php else: foreach ($orders as $o):
        $itemsStmt = $pdo->prepare("SELECT p.name, p.unit, oi.qty, oi.unit_price_cents
                            FROM order_items oi
                            JOIN products p ON p.id=oi.product_id
                            WHERE oi.order_id=? AND p.seller_id=?");
        $itemsStmt->execute([$o['id'], $seller['id']]);
        $items = $itemsStmt->fetchAll();
        $subTotal = 0;
        foreach ($items as $it) {
          $subTotal += $it['unit_price_cents'] * $it['qty'];
        }
      ?>
        <div class="card mb-3">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <div class="fw-bold">Narudžba #<?= $o['id'] ?></div>
                <?php $cls = status_badge_class($o['status']);
                $lbl = status_label($o['status']); ?>
                <div class="text-muted small">
                  <?= $o['created_at'] ?> · Status: <span class="badge text-bg-<?= $cls ?>"><?= $lbl ?></span>
                </div>
              </div>
              <div class="text-end">
                <div class="fw-bold">Tvoj dio: <?= fmtEur($subTotal) ?> €</div>
              </div>
            </div>

            <div class="row mt-3">
              <div class="col-md-7">
                <div class="table-responsive">
                  <table class="table table-sm mb-0">
                    <thead>
                      <tr>
                        <th>Proizvod</th>
                        <th class="text-center">Količina</th>
                        <th class="text-end">Cijena</th>
                        <th class="text-end">Ukupno</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($items as $it): ?>
                        <tr>
                          <td><?= h($it['name']) ?></td>
                          <td class="text-center"><?= rtrim(rtrim(number_format($it['qty'], 3, '.', ''), '0'), '.') ?> <?= h($it['unit']) ?></td>
                          <td class="text-end"><?= fmtEur($it['unit_price_cents']) ?> €/<?= h($it['unit']) ?></td>
                          <td class="text-end"><?= fmtEur($it['unit_price_cents'] * $it['qty']) ?> €</td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
              <div class="col-md-5">
                <div class="border rounded p-2 bg-light">
                  <div class="fw-bold mb-1">Dostava kupcu</div>
                  <div class="small">Adresa:<br><?= nl2br(h($o['shipping_address'] ?? '—')) ?></div>
                  <div class="small mt-1">Telefon: <?= h($o['shipping_phone'] ?? '—') ?></div>
                  <?php if ($o['shipping_note']): ?><div class="small mt-1">Napomena: <?= nl2br(h($o['shipping_note'])) ?></div><?php endif; ?>
                </div>
                <?php if ($o['status'] === 'cancelled'): ?>
                  <div class="mt-2 small text-muted">
                    <span class="badge text-bg-danger">Otkazano</span> — status se više ne može mijenjati.
                  </div>
                <?php else: ?>
                  <form class="d-flex gap-2 mt-2" method="post" action="<?= h(BASE_URL) ?>/api/order_status.php" onsubmit="return confirm('Promijeniti status?')">
                    <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                    <select name="status" class="form-select">
                      <?php foreach (['pending' => 'na čekanju', 'approved' => 'odobreno', 'shipped' => 'poslano', 'delivered' => 'isporučeno', 'cancelled' => 'otkazano'] as $val => $label): ?>
                        <option value="<?= $val ?>" <?= $o['status'] === $val ? 'selected' : '' ?>><?= $label ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button class="btn btn-primary">Spremi</button>
                  </form>
                <?php endif; ?>
              </div>
            </div>

          </div>
        </div>
    <?php endforeach;
    endif; ?>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>