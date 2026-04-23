<?php
/**
 * Giriş Sayfası
 * Çıktı gönderilmeden önce session başlatılmalı
 */
require_once __DIR__ . '/includes/auth.php';

// Zaten giriş yaptıysa yönlendir
if (isLoggedIn()) {
    header('Location: ' . BASE_PATH . '/index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ok = login($_POST['username'] ?? '', $_POST['password'] ?? '');
    if ($ok) {
        header('Location: ' . BASE_PATH . '/index.php');
        exit;
    } else {
        $error = 'Kullanıcı adı veya şifre hatalı.';
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>Giriş — <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-dark d-flex align-items-center justify-content-center min-vh-100">
    <div class="card shadow-lg" style="width:100%;max-width:380px">
        <div class="card-body p-4">
            <h4 class="mb-1 fw-bold"><i class="bi bi-boxes"></i> Depo Sistemi</h4>
            <p class="text-muted small mb-4"><?= htmlspecialchars(SITE_NAME) ?></p>
            
            <?php if ($error): ?>
            <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <form method="post" autocomplete="on">
                <div class="mb-3">
                    <label class="form-label">Kullanıcı Adı</label>
                    <input type="text" name="username" class="form-control form-control-lg" autocomplete="username" autofocus required>
                </div>
                <div class="mb-4">
                    <label class="form-label">Şifre</label>
                    <input type="password" name="password" class="form-control form-control-lg" autocomplete="current-password" required>
                </div>
                <button type="submit" class="btn btn-dark btn-lg w-100">Giriş Yap</button>
            </form>
        </div>
    </div>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</body>
</html>