<?php
// pages/products.php — Ürün yönetimi (Admin)
$pageTitle    = 'Ürünler';
$activePage   = 'products';
$extraScripts = [
    'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js',
    'assets/js/barcode.js',
];
require_once __DIR__ . '/../includes/header.php';
requireAdmin();
?>

<!-- Yeni Ürün Formu -->
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Yeni Ürün Ekle</strong>
        <button class="btn btn-sm btn-outline-secondary"
            data-bs-toggle="collapse" data-bs-target="#addForm">
            <i class="bi bi-plus-lg"></i>
        </button>
    </div>
    <div class="collapse" id="addForm">
        <div class="card-body">
            <div class="row g-2">
                <div class="col-12 col-md-6">
                  <label class="form-label small">Barkod *</label>
                  <div class="input-group">
                    <input type="text" id="newBarcode" class="form-control" placeholder="EAN-13 veya özel" required>
                    <button class="btn btn-outline-secondary" onclick="openBarcodeModal()">
                      <i class="bi bi-camera"></i>
                    </button>
                  </div>
                </div>
                <div class="col-12 col-md-6">
                  <label class="form-label small">Ürün Adı *</label>
                  <input type="text" id="newName" class="form-control" required>
                </div>
                <div class="col-12 col-md-4">
                  <label class="form-label small">Ürün Tipi *</label>
                  <select id="newProductType" class="form-select" required>
                    <option value="hazir">Hazır Ürün (Satış)</option>
                    <option value="imalat">Kendi İmalatı</option>
                    <option value="parca">Parça / Hammadde</option>
                  </select>
                  <small class="text-muted">Hazır: Direkt satılan | İmalat: Üretilen | Parça: Hammadde</small>
                </div>
                <div class="col-6 col-md-4">
                    <label class="form-label small">SKU (İç Kod)</label>
                    <input type="text" id="newSku" class="form-control">
                </div>
                <div class="col-6 col-md-4">
                    <label class="form-label small">Kategori</label>
                    <input type="text" id="newCategory" class="form-control">
                </div>
                <div class="col-6 col-md-4">
                    <label class="form-label small">Birim</label>
                    <select id="newUnit" class="form-select">
                        <option value="adet">adet</option>
                        <option value="kutu">kutu</option>
                        <option value="kg">kg</option>
                        <option value="lt">lt</option>
                        <option value="mt">mt</option>
                    </select>
                </div>
                <div class="col-6 col-md-4">
                    <label class="form-label small">Min. Stok Eşiği</label>
                    <input type="number" id="newMinStock" class="form-control" value="5" min="0">
                </div>
                <div class="col-12 col-md-8">
                    <label class="form-label small">Ana Lokasyon</label>
                    <select id="newLocationId" class="form-select">
                        <option value="">Belirtilmemiş</option>
                    </select>
                </div>
                <div class="col-12">
                    <button class="btn btn-dark" onclick="addProduct()">
                        <i class="bi bi-plus-circle"></i> Ürün Ekle
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Yeni Sipariş Formu -->
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Yeni Sipariş Oluştur</strong>
        <button class="btn btn-sm btn-outline-secondary"
            data-bs-toggle="collapse" data-bs-target="#orderForm">
            <i class="bi bi-plus-lg"></i>
        </button>
    </div>
    <div class="collapse" id="orderForm">
        <div class="card-body">
            <div class="row g-2 mb-3">
                <div class="col-12 col-md-4">
                    <label class="form-label small">Sipariş No * (WhatsApp/Fatura)</label>
                    <input type="text" id="orderNo" class="form-control" placeholder="ör: WP-2025-001">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label small">Müşteri Adı</label>
                    <input type="text" id="orderCustomer" class="form-control">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label small">Not</label>
                    <input type="text" id="orderNotes" class="form-control">
                </div>
            </div>

            <div id="orderItemsContainer">
                <div class="order-item row g-2 mb-2 align-items-end">
                    <div class="col-6">
                        <label class="form-label small">Barkod</label>
                        <input type="text" class="form-control item-barcode" placeholder="Barkod">
                    </div>
                    <div class="col-4">
                        <label class="form-label small">Adet</label>
                        <input type="number" class="form-control item-qty" value="1" min="1">
                    </div>
                    <div class="col-2">
                        <button class="btn btn-outline-danger btn-sm w-100" onclick="removeItem(this)">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="d-flex gap-2 mt-2">
                <button class="btn btn-outline-secondary btn-sm" onclick="addItem()">
                    <i class="bi bi-plus"></i> Kalem Ekle
                </button>
                <button class="btn btn-dark" onclick="createOrder()">
                    <i class="bi bi-check-circle"></i> Siparişi Oluştur
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Ürün Listesi -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Ürün Listesi</strong>
        <div class="input-group input-group-sm" style="max-width:200px">
            <input type="text" id="searchInput" class="form-control" placeholder="Ara..." oninput="filterTable()">
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0" id="productTable">
            <thead class="table-dark">
                <tr>
                <th>Ürün Adı</th>
                <th>Tip</th>
                <th>Barkod</th>
                <th>Kategori</th>
                <th class="text-center">Stok</th>
                <th class="text-center">Min</th>
                <th class="text-center">İşlem</th>
                </tr>
            </thead>
            <tbody id="productTableBody">
                <tr><td colspan="7" class="text-center py-3">
                    <div class="spinner-border spinner-border-sm"></div>
                </td></tr>
            </tbody>
        </table>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted" id="productCountInfo"></small>
        <nav id="productPagination"></nav>
    </div>
