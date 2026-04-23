<?php
// api/production.php - Üretim emri endpointleri
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();
$user   = currentUser();

if ($method === 'GET') {
    // Üretim emri detayı
    if (isset($_GET['order_id'])) {
        $orderId = (int)$_GET['order_id'];
        
        $order = $db->prepare("
            SELECT po.*, p.name AS mamul_name, p.barcode AS mamul_barcode
            FROM production_orders po
            JOIN products p ON p.id = po.mamul_id
            WHERE po.id = ?
        ");
        $order->execute([$orderId]);
        $order = $order->fetch();
        if (!$order) jsonResponse(false, null, 'Sipariş bulunamadı.');
        
        $items = $db->prepare("
            SELECT poi.*, p.name AS parca_name, p.barcode AS parca_barcode,
                (SELECT COALESCE(SUM(CASE WHEN sm.type='in' THEN sm.quantity WHEN sm.type='out' THEN -sm.quantity WHEN sm.type='correction' THEN sm.quantity END), 0)
                 FROM stock_movements sm WHERE sm.product_id = p.id) AS current_stock
            FROM production_order_items poi
            JOIN products p ON p.id = poi.parca_id
            WHERE poi.production_order_id = ?
        ");
        $items->execute([$orderId]);
        
        jsonResponse(true, [
            'order' => $order,
            'items' => $items->fetchAll()
        ]);
    }
    
    // Tüm üretim emirleri
    $limit = min((int)($_GET['limit'] ?? 50), 200);
    $status = $_GET['status'] ?? '';
    
    $sql = "
        SELECT po.*, p.name AS mamul_name, p.barcode AS mamul_barcode,
            (SELECT name FROM users WHERE id = po.created_by) AS creator_name
        FROM production_orders po
        JOIN products p ON p.id = po.mamul_id
    ";
    $params = [];
    if ($status) {
        $sql .= " WHERE po.status = ?";
        $params[] = $status;
    }
    $sql .= " ORDER BY po.created_at DESC LIMIT ?";
    $params[] = $limit;
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    jsonResponse(true, $stmt->fetchAll());
}

if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $body['action'] ?? '';
    
    // Sadece parça yeterliliğini kontrol et, üretme
    if ($action === 'check') {
        $mamulId = (int)($body['mamul_id'] ?? 0);
        $miktar   = (int)($body['miktar'] ?? 0);
        
        if (!$mamulId || $miktar < 1) {
            jsonResponse(false, null, 'Mamul ve miktar gerekli.');
        }
        
        // Mamul ürün kontrolü
        $prod = $db->prepare("SELECT id, name, product_type FROM products WHERE id = ? AND active = 1");
        $prod->execute([$mamulId]);
        $mamul = $prod->fetch();
        if (!$mamul) jsonResponse(false, null, 'Mamul ürün bulunamadı.');
        
        // BOM kontrolü
        $bom = $db->prepare("
            SELECT b.*, p.name AS parca_name, p.barcode,
                (SELECT COALESCE(SUM(CASE WHEN sm.type='in' THEN sm.quantity WHEN sm.type='out' THEN -sm.quantity WHEN sm.type='correction' THEN sm.quantity END), 0)
                 FROM stock_movements sm WHERE sm.product_id = p.id) AS current_stock
            FROM bom_items b
            JOIN products p ON p.id = b.parca_id
            WHERE b.mamul_id = ?
        ");
        $bom->execute([$mamulId]);
        $bomItems = $bom->fetchAll();
        
        if (empty($bomItems)) {
            jsonResponse(false, null, 'Bu mamul için reçete tanımlanmamış.');
        }
        
        // Parça yeterlilik kontrolü
        $yetersiz = [];
        foreach ($bomItems as $item) {
            $gerekli = $item['miktar'] * $miktar;
            $mevcut = (int)$item['current_stock'];
            if ($mevcut < $gerekli) {
                $yetersiz[] = [
                    'parca_name' => $item['parca_name'],
                    'gerekli' => $gerekli,
                    'mevcut' => $mevcut,
                    'eksik' => $gerekli - $mevcut
                ];
            }
        }
        
        if (!empty($yetersiz)) {
            jsonResponse(true, [
                'sufficiency' => false,
                'yetersiz_parcalar' => $yetersiz,
                'mamul' => $mamul,
                'miktar' => $miktar
            ]);
        } else {
            jsonResponse(true, [
                'sufficiency' => true,
                'mamul' => $mamul,
                'miktar' => $miktar
            ]);
        }
    }
    
    // Üretim emri oluştur (önceden kontrol edilmiş olmalı)
    if ($action === 'create') {
        $mamulId = (int)($body['mamul_id'] ?? 0);
        $miktar   = (int)($body['miktar'] ?? 0);
        $notes   = trim($body['notes'] ?? '');
        
        if (!$mamulId || $miktar < 1) {
            jsonResponse(false, null, 'Mamul ve miktar gerekli.');
        }
        
        // Mamul ürün kontrolü
        $prod = $db->prepare("SELECT id, name, product_type FROM products WHERE id = ? AND active = 1");
        $prod->execute([$mamulId]);
        $mamul = $prod->fetch();
        if (!$mamul) jsonResponse(false, null, 'Mamul ürün bulunamadı.');
        if ($mamul['product_type'] !== 'imalat') {
            jsonResponse(false, null, 'Sadece imalat ürünler için üretim emri oluşturulabilir.');
        }
        
        // BOM kontrolü
        $bom = $db->prepare("
            SELECT b.*, p.name AS parca_name, p.barcode,
                (SELECT COALESCE(SUM(CASE WHEN sm.type='in' THEN sm.quantity WHEN sm.type='out' THEN -sm.quantity WHEN sm.type='correction' THEN sm.quantity END), 0)
                 FROM stock_movements sm WHERE sm.product_id = p.id) AS current_stock
            FROM bom_items b
            JOIN products p ON p.id = b.parca_id
            WHERE b.mamul_id = ?
        ");
        $bom->execute([$mamulId]);
        $bomItems = $bom->fetchAll();
        
        if (empty($bomItems)) {
            jsonResponse(false, null, 'Bu mamul için reçete tanımlanmamış. Önce reçete oluşturun.');
        }
        
        // Parça yeterlilik kontrolü (sadece uyarı için, üretimi engelleme)
        $yetersiz = [];
        $parcaHareketleri = [];
        
        foreach ($bomItems as $item) {
            $gerekli = $item['miktar'] * $miktar;
            $mevcut = (int)$item['current_stock'];
            if ($mevcut < $gerekli) {
                $yetersiz[] = [
                    'parca_id' => $item['parca_id'],
                    'parca_name' => $item['parca_name'],
                    'barcode' => $item['barcode'],
                    'gerekli' => $gerekli,
                    'mevcut' => $mevcut,
                    'eksik' => $gerekli - $mevcut
                ];
            }
            $parcaHareketleri[] = [
                'parca_id' => $item['parca_id'],
                'miktar' => $gerekli
            ];
        }
        
        // Yetersiz parça varsa uyarı ile birlikte üretime devam et
        // (checkBOM'dan geçmişse yeterli demektir, ama yine de kontrol et)
        try {
            $db->beginTransaction();
            
            // Üretim emri kaydı
            $stmt = $db->prepare("
                INSERT INTO production_orders (mamul_id, miktar, status, notes, created_by)
                VALUES (?, ?, 'pending', ?, ?)
            ");
            $stmt->execute([$mamulId, $miktar, $notes, $user['id']]);
            $orderId = $db->lastInsertId();
            
            // Parça hareketlerini kaydet
            foreach ($parcaHareketleri as $ph) {
                $stmt = $db->prepare("
                    INSERT INTO production_order_items (production_order_id, parca_id, miktar)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$orderId, $ph['parca_id'], $ph['miktar']]);
                
                // Stoktan düş
                $stmt = $db->prepare("
                    INSERT INTO stock_movements (product_id, type, quantity, reference, user_id, note)
                    VALUES (?, 'out', ?, ?, ?, 'Üretim emri #" . $orderId . "')
                ");
                $stmt->execute([
                    $ph['parca_id'],
                    $ph['miktar'],
                    'URETIM #' . $orderId,
                    $user['id']
                ]);
            }
            
            // Mamul stoğa ekle
            $stmt = $db->prepare("
                INSERT INTO stock_movements (product_id, type, quantity, reference, user_id, note)
                VALUES (?, 'in', ?, ?, ?, 'Üretim emri #" . $orderId . "')
            ");
            $stmt->execute([
                $mamulId,
                $miktar,
                'URETIM #' . $orderId,
                $user['id']
            ]);
            
            // Emir tamamlandı
            $stmt = $db->prepare("
                UPDATE production_orders SET status = 'completed', completed_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([$orderId]);
            
            $db->commit();
            
            jsonResponse(true, [
                'sufficiency' => true,
                'order_id' => $orderId,
                'mamul' => $mamul['name'],
                'miktar' => $miktar,
                'message' => $mamul['name'] . ' × ' . $miktar . ' adet üretildi.'
            ]);
            
        } catch (PDOException $e) {
            $db->rollBack();
            jsonResponse(false, null, 'Hata: ' . $e->getMessage());
        }
    }
    
    // Yetersiz parça ile üretime zorla devam et
    if ($action === 'force_create') {
        $mamulId = (int)($body['mamul_id'] ?? 0);
        $miktar   = (int)($body['miktar'] ?? 0);
        $notes   = trim($body['notes'] ?? '');
        
        if (!$mamulId || $miktar < 1) {
            jsonResponse(false, null, 'Mamul ve miktar gerekli.');
        }
        
        $prod = $db->prepare("SELECT id, name FROM products WHERE id = ? AND active = 1");
        $prod->execute([$mamulId]);
        $mamul = $prod->fetch();
        if (!$mamul) jsonResponse(false, null, 'Mamul bulunamadı.');
        
        // BOM al
        $stmt = $db->prepare("SELECT parca_id, miktar FROM bom_items WHERE mamul_id = ?");
        $stmt->execute([$mamulId]);
        $bomItems = $stmt->fetchAll();
        
        if (empty($bomItems)) {
            jsonResponse(false, null, 'Reçete tanımlanmamış.');
        }
        
        try {
            $db->beginTransaction();
            
            $stmt = $db->prepare("
                INSERT INTO production_orders (mamul_id, miktar, status, notes, created_by)
                VALUES (?, ?, 'completed', ?, ?)
            ");
            $stmt->execute([$mamulId, $miktar, $notes . ' (Zorla)', $user['id']]);
            $orderId = $db->lastInsertId();
            
            foreach ($bomItems as $item) {
                $gerekli = $item['miktar'] * $miktar;
                
                $stmt = $db->prepare("
                    INSERT INTO production_order_items (production_order_id, parca_id, miktar)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$orderId, $item['parca_id'], $gerekli]);
                
                $stmt = $db->prepare("
                    INSERT INTO stock_movements (product_id, type, quantity, reference, user_id, note)
                    VALUES (?, 'out', ?, ?, ?, 'Üretim emri #" . $orderId . " (Zorla)')
                ");
                $stmt->execute([
                    $item['parca_id'],
                    $gerekli,
                    'URETIM #' . $orderId,
                    $user['id']
                ]);
            }
            
            $stmt = $db->prepare("
                INSERT INTO stock_movements (product_id, type, quantity, reference, user_id, note)
                VALUES (?, 'in', ?, ?, ?, 'Üretim emri #" . $orderId . "')
            ");
            $stmt->execute([
                $mamulId,
                $miktar,
                'URETIM #' . $orderId,
                $user['id']
            ]);
            
            $db->commit();
            
            jsonResponse(true, [
                'order_id' => $orderId,
                'message' => $mamul['name'] . ' × ' . $miktar . ' adet üretildi. (Eksik parçalarla)'
            ]);
            
        } catch (PDOException $e) {
            $db->rollBack();
            jsonResponse(false, null, 'Hata: ' . $e->getMessage());
        }
    }
    
    // Üretim emri iptal (parça iadesi + mamul düşme)
    if ($action === 'cancel') {
        requireAdmin();
        $orderId = (int)($body['order_id'] ?? 0);
        
        if (!$orderId) jsonResponse(false, null, 'Order ID gerekli.');
        
        $order = $db->prepare("SELECT * FROM production_orders WHERE id = ? AND status = 'pending'");
        $order->execute([$orderId]);
        $order = $order->fetch();
        
        if (!$order) jsonResponse(false, null, 'Sadece bekleyen üretim emirleri iptal edilebilir.');
        
        $mamulId = $order['mamul_id'];
        $miktar = $order['miktar'];
        
        $db->beginTransaction();
        try {
            // Parça iadesi (stok geri ekle)
            $items = $db->prepare("SELECT parca_id, miktar FROM production_order_items WHERE production_order_id = ?");
            $items->execute([$orderId]);
            $items = $items->fetchAll();
            
            foreach ($items as $item) {
                $db->prepare("INSERT INTO stock_movements (product_id, type, quantity, user_id, note) VALUES (?, 'in', ?, ?, 'Üretim iptal - parça iadesi')")
                   ->execute([$item['parca_id'], $item['miktar'] * $miktar, $user['id']]);
            }
            
            // Mamul düşme (stok geri al)
            $db->prepare("INSERT INTO stock_movements (product_id, type, quantity, user_id, note) VALUES (?, 'out', ?, ?, 'Üretim iptal - mamul iadesi')")
               ->execute([$mamulId, $miktar, $user['id']]);
            
            // Status güncelle
            $db->prepare("UPDATE production_orders SET status = 'cancelled' WHERE id = ?")->execute([$orderId]);
            
            adminLog('cancel', 'production_orders', $orderId, json_encode($order), null);
            $db->commit();
            jsonResponse(true, ['message' => 'Üretim emri iptal edildi.']);
        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(false, null, 'Hata: ' . $e->getMessage());
        }
    }
    
    // Üretim emri güncelle (sadece pending)
    if ($action === 'update') {
        requireAdmin();
        $orderId = (int)($body['order_id'] ?? 0);
        
        if (!$orderId) jsonResponse(false, null, 'Order ID gerekli.');
        
        $order = $db->prepare("SELECT * FROM production_orders WHERE id = ? AND status = 'pending'");
        $order->execute([$orderId]);
        $order = $order->fetch();
        
        if (!$order) jsonResponse(false, null, 'Sadece bekleyen üretim emirleri güncellenebilir.');
        
        $miktar = (int)($body['miktar'] ?? $order['miktar']);
        $notes = $body['notes'] ?? $order['notes'];
        
        $db->prepare("UPDATE production_orders SET miktar = ?, notes = ? WHERE id = ?")
           ->execute([$miktar, $notes, $orderId]);
        
        jsonResponse(true, null);
    }
    
    jsonResponse(false, null, 'Bilinmeyen işlem.');
}