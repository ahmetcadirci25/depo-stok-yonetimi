<?php
// pages/settings.php — Sistem Ayarları
$pageTitle = 'Ayarlar';
$activePage = 'settings';
require_once __DIR__ . '/../includes/header.php';
requireAdmin();

$db = getDB();
$success = '';
$error = '';

// Ayarları kaydet
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $companyName = trim($_POST['company_name'] ?? '');
    $companyShort = trim($_POST['company_short'] ?? '');
    $companyDomain = trim($_POST['company_domain'] ?? '');
    $siteName = trim($_POST['site_name'] ?? '');
    
    if (empty($companyName) || empty($companyShort) || empty($companyDomain)) {
        $error = 'Tüm alanlar doldurulmalıdır.';
    } elseif (strlen($companyShort) > 5) {
        $error = 'Firma kısaltması en fazla 5 karakter olmalıdır.';
    } else {
        try {
            // Ayarları JSON olarak tek satırda sakla
            $settings = json_encode([
                'company_name' => $companyName,
                'company_short' => $companyShort,
                'company_domain' => $companyDomain,
                'site_name' => $siteName
            ]);
            
            // Settings tablosunu kontrol et, yoksa oluştur
            $db->exec("CREATE TABLE IF NOT EXISTS app_settings (id INTEGER PRIMARY KEY, settings TEXT)");
            
            // Güncelle veya ekle
            $check = $db->query("SELECT COUNT(*) FROM app_settings")->fetchColumn();
            if ($check > 0) {
                $db->prepare("UPDATE app_settings SET settings = ? WHERE id = 1")->execute([$settings]);
            } else {
                $db->prepare("INSERT INTO app_settings (id, settings) VALUES (1, ?)")->execute([$settings]);
            }
            
            $success = 'Ayarlar kaydedildi. Değişiklikler hemen geçerli.';
        } catch (PDOException $e) {
            $error = 'Kaydetme hatası: ' . $e->getMessage();
        }
    }
}

// Mevcut ayarları yükle
$settings = [
    'company_name' => 'Stok',
    'company_short' => 'OMG',
    'company_domain' => 'firmaismi.com',
    'site_name' => 'Depo Yönetim Sistemi'
];

try {
    $row = $db->query("SELECT settings FROM app_settings WHERE id = 1")->fetchColumn();
    if ($row) {
        $settings = json_decode($row, true) ?: $settings;
    }
} catch (PDOException $e) {
    // Tablo yoksa varsayılan değerler
}
?>

<div class="row g-3">
    <div class="col-12 col-md-6">
        <div class="card">
            <div class="card-header">
                <strong><i class="bi bi-gear"></i> Firma Ayarları</strong>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                <div class="alert alert-success py-2"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
                
                <form method="post">
                    <input type="hidden" name="save_settings" value="1">
                    
                    <div class="mb-3">
                        <label class="form-label">Site Adı</label>
                        <input type="text" name="site_name" class="form-control" 
                               value="<?= htmlspecialchars($settings['site_name']) ?>" required>
                        <small class="text-muted">Tarayıcı başlığında görünür</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Firma Adı</label>
                        <input type="text" name="company_name" class="form-control" 
                               value="<?= htmlspecialchars($settings['company_name']) ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Firma Kısaltması</label>
                        <input type="text" name="company_short" class="form-control" 
                               value="<?= htmlspecialchars($settings['company_short']) ?>" 
                               maxlength="5" required style="text-transform: uppercase;">
                        <small class="text-muted">QR kod formatında kullanılır. Örn: HK, OMG, AD</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Firma Domain</label>
                        <input type="text" name="company_domain" class="form-control" 
                               value="<?= htmlspecialchars($settings['company_domain']) ?>" required>
                        <small class="text-muted">Örn: firmaismi.com</small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Kaydet
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-12 col-md-6">
        <div class="card">
            <div class="card-header">
                <strong><i class="bi bi-info-circle"></i> QR Kod Önizlemesi</strong>
            </div>
            <div class="card-body">
                <p class="mb-2">QR kod formatı:</p>
                <code class="d-block p-2 bg-light rounded mb-3">
                    <?= htmlspecialchars($settings['company_short']) ?>-[TIP]-[KOD]-[SIRA]
                </code>
                
                <p class="mb-2"><strong>Örnek:</strong></p>
                <ul class="small">
                    <li>Mamul: <code><?= htmlspecialchars($settings['company_short']) ?>-MK-280-001</code></li>
                    <li>Parça: <code><?= htmlspecialchars($settings['company_short']) ?>-PRK-GOVDE-280</code></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>