<?php
// pages/reports.php — Stok ve hareket raporu (Admin)
$pageTitle  = 'Rapor';
$activePage = 'reports';
require_once __DIR__ . '/../includes/header.php';
requireAdmin();
?>

<!-- Sekmeler -->
<ul class="nav nav-tabs mb-3" id="reportTabs">
    <li class="nav-item">
        <a class="nav-link active" data-bs-toggle="tab" href="#tabMamul">Mamul Stok</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="tab" href="#tabParca">Parça Stok</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="tab" href="#tabProduction">Üretim</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="tab" href="#tabMovements">Hareketler</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="tab" href="#tabOrders">Siparişler</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="tab" href="#tabLocations">Lokasyon</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="tab" href="#tabCriticalLoc">Kritik + Yer</a>
    </li>
</ul>

<div class="tab-content">

<!-- TAB: Mamul Stok -->
<div class="tab-pane fade show active" id="tabMamul">
    <div class="card">
        <div class="card-header d-flex justify-content-between">
            <strong>Mamul / Hazır Ürün Stok</strong>
            <button class="btn btn-sm btn-outline-secondary" onclick="loadMamulStock()">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>Ürün</th>
                        <th>Barkod</th>
                        <th class="text-center">Stok</th>
                        <th class="text-center">Min</th>
                        <th class="text-center">Durum</th>
                    </tr>
                </thead>
                <tbody id="mamulTableBody">
                    <tr><td colspan="5" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-end">
            <nav id="mamulPagination"></nav>
        </div>
    </div>
</div>

<!-- TAB: Parça Stok -->
<div class="tab-pane fade" id="tabParca">
    <div class="card">
        <div class="card-header d-flex justify-content-between">
            <strong>Parça / Hammadde Stok</strong>
            <button class="btn btn-sm btn-outline-secondary" onclick="loadParcaStock(1)">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>Ürün</th>
                        <th>Barkod</th>
                        <th class="text-center">Stok</th>
                        <th class="text-center">Min</th>
                        <th class="text-center">Durum</th>
                    </tr>
                </thead>
                <tbody id="parcaTableBody">
                    <tr><td colspan="5" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-end">
            <nav id="parcaPagination"></nav>
        </div>
    </div>
</div>

<!-- TAB: Üretim Geçmişi -->
<div class="tab-pane fade" id="tabProduction">
    <div class="card">
        <div class="card-header d-flex justify-content-between">
            <strong>Üretim Geçmişi</strong>
            <button class="btn btn-sm btn-outline-secondary" onclick="loadProductionHistory()">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>Tarih</th>
                        <th>Ürün</th>
                        <th class="text-center">Miktar</th>
                        <th>Durum</th>
                        <th>Kim</th>
                    </tr>
                </thead>
                <tbody id="productionTableBody">
                    <tr><td colspan="5" class="text-center py-3"><span class="text-muted small">Sekmeye tıklayınca yüklenir.</span></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- TAB: Hareketler -->
<div class="tab-pane fade" id="tabMovements">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Son Stok Hareketleri</strong>
            <div>
                <select class="form-select form-select-sm d-inline-block w-auto" onchange="loadMovements(this.value)">
                    <option value="">Tümü</option>
                    <option value="in">Giriş</option>
                    <option value="out">Çıkış</option>
                    <option value="production">Üretim</option>
                </select>
                <button class="btn btn-sm btn-outline-secondary" onclick="loadMovements()">
                    <i class="bi bi-arrow-clockwise"></i>
                </button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>Tarih</th>
                        <th>Ürün</th>
                        <th>Tür</th>
                        <th class="text-center">Miktar</th>
                        <th>Referans</th>
                    </tr>
                </thead>
                <tbody id="movTableBody">
                    <tr><td colspan="5" class="text-center py-3"><span class="text-muted small">Sekmeye tıklayınca yüklenir.</span></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- TAB: Siparişler -->
<div class="tab-pane fade" id="tabOrders">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Siparişler</strong>
            <div>
                <select class="form-select form-select-sm d-inline-block w-auto" id="orderPeriod" onchange="loadOrderSummary()">
                    <option value="today">Bugün</option>
                    <option value="week">Bu Hafta</option>
                    <option value="month">Bu Ay</option>
                </select>
                <button class="btn btn-sm btn-outline-secondary" onclick="loadOrderSummary()">
                    <i class="bi bi-arrow-clockwise"></i>
                </button>
            </div>
        </div>
        <div class="card-body" id="orderStats"></div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>Sipariş No</th>
                        <th>Müşteri</th>
                        <th class="text-center">Kalem</th>
                        <th class="text-center">Durum</th>
                        <th>Tarih</th>
                    </tr>
                </thead>
                <tbody id="ordersTableBody">
                    <tr><td colspan="5" class="text-center py-3"><span class="text-muted small">Sekmeye tıklayınca yüklenir.</span></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- TAB: Lokasyon Raporları -->
