<?php
/**
 * İlk Kurulum Scripti
 * Çalıştırdıktan sonra bu dosyayı SİL veya yeniden adlandır.
 * URL: https://siteniz.com/stok/install/setup.php
 */

// Base path otomatik algılama (install klasörü üst dizin)
$installDir = dirname(__DIR__); // /public_html/install or /stok/install
$baseDir = basename($installDir); // parent of install

// Kök dizinse (public_html) boş, alt klasörse (/stok) o klasör
$basePath = ($baseDir === 'public_html' || $baseDir === 'htdocs' || $baseDir === 'httpdocs') ? '' : '/' . $baseDir;
define('BASE_PATH', $basePath);

define('SETUP_MODE', true);
$dbPath = dirname(__DIR__) . '/database/depo.sqlite';
$schemaPath = __DIR__ . '/schema.sql';

$errors = [];
$success = [];

// 1. database/ klasörü yazılabilir mi?
$dbDir = dirname($dbPath);
if (!is_dir($dbDir)) {
    mkdir($dbDir, 0755, true);
    $success[] = "database/ klasörü oluşturuldu.";
}
if (!is_writable($dbDir)) {
    $errors[] = "database/ klasörü yazılabilir değil. chmod 755 yapın.";
}

// 1b. Güvenlik .htaccess
$htaccessContent = "# Güvenlik: Dizin listeleme kapalı
Options -Indexes +FollowSymLinks

# Apache 2.4+ güvenlik
Require all granted

# Sunucu imzası gizleme
ServerSignature Off

# Gizli dosyaları ve hassas extension'ları engelle
<FilesMatch \"(^\.|/\.env|\.log|\.sql|\.sqlite|\.git|\.htaccess|\.htpasswd|\.ini|\.cfg|\.json|\.xml|\.yml|\.yaml|\.bat|\.sh)$\">
    Require all denied
</FilesMatch>

# HTTP güvenlik başlıkları
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options \"nosniff\"
    Header always set X-Frame-Options \"SAMEORIGIN\"
    Header always set X-XSS-Protection \"1; mode=block\"
    Header always set Referrer-Policy \"strict-origin-when-cross-origin\"
    Header always set Permissions-Policy \"geolocation=(), microphone=(), camera=()\"
</IfModule>

# CSP
<IfModule mod_headers.c>
    Header always set Content-Security-Policy \"default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://unpkg.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; font-src 'self' data:; img-src 'self' data:;\"
</IfModule>";

$htaccessPath = dirname(__DIR__) . '/.htaccess';
if (!file_exists($htaccessPath)) {
    if (file_put_contents($htaccessPath, $htaccessContent) !== false) {
        chmod($htaccessPath, 0644);
        $success[] = ".htaccess dosyası oluşturuldu.";
    } else {
        $errors[] = ".htaccess dosyası oluşturulamadı. Manuel oluşturun.";
    }
}

// 1d. Tüm dosyalara okuma izni ver
$baseDir = dirname(__DIR__);
function chmodRecursive($dir, $fileMode, $dirMode) {
    if (is_dir($dir)) {
        chmod($dir, $dirMode);
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                chmodRecursive($path, $fileMode, $dirMode);
            } else {
                chmod($path, $fileMode);
            }
        }
    }
}
chmodRecursive($baseDir, 0644, 0755);
$success[] = "Tüm dosyaların izinleri 644/755 olarak ayarlandı.";

// 2. SQLite bağlantısı
if (empty($errors)) {
    try {
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("PRAGMA journal_mode=WAL");
        $pdo->exec("PRAGMA foreign_keys=ON");
        $success[] = "SQLite veritabanı bağlantısı başarılı.";
    } catch (Exception $e) {
        $errors[] = "SQLite bağlantı hatası: " . $e->getMessage();
    }
}

