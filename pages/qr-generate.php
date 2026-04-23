<?php
// pages/qr-generate.php — QR Kod Üretici
$pageTitle = 'QR Kod Üretici';
$activePage = 'qr';
$extraScripts = [
    'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js',
];
require_once __DIR__ . '/../includes/header.php';
requireAdmin();

$db = getDB();

// Ürünleri getir
$products = $db->query("
    SELECT id, name, barcode, sku, product_type
    FROM products
    WHERE active = 1 AND product_type IN ('imalat', 'parca')
    ORDER BY product_type, name
")->fetchAll();

// Lokasyonları getir
$locations = $db->query("
    SELECT id, kod, kat, bolge, raf, goz, kapasite
    FROM locations
    WHERE aktif = 1
    ORDER BY kat, bolge, raf, goz
")->fetchAll();
?>

<ul class="nav nav-tabs mb-3" id="qrTabs" role="tablist">
  <li class="nav-item">
    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#productTab" type="button">
      Ürün QR
    </button>
  </li>
  <li class="nav-item">
    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#locationTab" type="button">
      Lokasyon QR
    </button>
  </li>
</ul>

<div class="tab-content" id="qrTabsContent">
  <!-- Ürün QR -->
  <div class="tab-pane fade show active" id="productTab">
    <div class="card mb-3">
      <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Ürün QR Kodu</strong>
      </div>
      <div class="card-body">
        <div class="row g-3 mb-3">
          <div class="col-12 col-md-4">
            <label class="form-label">Ürün Seç</label>
            <select id="productSelect" class="form-select" onchange="generateProductQR()">
              <option value="">Seçiniz...</option>
              <?php foreach ($products as $p): ?>
              <option value="<?= $p['id'] ?>" data-type="<?= $p['product_type'] ?>">
                <?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($p['barcode']) ?>)
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label">Format</label>
            <select id="qrFormat" class="form-select" onchange="generateProductQR()">
              <option value="HK-[TIP]-[KOD]-[SIRA]">HK-[TIP]-[KOD]-[SIRA]</option>
              <option value="[BARKOD]">[BARKOD]</option>
            </select>
          </div>
        </div>
        
        <div id="qrPreview" class="text-center p-4 border rounded bg-light">
          <p class="text-muted">QR kod önizlemesi burada görünecek</p>
        </div>
        
        <div class="mt-3">
          <button class="btn btn-success" onclick="printQR()">
            <i class="bi bi-printer"></i> Yazdır
          </button>
          <button class="btn btn-primary" onclick="downloadQR()">
            <i class="bi bi-download"></i> İndir (PNG)
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Lokasyon QR -->
  <div class="tab-pane fade" id="locationTab">
    <div class="card mb-3">
      <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Lokasyon QR Kodu</strong>
        <button class="btn btn-sm btn-outline-secondary" onclick="generateAllLocationQRs()">
          <i class="bi bi-printer"></i> Tümünü Yazdır
        </button>
      </div>
      <div class="card-body">
        <div class="row g-3 mb-3">
          <div class="col-12 col-md-4">
            <label class="form-label">Kat</label>
            <select id="filterLocKat" class="form-select" onchange="filterLocations()">
              <option value="">Tüm Katlar</option>
              <option value="1">1. Kat</option>
              <option value="2">2. Kat</option>
              <option value="3">3. Kat</option>
              <option value="B">Bodrum</option>
            </select>
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label">Bölge</label>
            <select id="filterLocBolge" class="form-select" onchange="filterLocations()">
              <option value="">Tüm Bölgeler</option>
              <option value="A">A Koridoru</option>
              <option value="B">B Koridoru</option>
              <option value="C">C Koridoru</option>
              <option value="SOL">Sol Duvar</option>
              <option value="SAG">Sağ Duvar</option>
            </select>
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label">Raf</label>
            <select id="filterLocRaf" class="form-select" onchange="filterLocations()">
              <option value="">Tüm Raflar</option>
            </select>
          </div>
        </div>

        <div id="locationQRPreview" class="text-center p-4 border rounded bg-light">
          <p class="text-muted">Lokasyon QR kod önizlemesi burada görünecek</p>
        </div>

        <div id="locationList" class="mt-3" style="max-height:300px;overflow-y:auto;">
          <table class="table table-sm table-bordered">
            <thead><tr><th>Kod</th><th>QR</th><th>İşlem</th></tr></thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Yazdırma Alanı -->
<div id="qrPrintArea" style="display:none;"></div>

<script>
const COMPANY_SHORT = '<?= COMPANY_SHORT ?>';
let currentQRCode = null;
let allLocations = <?= json_encode($locations) ?>;
let filteredLocs = allLocations;

function generateProductQR() {
    const productId = document.getElementById('productSelect').value;
    const format = document.getElementById('qrFormat').value;
    
    if (!productId) {
        document.getElementById('qrPreview').innerHTML = '<p class="text-muted">QR kod önizlemesi burada görünecek</p>';
        currentQRCode = null;
        return;
    }
    
    apiGet(`/products.php?id=${productId}`).then(res => {
        if (!res.success) return showToast(res.error, 'danger');
        const p = res.data;
        const qrText = p.barcode;
        
        const container = document.getElementById('qrPreview');
        container.innerHTML = '';
        
        const qrDiv = document.createElement('div');
        qrDiv.id = 'qrcode-generated';
        container.appendChild(qrDiv);
        
        new QRCode(qrDiv, {
            text: qrText,
            width: 250,
            height: 250,
            colorDark: '#000000',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.H
        });
        
        const canvas = qrDiv.querySelector('canvas');
        const img = qrDiv.querySelector('img');
        
        currentQRCode = {
            text: qrText,
            product: p,
            toDataURL: function() { return canvas ? canvas.toDataURL('image/png') : img.src; }
        };
    });
}

function printQR() {
    if (!currentQRCode) return showToast('Önce QR kod oluşturun.', 'warning');
    const dataUrl = currentQRCode.toDataURL();
    const w = window.open('', '', 'width=600,height=600');
    w.document.write(`
        <html><head><title>${currentQRCode.product.name}</title></head>
        <body style="text-align:center;font-family:Arial;">
            <h2>${currentQRCode.product.name}</h2>
            <img src="${dataUrl}" />
            <p>Barkod: ${currentQRCode.product.barcode}</p>
        </body></html>
    `);
    w.document.close();
    w.print();
}

function downloadQR() {
    if (!currentQRCode) return showToast('Önce QR kod oluşturun.', 'warning');
    const link = document.createElement('a');
    link.download = `qr-${currentQRCode.product.barcode}.png`;
    link.href = currentQRCode.toDataURL();
    link.click();
    showToast('QR kod indirildi!', 'success');
}

function filterLocations() {
    const kat = document.getElementById('filterLocKat').value;
    const bolge = document.getElementById('filterLocBolge').value;
    const raf = document.getElementById('filterLocRaf').value;
    
    filteredLocs = allLocations.filter(l => {
        if (kat && l.kat !== kat) return false;
        if (bolge && l.bolge !== bolge) return false;
        if (raf && l.raf != raf) return false;
        return true;
    });
    
    renderLocationQRs();
    updateRafDropdown();
}

function updateRafDropdown() {
    const kat = document.getElementById('filterLocKat').value;
    const bolge = document.getElementById('filterLocBolge').value;
    const rafSelect = document.getElementById('filterLocRaf');
    const currentRaf = rafSelect.value;
    
    rafSelect.innerHTML = '<option value="">Tüm Raflar</option>';
    
    const rafs = [...new Set(allLocations
        .filter(l => (!kat || l.kat === kat) && (!bolge || l.bolge === bolge))
        .map(l => l.raf)
    )].sort((a,b) => a-b);
    
    rafs.forEach(r => {
        const opt = document.createElement('option');
        opt.value = r;
        opt.textContent = 'Raf ' + r;
        rafSelect.appendChild(opt);
    });
    
    rafSelect.value = currentRaf;
}

function renderLocationQRs() {
    const tbody = document.querySelector('#locationList tbody');
    tbody.innerHTML = filteredLocs.map(loc => `
        <tr>
            <td><strong>${loc.kod}</strong><br><small>${loc.kat}. Kat / ${loc.bolge} / Raf ${loc.raf} / Göz ${loc.goz}</small></td>
            <td><div id="qr-loc-${loc.id}"></div></td>
            <td>
                <button class="btn btn-sm btn-outline-secondary" onclick="printLocQR(${loc.id})">
                    <i class="bi bi-printer"></i>
                </button>
            </td>
        </tr>
    `).join('');
    
    filteredLocs.forEach(loc => {
        const container = document.getElementById('qr-loc-' + loc.id);
        if (container) {
            new QRCode(container, {
                text: loc.kod,
                width: 80,
                height: 80,
                colorDark: '#000000',
                colorLight: '#ffffff'
            });
        }
    });
}

function printLocQR(locId) {
    const loc = filteredLocs.find(l => l.id === locId);
    if (!loc) return;
    
    const w = window.open('', '', 'width=400,height=400');
    w.document.write(`
        <html><head><title>${loc.kod}</title></head>
        <body style="text-align:center;font-family:Arial;padding:20px;">
            <h3>${loc.kod}</h3>
            <p style="font-size:20px;">${loc.kat}. Kat | ${loc.bolge} | Raf ${loc.raf} | Göz ${loc.goz}</p>
            <div id="qr"></div>
        </body></html>
    `);
    w.document.close();
    
    setTimeout(() => {
        new QRCode(w.document.getElementById('qr'), {
            text: loc.kod,
            width: 200,
            height: 200
        });
        w.print();
    }, 100);
}

function generateAllLocationQRs() {
    showToast('Yazdırma penceresi açılıyor...', 'info');
    
    let html = '<html><head><title>Lokasyon QR Etiketleri</title>';
    html += '<style>body{font-family:Arial;}.label{display:inline-block;width:45%;padding:10px;margin:5px;border:1px solid #ccc;text-align:center;}</style></head><body>';
    html += '<h2>Lokasyon QR Etiketleri</h2>';
    
    filteredLocs.forEach(loc => {
        html += `<div class="label"><h3>${loc.kod}</h3><p>${loc.kat}. Kat / ${loc.bolge} / Raf ${loc.raf} / Göz ${loc.goz}</p></div>`;
    });
    
    html += '</body></html>';
    
    const w = window.open('', '', 'width=800,height=600');
    w.document.write(html);
    w.document.close();
    w.print();
}

document.addEventListener('DOMContentLoaded', function() {
    renderLocationQRs();
    updateRafDropdown();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>