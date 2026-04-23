<?php
// includes/header.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
$user = currentUser();
$pageTitle = $pageTitle ?? SITE_NAME;
// Generate CSRF token for forms
$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="theme-color" content="#1a1a2e">
    <title><?= htmlspecialchars($pageTitle) ?> — <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= url('assets/css/app.css') ?>" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-dark bg-dark sticky-top pb-2">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="<?= url('index.php') ?>">
            <i class="bi bi-boxes"></i> <?= htmlspecialchars(COMPANY_NAME) ?>
        </a>
        <div class="d-flex align-items-center gap-3">
            <span class="text-secondary small d-none d-sm-inline">
                <?= htmlspecialchars($user['name']) ?>
            </span>
            <a href="<?= url('logout.php') ?>" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
    </div>
</nav>
<nav class="navbar navbar-light bg-white border-bottom px-0 pb-2">
    <div class="container-fluid justify-content-start gap-1 flex-wrap">
        <a href="<?= url('index.php') ?>" class="btn btn-sm <?= ($activePage??'')==='dashboard' ? 'btn-dark' : 'btn-outline-secondary' ?>">
            <i class="bi bi-speedometer2"></i> Panel
        </a>
        <a href="<?= url('pages/order-prep.php') ?>" class="btn btn-sm <?= ($activePage??'')==='orders' ? 'btn-dark' : 'btn-outline-secondary' ?>">
            <i class="bi bi-box-seam"></i> Sipariş Hazırla
        </a>
        <?php if ($user['role'] === 'admin' || $user['role'] === 'muhasebe'): ?>
        <a href="<?= url('pages/order-create.php') ?>" class="btn btn-sm <?= ($activePage??'')==='order-create' ? 'btn-dark' : 'btn-outline-secondary' ?>">
            <i class="bi bi-cart-plus"></i> Sipariş Oluştur
        </a>
        <?php endif; ?>
        <?php if ($user['role'] === 'admin' || $user['role'] === 'depocu'): ?>
        <a href="<?= url('pages/stock-in.php') ?>" class="btn btn-sm <?= ($activePage??'')==='stock' ? 'btn-dark' : 'btn-outline-secondary' ?>">
            <i class="bi bi-plus-circle"></i> Stok Giriş
        </a>
        <a href="<?= url('pages/location-transfer.php') ?>" class="btn btn-sm <?= ($activePage??'')==='transfer' ? 'btn-dark' : 'btn-outline-secondary' ?>">
            <i class="bi bi-arrow-left-right"></i> Transfer
        </a>
        <?php endif; ?>
        <?php if ($user['role'] === 'admin' || $user['role'] === 'depocu'): ?>
        <a href="<?= url('pages/production.php') ?>" class="btn btn-sm <?= ($activePage??'')==='production' ? 'btn-dark' : 'btn-outline-secondary' ?>">
            <i class="bi bi-hammer"></i> Üretim
        </a>
        <?php endif; ?>
        <?php if ($user['role'] === 'admin'): ?>
                <a href="<?= url('pages/products.php') ?>" class="btn btn-sm <?= ($activePage??'')==='products' ? 'btn-dark' : 'btn-outline-secondary' ?>">
                <i class="bi bi-upc-scan"></i> Ürünler
                </a>
                <a href="<?= url('pages/locations.php') ?>" class="btn btn-sm <?= ($activePage??'')==='locations' ? 'btn-dark' : 'btn-outline-secondary' ?>">
                <i class="bi bi-geo-alt"></i> Lokasyonlar
                </a>
                <a href="<?= url('pages/bom-manage.php') ?>" class="btn btn-sm <?= ($activePage??'')==='bom' ? 'btn-dark' : 'btn-outline-secondary' ?>">
                <i class="bi bi-list-task"></i> Reçete
                </a>
                <a href="<?= url('pages/reports.php') ?>" class="btn btn-sm <?= ($activePage??'')==='reports' ? 'btn-dark' : 'btn-outline-secondary' ?>">
                <i class="bi bi-bar-chart"></i> Rapor
                </a>
                <a href="<?= url('pages/qr-generate.php') ?>" class="btn btn-sm <?= ($activePage??'')==='qr' ? 'btn-dark' : 'btn-outline-secondary' ?>">
                <i class="bi bi-upc-scan"></i> QR Kod
                </a>
                <a href="<?= url('pages/users.php') ?>" class="btn btn-sm <?= ($activePage??'')==='users' ? 'btn-dark' : 'btn-outline-secondary' ?>">
                <i class="bi bi-people"></i> Kullanıcılar
                </a>
                <a href="<?= url('pages/settings.php') ?>" class="btn btn-sm <?= ($activePage??'')==='settings' ? 'btn-dark' : 'btn-outline-secondary' ?>">
                <i class="bi bi-gear"></i> Ayarlar
                </a>
                <?php endif; ?>
    </div>
</nav>
<main class="container-fluid py-3">