// 3. Tabloları oluştur
if (empty($errors)) {
    $schema = file_get_contents($schemaPath);
    // Satır satır oku, yorumları ve boşlukları temizle
    $lines = explode("\n", $schema);
    $currentStmt = '';
    foreach ($lines as $line) {
        $line = trim($line);
        // Boş satır veya yorum satırını atla
        if (empty($line) || substr($line, 0, 2) === '--') {
            continue;
        }
        // SQL ifadesini biriktir
        $currentStmt .= ' ' . $line;
        // Noktalı virgül ile bitiyorsa çalıştır
        if (substr(rtrim($currentStmt), -1) === ';') {
            $currentStmt = trim($currentStmt);
            if (!empty($currentStmt) && substr(ltrim($currentStmt), 0, 2) !== '--') {
                try {
                    $pdo->exec($currentStmt);
                } catch (Exception $e) {
                    // Zaten var olan indeksler veya tablolar için hata verme
                    $msg = $e->getMessage();
                    if (strpos($msg, 'already exists') === false && strpos($msg, 'duplicate') === false) {
                        $errors[] = "SQL hatası: " . $msg;
                    }
                }
            }
            $currentStmt = '';
        }
    }
    if (empty($errors)) {
        $success[] = "Tablolar başarıyla oluşturuldu.";
    }

    // 3b. Mevcut tablolara yeni kolonları ekle (migration)
    if (empty($errors)) {
        $tableCheck = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='locations'")->fetch();
        if ($tableCheck) {
            $productsCols = $pdo->query("PRAGMA table_info(products)")->fetchAll(PDO::FETCH_COLUMN, 1);
            $movementsCols = $pdo->query("PRAGMA table_info(stock_movements)")->fetchAll(PDO::FETCH_COLUMN, 1);
            
            $migrations = [];
            if (!in_array('location_id', $productsCols)) {
                $migrations[] = "ALTER TABLE products ADD COLUMN location_id INTEGER";
            }
            if (!in_array('type', $movementsCols)) {
                $migrations[] = "ALTER TABLE stock_movements ADD COLUMN type TEXT DEFAULT 'in' CHECK(type IN ('in','out','correction','transfer'))";
            }
            if (!in_array('location_id', $movementsCols)) {
                $migrations[] = "ALTER TABLE stock_movements ADD COLUMN location_id INTEGER";
            }
            if (!in_array('from_location_id', $movementsCols)) {
                $migrations[] = "ALTER TABLE stock_movements ADD COLUMN from_location_id INTEGER";
            }
            if (!in_array('to_location_id', $movementsCols)) {
                $migrations[] = "ALTER TABLE stock_movements ADD COLUMN to_location_id INTEGER";
            }
            
            foreach ($migrations as $sql) {
                try {
                    $pdo->exec($sql);
                } catch (Exception $e) {}
            }
            if (count($migrations) > 0) {
                $success[] = "Migration tamamlandı.";
            }
        }
    }
}

// 4. Admin kullanıcı oluştur
if (empty($errors) && isset($_POST['create_admin'])) {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $fullname = trim($_POST['full_name'] ?? '');

    if (strlen($username) < 3) {
        $errors[] = "Kullanıcı adı en az 3 karakter olmalı.";
    } elseif (strlen($password) < 6) {
        $errors[] = "Şifre en az 6 karakter olmalı.";
    } else {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT OR IGNORE INTO users (username, password, full_name, role) VALUES (?,?,?,'admin')");
        $stmt->execute([$username, $hash, $fullname]);
        if ($stmt->rowCount() > 0) {
            $success[] = "Admin kullanıcı '{$username}' oluşturuldu.";
        } else {
            $errors[] = "Bu kullanıcı adı zaten mevcut.";
        }
    }
}

// Mevcut admin var mı?
$adminExists = false;
if (isset($pdo)) {
    try {
        $adminExists = (bool)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
    } catch(Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Kurulum — Depo Sistemi</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5" style="max-width:560px">
    <h2 class="mb-4">⚙️ Depo Sistemi Kurulum</h2>

    <?php foreach($success as $msg): ?>
        <div class="alert alert-success py-2">✅ <?= htmlspecialchars($msg) ?></div>
    <?php endforeach; ?>
    <?php foreach($errors as $msg): ?>
        <div class="alert alert-danger py-2">❌ <?= htmlspecialchars($msg) ?></div>
    <?php endforeach; ?>

    <?php if (empty($errors) && !$adminExists): ?>
    <div class="card">
        <div class="card-body">
            <h5>Admin Kullanıcı Oluştur</h5>
            <form method="post">
                <div class="mb-3">
                    <label class="form-label">Ad Soyad</label>
                    <input type="text" name="full_name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Kullanıcı Adı</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Şifre (min. 6 karakter)</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <button type="submit" name="create_admin" class="btn btn-primary w-100">
                    Kurulumu Tamamla
                </button>
            </form>
        </div>
    </div>
    <?php elseif ($adminExists): ?>
    <div class="alert alert-warning">
        <strong>⚠️ Güvenlik Uyarısı:</strong> Kurulum tamamlandı.<br>
        Bu dosyayı (<code>install/setup.php</code>) hemen <strong>silin</strong>.
        <hr>
        <a href="<?= BASE_PATH ?>/index.php" class="btn btn-success">Sisteme Git →</a>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
