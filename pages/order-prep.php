<?php
// pages/order-prep.php — Depocu sipariş hazırlama ekranı
$pageTitle    = 'Sipariş Hazırla';
$activePage   = 'orders';
$extraScripts = [
    'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js',
    'assets/js/barcode.js',
];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row g-3">

<!-- Sol: Sipariş Seç -->
<div class="col-12" id="orderSelectPanel">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Bekleyen Siparişler</strong>
            <button class="btn btn-sm btn-outline-secondary" onclick="loadPending()">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
        </div>
        <div class="card-body p-2" id="pendingList">
            <div class="text-center text-muted py-4">
                <div class="spinner-border spinner-border-sm"></div> Yükleniyor...
            </div>
        </div>
    </div>

    <!-- Manuel sipariş no girişi -->
    <div class="card mt-2">
        <div class="card-body py-2">
            <label class="form-label small mb-1">Veya sipariş numarası gir:</label>
            <div class="input-group">
                <input type="text" id="manualOrderNo" class="form-control"
                    placeholder="ör: WP-2025-001" autocomplete="off">
                <button class="btn btn-dark" onclick="loadOrderByNo()">
                    <i class="bi bi-search"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Sağ: Hazırlama Ekranı -->
<div class="col-12" id="prepPanel" style="display:none">

    <!-- Sipariş başlığı -->
    <div class="card mb-2">
        <div class="card-body py-2 d-flex justify-content-between align-items-center">
            <div>
                <strong id="prepOrderNo">—</strong>
                <span class="text-muted ms-2 small" id="prepCustomer"></span>
            </div>
            <button class="btn btn-sm btn-outline-secondary" onclick="resetPrep()">
                ← Geri
            </button>
        </div>
    </div>

    <!-- Rota önizleme -->
    <div class="card mb-2 bg-light" id="routePreview">
        <div class="card-body py-2">
            <strong class="small"><i class="bi bi-signpost-2"></i> Rota:</strong>
            <div id="routeText" class="small mt-1"></div>
        </div>
    </div>

    <!-- Kamera -->
    <div class="card mb-2">
        <div class="card-body p-2">
            <div id="scannerReader" style="width:100%"></div>
        </div>
    </div>

    <!-- Ürün listesi -->
    <div class="card">
        <div class="card-header small">
            Kalemler — <span id="doneCount">0</span>/<span id="totalCount">0</span> tamamlandı
        </div>
        <ul class="list-group list-group-flush" id="itemList"></ul>
    </div>

    <!-- Tamamla butonu (gizli, hepsi tamam olunca görünür) -->
    <div class="mt-3 d-none" id="completeDiv">
        <button class="btn btn-success btn-lg w-100" onclick="completeOrder()">
            <i class="bi bi-check-circle-fill"></i> Siparişi Teslim Et
        </button>
    </div>

</div>
</div>

<script>
let currentOrderId  = null;
let orderItems      = [];
let scanner         = null;

// ── Bekleyen siparişleri yükle ───────────────────────────────
async function loadPending() {
    const res = await apiGet('/orders.php?pending=1');
    const list = document.getElementById('pendingList');
    if (!res.success || !res.data.length) {
        list.innerHTML = '<p class="text-muted text-center py-3 small">Bekleyen sipariş yok.</p>';
        return;
    }
    list.innerHTML = res.data.map(o => `
        <div class="d-flex justify-content-between align-items-center border-bottom py-2 px-1">
            <div>
                <strong class="small">${escHtml(o.order_no)}</strong><br>
                <span class="text-muted x-small">${escHtml(o.customer||'')} · ${o.item_count} kalem</span>
            </div>
            <button class="btn btn-sm btn-dark" onclick="loadOrder(${o.id},'${escHtml(o.order_no)}')">
                Hazırla <i class="bi bi-arrow-right"></i>
            </button>
        </div>`).join('');
}

async function loadOrderByNo() {
    const no = document.getElementById('manualOrderNo').value.trim();
    if (!no) return;
    const res = await apiGet(`/orders.php?order_no=${encodeURIComponent(no)}`);
    if (!res.success) { showToast(res.error, 'danger'); return; }
    startPrep(res.data);
}

async function loadOrder(id, no) {
    const res = await apiGet(`/orders.php?order_no=${encodeURIComponent(no)}`);
    if (!res.success) { showToast(res.error, 'danger'); return; }
    startPrep(res.data);
}

// ── Hazırlama başlat ────────────────────────────────────────
function startPrep(order) {
    currentOrderId = order.id;
    orderItems     = order.items;

    document.getElementById('prepOrderNo').textContent  = order.order_no;
    document.getElementById('prepCustomer').textContent = order.customer || '';
    document.getElementById('orderSelectPanel').style.display = 'none';
    document.getElementById('prepPanel').style.display = 'block';

    // Rota önizlemesi
    renderRoute(order.kat_order || [], order.items);

    renderItems();
    startScanner();
}

