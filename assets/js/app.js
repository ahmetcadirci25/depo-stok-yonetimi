// assets/js/app.js — Genel yardımcı fonksiyonlar

/**
 * Toast bildirimi göster
 * @param {string} message
 * @param {'success'|'danger'|'warning'|'info'} type
 */
function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    const id = 'toast_' + Date.now();
    const icons = { success: '✅', danger: '❌', warning: '⚠️', info: 'ℹ️' };
    container.insertAdjacentHTML('beforeend', `
        <div id="${id}" class="toast align-items-center text-bg-${type} border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body fs-6">
                    ${icons[type] || ''} ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto"
                    data-bs-dismiss="toast"></button>
            </div>
        </div>`);
    const el = document.getElementById(id);
    new bootstrap.Toast(el, { delay: 3000 }).show();
    el.addEventListener('hidden.bs.toast', () => el.remove());
}

/**
 * Başarılı barkod taraması geri bildirimi (titreşim + ses)
 */
function scanFeedbackOk() {
    if (navigator.vibrate) navigator.vibrate(80);
    playBeep(880, 100, 'sine');
}

/**
 * Hatalı tarama geri bildirimi
 */
function scanFeedbackError() {
    if (navigator.vibrate) navigator.vibrate([100, 50, 100]);
    playBeep(220, 300, 'sawtooth');
}

/**
 * Basit ses üret (Web Audio API)
 */
function playBeep(freq = 880, duration = 150, type = 'sine') {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.type = type;
        osc.frequency.value = freq;
        gain.gain.setValueAtTime(0.3, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + duration / 1000);
        osc.start(ctx.currentTime);
        osc.stop(ctx.currentTime + duration / 1000);
    } catch (e) { /* ses desteklenmiyorsa sessizce geç */ }
}

/**
 * API POST helper
 */
async function apiPost(url, data) {
  const res = await fetch(window.apiBase + url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'same-origin',
    body: JSON.stringify(data)
  });
  return res.json();
}

async function apiGet(url) {
  const res = await fetch(window.apiBase + url, {
    credentials: 'same-origin'
  });
  return res.json();
}

/**
 * API GET helper
 */