<div class="tab-pane fade" id="tabLocations">
    <div class="row g-3">
        <div class="col-12 col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h3 id="totalLocCount">—</h3>
                    <small class="text-muted">Toplam Göz</small>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h3 id="emptyLocCount" class="text-success">—</h3>
                    <small class="text-muted">Boş Göz</small>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h3 id="occupancyRate" class="text-warning">—</h3>
                    <small class="text-muted">Doluuluk %</small>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card mt-3">
        <div class="card-header d-flex justify-content-between">
            <strong>Kat Bazlı Doluluk</strong>
        </div>
        <div class="card-body" id="katOccupancy"></div>
    </div>

    <div class="card mt-3">
        <div class="card-header d-flex justify-content-between">
            <strong>Boş Gözler</strong>
            <button class="btn btn-sm btn-outline-secondary" onclick="loadEmptyLocations()">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
        </div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-dark"><tr><th>Kod</th><th>Kat</th><th>Bölge</th><th>Raf</th><th>Göz</th><th>Kapasite</th></tr></thead>
                <tbody id="emptyLocTableBody">
                    <tr><td colspan="6" class="text-center py-3">Yükleniyor...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- TAB: Kritik + Lokasyon -->
<div class="tab-pane fade" id="tabCriticalLoc">
    <div class="card">
        <div class="card-header d-flex justify-content-between">
            <strong>Kritik Stok + Lokasyon</strong>
            <button class="btn btn-sm btn-outline-secondary" onclick="loadCriticalWithLoc()">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>Ürün</th>
                        <th>Lokasyon</th>
                        <th class="text-center">Stok</th>
                        <th class="text-center">Min</th>
                        <th class="text-center">Durum</th>
                    </tr>
                </thead>
                <tbody id="criticalLocTableBody">
                    <tr><td colspan="5" class="text-center py-3"><span class="text-muted small">Sekmeye tıklayınca yüklenir.</span></td></tr>
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-end">
            <nav id="criticalPagination"></nav>
        </div>
    </div>
</div>

</div>

<script>
const STATUS_MAP = {
    pending:    '<span class="badge bg-warning text-dark">Bekliyor</span>',
    preparing:  '<span class="badge bg-info text-dark">Hazırlanıyor</span>',
    done:       '<span class="badge bg-success">Tamamlandı</span>',
    cancelled:  '<span class="badge bg-secondary">İptal</span>',
};
const TYPE_MAP = {
    in:         '<span class="badge bg-success">Giriş</span>',
    out:        '<span class="badge bg-danger">Çıkış</span>',
    correction: '<span class="badge bg-secondary">Düzeltme</span>',
    transfer:   '<span class="badge bg-info">Transfer</span>',
};

function getBadge(cls, label) {
    return `<span class="badge bg-${cls}">${label}</span>`;
}

