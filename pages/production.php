<?php
// pages/production.php — Üretim Emri
$pageTitle = 'Üretim Emri';
$activePage = 'production';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();

// İmalat ürünleri (sadece BOM'u olanlar)
$mamuls = $db->query("
    SELECT p.id, p.name, p.barcode, p.sku, p.min_stock,
        (SELECT COALESCE(SUM(
            CASE WHEN sm.type='in' THEN sm.quantity
                 WHEN sm.type='out' THEN -sm.quantity
                 WHEN sm.type='correction' THEN sm.quantity
            END
        ), 0) FROM stock_movements sm WHERE sm.product_id = p.id) AS current_stock,
        (SELECT COUNT(*) FROM bom_items WHERE mamul_id = p.id) AS parca_count
    FROM products p
    WHERE p.product_type = 'imalat' AND p.active = 1
    AND (SELECT COUNT(*) FROM bom_items WHERE mamul_id = p.id) > 0
    ORDER BY p.name
")->fetchAll();

// Son üretim emirleri
$recentOrders = $db->query("
    SELECT po.*, p.name AS mamul_name,
        (SELECT name FROM users WHERE id = po.created_by) AS creator_name
    FROM production_orders po
    JOIN products p ON p.id = po.mamul_id
    ORDER BY po.created_at DESC
    LIMIT 20
")->fetchAll();
?>

<div class="row g-3">
    <!-- Üretim Emri Formu -->
    <div class="col-12 col-lg-6">
        <div class="card">
            <div class="card-header">
                <strong><i class="bi bi-hammer"></i> Yeni Üretim Emri</strong>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Mamul Ürün</label>
                    <select id="mamulSelect" class="form-select" onchange="checkBOM()">
                        <option value="">— Seçin —</option>
                        <?php foreach ($mamuls as $m): ?>
                        <option value="<?= $m['id'] ?>">
                            <?= htmlspecialchars($m['name']) ?> 
                            (Stok: <?= $m['current_stock'] ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Üretim Miktarı</label>
                    <div class="input-group">
                        <button class="btn btn-outline-secondary" onclick="changeProdQty(-1)">−</button>
                        <input type="number" id="prodQty" class="form-control text-center" value="1" min="1" max="9999" oninput="checkBOM()">
                        <button class="btn btn-outline-secondary" onclick="changeProdQty(1)">+</button>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Not (opsiyonel)</label>
                    <input type="text" id="prodNotes" class="form-control" placeholder="Üretim notu..." maxlength="200">
                </div>
                
                <button class="btn btn-primary btn-lg w-100" onclick="createProduction()">
                    <i class="bi bi-play-fill"></i> Üretimi Başlat
                </button>
                
                <div id="warningArea" class="alert alert-warning mt-3 d-none">
                    <h6><i class="bi bi-exclamation-triangle"></i> Parça Yetersiz!</h6>
                    <ul id="warningList" class="mb-0 small"></ul>
                    <hr>
                    <button class="btn btn-sm btn-outline-danger" onclick="forceProduction()">
                        Yine de üret (Eksik parçalarla)
                    </button>
                </div>
                
                <div id="successArea" class="alert alert-success mt-3 d-none">
                    <i class="bi bi-check-circle-fill"></i> <span id="successMsg"></span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Son Üretim Emirleri -->
    <div class="col-12 col-lg-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong><i class="bi bi-clock-history"></i> Son Üretimler</strong>
                <button class="btn btn-sm btn-outline-secondary" onclick="loadProductionHistory()">
                    <i class="bi bi-arrow-clockwise"></i>
                </button>
            </div>
            <ul class="list-group list-group-flush" id="productionList">
                <?php foreach ($recentOrders as $o): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <strong><?= htmlspecialchars($o['mamul_name']) ?></strong>
                        <span class="badge bg-<?= $o['status'] === 'completed' ? 'success' : ($o['status'] === 'cancelled' ? 'danger' : 'warning') ?>">
                            <?= $o['miktar'] ?> adet
                        </span>
                        <br>
                        <small class="text-muted">
                            <?= htmlspecialchars($o['creator_name'] ?? '—') ?> • 
                            <?= date('d.m H:i', strtotime($o['created_at'])) ?>
                        </small>
                    </div>
                    <?php if ($o['status'] === 'pending'): ?>
                    <button class="btn btn-sm btn-outline-danger" onclick="cancelProduction(<?= $o['id'] ?>)" title="İptal">
                        <i class="bi bi-x-lg"></i>
                    </button>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
                <?php if (empty($recentOrders)): ?>
                <li class="list-group-item text-muted text-center py-3">
                    Henüz üretim emri yok.
                </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>

<script>
let selectedMamulId = null;
let pendingProduction = null;

function changeProdQty(delta) {
    const input = document.getElementById('prodQty');
    input.value = Math.max(1, (parseInt(input.value) || 1) + delta);
}

async function checkBOM() {
    const mamulId = document.getElementById('mamulSelect').value;
    const qty = parseInt(document.getElementById('prodQty').value) || 1;
    const warningArea = document.getElementById('warningArea');
    const successArea = document.getElementById('successArea');
    
    warningArea.classList.add('d-none');
    successArea.classList.add('d-none');
    
    if (!mamulId) {
        selectedMamulId = null;
        pendingProduction = null;
        document.getElementById('warningArea').classList.add('d-none');
        document.getElementById('successArea').classList.add('d-none');
        return;
    }
    
    selectedMamulId = parseInt(mamulId);
    
    // Aynı ürün ve miktar için tekrar kontrol etme
    if (pendingProduction && 
        pendingProduction.mamul_id === selectedMamulId && 
        pendingProduction.miktar === qty) {
        return;
    }
    
    const res = await apiPost('/production.php', {
        action: 'check',
        mamul_id: selectedMamulId,
        miktar: qty
    });
    
    if (!res.success) {
        showToast(res.error || 'Hata oluştu.', 'danger');
        return;
    }
    
    if (res.data.sufficiency === false) {
        // Parça yetersiz
        const yetersiz = res.data.yetersiz_parcalar;
        const list = document.getElementById('warningList');
        list.innerHTML = yetersiz.map(p => 
            `<li>${escapeHtml(p.parca_name)}: ${p.mevcut}/${p.gerekli} (eksik: ${p.eksik})</li>`
        ).join('');
        warningArea.classList.remove('d-none');
        
        pendingProduction = {
            mamul_id: selectedMamulId,
            miktar: qty,
            notes: document.getElementById('prodNotes').value
        };
    } else {
        // Yeterli parça var, üretime hazır
        const successArea = document.getElementById('successArea');
        const msgSpan = document.getElementById('successMsg');
        if (msgSpan) msgSpan.textContent = 'Parçalar yeterli. "Üretimi Başlat" butonuna basarak üretimi başlatabilirsiniz.';
        successArea.classList.remove('d-none');
        pendingProduction = {
            mamul_id: selectedMamulId,
            miktar: qty,
            notes: document.getElementById('prodNotes').value
        };
    }
}

async function createProduction() {
    if (!selectedMamulId) {
        showToast('Önce mamul seçin.', 'warning');
        return;
    }
    
    // Önce warning/success alanlarını temizle
    document.getElementById('warningArea').classList.add('d-none');
    document.getElementById('successArea').classList.add('d-none');
    
    const qty = parseInt(document.getElementById('prodQty').value) || 1;
    
    const res = await apiPost('/production.php', {
        action: 'create',
        mamul_id: selectedMamulId,
        miktar: qty,
        notes: document.getElementById('prodNotes').value
    });
    
    if (!res.success) {
        showToast(res.error || 'Hata oluştu.', 'danger');
        return;
    }
    
    if (res.data.sufficiency === false) {
        showToast('Parça yetersiz! İnceleyin.', 'warning');
        return;
    }
    
    showSuccess(res.data.message);
    loadProductionHistory();
}

async function forceProduction() {
    if (!pendingProduction) return;
    
    const res = await apiPost('/production.php', {
        action: 'force_create',
        mamul_id: pendingProduction.mamul_id,
        miktar: pendingProduction.miktar,
        notes: pendingProduction.notes + ' (Zorla)'
    });
    
    if (!res.success) {
        showToast(res.error || 'Hata oluştu.', 'danger');
        return;
    }
    
    showSuccess(res.data.message);
    document.getElementById('warningArea').classList.add('d-none');
    loadProductionHistory();
}

function showSuccess(msg) {
    const area = document.getElementById('successArea');
    const msgSpan = document.getElementById('successMsg');
    if (msgSpan) msgSpan.textContent = msg;
    if (area) {
        area.classList.remove('d-none');
        const warning = document.getElementById('warningArea');
        if (warning) warning.classList.add('d-none');
        setTimeout(() => {
            area.classList.add('d-none');
        }, 5000);
    }
}

async function loadProductionHistory() {
    const res = await apiGet('/production.php?limit=20');
    const list = document.getElementById('productionList');
    
    if (!res.success || !res.data.length) {
        list.innerHTML = '<li class="list-group-item text-muted text-center py-3">Henüz üretim emri yok.</li>';
        return;
    }
    
    list.innerHTML = res.data.map(o => `
        <li class="list-group-item d-flex justify-content-between align-items-center">
            <div>
                <strong>${escapeHtml(o.mamul_name)}</strong>
                <span class="badge bg-${o.status === 'completed' ? 'success' : 'warning'}">
                    ${o.miktar} adet
                </span>
                <br>
                <small class="text-muted">
                    ${escapeHtml(o.creator_name || '—')} • 
                    ${new Date(o.created_at).toLocaleString('tr-TR')}
                </small>
            </div>
        </li>
    `).join('');
}

function escapeHtml(text) {
    return String(text||'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
</script>

<!-- İptal Onay Modal -->
<div class="modal fade" id="cancelProductionModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Üretim İptal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Bu üretim emrini iptal etmek istediğinizden emin misiniz?</p>
                <p class="text-muted small">İptal edildiğinde parçalar stoktan düşülür, mamul stoktan silinir.</p>
                <input type="hidden" id="cancelProductionId">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                <button type="button" class="btn btn-danger" onclick="confirmCancelProduction()">Evet, İptal Et</button>
            </div>
        </div>
    </div>
</div>

<script>
let cancelProdModal;

document.addEventListener('DOMContentLoaded', function() {
    cancelProdModal = new bootstrap.Modal(document.getElementById('cancelProductionModal'));
});

function cancelProduction(id) {
    document.getElementById('cancelProductionId').value = id;
    cancelProdModal.show();
}

async function confirmCancelProduction() {
    const id = document.getElementById('cancelProductionId').value;
    
    const res = await apiPost('/production.php', {
        action: 'cancel',
        production_id: parseInt(id)
    });
    
    if (res.success) {
        cancelProdModal.hide();
        showToast('Üretim iptal edildi', 'success');
        setTimeout(() => location.reload(), 1000);
    } else {
        alert(res.error || 'Hata');
    }
}

// loadProductionHistory'ı güncelle
const oldLoadProductionHistory = loadProductionHistory;
loadProductionHistory = async function() {
    const res = await apiGet('/production.php?limit=20');
    const list = document.getElementById('productionList');
    
    if (!res.success || !res.data.length) {
        list.innerHTML = '<li class="list-group-item text-muted text-center py-3">Henüz üretim emri yok.</li>';
        return;
    }
    
    list.innerHTML = res.data.map(o => {
        const statusClass = o.status === 'completed' ? 'success' : (o.status === 'cancelled' ? 'danger' : 'warning');
        let btn = '';
        if (o.status === 'pending') {
            btn = `<button class="btn btn-sm btn-outline-danger" onclick="cancelProduction(${o.id})" title="İptal">
                <i class="bi bi-x-lg"></i>
            </button>`;
        }
        return `<li class="list-group-item d-flex justify-content-between align-items-center">
            <div>
                <strong>${escapeHtml(o.mamul_name)}</strong>
                <span class="badge bg-${statusClass}">
                    ${o.miktar} adet
                </span>
                <br>
                <small class="text-muted">
                    ${escapeHtml(o.creator_name || '—')} • 
                    ${new Date(o.created_at).toLocaleString('tr-TR')}
                </small>
            </div>
            ${btn}
        </li>`;
    }).join('');
};
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>