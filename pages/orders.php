<?php
// pages/orders.php — Admin: Sipariş yönetimi
$pageTitle = 'Siparişler';
$activePage = 'orders';
require_once __DIR__ . '/../includes/header.php';
requireAdmin();

$db = getDB();

// Siparişleri getir (son 100)
$orders = $db->query("
    SELECT o.*, 
           u.username as prepared_by_name,
           (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
    FROM orders o
    LEFT JOIN users u ON u.id = o.prepared_by
    ORDER BY o.created_at DESC
    LIMIT 100
")->fetchAll();
?>

<div class="row">
    <div class="col-12">
        <h4 class="mb-3"><i class="bi bi-cart"></i> Siparişler</h4>
        
        <div class="row mb-3">
            <div class="col-auto">
                <select id="filterStatus" class="form-select">
                    <option value="">Tüm Durumlar</option>
                    <option value="pending">Bekleyen</option>
                    <option value="preparing">Hazırlanıyor</option>
                    <option value="done">Tamamlanan</option>
                    <option value="cancelled">İptal</option>
                </select>
            </div>
            <div class="col-auto">
                <input type="date" id="filterDate" class="form-control">
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover table-sm" id="ordersTable">
                <thead>
                    <tr>
                        <th>Sipariş No</th>
                        <th>Müşteri</th>
                        <th>Kalem</th>
                        <th>Durum</th>
                        <th>Tarih</th>
                        <th>Hazırlayan</th>
                        <th>İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $o): ?>
                    <tr data-status="<?= $o['status'] ?>" data-date="<?= substr($o['created_at'], 0, 10) ?>">
                        <td><strong><?= htmlspecialchars($o['order_no']) ?></strong></td>
                        <td><?= htmlspecialchars($o['customer'] ?: '-') ?></td>
                        <td><?= $o['item_count'] ?></td>
                        <td>
                            <?php if ($o['status'] === 'pending'): ?>
                                <span class="badge bg-warning">Bekleyen</span>
                            <?php elseif ($o['status'] === 'preparing'): ?>
                                <span class="badge bg-info">Hazırlanıyor</span>
                            <?php elseif ($o['status'] === 'done'): ?>
                                <span class="badge bg-success">Tamamlandı</span>
                            <?php else: ?>
                                <span class="badge bg-danger">İptal</span>
                            <?php endif; ?>
                        </td>
                        <td><?= date('d.m H:i', strtotime($o['created_at'])) ?></td>
                        <td><?= htmlspecialchars($o['prepared_by_name'] ?: '-') ?></td>
                        <td>
                            <?php if ($o['status'] === 'pending'): ?>
                                <button class="btn btn-sm btn-outline-primary me-1" onclick="editOrder(<?= $o['id'] ?>)">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteOrder(<?= $o['id'] ?>)">
                                    <i class="bi bi-trash"></i>
                                </button>
                            <?php elseif ($o['status'] === 'preparing'): ?>
                                <button class="btn btn-sm btn-outline-secondary" onclick="viewOrder(<?= $o['id'] ?>)">
                                    <i class="bi bi-eye"></i>
                                </button>
                            <?php else: ?>
                                <button class="btn btn-sm btn-outline-secondary" onclick="viewOrder(<?= $o['id'] ?>)">
                                    <i class="bi bi-eye"></i>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Düzenleme Modal -->
<div class="modal fade" id="editOrderModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Sipariş Düzenle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editOrderId">
                <div class="mb-3">
                    <label class="form-label">Müşteri</label>
                    <input type="text" id="editCustomer" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label">Not</label>
                    <textarea id="editNotes" class="form-control" rows="2"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Kalemler</label>
                    <div id="editItems" class="border rounded p-2" style="max-height: 200px; overflow-y: auto;"></div>
                    <button class="btn btn-sm btn-outline-success mt-2" onclick="addItemToOrder()">
                        <i class="bi bi-plus"></i> Kalem Ekle
                    </button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                <button type="button" class="btn btn-primary" onclick="saveOrder()">Kaydet</button>
            </div>
        </div>
    </div>
</div>

<!-- Silme Onay Modal -->
<div class="modal fade" id="deleteOrderModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Sipariş Sil</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Bu siparişi silmek istediğinizden emin misiniz?</p>
                <input type="hidden" id="deleteOrderId">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                <button type="button" class="btn btn-danger" onclick="confirmDeleteOrder()">Sil</button>
            </div>
        </div>
    </div>
</div>

<!-- Kalem Ekle Modal -->
<div class="modal fade" id="addItemModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Kalem Ekle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Ürün Ara</label>
                    <input type="text" id="itemSearch" class="form-control" placeholder="Barkod veya isim...">
                    <div id="itemResults" class="list-group mt-2" style="max-height: 200px; overflow-y: auto;"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Miktar</label>
                    <input type="number" id="itemQuantity" class="form-control" value="1" min="1">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                <button type="button" class="btn btn-success" onclick="confirmAddItem()">Ekle</button>
            </div>
        </div>
    </div>
</div>

<script>
let editModal, deleteModal, addItemModal;
let currentOrderItems = [];

document.addEventListener('DOMContentLoaded', function() {
    editModal = new bootstrap.Modal(document.getElementById('editOrderModal'));
    deleteModal = new bootstrap.Modal(document.getElementById('deleteOrderModal'));
    addItemModal = new bootstrap.Modal(document.getElementById('addItemModal'));
    
    // Filtreleme
    document.getElementById('filterStatus').addEventListener('change', filterOrders);
    document.getElementById('filterDate').addEventListener('change', filterOrders);
    
    // Ürün arama
    document.getElementById('itemSearch').addEventListener('input', debounce(searchItems, 300));
});

function filterOrders() {
    const status = document.getElementById('filterStatus').value;
    const date = document.getElementById('filterDate').value;
    const rows = document.querySelectorAll('#ordersTable tbody tr');
    
    rows.forEach(row => {
        let show = true;
        if (status && row.dataset.status !== status) show = false;
        if (date && row.dataset.date !== date) show = false;
        row.style.display = show ? '' : 'none';
    });
}

async function editOrder(id) {
    const res = await apiCall('/api/orders.php?order_id=' + id);
    if (!res.success) return alert(res.error || 'Hata');
    
    const order = res.data;
    document.getElementById('editOrderId').value = id;
    document.getElementById('editCustomer').value = order.customer || '';
    document.getElementById('editNotes').value = order.notes || '';
    
    // Kalemleri getir
    const itemsRes = await apiCall('/api/orders.php?order_id=' + id + '&items=1');
    currentOrderItems = itemsRes.success ? itemsRes.data.items : [];
    renderEditItems();
    
    editModal.show();
}

function renderEditItems() {
    const container = document.getElementById('editItems');
    container.innerHTML = currentOrderItems.map((item, idx) => `
        <div class="d-flex justify-content-between align-items-center border-bottom py-1">
            <span>${item.product_name} x ${item.quantity}</span>
            <button class="btn btn-sm btn-outline-danger" onclick="removeItem(${idx})">
                <i class="bi bi-x"></i>
            </button>
        </div>
    `).join('') || '<small class="text-muted">Kalem yok</small>';
}

function removeItem(idx) {
    currentOrderItems.splice(idx, 1);
    renderEditItems();
}

function addItemToOrder() {
    document.getElementById('itemSearch').value = '';
    document.getElementById('itemResults').innerHTML = '';
    document.getElementById('itemQuantity').value = '1';
    addItemModal.show();
}

async function searchItems(q) {
    if (q.length < 2) return;
    const res = await apiCall('/api/products.php?list=1&search=' + encodeURIComponent(q));
    const items = res.success ? res.data.items : [];
    document.getElementById('itemResults').innerHTML = items.map(p => `
        <button class="list-group-item list-group-item-action" onclick="selectItem(${p.id}, '${p.name.replace(/'/g, "\\'")}')">
            ${p.name} (${p.barcode})
        </button>
    `).join('');
}

function selectItem(id, name) {
    document.getElementById('itemSearch').value = name;
    document.getElementById('itemSearch').dataset.productId = id;
    document.getElementById('itemResults').innerHTML = '';
}

function confirmAddItem() {
    const productId = document.getElementById('itemSearch').dataset.productId;
    const name = document.getElementById('itemSearch').value;
    const qty = parseInt(document.getElementById('itemQuantity').value) || 1;
    
    if (!productId) return alert('Ürün seçin');
    
    currentOrderItems.push({ product_id: productId, product_name: name, quantity: qty });
    renderEditItems();
    addItemModal.hide();
}

async function saveOrder() {
    const id = document.getElementById('editOrderId').value;
    const customer = document.getElementById('editCustomer').value;
    const notes = document.getElementById('editNotes').value;
    
    const res = await apiCall('/api/orders.php', 'POST', {
        action: 'update',
        order_id: parseInt(id),
        customer,
        notes
    });
    
    if (res.success) {
        editModal.hide();
        location.reload();
    } else {
        alert(res.error || 'Hata');
    }
}

function deleteOrder(id) {
    document.getElementById('deleteOrderId').value = id;
    deleteModal.show();
}

async function confirmDeleteOrder() {
    const id = document.getElementById('deleteOrderId').value;
    const res = await apiCall('/api/orders.php', 'POST', {
        action: 'delete',
        order_id: parseInt(id)
    });
    
    if (res.success) {
        deleteModal.hide();
        location.reload();
    } else {
        alert(res.error || 'Hata');
    }
}

function viewOrder(id) {
    window.location.href = '/stok/pages/order-prep.php?order_id=' + id;
}

function debounce(fn, ms) {
    let timer;
    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn.apply(this, args), ms);
    };
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
