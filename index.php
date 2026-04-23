<?php
// index.php — Dashboard
$pageTitle  = 'Panel';
$activePage = 'dashboard';
require_once __DIR__ . '/includes/header.php';

$db = getDB();

// Özet sayılar
$pendingOrders  = (int)$db->query("SELECT COUNT(*) FROM orders WHERE status IN ('pending','preparing')")->fetchColumn();
$todayDone      = (int)$db->query("SELECT COUNT(*) FROM orders WHERE status='done' AND date(done_at)=date('now')")->fetchColumn();
$productCount   = (int)$db->query("SELECT COUNT(*) FROM products WHERE active=1")->fetchColumn();

// Kritik stok (stok min_stock altında veya 0)
$criticalItems = $db->query("
    SELECT p.name, p.min_stock,
        COALESCE(SUM(CASE WHEN sm.type='in' THEN sm.quantity WHEN sm.type='out' THEN -sm.quantity WHEN sm.type='correction' THEN sm.quantity END),0) AS current_stock
    FROM products p
    LEFT JOIN stock_movements sm ON sm.product_id=p.id
    WHERE p.active=1
    GROUP BY p.id
    HAVING current_stock <= p.min_stock
    ORDER BY current_stock ASC
    LIMIT 20
")->fetchAll();

// Son 5 tamamlanan sipariş
$recentOrders = $db->query("
    SELECT order_no, customer, done_at FROM orders
    WHERE status='done' ORDER BY done_at DESC LIMIT 5
")->fetchAll();
?>

<!-- Özet Kartlar -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card text-center h-100 border-warning">
            <div class="card-body">
                <div class="display-5 fw-bold text-warning"><?= $pendingOrders ?></div>
                <div class="small text-muted">Bekleyen Sipariş</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center h-100 border-success">
            <div class="card-body">
                <div class="display-5 fw-bold text-success"><?= $todayDone ?></div>
                <div class="small text-muted">Bugün Teslim</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center h-100">
            <div class="card-body">
                <div class="display-5 fw-bold"><?= $productCount ?></div>
                <div class="small text-muted">Ürün Çeşidi</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <?php
        // Kritik sayısı (stok min_stock altında veya 0)
        $criticalCount = (int)$db->query("
            SELECT COUNT(*) FROM (
                SELECT p.id FROM products p
                LEFT JOIN stock_movements sm ON sm.product_id=p.id
                WHERE p.active=1
                GROUP BY p.id
                HAVING COALESCE(SUM(CASE WHEN sm.type='in' THEN sm.quantity WHEN sm.type='out' THEN -sm.quantity WHEN sm.type='correction' THEN sm.quantity END),0) <= p.min_stock
            )
        ")->fetchColumn();
        ?>
        <div class="card text-center h-100 <?= $criticalCount > 0 ? 'border-danger' : '' ?>">
            <div class="card-body">
                <div class="display-5 fw-bold <?= $criticalCount > 0 ? 'text-danger' : '' ?>">
                    <?= $criticalCount ?>
                </div>
                <div class="small text-muted">Kritik Stok</div>
            </div>
        </div>
    </div>
</div>

<!-- Hızlı Erişim -->
<div class="row g-2 mb-4">
    <?php if ($user['role'] === 'admin' || $user['role'] === 'depocu'): ?>
    <div class="col-6">
    <a href="<?= url('pages/order-prep.php') ?>" class="btn btn-dark btn-lg w-100">
    <i class="bi bi-box-seam"></i><br>Sipariş Hazırla
    </a>
    </div>
    <?php endif; ?>
    <?php if ($user['role'] === 'admin' || $user['role'] === 'depocu'): ?>
    <div class="col-6">
    <a href="<?= url('pages/stock-in.php') ?>" class="btn btn-outline-dark btn-lg w-100">
    <i class="bi bi-plus-circle"></i><br>Stok Giriş
    </a>
    </div>
    <?php endif; ?>
</div>

<!-- Kritik Stok Uyarıları (stok min_stock altında) -->
<?php if (!empty($criticalItems)): ?>
<div class="card border-danger mb-3">
    <div class="card-header bg-danger text-white">
        <i class="bi bi-exclamation-triangle-fill"></i> Kritik Stok Uyarıları (<?= count($criticalItems) ?>)
    </div>
    <ul class="list-group list-group-flush">
        <?php foreach ($criticalItems as $item): ?>
        <li class="list-group-item d-flex justify-content-between">
            <span><?= htmlspecialchars($item['name']) ?></span>
            <span class="badge bg-danger"><?= $item['current_stock'] ?> / min <?= $item['min_stock'] ?></span>
        </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<!-- Son Siparişler -->
<?php if (!empty($recentOrders)): ?>
<div class="card">
    <div class="card-header small">Son Teslim Edilen Siparişler</div>
    <ul class="list-group list-group-flush">
        <?php foreach ($recentOrders as $o): ?>
        <li class="list-group-item small d-flex justify-content-between">
            <span><strong><?= htmlspecialchars($o['order_no']) ?></strong>
                <?= $o['customer'] ? '· ' . htmlspecialchars($o['customer']) : '' ?>
            </span>
            <span class="text-muted"><?= date('d.m H:i', strtotime($o['done_at'])) ?></span>
        </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