async function loadMamulStock(page = 1) {
    const res = await apiGet(`/reports.php?type=mamul&page=${page}&per_page=50`);
    if (!res.success) return;
    const data = res.data;
    const rows = data.items || data;
    const tbody = document.getElementById('mamulTableBody');
    if (!rows.length) { tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Ürün yok.</td></tr>'; return; }
    tbody.innerHTML = rows.map(p => {
        const cls = p.current_stock <= 0 ? 'danger' : p.current_stock <= p.min_stock ? 'warning' : 'success';
        const label = p.current_stock <= 0 ? 'Tükendi' : p.current_stock <= p.min_stock ? 'Kritik' : 'Normal';
        return `<tr>
            <td>${escHtml(p.name)}</td>
            <td><small class="font-monospace">${escHtml(p.barcode)}</small></td>
            <td class="text-center fw-bold">${p.current_stock}</td>
            <td class="text-center text-muted">${p.min_stock}</td>
            <td class="text-center">${getBadge(cls, label)}</td>
        </tr>`;
    }).join('');
    renderTablePagination('mamulPagination', data, page, loadMamulStock);
}
function renderTablePagination(id, data, currentPage, loadFn) {
    const nav = document.getElementById(id);
    if (!nav) return;
    const total = data.total || 0;
    const pages = data.pages || 1;
    if (pages <= 1) { nav.innerHTML = ''; return; }
    let html = '<ul class="pagination pagination-sm mb-0">';
    if (currentPage > 1) html += `<li class="page-item"><a class="page-link" href="#" onclick="arguments[0].preventDefault();${loadFn.name}(${currentPage-1})">&laquo;</a></li>`;
    for (let i = 1; i <= pages; i++) {
        if (i === 1 || i === pages || (i >= currentPage - 1 && i <= currentPage + 1)) {
            html += `<li class="page-item${i === currentPage ? ' active' : ''}"><a class="page-link" href="#" onclick="arguments[0].preventDefault();${loadFn.name}(${i})">${i}</a></li>`;
        } else if (i === currentPage - 2 || i === currentPage + 2) {
            html += '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }
    }
    if (currentPage < pages) html += `<li class="page-item"><a class="page-link" href="#" onclick="arguments[0].preventDefault();${loadFn.name}(${currentPage+1})">&raquo;</a></li>`;
    html += '</ul>';
    nav.innerHTML = html;
}

async function loadParcaStock(page = 1) {
    const res = await apiGet(`/reports.php?type=parca&page=${page}&per_page=50`);
    if (!res.success) return;
    const data = res.data;
    const rows = data.items || data;
    const tbody = document.getElementById('parcaTableBody');
    if (!rows.length) { tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Parça yok.</td></tr>'; return; }
    tbody.innerHTML = rows.map(p => {
        const cls = p.current_stock <= 0 ? 'danger' : p.current_stock <= p.min_stock ? 'warning' : 'success';
        const label = p.current_stock <= 0 ? 'Tükendi' : p.current_stock <= p.min_stock ? 'Kritik' : 'Normal';
        return `<tr>
            <td>${escHtml(p.name)}</td>
            <td><small class="font-monospace">${escHtml(p.barcode)}</small></td>
            <td class="text-center fw-bold">${p.current_stock}</td>
            <td class="text-center text-muted">${p.min_stock}</td>
            <td class="text-center">${getBadge(cls, label)}</td>
        </tr>`;
    }).join('');
    renderTablePagination('parcaPagination', data, page, loadParcaStock);
}

async function loadProductionHistory() {
    const res = await apiGet('/reports.php?type=production&limit=50');
    if (!res.success) return;
    const tbody = document.getElementById('productionTableBody');
    if (!res.data.length) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Üretim yok.</td></tr>'; return;
    }
    tbody.innerHTML = res.data.map(p => `
        <tr>
            <td><small>${fmtDate(p.created_at)}</small></td>
            <td>${escHtml(p.mamul_name)}</td>
            <td class="text-center fw-bold">${p.miktar}</td>
            <td>${p.status === 'completed' ? getBadge('success', 'Tamamlandı') : getBadge('warning', 'Bekliyor')}</td>
            <td><small class="text-muted">${escHtml(p.creator_name || '—')}</small></td>
        </tr>`).join('');
}

async function loadMovements(filter = '') {
    const url = filter ? `/reports.php?type=movements&filter=${filter}&limit=100` : '/reports.php?type=movements&limit=100';
    const res = await apiGet(url);
    if (!res.success) return;
    const tbody = document.getElementById('movTableBody');
    if (!res.data.length) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Hareket yok.</td></tr>'; return;
    }
    tbody.innerHTML = res.data.map(m => `
        <tr>
            <td><small>${fmtDate(m.created_at)}</small></td>
            <td>${escHtml(m.product_name)}</td>
            <td>${TYPE_MAP[m.type]||m.type}</td>
            <td class="text-center">${m.type === 'out' ? '-' : '+'}${m.quantity}</td>
            <td><small class="text-muted">${escHtml(m.reference || m.note || '—')}</small></td>
        </tr>`).join('');
}

async function loadOrderSummary() {
    const period = document.getElementById('orderPeriod').value;
    const res = await apiGet(`/reports.php?type=orders&period=${period}`);
    if (!res.success) return;
    
    const tbody = document.getElementById('ordersTableBody');
    const statsDiv = document.getElementById('orderStats');
    
    if (!res.data.orders.length) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Sipariş yok.</td></tr>';
        statsDiv.innerHTML = '';
        return;
    }
    
    const stats = res.data.stats;
    statsDiv.innerHTML = `
        <div class="row text-center mb-3">
            <div class="col"><strong>${stats.total_orders}</strong><br><small class="text-muted">Toplam</small></div>
            <div class="col"><strong class="text-success">${stats.completed_orders}</strong><br><small class="text-muted">Tamam</small></div>
            <div class="col"><strong class="text-warning">${stats.pending_orders}</strong><br><small class="text-muted">Bekliyor</small></div>
            <div class="col"><strong class="text-info">${stats.preparing_orders}</strong><br><small class="text-muted">Hazırlanıyor</small></div>
        </div>`;
    
    tbody.innerHTML = res.data.orders.map(o => `
        <tr>
            <td><strong>${escHtml(o.order_no)}</strong></td>
            <td>${escHtml(o.customer || '—')}</td>
            <td class="text-center">${o.item_count}</td>
            <td class="text-center">${STATUS_MAP[o.status] || o.status}</td>
            <td><small>${fmtDate(o.created_at)}</small></td>
        </tr>`).join('');
}

