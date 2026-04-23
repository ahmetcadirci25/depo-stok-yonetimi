<?php
// pages/location-transfer.php — Lokasyonlar arası transfer
$pageTitle   = 'Lokasyon Transfer';
$activePage = 'transfer';
$extraScripts = [
    'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js',
    'assets/js/barcode.js',
];
require_once __DIR__ . '/../includes/header.php';
requireDepocu();
?>

<div class="row g-3">

<!-- Kaynak Lokasyon -->
<div class="col-12 col-md-6">
    <div class="card">
        <div class="card-header">
            <strong>1. Kaynak Göz</strong>
        </div>
        <div class="card-body">
            <div class="input-group mb-3">
                <input type="text" id="sourceLocCode" class="form-control" placeholder="K1-A-R3-G2">
                <button class="btn btn-outline-secondary" onclick="scanSource()">
                    <i class="bi bi-camera"></i>
                </button>
            </div>
            <div id="sourceLocPreview" class="d-none">
                <h5 id="sourceLocKod">—</h5>
                <table class="table table-sm">
                    <thead><tr><th>Ürün</th><th>Miktar</th></tr></thead>
                    <tbody id="sourceItems"></tbody>
                </table>
            </div>
            <div id="sourceEmpty" class="text-muted text-center py-3">
                Göz kodu girin veya tarayın
            </div>
        </div>
    </div>
</div>

<!-- Hedef Lokasyon -->
<div class="col-12 col-md-6">
    <div class="card">
        <div class="card-header">
            <strong>2. Hedef Göz</strong>
        </div>
        <div class="card-body">
            <div class="input-group mb-3">
                <input type="text" id="targetLocCode" class="form-control" placeholder="K2-B-R1-G2">
                <button class="btn btn-outline-secondary" onclick="scanTarget()">
                    <i class="bi bi-camera"></i>
                </button>
            </div>
            <div id="targetLocPreview" class="d-none">
                <h5 id="targetLocKod">—</h5>
                <p class="text-muted small">Bu gözdeki mevcut ürünler:</p>
                <div id="targetCurrentItems"></div>
            </div>
            <div id="targetEmpty" class="text-muted text-center py-3">
                Hedef göz kodu girin veya tarayın
            </div>
        </div>
    </div>
</div>

<!-- Transfer Form -->
<div class="col-12">
    <div class="card">
        <div class="card-header">
            <strong>3. Transfer Yap</strong>
        </div>
        <div class="card-body">
            <div class="row g-2">
                <div class="col-12 col-md-4">
                    <label class="form-label">Taşınacak Ürün</label>
                    <select id="transferProduct" class="form-select">
                        <option value="">Seçiniz...</option>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label">Miktar</label>
                    <input type="number" id="transferQty" class="form-control" min="1" value="1">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <button class="btn btn-success w-100" onclick="doTransfer()">
                        <i class="bi bi-arrow-right-circle"></i> Transfer Et
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Transfer Geçmişi -->
<div class="col-12">
    <div class="card">
        <div class="card-header d-flex justify-content-between">
            <strong>Son Transferler</strong>
            <button class="btn btn-sm btn-outline-secondary" onclick="loadTransfers()">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
        </div>
        <div class="card-body">
            <div id="transfersList" class="table-responsive">
                <table class="table table-sm">
                    <thead><tr><th>Tarih</th><th>Ürün</th><th>Kaynak</th><th>Hedef</th><th>Miktar</th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

</div>

<!-- Barkod Modal -->
<div class="modal fade" id="scanModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">QR Tara</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="modalScanner" style="width:100%"></div>
            </div>
        </div>
    </div>
</div>

<script>
let sourceLocId = null;
let targetLocId = null;
let scanner = null;
let scanTarget = null;

document.getElementById('sourceLocCode').addEventListener('change', loadSourceLocation);
document.getElementById('targetLocCode').addEventListener('change', loadTargetLocation);