</div>

<!-- Barkod Tarama Modal -->
<div class="modal fade" id="barcodeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Barkod Tara</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-2">
                <div id="modalReader"></div>
            </div>
        </div>
    </div>
</div>

<script>
let allProducts = [];
let modalScanner = null;

let currentPage = 1;
let totalPages = 1;

function escHtml(s) { return s == null ? '' : String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

async function loadProducts(page = 1) {
    currentPage = page;
    const res = await apiGet(`/products.php?list=1&page=${page}&per_page=20`);
    if (!res.success) return;
    totalPages = res.data.pages || 1;
    allProducts = res.data.items || res.data;
    renderTable(allProducts);
    renderPagination();
}

function renderPagination() {
    const nav = document.getElementById('productPagination');
    const info = document.getElementById('productCountInfo');
    if (totalPages <= 1) { nav.innerHTML = ''; return; }
    let html = '<ul class="pagination pagination-sm mb-0">';
    if (currentPage > 1) html += `<li class="page-item"><a class="page-link" href="#" onclick="loadProducts(${currentPage-1});return false">&laquo;</a></li>`;
    for (let i = 1; i <= totalPages; i++) {
        if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
            html += `<li class="page-item${i === currentPage ? ' active' : ''}"><a class="page-link" href="#" onclick="loadProducts(${i});return false">${i}</a></li>`;
        } else if (i === currentPage - 2 || i === currentPage + 2) {
            html += '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }
    }
    if (currentPage < totalPages) html += `<li class="page-item"><a class="page-link" href="#" onclick="loadProducts(${currentPage+1});return false">&raquo;</a></li>`;
    html += '</ul>';
    nav.innerHTML = html;
}

function renderTable(products) {
    const tbody = document.getElementById('productTableBody');
    if (!products.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3">Ürün bulunamadı.</td></tr>';
        return;
    }
    tbody.innerHTML = products.map(p => {
    const stockClass = p.current_stock <= 0 ? 'text-danger fw-bold' : p.current_stock <= p.min_stock ? 'text-warning fw-bold' : 'text-success';
    const typeBadge = p.product_type === 'imalat' ? '<span class="badge bg-primary">İmalat</span>' : p.product_type === 'parca' ? '<span class="badge bg-info">Parça</span>' : '<span class="badge bg-secondary">Hazır</span>';
    return `<tr data-id="${p.id}">
    <td>${escHtml(p.name)} <small class="text-muted">${escHtml(p.sku||'')}</small></td>
    <td>${typeBadge}</td>
    <td><small class="font-monospace">${escHtml(p.barcode)}</small></td>
    <td><small>${escHtml(p.category||'—')}</small></td>
    <td class="text-center ${stockClass}">${p.current_stock}</td>
    <td class="text-center text-muted small">${p.min_stock}</td>
    <td class="text-center">
        <button class="btn btn-sm btn-outline-primary" onclick="editProduct(${p.id})" title="Düzenle">
            <i class="bi bi-pencil"></i>
        </button>
        <button class="btn btn-sm btn-outline-danger" onclick="deleteProduct(${p.id})" title="Sil">
            <i class="bi bi-trash"></i>
        </button>
    </td>
    </tr>`;
    }).join('');
}

function filterTable() {
    const q = document.getElementById('searchInput').value.toLowerCase();
    renderTable(allProducts.filter(p =>
        p.name.toLowerCase().includes(q) ||
        p.barcode.toLowerCase().includes(q) ||
        (p.category||'').toLowerCase().includes(q)
    ));
}

async function addProduct() {
    const data = {
      action: 'create',
      barcode: document.getElementById('newBarcode').value.trim(),
      name: document.getElementById('newName').value.trim(),
      product_type: document.getElementById('newProductType').value,
      sku: document.getElementById('newSku').value.trim(),
      category: document.getElementById('newCategory').value.trim(),
      unit: document.getElementById('newUnit').value,
      min_stock: parseInt(document.getElementById('newMinStock').value) || 5,
      location_id: document.getElementById('newLocationId').value || null,
    };
    if (!data.barcode || !data.name) { showToast('Barkod ve ürün adı zorunlu.', 'warning'); return; }

    const res = await apiPost('/products.php', data);
    if (!res.success) { showToast(res.error, 'danger'); return; }

    showToast('Ürün eklendi!', 'success');
    ['newBarcode','newName','newSku','newCategory','newProductType'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('newMinStock').value = 5;
    document.getElementById('newLocationId').value = '';
    loadProducts();
}

// Sipariş
function addItem() {
    const container = document.getElementById('orderItemsContainer');
    const div = document.createElement('div');
    div.className = 'order-item row g-2 mb-2 align-items-end';
    div.innerHTML = `
        <div class="col-6"><input type="text" class="form-control item-barcode" placeholder="Barkod"></div>
        <div class="col-4"><input type="number" class="form-control item-qty" value="1" min="1"></div>
        <div class="col-2"><button class="btn btn-outline-danger btn-sm w-100" onclick="removeItem(this)">
            <i class="bi bi-trash"></i></button></div>`;
    container.appendChild(div);
}
function removeItem(btn) { btn.closest('.order-item').remove(); }

async function createOrder() {
    const orderNo = document.getElementById('orderNo').value.trim();
    if (!orderNo) { showToast('Sipariş numarası zorunlu.', 'warning'); return; }

    const itemRows = document.querySelectorAll('.order-item');
    const items = [];
    for (const row of itemRows) {
        const barcode = row.querySelector('.item-barcode').value.trim();
        const qty     = parseInt(row.querySelector('.item-qty').value) || 0;
        if (barcode && qty > 0) items.push({ barcode, quantity: qty });
    }
    if (!items.length) { showToast('En az 1 kalem ekleyin.', 'warning'); return; }

    const res = await apiPost('/orders.php', {
        action:   'create',
        order_no: orderNo,
        customer: document.getElementById('orderCustomer').value.trim(),
        notes:    document.getElementById('orderNotes').value.trim(),
        items
    });
    if (!res.success) { showToast(res.error, 'danger'); return; }
    showToast(`Sipariş oluşturuldu! #${orderNo}`, 'success');
    document.getElementById('orderNo').value = '';
    document.getElementById('orderCustomer').value = '';
    document.getElementById('orderNotes').value = '';
    document.getElementById('orderItemsContainer').innerHTML = `
        <div class="order-item row g-2 mb-2 align-items-end">
            <div class="col-6"><label class="form-label small">Barkod</label>
                <input type="text" class="form-control item-barcode" placeholder="Barkod"></div>
            <div class="col-4"><label class="form-label small">Adet</label>
                <input type="number" class="form-control item-qty" value="1" min="1"></div>
            <div class="col-2"><button class="btn btn-outline-danger btn-sm w-100" onclick="removeItem(this)">
                <i class="bi bi-trash"></i></button></div></div>`;
}

// Barkod modal
function openBarcodeModal() {
    const modal = new bootstrap.Modal(document.getElementById('barcodeModal'));
    modal.show();
    setTimeout(() => {
        modalScanner = new BarcodeScanner('modalReader', (barcode) => {
            document.getElementById('newBarcode').value = barcode;
            modalScanner.stop();
            modal.hide();
            showToast('Barkod okundu: ' + barcode, 'success');
        });
        modalScanner.start();
    }, 300);
}

async function loadLocationsForProduct() {
    const res = await apiGet('/locations.php?list');
    if (!res.success) return;
    const select = document.getElementById('newLocationId');
    select.innerHTML = '<option value="">Belirtilmemiş</option>';
    res.data.forEach(loc => {
        const opt = document.createElement('option');
        opt.value = loc.id;
        opt.textContent = loc.kod + ' (' + loc.kat + '. Kat / ' + loc.bolge + ' / Raf ' + loc.raf + ')';
        select.appendChild(opt);
    });
}

document.getElementById('barcodeModal').addEventListener('hidden.bs.modal', () => {
    if (modalScanner) { modalScanner.stop(); modalScanner = null; }
});

document.addEventListener('DOMContentLoaded', function() {
    loadLocationsForProduct();
    loadProducts(1);
});

</script>

<!-- Edit Modal -->
<div class="modal fade" id="editProductModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ürün Düzenle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editProductId">
                <div class="mb-2">
                    <label class="form-label">Barkod</label>
                    <input type="text" id="editBarcode" class="form-control" readonly>
                </div>
                <div class="mb-2">
                    <label class="form-label">Ürün Adı</label>
                    <input type="text" id="editName" class="form-control">
                </div>
                <div class="mb-2">
                    <label class="form-label">Ürün Tipi</label>
                    <select id="editProductType" class="form-select">
                        <option value="hazir">Hazır Ürün</option>
                        <option value="imalat">İmalat</option>
                        <option value="parca">Parça</option>
                    </select>
                </div>
                <div class="row">
                    <div class="col-6 mb-2">
                        <label class="form-label">SKU</label>
                        <input type="text" id="editSku" class="form-control">
                    </div>
                    <div class="col-6 mb-2">
                        <label class="form-label">Kategori</label>
                        <input type="text" id="editCategory" class="form-control">
                    </div>
                </div>
                <div class="row">
                    <div class="col-6 mb-2">
                        <label class="form-label">Birim</label>
                        <select id="editUnit" class="form-select">
                            <option value="adet">adet</option>
                            <option value="kutu">kutu</option>
                            <option value="kg">kg</option>
                            <option value="lt">lt</option>
                            <option value="mt">mt</option>
                        </select>
                    </div>
                    <div class="col-6 mb-2">
                        <label class="form-label">Min. Stok</label>
                        <input type="number" id="editMinStock" class="form-control">
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label">Ana Lokasyon</label>
                    <select id="editLocationId" class="form-select">
                        <option value="">Belirtilmemiş</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                <button type="button" class="btn btn-primary" onclick="saveProduct()">Kaydet</button>
            </div>
        </div>
    </div>
</div>

<!-- Silme Onay Modal -->
<div class="modal fade" id="deleteProductModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ürün Sil</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Bu ürünü silmek istediğinizden emin misiniz?</p>
                <input type="hidden" id="deleteProductId">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                <button type="button" class="btn btn-danger" onclick="confirmDeleteProduct()">Sil</button>
            </div>
        </div>
    </div>
</div>

<script>
let editProductModal, deleteProductModal;

document.addEventListener('DOMContentLoaded', function() {
    editProductModal = new bootstrap.Modal(document.getElementById('editProductModal'));
    deleteProductModal = new bootstrap.Modal(document.getElementById('deleteProductModal'));
});

async function editProduct(id) {
    const res = await apiGet('/products.php?id=' + id);
    if (!res.success) return alert(res.error || 'Hata');
    
    const p = res.data;
    document.getElementById('editProductId').value = p.id;
    document.getElementById('editBarcode').value = p.barcode;
    document.getElementById('editName').value = p.name;
    document.getElementById('editProductType').value = p.product_type;
    document.getElementById('editSku').value = p.sku || '';
    document.getElementById('editCategory').value = p.category || '';
    document.getElementById('editUnit').value = p.unit || 'adet';
    document.getElementById('editMinStock').value = p.min_stock || 5;
    
    // Lokasyon dropdown'ı doldur
    const locSelect = document.getElementById('editLocationId');
    const resLoc = await apiGet('/locations.php?list');
    if (resLoc.success) {
        locSelect.innerHTML = '<option value="">Belirtilmemiş</option>';
        resLoc.data.forEach(loc => {
            const opt = document.createElement('option');
            opt.value = loc.id;
            opt.textContent = loc.kod + ' (' + loc.kat + '. Kat)';
            if (loc.id == p.location_id) opt.selected = true;
            locSelect.appendChild(opt);
        });
    }
    
    editProductModal.show();
}

async function saveProduct() {
    const id = document.getElementById('editProductId').value;
    const res = await apiPost('/products.php', {
        action: 'update',
        product_id: parseInt(id),
        name: document.getElementById('editName').value,
        product_type: document.getElementById('editProductType').value,
        sku: document.getElementById('editSku').value,
        category: document.getElementById('editCategory').value,
        unit: document.getElementById('editUnit').value,
        min_stock: parseInt(document.getElementById('editMinStock').value) || 5,
        location_id: document.getElementById('editLocationId').value || null
    });
    
    if (res.success) {
        editProductModal.hide();
        showToast('Ürün güncellendi', 'success');
        loadProducts(currentPage);
    } else {
        alert(res.error || 'Hata');
    }
}

function deleteProduct(id) {
    document.getElementById('deleteProductId').value = id;
    deleteProductModal.show();
}

async function confirmDeleteProduct() {
    const id = document.getElementById('deleteProductId').value;
    const res = await apiPost('/products.php', {
        action: 'delete',
        product_id: parseInt(id)
    });
    
    if (res.success) {
        deleteProductModal.hide();
        showToast('Ürün silindi', 'success');
        loadProducts(currentPage);
    } else {
        alert(res.error || 'Hata');
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