document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
    tab.addEventListener('shown.bs.tab', e => {
        const target = e.target.getAttribute('href');
        if (target === '#tabMamul') loadMamulStock();
        if (target === '#tabParca') loadParcaStock();
        if (target === '#tabProduction') loadProductionHistory();
        if (target === '#tabMovements') loadMovements();
        if (target === '#tabOrders') loadOrderSummary();
        if (target === '#tabLocations') loadLocationReports();
        if (target === '#tabCriticalLoc') loadCriticalWithLoc();
    });
});

async function loadLocationReports() {
    const res = await apiGet('/locations.php?list');
    if (!res.success) return;
    const locs = res.data;
    const total = locs.length;
    const empty = locs.filter(l => !l.current_stock || l.current_stock === 0).length;
    const full = locs.filter(l => l.current_stock >= l.kapasite).length;
    const rate = total ? Math.round((total - empty) / total * 100) : 0;
    
    document.getElementById('totalLocCount').textContent = total;
    document.getElementById('emptyLocCount').textContent = empty;
    document.getElementById('occupancyRate').textContent = rate + '%';
    
    const katNames = { '1': '1. Kat', '2': '2. Kat', '3': '3. Kat', 'B': 'Bodrum' };
    const katStats = {};
    locs.forEach(l => {
        const kat = l.kat || 'B';
        if (!katStats[kat]) katStats[kat] = { total: 0, filled: 0 };
        katStats[kat].total++;
        if (l.current_stock > 0) katStats[kat].filled++;
    });
    
    lethtml = '<div class="row">';
    Object.entries(katStats).forEach(([kat, s]) => {
        const pct = s.total ? Math.round(s.filled / s.total * 100) : 0;
        lethtml += `<div class="col-6 col-md-3 mb-2">
            <div class="border rounded p-2">
                <strong>${katNames[kat] || kat}</strong>
                <div class="progress mt-1" style="height:8px">
                    <div class="progress-bar bg-${pct > 70 ? 'danger' : pct > 30 ? 'warning' : 'success'}" style="width:${pct}%"></div>
                </div>
                <small class="text-muted">${s.filled}/${s.total} (%${pct})</small>
            </div>
        </div>`;
    });
    document.getElementById('katOccupancy').innerHTML = lethtml;
    
    loadEmptyLocations();
}

async function loadEmptyLocations() {
    const res = await apiGet('/locations.php?list&empty=1');
    const tbody = document.getElementById('emptyLocTableBody');
    if (!res.success || !res.data.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Boş göz yok.</td></tr>';
        return;
    }
    tbody.innerHTML = res.data.map(l => `<tr>
        <td><strong>${l.kod}</strong></td>
        <td>${l.kat}. Kat</td>
        <td>${l.bolge}</td>
        <td>Raf ${l.raf}</td>
        <td>Göz ${l.goz}</td>
        <td>${l.kapasite}</td>
    </tr>`).join('');
}

async function loadCriticalWithLoc(page = 1) {
    const res = await apiGet(`/reports.php?type=critical&page=${page}&per_page=50`);
    const tbody = document.getElementById('criticalLocTableBody');
    if (!res.success || !res.data.items?.length) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Kritik ürün yok.</td></tr>';
        return;
    }
    const rows = res.data.items;
    tbody.innerHTML = rows.map(p => {
        const cls = p.current_stock <= 0 ? 'danger' : 'warning';
        const label = p.current_stock <= 0 ? 'Tükendi' : 'Kritik';
        const loc = p.location_kod || p.pl_kod || '—';
        return `<tr>
            <td>${escHtml(p.name)}</td>
            <td><span class="badge bg-info">${loc}</span></td>
            <td class="text-center fw-bold text-${cls}">${p.current_stock}</td>
            <td class="text-center text-muted">${p.min_stock}</td>
            <td class="text-center">${getBadge(cls, label)}</td>
        </tr>`;
    }).join('');
    renderTablePagination('criticalPagination', res.data, page, loadCriticalWithLoc);
}

function fmtDate(d) {
    if (!d) return '—';
    return new Date(d).toLocaleString('tr-TR', {day:'2-digit', month:'2-digit', hour:'2-digit', minute:'2-digit'});
}
function escHtml(s) {
    return String(s||'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

window.addEventListener('load', function() {
    if (typeof loadMamulStock === 'function') loadMamulStock();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