async function loadSourceLocation() {
    const kod = document.getElementById('sourceLocCode').value.trim();
    if (!kod) return;
    
    const res = await apiGet('/locations.php?kod=' + encodeURIComponent(kod));
    if (!res.success) {
        showToast('Lokasyon bulunamadı', 'danger');
        return;
    }
    
    const loc = res.data;
    sourceLocId = loc.id;
    document.getElementById('sourceLocKod').textContent = loc.kod;
    document.getElementById('sourceLocPreview').classList.remove('d-none');
    document.getElementById('sourceEmpty').classList.add('d-none');
    
    const tbody = document.getElementById('sourceItems');
    const prods = loc.products || [];
    if (prods.length === 0) {
        tbody.innerHTML = '<tr><td colspan="2" class="text-muted">Bu gözde ürün yok</td></tr>';
    } else {
        tbody.innerHTML = prods.map(p => `<tr><td>${p.name}</td><td>${p.stored}</td></tr>`).join('');
    }
    
    updateProductSelect();
}

async function loadTargetLocation() {
    const kod = document.getElementById('targetLocCode').value.trim();
    if (!kod) return;
    
    const res = await apiGet('/locations.php?kod=' + encodeURIComponent(kod));
    if (!res.success) {
        showToast('Lokasyon bulunamadı', 'danger');
        return;
    }
    
    const loc = res.data;
    targetLocId = loc.id;
    document.getElementById('targetLocKod').textContent = loc.kod;
    document.getElementById('targetLocPreview').classList.remove('d-none');
    document.getElementById('targetEmpty').classList.add('d-none');
    
    const div = document.getElementById('targetCurrentItems');
    const prods = loc.products || [];
    if (prods.length === 0) {
        div.innerHTML = '<span class="text-muted">Boş göz</span>';
    } else {
        div.innerHTML = prods.map(p => `<span class="badge bg-warning me-1">${p.name}: ${p.stored}</span>`).join('');
    }
}

function updateProductSelect() {
    const select = document.getElementById('transferProduct');
    select.innerHTML = '<option value="">Seçiniz...</option>';
    
    if (!sourceLocId) return;
    
    apiGet('/locations.php?id=' + sourceLocId).then(res => {
        if (!res.success || !res.data.products) return;
        res.data.products.forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.id;
            opt.textContent = p.name + ' (' + p.stored + ')';
            select.appendChild(opt);
        });
    });
}

async function doTransfer() {
    if (!sourceLocId || !targetLocId) {
        showToast('Kaynak ve hedef göz seçilmeli', 'warning');
        return;
    }
    
    const productId = document.getElementById('transferProduct').value;
    const qty = parseInt(document.getElementById('transferQty').value) || 0;
    
    if (!productId || qty < 1) {
        showToast('Ürün ve miktar seçilmeli', 'warning');
        return;
    }
    
    if (sourceLocId === targetLocId) {
        showToast('Kaynak ve hedef aynı olamaz', 'warning');
        return;
    }
    
    const res = await apiPost('/stock.php', {
        action: 'transfer',
        product_id: productId,
        from_location_id: sourceLocId,
        to_location_id: targetLocId,
        quantity: qty
    });
    
    if (!res.success) {
        showToast(res.error, 'danger');
        return;
    }
    
    showToast('Transfer tamamlandı!', 'success');
    loadSourceLocation();
    loadTargetLocation();
    loadTransfers();
}

async function loadTransfers() {
    const res = await apiGet('/stock.php?transfers=1&limit=20');
    const tbody = document.querySelector('#transfersList tbody');
    
    if (!res.success || !res.data.length) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-muted">Henüz transfer yok</td></tr>';
        return;
    }
    
    tbody.innerHTML = res.data.map(t => `<tr>
        <td>${t.created_at}</td>
        <td>${t.product_name}</td>
        <td>${t.from_kod}</td>
        <td>${t.to_kod}</td>
        <td>${t.quantity}</td>
    </tr>`).join('');
}

function scanSource() {
    scanTarget = 'source';
    new bootstrap.Modal(document.getElementById('scanModal')).show();
    setTimeout(() => {
        scanner = new BarcodeScanner('modalScanner', (kod) => {
            document.getElementById('sourceLocCode').value = kod;
            document.getElementById('sourceLocCode').dispatchEvent(new Event('change'));
            bootstrap.Modal.getInstance(document.getElementById('scanModal')).hide();
        });
        scanner.start();
    }, 300);
}

document.getElementById('scanModal').addEventListener('hidden.bs.modal', () => {
    if (scanner) { scanner.stop(); scanner = null; }
});

document.addEventListener('DOMContentLoaded', loadTransfers);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>