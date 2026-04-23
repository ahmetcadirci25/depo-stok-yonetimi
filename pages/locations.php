<?php
// pages/locations.php — Lokasyon yönetimi (Admin)
$pageTitle   = 'Lokasyonlar';
$activePage = 'locations';
require_once __DIR__ . '/../includes/header.php';
requireAdmin();
?>

<!-- Toplu Ekleme Modal -->
<div class="modal fade" id="bulkAddModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Toplu Lokasyon Ekle</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-2">
          <div class="col-6">
            <label class="form-label">Kat *</label>
            <select id="bulkKat" class="form-select" required>
              <option value="">Seç...</option>
              <option value="1">1. Kat</option>
              <option value="2">2. Kat</option>
              <option value="3">3. Kat</option>
              <option value="B">Bodrum</option>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label">Bölge *</label>
            <select id="bulkBolge" class="form-select" required>
              <option value="">Seç...</option>
              <option value="A">A Koridoru</option>
              <option value="B">B Koridoru</option>
              <option value="C">C Koridoru</option>
              <option value="SOL">Sol Duvar</option>
              <option value="SAG">Sağ Duvar</option>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label">Raf Başlangıç *</label>
            <input type="number" id="bulkRafStart" class="form-control" value="1" min="1" required>
          </div>
          <div class="col-6">
            <label class="form-label">Raf Bitiş *</label>
            <input type="number" id="bulkRafEnd" class="form-control" value="5" min="1" required>
          </div>
          <div class="col-12">
            <label class="form-label">Her Rafta Göz Sayısı *</label>
            <input type="number" id="bulkGozPerRaf" class="form-control" value="4" min="1" max="10" required>
          </div>
          <div class="col-12">
            <label class="form-label">Kapasite (adet)</label>
            <input type="number" id="bulkKapasite" class="form-control" value="100" min="1">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
        <button type="button" class="btn btn-primary" onclick="bulkAddLocations()">Oluştur</button>
      </div>
    </div>
  </div>
</div>

<!-- Lokasyon Detay Modal -->
<div class="modal fade" id="locDetailModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="locDetailTitle">Lokasyon</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row mb-3">
          <div class="col-6">
            <strong>Kod:</strong> <span id="locDetailKod"></span>
          </div>
          <div class="col-6">
            <strong>Kap:</strong> <span id="locDetailKapasite"></span> adet
          </div>
        </div>
        <h6>Bu Gözdeki Ürünler:</h6>
        <div id="locDetailProducts" class="table-responsive">
          <table class="table table-sm table-bordered">
            <thead><tr><th>Ürün</th><th>Miktar</th></tr></thead>
            <tbody></tbody>
          </table>
        </div>
        <div id="locDetailEmpty" class="text-muted text-center py-3">Bu göz boş</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-danger btn-sm" onclick="deactivateLocation()">Pasif Yap</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
      </div>
    </div>
  </div>
</div>

<div class="card mb-3">
  <div class="card-header d-flex justify-content-between align-items-center">
    <strong>Lokasyonlar</strong>
    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#bulkAddModal">
      <i class="bi bi-plus-lg"></i> Toplu Ekle
    </button>
  </div>
  <div class="card-body">
    <div class="row g-2 mb-3">
      <div class="col-4">
        <select id="filterKat" class="form-select form-select-sm" onchange="loadLocations(1)">
          <option value="">Tüm Katlar</option>
          <option value="1">1. Kat</option>
          <option value="2">2. Kat</option>
          <option value="3">3. Kat</option>
          <option value="B">Bodrum</option>
        </select>
      </div>
      <div class="col-4">
        <select id="filterBolge" class="form-select form-select-sm" onchange="loadLocations(1)">
          <option value="">Tüm Bölgeler</option>
          <option value="A">A Koridoru</option>
          <option value="B">B Koridoru</option>
          <option value="C">C Koridoru</option>
          <option value="SOL">Sol Duvar</option>
          <option value="SAG">Sağ Duvar</option>
        </select>
      </div>
      <div class="col-4">
        <select id="filterStatus" class="form-select form-select-sm" onchange="loadLocations(1)">
          <option value="">Tümü</option>
          <option value="empty">Boş Gözler</option>
          <option value="full">Dolu Gözler</option>
        </select>
      </div>
    </div>
    <div id="locationsTable" class="table-responsive">
      <table class="table table-sm table-hover">
        <thead>
          <tr>
            <th>Kod</th>
            <th>Kat</th>
            <th>Bölge</th>
            <th>Raf</th>
            <th>Göz</th>
            <th>Kap.</th>
            <th>Durum</th>
            <th>İşlem</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
    <div id="locationsEmpty" class="text-muted text-center py-4">
      Henüz lokasyon yok. "Toplu Ekle" ile oluşturun.
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center">
      <small class="text-muted" id="locCountInfo"></small>
      <nav id="locationsPagination"></nav>
    </div>
  </div>
