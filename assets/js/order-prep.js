// assets/js/order-prep.js — Sipariş hazırlama sayfası özel scriptleri

let currentOrderId = null;
let orderItems = [];
let scanner = null;

// ── Bekleyen siparişleri yücle ───────────────────────────────
async function loadPending() {
    const res = await apiGet('/orders.php?pending=1');
    const list = document.getElementById('pendingList');
    if (!res.success || !res.data.length) {
        list.innerHTML = '<p class="text-muted text-center py-3 small">Bekleyen sipariş yok.</p>';
        return;
    }
    list.innerHTML = res.data.map(o => `
        <div class="d-flex justify-content-between align-items-center border-bottom py-2 px-1">
            <div>
                <strong class="small">${escHtml(o.order_no)}</strong><br>
                <span class="text-muted x-small">${escHtml(o.customer||'')} · ${o.item_count} kalem</span>
            </div>
            <button class="btn btn-sm btn-dark" onclick="loadOrder(${o.id},'${escHtml(o.order_no)}')">
                Hazırla <i class="bi bi-arrow-right"></i>
            </button>
        </div>`).join('');
}

async function loadOrderByNo() {
    const no = document.getElementById('manualOrderNo').value.trim();
    if (!no) return;
    const res = await apiGet('/orders.php?order_no=${encodeURIComponent(no)}`);
    if (!res.success) {
        showToast(res.error, 'danger');
        return;
    }
    startPrep(res.data);
}

async function loadOrder(id, no) {
    const res = await apiGet('/orders.php?order_no=${encodeURIComponent(no)}`);
    if (!res.success) {
        showToast(res.error, 'danger');
        return;
    }
    startPrep(res.data);
}

// ── Hazırlama başlat ────────────────────────────────────────
function startPrep(order) {
    currentOrderId = order.id;
    orderItems = order.items;
    document.getElementById('prepOrderNo').textContent = order.order_no;
    document.getElementById('prepCustomer').textContent = order.customer || '';
    document.getElementById('orderSelectPanel').style.display = 'none';
    document.getElementById('prepPanel').style.display = 'block';
    renderItems();
    startScanner();
}

function renderItems() {
    const list = document.getElementById('itemList');
    const done = orderItems.filter(i => i.scanned >= i.quantity).length;
    document.getElementById('doneCount').textContent = done;
    document.getElementById('totalCount').textContent = orderItems.length;
    list.innerHTML = orderItems.map(item => {
        const complete = item.scanned >= item.quantity;
        const rowClass = complete ? 'list-group-item-success' : (item.scanned > 0 ? 'list-group-item-warning' : '');
        return `
            <li class="list-group-item ${rowClass} d-flex justify-content-between align-items-center" id="item_${item.id}">
                <div>
                    <span class="fw-semibold">${escHtml(item.product_name)}</span><br>
                    <small class="text-muted">${escHtml(item.barcode)}</small>
                </div>
                <span class="badge bg-${complete?'success':'secondary'} fs-6">
                    ${item.scanned}/${item.quantity}
                </span>
            </li>`;
    }).join('');
    // Hepsi tamam mı?
    const allDone = orderItems.every(i => i.scanned >= i.quantity);
    document.getElementById('completeDiv').classList.toggle('d-none', !allDone);
}

// ── Barkod tarama ───────────────────────────────────────────
function startScanner() {
    if (scanner) scanner.stop();
    scanner = new BarcodeScanner('scannerReader', onBarcodeScanned);
    scanner.start();
    // iPhone fallback
    setupFileFallback('fallbackInput', onBarcodeScanned);
}

async function onBarcodeScanned(barcode) {
    const res = await apiPost('/orders.php', {
        action: 'scan',
        order_id: currentOrderId,
        barcode
    });
    if (!res.success) {
        scanFeedbackError();
        showToast(res.error, 'danger');
        return;
    }
    const d = res.data;
    // Yerel state güncelle
    const item = orderItems.find(i => i.id === d.item_id);
    if (item) item.scanned = d.scanned;
    renderItems();
    if (d.status === 'complete') {
        showToast(`✅ ${d.scanned}/${d.expected} — tamamlandı`, 'success');
    } else {
        showToast(`${d.scanned}/${d.expected} — devam et`, 'info');
    }
}

// ── Siparişi tamamla ────────────────────────────────────────
async function completeOrder() {
    if (!confirm('Sipariş teslim edildi ve stok düşülecek. Onaylıyor musunuz?')) return;
    const res = await apiPost('/orders.php', {
        action: 'complete',
        order_id: currentOrderId
    });
    if (!res.success) {
        showToast(res.error, 'danger');
        return;
    }
    showToast('Sipariş tamamlandı! 🎉', 'success');
    setTimeout(() => resetPrep(), 1500);
}

function resetPrep() {
    if (scanner) scanner.stop();
    scanner = null;
    currentOrderId = null;
    orderItems = [];
    document.getElementById('prepPanel').style.display = 'none';
    document.getElementById('orderSelectPanel').style.display = 'block';
    document.getElementById('manualOrderNo').value = '';
    document.getElementById('completeDiv').classList.add('d-none');
    loadPending();
}

function escHtml(s) {
    return String(s||'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

// Sayfa yüklenince bekleyen siparişleri getir
document.addEventListener('DOMContentLoaded', () => {
    loadPending();
});