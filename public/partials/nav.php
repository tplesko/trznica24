<?php
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
$u = user();
$cartCount = 0;
if ($u) {
  $stmt = db()->prepare(
    'SELECT COUNT(DISTINCT ci.product_id)
       FROM cart_items ci
       JOIN products p ON p.id = ci.product_id
      WHERE ci.user_id = ? AND ci.qty > 0'
  );
  $stmt->execute([$u['id']]);
  $cartCount = (int)$stmt->fetchColumn();
}

?>
<nav class="navbar navbar-expand-lg bg-white border-bottom mb-3">
  <div class="container">
    <a class="navbar-brand" href="<?= h(BASE_URL) ?>/">Tržnica24</a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div id="navMain" class="collapse navbar-collapse">
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a class="nav-link" href="<?= h(BASE_URL) ?>/">Početna</a></li>
        <?php if ($u && ($u['role'] ?? 'buyer') === 'seller'): ?>
          <li class="nav-item"><a class="nav-link" href="<?= h(BASE_URL) ?>/seller/profile.php">Moj profil</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= h(BASE_URL) ?>/seller/products.php">Moji proizvodi</a></li>
          <li class="nav-item"><a class="nav-link" href="<?=h(BASE_URL)?>/seller/orders.php">Narudžbe (prodaja)</a></li>
        <?php endif; ?>
        <?php if ($u): ?>
          <li class="nav-item position-relative">
            <a class="nav-link" href="<?= h(BASE_URL) ?>/cart.php">
              Košarica
              <span id="cartCount" class="badge text-bg-danger ms-1 <?= $cartCount ? '' : 'd-none' ?>"><?= $cartCount ?></span>
            </a>
          </li>
          <li class="nav-item"><a class="nav-link" href="<?= h(BASE_URL) ?>/orders.php">Narudžbe</a></li>
        <?php endif; ?>
      </ul>

      <div class="ms-auto d-flex gap-2">
        <?php if ($u): ?>
          <span class="align-self-center small text-muted d-none d-md-inline"><?= h($u['name'] ?: $u['email']) ?></span>
          <a class="btn btn-outline-secondary btn-sm" href="<?= h(BASE_URL) ?>/logout.php">Odjava</a>
        <?php else: ?>
          <a class="btn btn-primary btn-sm" href="<?= h(BASE_URL) ?>/auth/login.php">Prijava</a>
          <a class="btn btn-outline-primary btn-sm" href="<?= h(BASE_URL) ?>/auth/register.php">Registracija</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>