</div>

<script>
let locCurrentPage = 1;
let locTotalPages = 1;
let currentLocId = null;

function loadLocations(page = 1) {
  if (typeof page === 'object' && page !== null) page = 1; // event obj gelirse
  locCurrentPage = page;
  const kat = document.getElementById('filterKat').value;
  const bolge = document.getElementById('filterBolge').value;
  const status = document.getElementById('filterStatus').value;

  let url = apiBase + '/locations.php?list=1&page=' + page + '&per_page=30';
  if (kat) url += '&kat=' + kat;
  if (bolge) url += '&bolge=' + bolge;
  if (status === 'empty') url += '&empty=1';
  if (status === 'full') url += '&full=1';

  fetch(url)
    .then(r => r.json())
    .then(res => {
      if (!res.success) return showToast(res.error, 'danger');
      const data = res.data;
      const locs = data.items || data;
      const tbody = document.querySelector('#locationsTable tbody');
      const emptyDiv = document.getElementById('locationsEmpty');
      const tableDiv = document.getElementById('locationsTable');
      const pagination = document.getElementById('locationsPagination');
      const countInfo = document.getElementById('locCountInfo');

      if (locs.length === 0) {
        tableDiv.classList.add('d-none');
        emptyDiv.classList.remove('d-none');
        pagination.innerHTML = '';
        return;
      }

      tableDiv.classList.remove('d-none');
      emptyDiv.classList.add('d-none');

      tbody.innerHTML = locs.map(loc => {
        const stock = loc.current_stock || 0;
        const statusClass = stock === 0 ? 'bg-success' : (stock >= loc.kapasite ? 'bg-danger' : 'bg-warning');
        const statusText = stock === 0 ? 'Boş' : (stock >= loc.kapasite ? 'Dolu' : 'Kısmi');
        return `<tr onclick="showLocDetail(${loc.id})" style="cursor:pointer">
          <td><strong>${loc.kod}</strong></td>
          <td>${loc.kat}. Kat</td>
          <td>${loc.bolge}</td>
          <td>Raf ${loc.raf}</td>
          <td>Göz ${loc.goz}</td>
          <td>${loc.kapasite}</td>
          <td><span class="badge ${statusClass}">${statusText}</span></td>
          <td onclick="event.stopPropagation()">
            <button class="btn btn-sm btn-outline-danger" onclick="deleteLocation(${loc.id}, '${loc.kod}', ${stock})" title="Sil">
              <i class="bi bi-trash"></i>
            </button>
          </td>
        </tr>`;
      }).join('');

      const totalPages = data.pages || 1;
      const total = data.total || locs.length;
      countInfo.textContent = `${total} lokasyon — Sayfa ${page}/${totalPages}`;

      let pHtml = '<ul class="pagination pagination-sm mb-0 ms-auto" style="width:auto;">';
      if (page > 1) pHtml += `<li class="page-item"><a class="page-link" href="#" onclick="loadLocations(${page-1});return false">&laquo;</a></li>`;
      for (let i = 1; i <= totalPages; i++) {
        if (i === 1 || i === totalPages || (i >= page - 1 && i <= page + 1)) {
          pHtml += `<li class="page-item${i === page ? ' active' : ''}"><a class="page-link" href="#" onclick="loadLocations(${i});return false">${i}</a></li>`;
        } else if (i === page - 2 || i === page + 2) {
          pHtml += '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }
      }
      if (page < totalPages) pHtml += `<li class="page-item"><a class="page-link" href="#" onclick="loadLocations(${page+1});return false">&raquo;</a></li>`;
      pHtml += '</ul>';
      pagination.innerHTML = pHtml;
    })
    .catch(err => showToast('Yükleme hatası', 'danger'));
}

function bulkAddLocations() {
  const data = {
    action: 'createBulk',
    kat: document.getElementById('bulkKat').value,
    bolge: document.getElementById('bulkBolge').value,
    raf_start: parseInt(document.getElementById('bulkRafStart').value),
    raf_end: parseInt(document.getElementById('bulkRafEnd').value),
    goz_per_raf: parseInt(document.getElementById('bulkGozPerRaf').value),
    kapasite: parseInt(document.getElementById('bulkKapasite').value)
  };
  
  if (!data.kat || !data.bolge) {
    showToast('Kat ve Bölge zorunlu', 'warning');
    return;
  }
  
  fetch(apiBase + '/locations.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify(data)
  })
  .then(r => r.json())
  .then(res => {
    if (!res.success) return showToast(res.error, 'danger');
    showToast(res.data.created + ' lokasyon oluşturuldu', 'success');
    bootstrap.Modal.getInstance(document.getElementById('bulkAddModal')).hide();
    loadLocations();
    document.getElementById('bulkKat').value = '';
    document.getElementById('bulkBolge').value = '';
  })
  .catch(err => showToast('İşlem hatası', 'danger'));
}

