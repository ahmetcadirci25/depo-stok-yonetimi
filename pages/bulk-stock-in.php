<?php
// pages/bulk-stock-in.php — Toplu Stok Girişi
$pageTitle = 'Toplu Stok Girişi';
$activePage = 'stock';
require_once __DIR__ . '/../includes/header.php';
requireAdmin(); // Sadece admin

$db = getDB();
$success = '';
$error = '';

// Toplu stok girişi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'bulk_stock_in') {
        $items = json_decode($_POST['items'] ?? '[]', true);
        $note = trim($_POST['note'] ?? '');
        
        if (is_array($items) && !empty($items)) {
            try {
                $db->beginTransaction();
                
                foreach ($items as $item) {
                    if (isset($item['barcode']) && isset($item['quantity'])) {
                        $barcode = trim($item['barcode']);
                        $qty = (int)$item['quantity'];
                        $itemNote = trim($item['note'] ?? '');
                        
                        if ($barcode && $qty > 0) {
                            // Ürünü bul
                            $stmt = $db->prepare("SELECT id FROM products WHERE barcode = ? AND active = 1");
                            $stmt->execute([$barcode]);
                            $product = $stmt->fetch();
                            
                            if ($product) {
                                // Stok hareketi ekle
                                $stmt = $db->prepare("
                                    INSERT INTO stock_movements (product_id, type, quantity, reference, note, user_id)
                                    VALUES (?, 'in', ?, 'Toplu Giriş', ?, ?)
                                ");
                                $stmt->execute([
                                    $product['id'], 
                                    $qty, 
                                    $itemNote ?: $note,
                                    $_SESSION['user_id'] ?? null
                                ]);
                            }
                        }
                    }
                }
                
                $db->commit();
                $success = count($items) . " ürün için toplu stok girişi yapıldı.";
            } catch (PDOException $e) {
                $db->rollBack();
                $error = "Hata: " . $e->getMessage();
            }
        }
    }
}

// Tüm ürünleri al
$products = $db->query("
    SELECT barcode, name, product_type, sku
    FROM products 
    WHERE active = 1
    ORDER BY name
")->fetchAll();
?>

<div class="row g-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <strong>Toplu Stok Girişi</strong>
            </div>
            <div class="card-body">
                <form id="bulkStockForm">
                    <div class="mb-3">
                        <label class="form-label">Genel Not</label>
                        <input type="text" id="bulkNote" class="form-control" placeholder="Tüm kalemler için genel not">
                    </div>
                    
                    <div class="mb-3">
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="addBulkItemRow()">
                            <i class="bi bi-plus-lg"></i> Yeni Satır
                        </button>
                    </div>
                    
                    <div id="bulkItemsContainer">
                        <div class="row g-2 mb-2 align-items-end bulk-item-row">
                            <div class="col-5">
                                <label class="form-label">Ürün</label>
                                <select class="form-select form-select-sm product-select">
                                    <option value="">Seçiniz...</option>
                                    <?php foreach ($products as $p): ?>
                                    <option value="<?= htmlspecialchars($p['barcode']) ?>">
                                        <?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($p['barcode']) ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-2">
                                <label class="form-label">Miktar</label>
                                <input type="number" class="form-control form-control-sm quantity-input" 
                                       min="1" value="1">
                            </div>
                            <div class="col-3">
                                <label class="form-label">Lokasyon</label>
                                <select class="form-select form-select-sm location-select">
                                    <option value="">—</option>
                                </select>
                            </div>
                            <div class="col-1">
                                <label class="form-label">&nbsp;</label>
                                <button type="button" class="btn btn-outline-danger btn-sm w-100 remove-row">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <button type="button" class="btn btn-success" onclick="saveBulkStock()">
                        <i class="bi bi-save"></i> Toplu Stok Girişi Yap
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
let allLocations = [];

async function loadLocationsForBulkStockIn() {
    const res = await apiGet('/locations.php?list');
    if (!res.success) return;
    allLocations = res.data;
    document.querySelectorAll('.location-select').forEach(select => {
        select.innerHTML = '<option value="">—</option>';
        allLocations.forEach(loc => {
            const opt = document.createElement('option');
            opt.value = loc.id;
            opt.textContent = loc.kod;
            select.appendChild(opt);
        });
    });
}

function addBulkItemRow() {
    const container = document.getElementById('bulkItemsContainer');
    const row = document.createElement('div');
    row.className = 'row g-2 mb-2 align-items-end bulk-item-row';
    row.innerHTML = `
        <div class="col-5">
            <select class="form-select form-select-sm product-select">
                <option value="">Seçiniz...</option>
                <?php foreach ($products as $p): ?>
                <option value="<?= htmlspecialchars($p['barcode']) ?>">
                    <?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($p['barcode']) ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-2">
            <input type="number" class="form-control form-control-sm quantity-input" min="1" value="1">
        </div>
        <div class="col-3">
            <select class="form-select form-select-sm location-select">
                <option value="">—</option>
            </select>
        </div>
        <div class="col-1">
            <button type="button" class="btn btn-outline-danger btn-sm w-100 remove-row" onclick="this.closest('.bulk-item-row').remove()">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    `;
    container.appendChild(row);
    
    const select = row.querySelector('.location-select');
    allLocations.forEach(loc => {
        const opt = document.createElement('option');
        opt.value = loc.id;
        opt.textContent = loc.kod;
        select.appendChild(opt);
    });
}

function saveBulkStock() {
    const rows = document.querySelectorAll('.bulk-item-row');
    const items = [];
    
    for (let row of rows) {
        const select = row.querySelector('.product-select');
        const qtyInput = row.querySelector('.quantity-input');
        const locSelect = row.querySelector('.location-select');
        
        const barcode = select.value;
        const quantity = parseInt(qtyInput.value) || 1;
        const location_id = locSelect.value ? parseInt(locSelect.value) : null;
        
        if (barcode && quantity > 0) {
            items.push({
                barcode: barcode,
                quantity: quantity,
                location_id: location_id
            });
        }
    }
    
    if (items.length === 0) {
        showToast('En az 1 ürün ekleyin.', 'warning');
        return;
    }
    
    fetch(apiBase + '/stock.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'bulk_stock_in', items: items })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast(data.message || 'Toplu stok girişi yapıldı!', 'success');
            document.getElementById('bulkItemsContainer').innerHTML = '';
            addBulkItemRow();
        } else {
            showToast(data.error || 'Hata oluştu.', 'danger');
        }
    })
    .catch(err => showToast('Hata: ' + err.message, 'danger'));
}

document.addEventListener('click', function(e) {
    if (e.target.classList.contains('remove-row')) {
        e.target.closest('.bulk-item-row').remove();
    }
});

document.addEventListener('DOMContentLoaded', loadLocationsForBulkStockIn);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
