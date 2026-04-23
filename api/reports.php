<?php
// api/reports.php - Rapor endpointleri
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requireAdmin();
require_once __DIR__ . '/../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();

if ($method === 'GET') {
    $type = $_GET['type'] ?? '';
    
    // Mamul stok durumu (kritik olanlar üstte)
if ($type === 'mamul') {
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(200, max(10, (int)($_GET['per_page'] ?? 50)));
        $offset  = ($page - 1) * $perPage;
        $total   = (int)$db->query("SELECT COUNT(*) FROM products WHERE active=1 AND product_type IN ('imalat','hazir')")->fetchColumn();
        $rows = $db->query("
            SELECT p.id, p.name, p.barcode, p.sku, p.min_stock, p.unit,
                COALESCE(SUM(
                    CASE WHEN sm.type='in' THEN sm.quantity
                         WHEN sm.type='out' THEN -sm.quantity
                         WHEN sm.type='correction' THEN sm.quantity
                    END
                ), 0) AS current_stock
            FROM products p
            LEFT JOIN stock_movements sm ON sm.product_id = p.id
            WHERE p.active = 1 AND p.product_type IN ('imalat', 'hazir')
            GROUP BY p.id
            ORDER BY current_stock ASC, p.name ASC
            LIMIT $perPage OFFSET $offset
        ")->fetchAll();
        jsonResponse(true, ['items' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => (int)ceil($total / $perPage)]);
    }
    
    if ($type === 'parca') {
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(200, max(10, (int)($_GET['per_page'] ?? 50)));
        $offset  = ($page - 1) * $perPage;
        $total   = (int)$db->query("SELECT COUNT(*) FROM products WHERE active=1 AND product_type='parca'")->fetchColumn();
        $rows = $db->query("
            SELECT p.id, p.name, p.barcode, p.sku, p.min_stock, p.unit,
                COALESCE(SUM(
                    CASE WHEN sm.type='in' THEN sm.quantity
                         WHEN sm.type='out' THEN -sm.quantity
                         WHEN sm.type='correction' THEN sm.quantity
                    END
                ), 0) AS current_stock
            FROM products p
            LEFT JOIN stock_movements sm ON sm.product_id = p.id
            WHERE p.active = 1 AND p.product_type = 'parca'
            GROUP BY p.id
            ORDER BY current_stock ASC, p.name ASC
            LIMIT $perPage OFFSET $offset
        ")->fetchAll();
        jsonResponse(true, ['items' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => (int)ceil($total / $perPage)]);
    }
    
    // Tüm ürünler (mevcut)
    if ($type === 'all') {
        $rows = $db->query("
            SELECT p.id, p.name, p.barcode, p.sku, p.category, p.product_type, p.min_stock, p.unit,
                COALESCE(SUM(
                    CASE WHEN sm.type='in' THEN sm.quantity
                         WHEN sm.type='out' THEN -sm.quantity
                         WHEN sm.type='correction' THEN sm.quantity
                    END
                ), 0) AS current_stock
            FROM products p
            LEFT JOIN stock_movements sm ON sm.product_id = p.id
            WHERE p.active = 1
            GROUP BY p.id
            ORDER BY p.product_type, p.name ASC
        ")->fetchAll();
        jsonResponse(true, $rows);
    }
    
    // Üretim geçmişi
    if ($type === 'production') {
        $limit = min((int)($_GET['limit'] ?? 50), 200);
        $rows = $db->prepare("
            SELECT po.*, p.name AS mamul_name, p.barcode AS mamul_barcode,
                (SELECT name FROM users WHERE id = po.created_by) AS creator_name
            FROM production_orders po
            JOIN products p ON p.id = po.mamul_id
            ORDER BY po.created_at DESC
            LIMIT ?
        ");
        $rows->execute([$limit]);
        jsonResponse(true, $rows->fetchAll());
    }
    
    // Stok hareket geçmişi
    if ($type === 'movements') {
        $limit = min((int)($_GET['limit'] ?? 100), 500);
        $filter = $_GET['filter'] ?? '';
        
        $sql = "
            SELECT sm.*, p.name AS product_name, p.barcode, p.product_type,
                (SELECT name FROM users WHERE id = sm.user_id) AS user_name
            FROM stock_movements sm
            JOIN products p ON p.id = sm.product_id
        ";
        $params = [];
        
        if ($filter === 'in') {
            $sql .= " WHERE sm.type = 'in'";
        } elseif ($filter === 'out') {
            $sql .= " WHERE sm.type = 'out'";
        } elseif ($filter === 'production') {
            $sql .= " WHERE sm.reference LIKE 'URETIM%'";
        }
        
        $sql .= " ORDER BY sm.created_at DESC LIMIT ?";
        $params[] = $limit;
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        jsonResponse(true, $stmt->fetchAll());
    }
    
    // Sipariş özeti (günlük/haftalık)
    if ($type === 'orders') {
        $period = $_GET['period'] ?? 'today';
        
        if ($period === 'today') {
            $dateCond = "date(o.created_at) = date('now')";
        } elseif ($period === 'week') {
            $dateCond = "o.created_at >= datetime('now', '-7 days')";
        } elseif ($period === 'month') {
            $dateCond = "o.created_at >= datetime('now', '-30 days')";
        } else {
            $dateCond = "date(o.created_at) = date('now')";
        }
        
        $rows = $db->query("
            SELECT o.*, 
                (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) AS item_count,
                (SELECT SUM(quantity) FROM order_items WHERE order_id = o.id) AS total_qty
            FROM orders o
            WHERE $dateCond
            ORDER BY o.created_at DESC
        ")->fetchAll();
        
        // Özet istatistikler
        $stats = $db->query("
            SELECT 
                COUNT(*) AS total_orders,
                SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) AS completed_orders,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_orders,
                SUM(CASE WHEN status = 'preparing' THEN 1 ELSE 0 END) AS preparing_orders
            FROM orders o
            WHERE $dateCond
        ")->fetch();
        
        jsonResponse(true, [
            'orders' => $rows,
            'stats' => $stats
        ]);
    }
    
    // Kritik stok + lokasyon
    if ($type === 'critical') {
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(200, max(10, (int)($_GET['per_page'] ?? 50)));
        $offset  = ($page - 1) * $perPage;

        $rows = $db->query("
            SELECT p.id, p.name, p.barcode, p.min_stock, p.unit,
                COALESCE(SUM(
                    CASE WHEN sm.type='in' THEN sm.quantity
                         WHEN sm.type='out' THEN -sm.quantity
                         WHEN sm.type='correction' THEN sm.quantity
                    END
                ), 0) AS current_stock,
                l.kod AS location_kod,
                pll.kod AS pl_kod
            FROM products p
            LEFT JOIN stock_movements sm ON sm.product_id = p.id
            LEFT JOIN locations l ON l.id = p.location_id
            LEFT JOIN product_locations pl ON pl.product_id = p.id AND pl.quantity > 0
            LEFT JOIN locations pll ON pll.id = pl.location_id
            WHERE p.active = 1
            GROUP BY p.id
            HAVING current_stock <= p.min_stock
            ORDER BY current_stock ASC, p.name ASC
            LIMIT $perPage OFFSET $offset
        ")->fetchAll();
        $total = count($rows);
        
        jsonResponse(true, ['items' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => (int)ceil($total / $perPage)]);
    }
    
    jsonResponse(false, null, 'Geçersiz rapor tipi.');
}

jsonResponse(false, null, 'Geçersiz istek.');
