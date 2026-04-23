<?php
// api/orders.php — Sipariş yönetimi (en kritik modül)
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();
$user   = currentUser();

// ─── GET ──────────────────────────────────────────────────────
if ($method === 'GET') {

    // Sipariş detayı (hazırlama ekranı için)
    if (isset($_GET['order_no'])) {
        $stmt = $db->prepare("SELECT * FROM orders WHERE order_no=? LIMIT 1");
        $stmt->execute([trim($_GET['order_no'])]);
        $order = $stmt->fetch();
        if (!$order) jsonResponse(false, null, 'Sipariş bulunamadı: ' . htmlspecialchars($_GET['order_no']));

        // Kalemleri getir (lokasyon bilgisiyle)
        $items = $db->prepare("
            SELECT oi.*, p.name AS product_name, p.barcode, p.unit,
                l.kod AS location_kod, l.kat, l.bolge, l.raf, l.goz
            FROM order_items oi
            JOIN products p ON p.id=oi.product_id
            LEFT JOIN locations l ON l.id=p.location_id
            LEFT JOIN product_locations pl ON pl.product_id=p.id AND pl.quantity>0
            LEFT JOIN locations pll ON pll.id=pl.location_id
            WHERE oi.order_id=?
            ORDER BY COALESCE(pll.kat, l.kat, 999), 
                     COALESCE(pll.bolge, l.bolge, 'ZZZ'),
                     COALESCE(pll.raf, l.raf, 999),
                     COALESCE(pll.goz, l.goz, 999)
        ");
        $items->execute([$order['id']]);
        $order['items'] = $items->fetchAll();

        // Kat sıralarına göre grupla (rota için)
        $katGroups = [];
        foreach ($order['items'] as &$item) {
            $kat = $item['kat'] ?? $item['pll.kat'] ?? '999';
            if (!isset($katGroups[$kat])) $katGroups[$kat] = [];
            $katGroups[$kat][] = $item['bolge'] ?? $item['pll.bolge'] ?? 'Z';
        }
        $order['kat_order'] = array_keys($katGroups);

        jsonResponse(true, $order);
    }

    // Bekleyen / hazırlanıyor siparişler listesi
    if (isset($_GET['pending'])) {
        $rows = $db->query("
            SELECT o.*, COUNT(oi.id) AS item_count
            FROM orders o
            LEFT JOIN order_items oi ON oi.order_id=o.id
            WHERE o.status IN ('pending','preparing')
            GROUP BY o.id
            ORDER BY o.created_at ASC
        ")->fetchAll();
        jsonResponse(true, $rows);
    }

    // Tüm siparişler (admin için)
    if (isset($_GET['all'])) {
        requireAdmin();
        $limit = min((int)($_GET['limit'] ?? 50), 200);
        $rows = $db->prepare("
            SELECT o.*, COUNT(oi.id) AS item_count
            FROM orders o
            LEFT JOIN order_items oi ON oi.order_id=o.id
            GROUP BY o.id
            ORDER BY o.created_at DESC
            LIMIT ?
        ");
        $rows->execute([$limit]);
        jsonResponse(true, $rows->fetchAll());
    }

    // Bugünün kargoları
    if (isset($_GET['today'])) {
        $rows = $db->query("
            SELECT o.*, COUNT(oi.id) AS item_count
            FROM orders o
            LEFT JOIN order_items oi ON oi.order_id=o.id
            WHERE date(o.created_at) = date('now')
            GROUP BY o.id
            ORDER BY o.created_at DESC
        ")->fetchAll();
        jsonResponse(true, $rows);
    }

    // Polling: yeni sipariş kontrolü (30 dakika interval)
    if (isset($_GET['action']) && $_GET['action'] === 'check') {
        $since = $_GET['since'] ?? date('Y-m-d H:i:s', strtotime('-30 minutes'));
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM orders
            WHERE created_at > ?
            AND status IN ('pending','preparing')
        ");
        $stmt->execute([$since]);
        $count = (int)$stmt->fetchColumn();

        // Son sipariş zamanını da dönelim
        $lastStmt = $db->query("SELECT created_at FROM orders ORDER BY created_at DESC LIMIT 1");
        $lastOrder = $lastStmt->fetch();
        $lastTime = $lastOrder ? $lastOrder['created_at'] : date('Y-m-d H:i:s');

        jsonResponse(true, [
            'new_orders' => $count,
            'last_check' => date('Y-m-d H:i:s'),
            'last_order' => $lastTime
        ]);
    }

    jsonResponse(false, null, 'Geçersiz istek.');
}

// ─── POST ─────────────────────────────────────────────────────
if ($method === 'POST') {
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $body['action'] ?? '';

    // Yeni sipariş oluştur (admin)
    if ($action === 'create') {
        requireAdmin();
        $orderNo  = trim($body['order_no']  ?? '');
        $customer = trim($body['customer']  ?? '');
        $items    = $body['items'] ?? [];

        if (!$orderNo)      jsonResponse(false, null, 'Sipariş numarası gerekli.');
        if (empty($items))  jsonResponse(false, null, 'En az 1 kalem gerekli.');

        $db->beginTransaction();
        try {
            // Duplicate kontrolü
            $check = $db->prepare("SELECT id FROM orders WHERE order_no = ?");
            $check->execute([$orderNo]);
            if ($check->fetch()) {
                jsonResponse(false, null, 'Bu sipariş numarası zaten mevcut.');
            }

            $stmt = $db->prepare("INSERT INTO orders (order_no,customer,notes) VALUES (?,?,?)");
            $stmt->execute([$orderNo, $customer, $body['notes'] ?? null]);
            $orderId = $db->lastInsertId();

            foreach ($items as $item) {
                $barcode = trim($item['barcode'] ?? '');
                $qty     = (int)($item['quantity'] ?? 1);
                $prod    = $db->prepare("SELECT id FROM products WHERE barcode=? AND active=1");
                $prod->execute([$barcode]);
                $product = $prod->fetch();
                if (!$product) {
                    $db->rollBack();
                    jsonResponse(false, null, "Ürün bulunamadı: $barcode");
                }
                $db->prepare("INSERT INTO order_items (order_id,product_id,quantity) VALUES (?,?,?)")
                   ->execute([$orderId, $product['id'], $qty]);
            }

            $db->commit();
            jsonResponse(true, ['order_id' => $orderId]);
        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(false, null, 'Sipariş oluşturulamadı: ' . $e->getMessage());
        }
    }

    // Barkod tarama (depocu hazırlama)
    if ($action === 'scan') {
        $orderId = (int)($body['order_id'] ?? 0);
        $barcode = trim($body['barcode']   ?? '');
        if (!$orderId || !$barcode) jsonResponse(false, null, 'Eksik veri.');

        // Siparişi bul
        $order = $db->prepare("SELECT * FROM orders WHERE id=? AND status IN ('pending','preparing')");
        $order->execute([$orderId]);
        $orderRow = $order->fetch();
        if (!$orderRow) jsonResponse(false, null, 'Sipariş bulunamadı veya tamamlanmış.');

        // Ürünü barkoddan bul
        $prodStmt = $db->prepare("SELECT id FROM products WHERE barcode=? AND active=1");
        $prodStmt->execute([$barcode]);
        $product = $prodStmt->fetch();
        if (!$product) jsonResponse(false, null, 'Bu barkod sistemde kayıtlı değil.');

        // Sipariş kalemini bul
        $itemStmt = $db->prepare("SELECT * FROM order_items WHERE order_id=? AND product_id=?");
        $itemStmt->execute([$orderId, $product['id']]);
        $item = $itemStmt->fetch();
        if (!$item) jsonResponse(false, null, '⚠️ Bu ürün bu siparişte yok!');

        // Fazla tarama kontrolü
        if ($item['scanned'] >= $item['quantity']) {
            jsonResponse(false, null, "⚠️ Fazla tarama! Beklenen: {$item['quantity']}, zaten taranan: {$item['scanned']}");
        }

        // Taramayı artır
        $db->prepare("UPDATE order_items SET scanned=scanned+1 WHERE id=?")->execute([$item['id']]);

        // Sipariş 'preparing' durumuna al
        if ($orderRow['status'] === 'pending') {
            $db->prepare("UPDATE orders SET status='preparing', prepared_by=? WHERE id=?")
               ->execute([$user['id'], $orderId]);
        }

        $newScanned = $item['scanned'] + 1;
        $status     = $newScanned >= $item['quantity'] ? 'complete' : 'partial';

        // Lokasyon bilgisini al (ürünün ana lokasyonu veya ilk dolu göz)
        $locStmt = $db->prepare("
            SELECT l.kod FROM products p
            LEFT JOIN product_locations pl ON pl.product_id=p.id AND pl.quantity>0
            LEFT JOIN locations l ON l.id=pl.location_id OR l.id=p.location_id
            WHERE p.id=? AND l.kod IS NOT NULL
            ORDER BY pl.quantity DESC LIMIT 1
        ");
        $locStmt->execute([$product['id']]);
        $location = $locStmt->fetch();

        // Tüm kalemler tamam mı kontrol et
        $allDone = $db->prepare("SELECT COUNT(*) FROM order_items WHERE order_id=? AND scanned < quantity");
        $allDone->execute([$orderId]);
        $remaining = (int)$allDone->fetchColumn();

        jsonResponse(true, [
            'item_id'      => $item['id'],
            'scanned'      => $newScanned,
            'expected'     => $item['quantity'],
            'status'       => $status,   // 'partial' | 'complete'
            'all_complete' => $remaining === 0,
            'location_kod' => $location['kod'] ?? null,
        ]);
    }

    // Siparişi tamamla (stok düş)
    if ($action === 'complete') {
        $orderId = (int)($body['order_id'] ?? 0);
        if (!$orderId) jsonResponse(false, null, 'ID gerekli.');

        // Sipariş durumunu kontrol et
        $statusCheck = $db->prepare("SELECT status FROM orders WHERE id=?");
        $statusCheck->execute([$orderId]);
        $currentStatus = $statusCheck->fetch();
        if (!$currentStatus) jsonResponse(false, null, 'Sipariş bulunamadı.');
        if ($currentStatus['status'] !== 'preparing') {
            jsonResponse(false, null, 'Sipariş hazırlama modunda değil.');
        }

        // Tüm kalemler tarandı mı?
        $check = $db->prepare("SELECT COUNT(*) FROM order_items WHERE order_id=? AND scanned < quantity");
        $check->execute([$orderId]);
        if ((int)$check->fetchColumn() > 0) {
            jsonResponse(false, null, 'Henüz taranmamış ürünler var!');
        }

        $db->beginTransaction();
        try {
            $items = $db->prepare("SELECT * FROM order_items WHERE order_id=?");
            $items->execute([$orderId]);
            $items = $items->fetchAll();

            $orderStmt = $db->prepare("SELECT order_no FROM orders WHERE id=?");
            $orderStmt->execute([$orderId]);
            $order = $orderStmt->fetch();

            foreach ($items as $item) {
                $db->prepare("
                    INSERT INTO stock_movements (product_id,type,quantity,reference,user_id)
                    VALUES (?,'out',?,?,?)
                ")->execute([$item['product_id'], $item['quantity'], "Sipariş #" . $order['order_no'], $user['id']]);
            }

            $db->prepare("UPDATE orders SET status='done', done_at=CURRENT_TIMESTAMP WHERE id=?")
               ->execute([$orderId]);

            $db->commit();
            jsonResponse(true, ['message' => 'Sipariş tamamlandı, stok güncellendi.']);
        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(false, null, 'Hata: ' . $e->getMessage());
        }
    }

    // Sipariş güncelle (sadece pending)
    if ($action === 'update') {
        $orderId = (int)($body['order_id'] ?? 0);
        if (!$orderId) jsonResponse(false, null, 'ID gerekli.');
        
        $stmt = $db->prepare("SELECT * FROM orders WHERE id=?");
        $stmt->execute([$orderId]);
        $oldOrder = $stmt->fetch();
        if (!$oldOrder) jsonResponse(false, null, 'Sipariş bulunamadı.');
        if ($oldOrder['status'] !== 'pending') jsonResponse(false, null, 'Sadece bekleyen siparişler düzenlenebilir.');
        
        $stmt = $db->prepare("UPDATE orders SET customer=?, notes=? WHERE id=?");
        $stmt->execute([
            $body['customer'] ?? $oldOrder['customer'],
            $body['notes'] ?? $oldOrder['notes'],
            $orderId
        ]);
        adminLog('update', 'orders', $orderId, json_encode($oldOrder), json_encode($body));
        jsonResponse(true, null);
    }

    // Sipariş sil (sadece pending)
    if ($action === 'delete') {
        $orderId = (int)($body['order_id'] ?? 0);
        if (!$orderId) jsonResponse(false, null, 'ID gerekli.');
        
        $stmt = $db->prepare("SELECT * FROM orders WHERE id=?");
        $stmt->execute([$orderId]);
        $oldOrder = $stmt->fetch();
        if (!$oldOrder) jsonResponse(false, null, 'Sipariş bulunamadı.');
        if ($oldOrder['status'] !== 'pending') jsonResponse(false, null, 'Sadece bekleyen siparişler silinebilir.');
        
        $db->beginTransaction();
        try {
            $db->prepare("DELETE FROM order_items WHERE order_id=?")->execute([$orderId]);
            $db->prepare("UPDATE orders SET status='cancelled' WHERE id=?")->execute([$orderId]);
            $db->commit();
            adminLog('delete', 'orders', $orderId, json_encode($oldOrder), null);
            jsonResponse(true, null);
        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(false, null, 'Hata: ' . $e->getMessage());
        }
    }

    // Sadece pending siparişler için kalem ekle
    if ($action === 'add_item') {
        $orderId = (int)($body['order_id'] ?? 0);
        if (!$orderId) jsonResponse(false, null, 'ID gerekli.');
        
        $stmt = $db->prepare("SELECT status FROM orders WHERE id=?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (!$order || $order['status'] !== 'pending') jsonResponse(false, null, 'Sadece bekleyen siparişe kalem eklenebilir.');
        
        $barcode = trim($body['barcode'] ?? '');
        $qty = (int)($body['quantity'] ?? 1);
        
        $prod = $db->prepare("SELECT id FROM products WHERE barcode=? AND active=1");
        $prod->execute([$barcode]);
        $product = $prod->fetch();
        if (!$product) jsonResponse(false, null, 'Ürün bulunamadı.');
        
        $db->prepare("INSERT INTO order_items (order_id,product_id,quantity) VALUES (?,?,?)")
           ->execute([$orderId, $product['id'], $qty]);
        
        adminLog('add_item', 'order_items', $orderId, null, json_encode(['barcode' => $barcode, 'quantity' => $qty]));
        jsonResponse(true, null);
    }

    // Sadece pending siparişler için kalem sil
    if ($action === 'remove_item') {
        $itemId = (int)($body['item_id'] ?? 0);
        if (!$itemId) jsonResponse(false, null, 'Kalem ID gerekli.');
        
        $stmt = $db->prepare("SELECT oi.*, o.status FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE oi.id=?");
        $stmt->execute([$itemId]);
        $item = $stmt->fetch();
        if (!$item) jsonResponse(false, null, 'Kalem bulunamadı.');
        if ($item['status'] !== 'pending') jsonResponse(false, null, 'Sadece bekleyen siparişten kalem silinebilir.');
        
        $db->prepare("DELETE FROM order_items WHERE id=?")->execute([$itemId]);
        adminLog('remove_item', 'order_items', $itemId, json_encode($item), null);
        jsonResponse(true, null);
    }

    jsonResponse(false, null, 'Bilinmeyen işlem.');
}
