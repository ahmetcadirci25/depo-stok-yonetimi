<?php
// pages/order-create.php — Admin: Sipariş oluşturma
$pageTitle = 'Sipariş Oluştur';
$activePage = 'order-create';
require_once __DIR__ . '/../includes/header.php';
requireMuhasebe();

$db = getDB();

// Mamul ürünleri (satılacak)
$products = $db->query("
    SELECT id, name, barcode, sku, product_type,
        (SELECT COALESCE(SUM(
            CASE WHEN sm.type='in' THEN sm.quantity
                 WHEN sm.type='out' THEN -sm.quantity
                 WHEN sm.type='correction' THEN sm.quantity
            END
        ), 0) FROM stock_movements sm WHERE sm.product_id = id) AS current_stock
    FROM products
    WHERE active = 1 AND product_type IN ('imalat', 'hazir')
    ORDER BY name
")->fetchAll();
?>

<div class="row g-3">
    <div class="col-12 col-lg-6">
        <div class="card">
            <div class="card-header">
                <strong><i class="bi bi-cart-plus"></i> Yeni Sipariş</strong>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Sipariş No</label>
                    <input type="text" id="orderNo" class="form-control" placeholder="ör: WP-2025-001" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Müşteri</label>
                    <input type="text" id="customer" class="form-control" placeholder="Müşteri adı...">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Not (opsiyonel)</label>
                    <input type="text" id="notes" class="form-control" placeholder="Kargo notu...">
                </div>
                
                <hr>
                <label class="form-label">Kalemler</label>
                <div id="itemsContainer" class="mb-3"></div>
                
                <button type="button" class="btn btn-outline-success btn-sm mb-3" onclick="addItem()">
                    <i class="bi bi-plus-lg"></i> Kalem Ekle
                </button>
                
                <div class="d-grid">
                    <button class="btn btn-primary btn-lg" onclick="createOrder()">
                        <i class="bi bi-check-lg"></i> Siparişi Kaydet
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-12 col-lg-6">
        <div class="card">
            <div class="card-header">
                <strong><i class="bi bi-clock-history"></i> Son Siparişler</strong>
            </div>
            <ul class="list-group list-group-flush" id="recentOrders">
                <?php
                $recent = $db->query("
                    SELECT o.*, (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) AS item_count
                    FROM orders o
                    ORDER BY o.created_at DESC
                    LIMIT 15
                ")->fetchAll();
                foreach ($recent as $o): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <strong><?= htmlspecialchars($o['order_no']) ?></strong>
                        <span class="badge bg-<?= $o['status'] === 'done' ? 'success' : ($o['status'] === 'preparing' ? 'warning' : 'secondary') ?>">
                            <?= htmlspecialchars($o['status']) ?>
                        </span>
                        <br>
                        <small class="text-muted"><?= htmlspecialchars($o['customer'] ?? '') ?> · <?= $o['item_count'] ?> kalem · <?= date('d.m H:i', strtotime($o['created_at'])) ?></small>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>

<script>
const allProducts = <?= json_encode($products) ?>;
let orderItems = [];

function addItem() {
    const idx = orderItems.length;
    orderItems.push({ product_id: null, quantity: 1 });
    renderItems();
}

function removeItem(idx) {
    orderItems.splice(idx, 1);
    renderItems();
}

function updateItem(idx, field, value) {
    orderItems[idx][field] = field === 'quantity' ? parseInt(value) || 1 : value;
}

function renderItems() {
    const container = document.getElementById('itemsContainer');
    
    if (!orderItems.length) {
        container.innerHTML = '<p class="text-muted small">Henüz kalem yok. "Kalem Ekle" butonuna tıklayın.</p>';
        return;
    }
    
    container.innerHTML = orderItems.map((item, idx) => `
        <div class="card mb-2">
            <div class="card-body py-2">
                <div class="row g-2 align-items-center">
                    <div class="col-7">
                        <select class="form-select form-select-sm" onchange="updateItem(${idx}, 'product_id', this.value)">
                            <option value="">— Ürün seçin —</option>
                            ${allProducts.map(p => `
                                <option value="${p.id}" ${item.product_id == p.id ? 'selected' : ''}>
                                    ${escapeHtml(p.name)} (Stok: ${p.current_stock})
                                </option>
                            `).join('')}
                        </select>
                    </div>
                    <div class="col-3">
                        <input type="number" class="form-control form-control-sm" value="${item.quantity}" min="1"
                            onchange="updateItem(${idx}, 'quantity', this.value)">
                    </div>
                    <div class="col-2">
                        <button class="btn btn-sm btn-outline-danger w-100" onclick="removeItem(${idx})">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `).join('');
}

async function createOrder() {
    const orderNo = document.getElementById('orderNo').value.trim();
    const customer = document.getElementById('customer').value.trim();
    const notes = document.getElementById('notes').value.trim();
    
    if (!orderNo) { showToast('Sipariş no gerekli.', 'warning'); return; }
    
    const validItems = orderItems.filter(i => i.product_id);
    if (!validItems.length) { showToast('En az 1 kalem gerekli.', 'warning'); return; }
    
    const items = validItems.map(i => {
        const p = allProducts.find(x => x.id == i.product_id);
        return { barcode: p.barcode, quantity: i.quantity };
    });
    
    const res = await apiPost('/orders.php', {
        action: 'create',
        order_no: orderNo,
        customer: customer,
        notes: notes,
        items: items
    });
    
    if (!res.success) {
        showToast(res.error || 'Hata oluştu.', 'danger');
        return;
    }
    
    showToast('Sipariş oluşturuldu!', 'success');
    document.getElementById('orderNo').value = '';
    document.getElementById('customer').value = '';
    document.getElementById('notes').value = '';
    orderItems = [];
    renderItems();
    loadRecentOrders();
}

async function loadRecentOrders() {
    const res = await apiGet('/orders.php?all=1&limit=15');
    const list = document.getElementById('recentOrders');
    if (!res.success || !res.data.length) {
        list.innerHTML = '<li class="list-group-item text-muted text-center">Henüz sipariş yok.</li>';
        return;
    }
    list.innerHTML = res.data.map(o => `
        <li class="list-group-item d-flex justify-content-between align-items-center">
            <div>
                <strong>${escapeHtml(o.order_no)}</strong>
                <span class="badge bg-${o.status === 'done' ? 'success' : (o.status === 'preparing' ? 'warning' : 'secondary')}">
                    ${escapeHtml(o.status)}
                </span>
                <br>
                <small class="text-muted">${escapeHtml(o.customer || '')} · ${o.item_count} kalem · ${new Date(o.created_at).toLocaleString('tr-TR')}</small>
            </div>
        </li>
    `).join('');
}

function escapeHtml(text) {
    return String(text||'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>