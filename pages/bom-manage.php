<?php
// pages/bom-manage.php — Reçete (BOM) Yönetimi
$pageTitle = 'Reçete Yönetimi';
$activePage = 'bom';
require_once __DIR__ . '/../includes/header.php';
requireAdmin();

$db = getDB();
$success = '';
$error = '';

// Reçete kaydetme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save_bom') {
        $mamulId = (int)($_POST['mamul_id'] ?? 0);
        $parcaList = json_decode($_POST['parca_list'] ?? '[]', true);
        
        if ($mamulId && is_array($parcaList)) {
            try {
                $db->beginTransaction();
                
                // Eski reçeteyi sil
                $db->prepare("DELETE FROM bom_items WHERE mamul_id = ?")->execute([$mamulId]);
                
                // Yeni reçeteyi ekle
                $stmt = $db->prepare("INSERT INTO bom_items (mamul_id, parca_id, miktar) VALUES (?, ?, ?)");
                foreach ($parcaList as $item) {
                    if (isset($item['parca_id']) && isset($item['miktar'])) {
                        $stmt->execute([$mamulId, (int)$item['parca_id'], (int)$item['miktar']]);
                    }
                }
                
                $db->commit();
                $success = "Reçete kaydedildi.";
            } catch (PDOException $e) {
                $db->rollBack();
                $error = "Hata: " . $e->getMessage();
            }
        }
    }
}

// Mamul ürünleri al (imalat tipi)
$mamuls = $db->query("
    SELECT p.id, p.name, p.barcode, p.sku,
           (SELECT COUNT(*) FROM bom_items WHERE mamul_id = p.id) as parca_count
    FROM products p
    WHERE p.product_type = 'imalat' AND p.active = 1
    ORDER BY p.name
")->fetchAll();

// Parça ürünleri al (parça tipi)
$parcas = $db->query("
    SELECT id, name, barcode, sku, product_type
    FROM products
    WHERE product_type IN ('parca', 'hazir') AND active = 1
    ORDER BY name
")->fetchAll();
?>

<div class="row g-3">
    <!-- Mamul Seçimi -->
    <div class="col-12 col-md-4">
        <div class="card">
            <div class="card-header">
                <strong>İmalat Ürünleri</strong>
            </div>
            <div class="list-group list-group-flush" id="mamulList">
                <?php foreach ($mamuls as $m): ?>
                <button class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
                        onclick="loadBOM(<?= $m['id'] ?>)" data-bs-toggle="list">
                    <div>
                        <strong><?= htmlspecialchars($m['name']) ?></strong><br>
                        <small class="text-muted"><?= htmlspecialchars($m['sku'] ?: $m['barcode']) ?></small>
                    </div>
                    <span class="badge bg-primary rounded-pill"><?= $m['parca_count'] ?> parça</span>
                </button>
                <?php endforeach; ?>
                <?php if (empty($mamuls)): ?>
                <div class="list-group-item text-muted text-center py-3">
                    İmalat ürünü bulunamadı.<br>
                    <small>Ürün eklerken "Kendi İmalatı" tipini seçin.</small>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Reçete Düzenleme -->
    <div class="col-12 col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Reçete İçeriği</strong>
                <button class="btn btn-sm btn-outline-success" onclick="addParcaRow()">
                    <i class="bi bi-plus-lg"></i> Parça Ekle
                </button>
            </div>
            <div class="card-body">
                <div class="alert alert-info py-2 mb-3">
                    <i class="bi bi-info-circle"></i>
                    Sol taraftan bir ürün seçin veya yeni imalat ürünü ekleyin.
                </div>
                
                <form id="bomForm" style="display:none;">
                    <input type="hidden" id="currentMamulId">
                    <h5 id="currentMamulName" class="mb-3"></h5>
                    
                    <div id="parcaContainer" class="mb-3"></div>
                    
                    <button type="button" class="btn btn-primary" onclick="saveBOM()">
                        <i class="bi bi-save"></i> Kaydet
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
let currentBOM = [];
let allParcas = <?= json_encode($parcas) ?>;

async function loadBOM(mamulId) {
    document.getElementById('bomForm').style.display = 'block';
    document.getElementById('currentMamulId').value = mamulId;

    const product = allParcas.find(p => false); // placeholder, aşağıda güncellenir
    document.getElementById('currentMamulName').textContent = '';

    const prodRes = await apiGet(`/products.php?id=${mamulId}`);
    if (prodRes.success) {
        document.getElementById('currentMamulName').textContent = prodRes.data.name;
    }

    const bomRes = await apiGet(`/bom.php?mamul_id=${mamulId}`);
    currentBOM = bomRes.success ? bomRes.data : [];
    renderParcaList();
}

function renderParcaList() {
    const container = document.getElementById('parcaContainer');
    if (!currentBOM.length) {
        container.innerHTML = '<p class="text-muted">Henüz parça eklenmedi.</p>';
        return;
    }
    
    container.innerHTML = currentBOM.map((item, idx) => `
        <div class="row g-2 mb-2 align-items-center">
            <div class="col-6">
                <select class="form-select form-select-sm" onchange="updateParca(${idx}, this.value)">
                    ${allParcas.map(p => `
                        <option value="${p.id}" ${item.parca_id == p.id ? 'selected' : ''}>
                            ${escapeHtml(p.name)} (${escapeHtml(p.barcode)})
                        </option>
                    `).join('')}
                </select>
            </div>
            <div class="col-3">
                <input type="number" class="form-control form-control-sm" 
                       value="${item.miktar}" min="1"
                       onchange="updateParcaQty(${idx}, this.value)">
            </div>
            <div class="col-3">
                <button class="btn btn-sm btn-outline-danger w-100" onclick="removeParca(${idx})">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        </div>
    `).join('');
}

function addParcaRow() {
    currentBOM.push({ parca_id: allParcas[0]?.id || 1, miktar: 1 });
    renderParcaList();
}

function updateParca(idx, value) {
    currentBOM[idx].parca_id = parseInt(value);
}

function updateParcaQty(idx, value) {
    currentBOM[idx].miktar = parseInt(value) || 1;
}

function removeParca(idx) {
    currentBOM.splice(idx, 1);
    renderParcaList();
}

async function saveBOM() {
    const mamulId = document.getElementById('currentMamulId').value;
    const res = await apiPost('/bom.php', {
        action: 'save_bom',
        mamul_id: mamulId,
        parca_list: currentBOM
    });
    
    if (res.success) {
        showToast('Reçete kaydedildi!', 'success');
        location.reload();
    } else {
        showToast(res.error || 'Hata oluştu.', 'danger');
    }
}

function escapeHtml(text) {
    return String(text).replace(/[&<>"']/g, function(m) {
        return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m];
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
