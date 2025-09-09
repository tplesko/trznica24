<?php
require_once __DIR__ . '/../lib/auth.php';
require_login();
?>
<!doctype html>
<html lang="hr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Košarica — Tržnica24</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
    <?php require_once __DIR__ . '/partials/nav.php'; ?>

    <div class="container py-4" style="max-width:1100px">
        <h1 class="h4 mb-3">Košarica</h1>

        <div id="cartEmpty" class="alert alert-info d-none">Košarica je prazna.</div>

        <div id="cartWrap" class="card d-none">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width:70px"></th>
                            <th>Proizvod</th>
                            <th style="width:160px" class="text-end">Cijena</th>
                            <th style="width:160px">Količina</th>
                            <th style="width:140px" class="text-end">Ukupno</th>
                            <th style="width:80px"></th>
                        </tr>
                    </thead>
                    <tbody id="cartBody"></tbody>
                </table>
            </div>

            <!-- Podaci za dostavu + ukupno + gumb Naruči -->
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-7">
                        <label class="form-label">Adresa za dostavu</label>
                        <textarea id="shipAddress" class="form-control" rows="2" placeholder="Ulica i broj, grad"></textarea>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Telefon</label>
                        <input id="shipPhone" class="form-control" placeholder="+385...">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button id="btnCheckout" class="btn btn-success w-100">Naruči</button>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Napomena (opcionalno)</label>
                        <input id="shipNote" class="form-control" placeholder="Npr. dostava poslije 17h">
                    </div>
                    <div class="col-12 d-flex justify-content-between align-items-center">
                        <a class="btn btn-outline-secondary" href="<?= h(BASE_URL) ?>/">Nastavi kupovati</a>
                        <div class="fw-bold">Ukupno: <span id="cartTotal">0.00</span> €</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const fmt = v => (Number(v) / 100).toFixed(2);

        const cartBody = document.getElementById('cartBody');
        const cartWrap = document.getElementById('cartWrap');
        const cartEmpty = document.getElementById('cartEmpty');
        const cartTotal = document.getElementById('cartTotal');
        const cartCountEl = document.getElementById('cartCount');
        const btnCheckout = document.getElementById('btnCheckout');

        function updateBadge(count) {
            if (!cartCountEl) return;
            cartCountEl.textContent = count;
            cartCountEl.classList.toggle('d-none', !count);
        }

        async function loadCart() {
            const res = await fetch('<?= h(BASE_URL) ?>/api/cart.php');
            if (res.status === 401) {
                location.href = '<?= h(BASE_URL) ?>/auth/login.php';
                return;
            }
            const data = await res.json();
            render(data.items || [], data.total_cents || 0);
        }

        function render(items, total){
            cartBody.innerHTML = '';
            if(!items.length){
                cartWrap.classList.add('d-none');
                cartEmpty.classList.remove('d-none');
                updateBadge(0);
                cartTotal.textContent = '0.00';
                return;
            }
            cartWrap.classList.remove('d-none');
            cartEmpty.classList.add('d-none');

            for(const it of items){
                const step = (it.unit === 'kg') ? '0.01' : '1';
                const tr = document.createElement('tr');
                tr.innerHTML = `
                <td><img src="${it.image_url || 'https://via.placeholder.com/60x40?text='}" style="width:70px;height:48px;object-fit:cover"></td>
                <td>${it.name}</td>
                <td class="text-end">${fmt(it.price_cents)} €/${it.unit || 'kom'}</td>
                <td><input type="number" min="0" step="${step}" max="${it.stock}" value="${it.qty}" data-item="${it.item_id}" class="form-control form-control-sm" style="width:120px"></td>
                <td class="text-end">${fmt(it.price_cents * it.qty)} €</td>
                <td class="text-end"><button class="btn btn-outline-danger btn-sm" data-del="${it.item_id}">×</button></td>`;
                cartBody.appendChild(tr);
            }

            cartTotal.textContent = fmt(total);
            updateBadge(items.length);
        }


        // promjena količine
        cartBody.addEventListener('change', async (e) => {
            const inp = e.target.closest('input[type="number"][data-item]');
            if (!inp) return;
            const item_id = Number(inp.getAttribute('data-item'));
            const qty = Number(inp.value);
            const res = await fetch('<?= h(BASE_URL) ?>/api/cart.php', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    item_id,
                    qty
                })
            });
            const data = await res.json();
            if (!data.ok) {
                alert(data.error || 'Greška');
                return;
            }
            loadCart();
        });

        // brisanje stavke
        cartBody.addEventListener('click', async (e) => {
            const btn = e.target.closest('[data-del]');
            if (!btn) return;
            const item_id = Number(btn.getAttribute('data-del'));
            const res = await fetch('<?= h(BASE_URL) ?>/api/cart.php', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    item_id
                })
            });
            const data = await res.json();
            if (!data.ok) {
                alert(data.error || 'Greška');
                return;
            }
            loadCart();
        });

        // checkout s adresom/telefonom/napomenom
        btnCheckout.addEventListener('click', async () => {
            const shipping_address = document.getElementById('shipAddress').value.trim();
            const shipping_phone = document.getElementById('shipPhone').value.trim();
            const shipping_note = document.getElementById('shipNote').value.trim();

            if (!shipping_address || !shipping_phone) {
                alert('Upiši adresu i telefon.');
                return;
            }

            const res = await fetch('<?= h(BASE_URL) ?>/api/orders.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    shipping_address,
                    shipping_phone,
                    shipping_note
                })
            });
            const data = await res.json();
            if (!data.ok) {
                alert(data.error || 'Greška pri narudžbi');
                return;
            }
            location.href = '<?= h(BASE_URL) ?>/orders.php';
        });

        loadCart();
    </script>
</body>

</html>