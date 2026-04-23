// assets/js/barcode.js
// html5-qrcode wrapper — QR Code ve barkod tarama
// iPhone ve Android kameralarında çalışır
// CDN: https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js

class BarcodeScanner {
    /**
     * @param {string} elementId    - Kameranın render edileceği div id'si
     * @param {Function} onSuccess  - (barcode: string) => void
     * @param {Function} onError    - opsiyonel hata callback
     */
    constructor(elementId, onSuccess, onError = null) {
        this.elementId  = elementId;
        this.onSuccess  = onSuccess;
        this.onError    = onError;
        this.scanner    = null;
        this.lastScan   = '';
        this.lastScanAt = 0;
        this.DEBOUNCE_MS = 1500; // Aynı barkodu art arda okuma önleme
    }

    start() {
        this.scanner = new Html5QrcodeScanner(
            this.elementId,
            {
                fps: 10,
                qrbox: { width: 250, height: 250 },
                rememberLastUsedCamera: true,
                aspectRatio: 1.0,
                showTorchButtonIfSupported: true,
                supportedScanTypes: [Html5QrcodeScanType.SCAN_TYPE_CAMERA]
            },
            false // verbose = false
        );

        this.scanner.render(
            (decodedText) => this._handleSuccess(decodedText),
            (errorMsg)    => { /* sessizce geç, her frame'de hata mesajı gelir */ }
        );
    }

    _handleSuccess(barcode) {
        const now = Date.now();
        // Debounce: aynı kodu 1.5s içinde tekrar işleme
        if (barcode === this.lastScan && (now - this.lastScanAt) < this.DEBOUNCE_MS) {
            return;
        }
        this.lastScan   = barcode;
        this.lastScanAt = now;
        scanFeedbackOk(); // app.js
        this.onSuccess(barcode);
    }

    stop() {
        if (this.scanner) {
            this.scanner.clear().catch(() => {});
            this.scanner = null;
        }
    }

    pause()  { this.scanner?.pause(true); }
    resume() { this.scanner?.resume(); }
}