function showLocDetail(id) {
  currentLocId = id;
  fetch(apiBase + '/locations.php?id=' + id)
    .then(r => r.json())
    .then(res => {
      if (!res.success) return showToast(res.error, 'danger');
      const loc = res.data;
      document.getElementById('locDetailTitle').textContent = loc.kod;
      document.getElementById('locDetailKod').textContent = loc.kod;
      document.getElementById('locDetailKapasite').textContent = loc.kapasite;
      
      const prods = loc.products || [];
      const tbody = document.querySelector('#locDetailProducts tbody');
      const emptyDiv = document.getElementById('locDetailEmpty');
      
      if (prods.length === 0) {
        document.getElementById('locDetailProducts').classList.add('d-none');
        emptyDiv.classList.remove('d-none');
      } else {
        document.getElementById('locDetailProducts').classList.remove('d-none');
        emptyDiv.classList.add('d-none');
        tbody.innerHTML = prods.map(p => `<tr><td>${p.name}</td><td>${p.stored}</td></tr>`).join('');
      }
      
      new bootstrap.Modal(document.getElementById('locDetailModal')).show();
    });
}

function deactivateLocation() {
  if (!confirm('Bu lokasyonu pasif yapmak istediğinize emin misiniz?')) return;
  
  fetch(apiBase + '/locations.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({action: 'deactivate', id: currentLocId})
  })
  .then(r => r.json())
  .then(res => {
    if (!res.success) return showToast(res.error, 'danger');
    showToast('Lokasyon pasif yapıldı', 'success');
    bootstrap.Modal.getInstance(document.getElementById('locDetailModal')).hide();
    loadLocations();
  });
}

document.addEventListener('DOMContentLoaded', loadLocations);
</script>

<!-- Silme Onay Modal -->
<div class="modal fade" id="deleteLocModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Lokasyon Sil</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="deleteLocId">
        <p><strong id="deleteLocKod"></strong> silinecek.</p>
        <div id="deleteLocWarning" class="alert alert-warning d-none">
          <i class="bi bi-exclamation-triangle"></i> Bu gözde <span id="deleteLocStock"></span> adet ürün var!
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
        <button type="button" class="btn btn-danger" onclick="confirmDeleteLocation()">Sil</button>
      </div>
    </div>
  </div>
</div>

<script>
let deleteLocModal;

document.addEventListener('DOMContentLoaded', function() {
  deleteLocModal = new bootstrap.Modal(document.getElementById('deleteLocModal'));
});

function deleteLocation(id, kod, stock) {
  event.stopPropagation();
  document.getElementById('deleteLocId').value = id;
  document.getElementById('deleteLocKod').textContent = kod;
  
  const warning = document.getElementById('deleteLocWarning');
  if (stock > 0) {
    warning.classList.remove('d-none');
    document.getElementById('deleteLocStock').textContent = stock;
  } else {
    warning.classList.add('d-none');
  }
  
  deleteLocModal.show();
}

async function confirmDeleteLocation() {
  const id = document.getElementById('deleteLocId').value;
  
  const res = await apiPost('/locations.php', {
    action: 'delete',
    id: parseInt(id)
  });
  
  if (res.success) {
    deleteLocModal.hide();
    showToast('Lokasyon silindi', 'success');
    loadLocations();
  } else {
    alert(res.error || 'Hata');
  }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>