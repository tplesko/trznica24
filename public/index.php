<?php
// Dovoljno je povući auth.php — on već include-a db.php (koji include-a config.php)
require_once __DIR__ . '/../lib/auth.php';

$u = user();
$pdo = db();

$types = $pdo->query("SELECT id, name FROM product_types ORDER BY name")->fetchAll();
$counties = $pdo->query("SELECT id, name FROM counties ORDER BY name")->fetchAll();
?>
<!doctype html>
<html lang="hr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tržnica24</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
    <?php require_once __DIR__ . '/partials/nav.php'; ?>

    <main class="container">
        <div class="row g-3">
            <aside class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Filteri</h5>

                        <div class="mb-2">
                            <label class="form-label">Pretraga</label>
                            <input id="q" class="form-control" placeholder="npr. jagode">
                        </div>

                        <div class="mb-2">
                            <label class="form-label">Tip proizvoda</label>
                            <select id="type" class="form-select">
                                <option value="">Svi</option>
                                <?php foreach ($types as $t): ?>
                                    <option value="<?= $t['id'] ?>"><?= h($t['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-2">
                            <label class="form-label">Županija</label>
                            <select id="county" class="form-select">
                                <option value="">Sve</option>
                                <?php foreach ($counties as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= h($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-2">
                            <label class="form-label">Grad/Mjesto</label>
                            <input id="city" class="form-control" placeholder="npr. Osijek">
                        </div>

                        <button id="btnFilter" class="btn btn-success w-100">Primijeni</button>
                    </div>
                </div>
            </aside>

            <section class="col-md-9">
                <div id="products" class="row g-3"></div>
                <div id="empty" class="text-muted text-center py-5 d-none">Nema rezultata.</div>
            </section>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const $ = sel => document.querySelector(sel);

        function renderProducts(items) {
            const grid = $('#products');
            grid.innerHTML = '';
            if (!items.length) {
                $('#empty').classList.remove('d-none');
                return;}
            $('#empty').classList.add('d-none');

            for (const p of items) {
                const col = document.createElement('div');
                col.className = 'col-md-6 col-lg-4';
                col.innerHTML = `
                <div class="card h-100">
                    <img src="${p.image_url || 'https://via.placeholder.com/600x400?text=Proizvod'}"
                        class="card-img-top" alt=""
                        style="height:220px; object-fit:cover;">
                    <div class="card-body d-flex flex-column">
                    <h5 class="card-title mb-1">${p.name ?? 'Bez naziva'}</h5>
                    <p class="card-text small text-muted mb-1">
                        <a href="<?=h(BASE_URL)?>/seller/view.php?id=${p.seller_id}" class="text-decoration-none">${p.seller_name}</a>
                    </p>
                    <p class="card-text small text-muted">
                        ${p.county_name || ''} ${p.city ? '· '+p.city : ''}
                    </p>
                    <p class="fw-bold">${(p.price_cents/100).toFixed(2)} €/${p.unit || 'kom'}</p>
                    <button class="btn btn-sm btn-success mt-auto" data-add="${p.id}">Dodaj u košaricu</button>
                    </div>
                </div>`;
                grid.appendChild(col);
            }
        }

        async function fetchProducts() {
            const params = new URLSearchParams({
                q: $('#q').value.trim(),
                type_id: $('#type').value,
                county_id: $('#county').value,
                city: $('#city').value.trim()
            });
            const res = await fetch('<?= h(BASE_URL) ?>/api/products.php?' + params.toString());
            const data = await res.json();
            renderProducts(data.items);
        }

        $('#btnFilter').addEventListener('click', fetchProducts);
        ['#q', '#city', '#type', '#county'].forEach(id => {
            document.querySelector(id).addEventListener('change', fetchProducts);
        });
        let t;
        document.querySelector('#q').addEventListener('input', () => {
            clearTimeout(t);
            t = setTimeout(fetchProducts, 300);
        });
        fetchProducts();

        function updateCartBadge(count) {
            const b = document.getElementById('cartCount');
            if (!b) return;
            b.textContent = count;
            b.classList.toggle('d-none', !count);}

        document.getElementById('products').addEventListener('click', async (e) => {
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
                    qty: 1})
            });
            if (res.status === 401) {
                location.href = '<?= h(BASE_URL) ?>/auth/login.php';
                return;}
            const data = await res.json();
            if (data.ok) {
                btn.textContent = 'Dodano ✓';
                updateCartBadge(data.count);
                setTimeout(() => {
                    btn.textContent = 'Dodaj u košaricu';}, 900);
            } else {
                alert(data.error || 'Greška');
            }
        });
    </script>
</body>

</html>