function renderRoute(katOrder, items) {
    const routeDiv = document.getElementById('routeText');
    const routeDivPanel = document.getElementById('routePreview');
    
    if (!katOrder || katOrder.length === 0) {
        routeDivPanel.classList.add('d-none');
        return;
    }
    
    routeDivPanel.classList.remove('d-none');
    const routes = [];
    const katNames = { '1': '1. Kat', '2': '2. Kat', '3': '3. Kat', 'B': 'Bodrum' };
    
    katOrder.forEach(kat => {
        const katName = katNames[kat] || kat + '. Kat';
        const count = items.filter(i => (i.kat || i.pll?.kat || '999') === kat).length;
        routes.push(`${katName} (${count} ürün)`);
    });
    
    routeDiv.textContent = routes.join(' → ');
}

function renderItems() {
    const list   = document.getElementById('itemList');
    const done   = orderItems.filter(i => i.scanned >= i.quantity).length;
    document.getElementById('doneCount').textContent  = done;
    document.getElementById('totalCount').textContent = orderItems.length;

    list.innerHTML = orderItems.map(item => {
        const complete = item.scanned >= item.quantity;
        const rowClass = complete ? 'list-group-item-success' : (item.scanned > 0 ? 'list-group-item-warning' : '');
        const location = item.location_kod || item.pll?.kod || '—';
        const locClass = complete ? 'text-success' : (item.scanned > 0 ? 'text-warning' : 'text-muted');
        return `
            <li class="list-group-item ${rowClass} d-flex justify-content-between align-items-center" id="item_${item.id}">
                <div>
                    <span class="fw-semibold">${escHtml(item.product_name)}</span>
                    <span class="badge bg-light text-dark fs-6 ms-1">${item.quantity}</span>
                    <br>
                    <small class="${locClass}"><i class="bi bi-geo-alt"></i> ${location}</small>
                </div>
                <span class="badge bg-${complete?'success':'secondary'} fs-6">
                    ${item.scanned}/${item.quantity}
                </span>
            </li>`;
    }).join('');

    // Hepsi tamam mı?
    const allDone = orderItems.every(i => i.scanned >= i.quantity);
    document.getElementById('completeDiv').classList.toggle('d-none', !allDone);
}

// ── Barkod tarama ───────────────────────────────────────────
function startScanner() {
    if (scanner) scanner.stop();
    scanner = new BarcodeScanner('scannerReader', onBarcodeScanned);
    scanner.start();
}

async function onBarcodeScanned(barcode) {
    const res = await apiPost('/orders.php', {
        action: 'scan', order_id: currentOrderId, barcode
    });

    if (!res.success) {
        scanFeedbackError();
        showToast(res.error, 'danger');
        return;
    }

    const d = res.data;
    // Yerel state güncelle
    const item = orderItems.find(i => i.id === d.item_id);
    if (item) {
        item.scanned = d.scanned;
        // Lokasyon uyarısı
        const scannedLoc = d.location_kod || item.location_kod || '—';
        const expectedLoc = item.location_kod || item.pll?.kod;
        if (expectedLoc && scannedLoc !== expectedLoc) {
            showToast(`⚠️ ${item.product_name} — Farklı gözde: ${scannedLoc}`, 'warning');
        }
    }
    renderItems();

    if (d.status === 'complete') {
        showToast(`✅ ${d.scanned}/${d.expected} — tamamlandı`, 'success');
    } else {
        showToast(`${d.scanned}/${d.expected} — devam et`, 'info');
    }
}

// ── Siparişi tamamla ────────────────────────────────────────
async function completeOrder() {
    if (!confirm('Sipariş teslim edildi ve stok düşülecek. Onaylıyor musunuz?')) return;
    const res = await apiPost('/orders.php', { action: 'complete', order_id: currentOrderId });
    if (!res.success) { showToast(res.error, 'danger'); return; }
    showToast('Sipariş tamamlandı! 🎉', 'success');
    setTimeout(() => resetPrep(), 1500);
}

function resetPrep() {
    if (scanner) scanner.stop();
    scanner = null;
    currentOrderId = null;
    orderItems = [];
    document.getElementById('prepPanel').style.display = 'none';
    document.getElementById('orderSelectPanel').style.display = 'block';
    document.getElementById('manualOrderNo').value = '';
    document.getElementById('completeDiv').classList.add('d-none');
    loadPending();
}

function escHtml(s) {
    return String(s||'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

// Sayfa yüklenince bekleyen siparişleri getir
// app.js footer'da yüklendiği için window.onload kullanıyoruz
window.addEventListener('load', function() {
    if (typeof loadPending === 'function') {
        loadPending();
    }
});

// ── Polling: yeni sipariş kontrolü (30 saniye) ────────────
let lastOrderTime = null;

async function checkNewOrders() {
    const since = lastOrderTime || '';
    const res = await apiGet(`/orders.php?action=check&since=${encodeURIComponent(since)}`);
    if (res.success && res.data.new_orders > 0) {
        scanFeedbackOk(); // sesli bildirim
        showToast(`📦 ${res.data.new_orders} yeni sipariş!`, 'info');
        loadPending(); // listeyi güncelle
    }
    if (res.success && res.data.last_order) {
        lastOrderTime = res.data.last_order;
    }
}

// Her 30 dakikada kontrol
setInterval(checkNewOrders, 30 * 60 * 1000);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
