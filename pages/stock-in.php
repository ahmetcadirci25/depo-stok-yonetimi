<?php
// pages/stock-in.php — Barkodla stok girişi
$pageTitle    = 'Stok Giriş';
$activePage   = 'stock';
$extraScripts = [
    'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js',
    'assets/js/barcode.js',
];
require_once __DIR__ . '/../includes/header.php';
requireDepocu();
?>

<div class="row g-3">

<!-- Tarama Paneli -->
<div class="col-12 col-md-6">
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Ürün Tara</strong>
            <select id="productTypeFilter" class="form-select form-select-sm" style="width: auto;" onchange="filterProducts()">
                <option value="">Tüm Ürünler</option>
                <option value="hazir">Hazır Ürün</option>
                <option value="imalat">İmalat</option>
                <option value="parca">Parça/Hammadde</option>
            </select>
        </div>
        <div class="card-body p-2">
            <div id="scannerReader" style="width:100%"></div>
        </div>
    </div>

    <!-- Ürün Bilgisi -->
    <div class="card d-none" id="productCard">
        <div class="card-body">
            <p class="mb-1 text-muted small">Taranan Ürün</p>
            <h5 id="productName" class="mb-0">—</h5>
            <p class="text-muted small mb-2" id="productBarcode"></p>
            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="text-muted small">Mevcut Stok:</span>
                <span class="badge bg-secondary fs-6" id="currentStock">—</span>
                <span class="text-muted small">(Min: <span id="minStock">—</span>)</span>
            </div>
            
            <label class="form-label">Lokasyon</label>
            <select id="stockLocation" class="form-select mb-3">
                <option value="">Belirtilmemiş</option>
            </select>
            
            <label class="form-label">Giriş Miktarı</label>
            <div class="input-group mb-2">
                <button class="btn btn-outline-secondary" onclick="changeQty(-1)">−</button>
                <input type="number" id="stockQty" class="form-control text-center fs-5" value="1" min="1" max="9999">
                <button class="btn btn-outline-secondary" onclick="changeQty(1)">+</button>
            </div>
            
            <input type="text" id="stockNote" class="form-control mb-3" placeholder="Not (opsiyonel)" maxlength="100">
            
            <button class="btn btn-success btn-lg w-100" onclick="saveStockIn()">
                <i class="bi bi-plus-circle-fill"></i> Stok Ekle
            </button>
        </div>
    </div>
</div>

<!-- Son Girişler -->
<div class="col-12 col-md-6">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Son Girişler</strong>
            <button class="btn btn-sm btn-outline-secondary" onclick="loadRecent()">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
        </div>
        <ul class="list-group list-group-flush" id="recentList">
            <li class="list-group-item text-muted small text-center py-3">Yükleniyor...</li>
        </ul>
    </div>
</div>

</div>

<script>
let scannedProductId = null;
let scanner = null;

async function loadLocationsForStockIn() {
    const res = await apiGet('/locations.php?list');
    const select = document.getElementById('stockLocation');
    select.innerHTML = '<option value="">Belirtilmemiş</option>';
    if (!res.success) return;
    res.data.forEach(loc => {
        const opt = document.createElement('option');
        opt.value = loc.id;
        opt.textContent = loc.kod;
        select.appendChild(opt);
    });
}

function filterLocationsByProduct() {
    if (!scannedProductId) {
        loadLocationsForStockIn();
        return;
    }
    apiGet('/products.php?id=' + scannedProductId).then(res => {
        const select = document.getElementById('stockLocation');
        select.innerHTML = '<option value="">Belirtilmemiş</option>';
        if (!res.success) return;
        if (res.data.location_id) {
            select.value = res.data.location_id;
        }
    });
}

window.addEventListener('DOMContentLoaded', () => {
    scanner = new BarcodeScanner('scannerReader', onBarcodeScanned);
    scanner.start();
    loadRecent();
    loadLocationsForStockIn();
});

async function onBarcodeScanned(barcode) {
    console.log('Barkod okundu:', barcode);
    
    const res = await apiGet(`/products.php?barcode=${encodeURIComponent(barcode)}`);
    console.log('API yanıtı:', res);
    
    if (!res.success) {
        scanFeedbackError();
        showToast(res.error, 'danger');
        return;
    }
    
    const p = res.data;
    scannedProductId = null;
    
    document.getElementById('productName').textContent = p.name;
    document.getElementById('productBarcode').textContent = p.barcode;
    document.getElementById('currentStock').textContent = p.current_stock + ' ' + (p.unit || 'adet');
    document.getElementById('minStock').textContent = p.min_stock + ' ' + (p.unit || 'adet');
    document.getElementById('stockQty').value = 1;
    document.getElementById('stockNote').value = '';
    
    document.getElementById('productCard').classList.remove('d-none');
    scannedProductId = p.id;
    document.getElementById('stockQty').focus();
}

function filterProducts() {
    console.log('Filtre:', document.getElementById('productTypeFilter').value);
}

function changeQty(delta) {
    const input = document.getElementById('stockQty');
    input.value = Math.max(1, (parseInt(input.value) || 1) + delta);
}

async function saveStockIn() {
    if (!scannedProductId) {
        showToast('Önce ürün tarayın.', 'warning');
        return;
    }
    
    const barcode = document.getElementById('productBarcode').textContent;
    const qty = parseInt(document.getElementById('stockQty').value) || 0;
    const note = document.getElementById('stockNote').value.trim();
    const locationId = document.getElementById('stockLocation').value || null;
    
    if (qty < 1) {
        showToast('Miktar en az 1 olmalı.', 'warning');
        return;
    }
    
    const res = await apiPost('/stock.php', {
        action: 'stock_in',
        barcode,
        quantity: qty,
        note,
        location_id: locationId
    });
    
    if (!res.success) {
        showToast(res.error, 'danger');
        return;
    }
    
    const productName = document.getElementById('productName').textContent;
    showToast(`✅ ${productName} — ${qty} adet eklendi. Yeni stok: ${res.data.new_balance}`, 'success');
    
    document.getElementById('productCard').classList.add('d-none');
    scannedProductId = null;
    loadRecent();
}

async function loadRecent() {
    const res = await apiGet('/stock.php?movements=1&limit=20');
    const list = document.getElementById('recentList');
    
    if (!res.success || !res.data.length) {
        list.innerHTML = '<li class="list-group-item text-muted small text-center">Henüz hareket yok.</li>';
        return;
    }
    
    const rows = res.data.filter(m => m.type === 'in').slice(0, 15);
    list.innerHTML = rows.map(m => `
        <li class="list-group-item small d-flex justify-content-between align-items-center">
            <div>
                <span class="fw-semibold">${escHtml(m.product_name)}</span><br>
                <span class="text-muted">${escHtml(m.note||'')}</span>
            </div>
            <span class="badge bg-success">+${m.quantity}</span>
        </li>`).join('');
}

function escHtml(s) {
    return String(s||'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>