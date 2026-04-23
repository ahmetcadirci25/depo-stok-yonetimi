<?php
// api/products.php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();

// GET: Barkod veya ID ile ürün sorgula
if ($method === 'GET') {
    // ID ile ürün getir
    if (isset($_GET['id'])) {
        $stmt = $db->prepare("SELECT id, barcode, name, sku, category, unit, product_type, min_stock FROM products WHERE id=? AND active=1");
        $stmt->execute([(int)$_GET['id']]);
        $product = $stmt->fetch();
        if (!$product) jsonResponse(false, null, 'Ürün bulunamadı.');
        $product['current_stock'] = getStockBalance($product['id']);
        jsonResponse(true, $product);
    }

    if (isset($_GET['barcode'])) {
            $stmt = $db->prepare("SELECT id, barcode, name, sku, category, unit, product_type, min_stock FROM products WHERE barcode=? AND active=1");
            $stmt->execute([trim($_GET['barcode'])]);
            $product = $stmt->fetch();
            if (!$product) jsonResponse(false, null, 'Ürün bulunamadı: ' . htmlspecialchars($_GET['barcode']));
            $product['current_stock'] = getStockBalance($product['id']);
            jsonResponse(true, $product);
        }

        if (isset($_GET['list'])) {
            $page   = max(1, (int)($_GET['page'] ?? 1));
            $perPage = min(100, max(10, (int)($_GET['per_page'] ?? 20)));
            $offset  = ($page - 1) * $perPage;
            $productType = $_GET['type'] ?? '';

            if ($productType) {
                $stmt = $db->prepare("
                    SELECT COUNT(*) FROM products p
                    WHERE p.product_type = ? AND p.active = 1
                ");
                $stmt->execute([$productType]);
                $total = (int)$stmt->fetchColumn();

                $stmt = $db->prepare("
                    SELECT
                        p.*,
                        COALESCE(SUM(
                            CASE WHEN sm.type='in'         THEN sm.quantity
                                 WHEN sm.type='out'        THEN -sm.quantity
                                 WHEN sm.type='correction' THEN sm.quantity
                            END
                        ), 0) AS current_stock
                    FROM products p
                    LEFT JOIN stock_movements sm ON sm.product_id = p.id
                    WHERE p.product_type = ? AND p.active = 1
                    GROUP BY p.id
                    ORDER BY p.name ASC
                    LIMIT ? OFFSET ?
                ");
                $stmt->execute([$productType, $perPage, $offset]);
                $results = $stmt->fetchAll();
            } else {
                $total = (int)$db->query("SELECT COUNT(*) FROM products WHERE active = 1")->fetchColumn();
                $results = getAllProductsWithStock($perPage, $offset);
            }
            jsonResponse(true, [
                'items'   => $results,
                'total'   => $total,
                'page'    => $page,
                'per_page'=> $perPage,
                'pages'   => (int)ceil($total / $perPage),
            ]);
        }

    jsonResponse(false, null, 'Geçersiz istek.');
}

// POST: Ürün ekle / güncelle / sil (sadece admin)
if ($method === 'POST') {
    require_once __DIR__ . '/../includes/functions.php';
    requireAdmin();
    requireCSRFToken();
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $body['action'] ?? '';

    if ($action === 'create') {
        $barcode = trim($body['barcode'] ?? '');
        $name = trim($body['name'] ?? '');
        $product_type = $body['product_type'] ?? 'hazir';
        if (!$barcode || !$name) jsonResponse(false, null, 'Barkod ve ürün adı zorunlu.');
        if (!in_array($product_type, ['hazir', 'imalat', 'parca'])) jsonResponse(false, null, 'Geçersiz ürün tipi.');
        $stmt = $db->prepare("INSERT INTO products (barcode,name,product_type,sku,category,unit,min_stock,location_id) VALUES (?,?,?,?,?,?,?)");
        try {
            $stmt->execute([
                $barcode,
                $name,
                $product_type,
                $body['sku'] ?? null,
                $body['category'] ?? null,
                $body['unit'] ?? 'adet',
                (int)($body['min_stock'] ?? 5),
                $body['location_id'] ?? null,
            ]);
            $newId = $db->lastInsertId();
            adminLog('create', 'products', $newId, null, json_encode($body));
            jsonResponse(true, ['id' => $newId]);
        } catch (PDOException $e) {
            jsonResponse(false, null, 'Bu barkod zaten kayıtlı.');
        }
    }

    if ($action === 'update') {
        $id = (int)($body['id'] ?? 0);
        if (!$id) jsonResponse(false, null, 'ID gerekli.');
        
        // Get old values for logging
        $stmt = $db->prepare("SELECT * FROM products WHERE id=?");
        $stmt->execute([$id]);
        $oldProduct = $stmt->fetch();
        
        $product_type = $body['product_type'] ?? 'hazir';
        if (!in_array($product_type, ['hazir', 'imalat', 'parca'])) jsonResponse(false, null, 'Geçersiz ürün tipi.');
        $stmt = $db->prepare("UPDATE products SET name=?,product_type=?,sku=?,category=?,unit=?,min_stock=?,location_id=? WHERE id=?");
        $stmt->execute([
            $body['name'] ?? '',
            $product_type,
            $body['sku'] ?? null,
            $body['category'] ?? null,
            $body['unit'] ?? 'adet',
            (int)($body['min_stock'] ?? 5),
            $body['location_id'] ?? null,
            $id
        ]);
        adminLog('update', 'products', $id, json_encode($oldProduct), json_encode($body));
        jsonResponse(true, null);
    }

    if ($action === 'delete') {
        $id = (int)($body['id'] ?? 0);
        
        // Check if in active order
        $stmt = $db->prepare("SELECT COUNT(*) FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE oi.product_id=? AND o.status IN ('pending','preparing')");
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() > 0) {
            jsonResponse(false, null, 'Bu ürün aktif siparişlerde mevcut. Önce siparişleri tamamlayın.');
        }
        
        // Get old values for logging
        $stmt = $db->prepare("SELECT * FROM products WHERE id=?");
        $stmt->execute([$id]);
        $oldProduct = $stmt->fetch();
        
        $db->prepare("UPDATE products SET active=0 WHERE id=?")->execute([$id]);
        adminLog('delete', 'products', $id, json_encode($oldProduct), null);
        jsonResponse(true, null);
    }

jsonResponse(false, null, 'Bilinmeyen işlem.');
}
