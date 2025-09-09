<?php require_once __DIR__ . '/../lib/auth.php';
require_login(); ?>
<!doctype html>
<html lang="hr">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Narudžbe — Tržnica24</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
  <?php require_once __DIR__ . '/partials/nav.php'; ?>
  <div class="container py-4" style="max-width:1000px">
    <h1 class="h4 mb-3">Moje narudžbe</h1>
    <div class="card mb-3">
      <div class="card-body">
        <form id="filters" class="row g-2 align-items-end">
          <div class="col-sm-4 col-md-3">
            <label class="form-label">Status</label>
            <select id="fStatus" class="form-select">
              <option value="">Svi</option>
              <option value="pending">Na čekanju</option>
              <option value="approved">Odobreno</option>
              <option value="shipped">Poslano</option>
              <option value="delivered">Isporučeno</option>
              <option value="cancelled">Otkazano</option>
            </select>
          </div>
          <div class="col-sm-4 col-md-3">
            <label class="form-label">Od datuma</label>
            <input id="fFrom" type="date" class="form-control">
          </div>
          <div class="col-sm-4 col-md-3">
            <label class="form-label">Do datuma</label>
            <input id="fTo" type="date" class="form-control">
          </div>
          <div class="col-sm-4 col-md-2">
            <label class="form-label">Sort</label>
            <select id="fSort" class="form-select">
              <option value="desc">Najnovije</option>
              <option value="asc">Najstarije</option>
            </select>
          </div>
          <div class="col-sm-4 col-md-1">
            <button class="btn btn-success w-100">Primijeni</button>
          </div>
        </form>
      </div>
    </div>
    <div id="wrap"></div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    const STATUS = {
      pending: {
        label: 'Na čekanju',
        cls: 'warning'
      },
      approved: {
        label: 'Odobreno',
        cls: 'primary'
      },
      shipped: {
        label: 'Poslano',
        cls: 'info'
      },
      delivered: {
        label: 'Isporučeno',
        cls: 'success'
      },
      cancelled: {
        label: 'Otkazano',
        cls: 'danger'
      }
    };
    const wrap = document.getElementById('wrap');
    function qsParams(){
      const s = document.getElementById('fStatus').value.trim();
      const df = document.getElementById('fFrom').value;
      const dt = document.getElementById('fTo').value;
      const sort = document.getElementById('fSort').value;
      const p = new URLSearchParams();
      if(s)   p.set('status', s);
      if(df)  p.set('date_from', df);
      if(dt)  p.set('date_to', dt);
      if(sort) p.set('sort', sort);
      return p.toString();
    }
    const fmt = v => (v / 100).toFixed(2);
    async function load(){
        const res = await fetch('<?=h(BASE_URL)?>/api/orders.php' + (qsParams() ? ('?'+qsParams()) : ''));
        if(res.status===401){ location.href='<?=h(BASE_URL)?>/auth/login.php'; return; }
        const data = await res.json();
        const orders = data.orders||[];
        if(!orders.length){ wrap.innerHTML = '<div class="alert alert-info">Nema narudžbi.</div>'; return; }

      wrap.innerHTML = '';
      for (const o of orders) {
        const items = (o.items || []).map(it => `
      <tr>
        <td>${it.name}</td>
        <td class="text-end">${fmt(it.unit_price_cents)} €/ ${it.unit || 'kom'}</td>
        <td class="text-center">${Number(it.qty).toFixed(3).replace(/\\.0+$/,'').replace(/(\\.\\d*[1-9])0+$/,'$1')}</td>
        <td class="text-end">${fmt(it.unit_price_cents*it.qty)} €</td>
      </tr>
    `).join('');
        const card = document.createElement('div');
        const st = STATUS[o.status] || {
          label: o.status,
          cls: 'secondary'
        };
        card.className = 'card mb-3';
        card.innerHTML = `
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <div>
            <div class="fw-bold">Narudžba #${o.id}</div>
            <div class="text-muted small">
              ${o.created_at} · Status: <span class="badge text-bg-${st.cls}">${st.label}</span>
            </div>
            <div class="small mt-1">Adresa: ${o.shipping_address || '—'}</div>
            <div class="small">Telefon: ${o.shipping_phone || '—'}</div>
            ${o.shipping_note ? `<div class="small">Napomena: ${o.shipping_note}</div>` : ``}
          </div>
          <div class="fs-5 fw-bold">Ukupno: ${fmt(o.total_cents)} €</div>
        </div>
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead><tr><th>Proizvod</th><th class="text-end">Cijena</th><th class="text-center">Količina</th><th class="text-end">Ukupno</th></tr></thead>
            <tbody>${items}</tbody>
          </table>
        </div>
      </div>`;
        wrap.appendChild(card);
      }
    }
    load();
    document.getElementById('filters').addEventListener('submit', (e)=>{
  e.preventDefault();
  load();
});

  </script>
</body>

</html>