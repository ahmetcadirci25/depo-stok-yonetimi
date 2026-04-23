<?php
// api/stock.php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();
$user   = currentUser();

if ($method === 'GET') {
    // Kritik stok listesi
    if (isset($_GET['critical'])) {
        $rows = $db->query("
            SELECT p.id, p.name, p.barcode, p.min_stock, p.unit,
                COALESCE(SUM(
                    CASE WHEN sm.type='in'         THEN sm.quantity
                         WHEN sm.type='out'        THEN -sm.quantity
                         WHEN sm.type='correction' THEN sm.quantity
                    END
                ),0) AS current_stock
            FROM products p
            LEFT JOIN stock_movements sm ON sm.product_id=p.id
            WHERE p.active=1
            GROUP BY p.id
            HAVING current_stock <= p.min_stock
            ORDER BY current_stock ASC
        ")->fetchAll();
        jsonResponse(true, $rows);
    }

    // Son hareketler
    if (isset($_GET['movements'])) {
        $limit = min((int)($_GET['limit'] ?? 50), 200);
        $rows  = $db->prepare("
            SELECT sm.*, p.name AS product_name, p.barcode
            FROM stock_movements sm
            JOIN products p ON p.id=sm.product_id
            ORDER BY sm.created_at DESC LIMIT ?
        ");
        $rows->execute([$limit]);
        jsonResponse(true, $rows->fetchAll());
    }

    // Transfer geçmişi
    if (isset($_GET['transfers'])) {
        $limit = min((int)($_GET['limit'] ?? 50), 200);
        $rows  = $db->prepare("
            SELECT sm.quantity, sm.created_at, p.name AS product_name,
                fl.kod AS from_kod, tl.kod AS to_kod
            FROM stock_movements sm
            JOIN products p ON p.id=sm.product_id
            LEFT JOIN locations fl ON fl.id=sm.from_location_id
            LEFT JOIN locations tl ON tl.id=sm.to_location_id
            WHERE sm.type='transfer'
            ORDER BY sm.created_at DESC LIMIT ?
        ");
        $rows->execute([$limit]);
        jsonResponse(true, $rows->fetchAll());
    }

    jsonResponse(false, null, 'Geçersiz istek.');
}

if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $body['action'] ?? '';

    // Stok girişi (barkod ile)
    if ($action === 'stock_in') {
        $barcode  = trim($body['barcode']  ?? '');
        $quantity = (int)($body['quantity'] ?? 0);
        $note     = trim($body['note']      ?? '');
        $locationId = (int)($body['location_id'] ?? 0) ?: null;

        if (!$barcode)    jsonResponse(false, null, 'Barkod gerekli.');
        if ($quantity < 1) jsonResponse(false, null, 'Miktar en az 1 olmalı.');

        $prod = $db->prepare("SELECT id FROM products WHERE barcode=? AND active=1");
        $prod->execute([$barcode]);
        $product = $prod->fetch();
        if (!$product) jsonResponse(false, null, 'Ürün bulunamadı.');

        $stmt = $db->prepare("INSERT INTO stock_movements (product_id,type,quantity,reference,user_id,note,location_id) VALUES (?, 'in', ?, ?, ?, ?, ?)");
        $stmt->execute([$product['id'], $quantity, $body['reference'] ?? null, $user['id'], $note, $locationId]);

        // Location FK kontrolü
        if ($locationId) {
            $locCheck = $db->prepare("SELECT id FROM locations WHERE id = ?");
            $locCheck->execute([$locationId]);
            if (!$locCheck->fetch()) {
                jsonResponse(false, null, 'Geçersiz lokasyon.');
            }
        }

        if ($locationId) {
            $stmt = $db->prepare("INSERT OR REPLACE INTO product_locations (product_id, location_id, quantity) VALUES (?, ?, COALESCE((SELECT quantity FROM product_locations WHERE product_id=? AND location_id=?), 0) + ?)");
            $stmt->execute([$product['id'], $locationId, $product['id'], $locationId, $quantity]);
        }

        $newBalance = getStockBalance($product['id']);
        jsonResponse(true, ['new_balance' => $newBalance]);
    }

    // Stok düzeltme (sadece admin)
    if ($action === 'correction') {
        requireAdmin();
        $productId = (int)($body['product_id'] ?? 0);
        $newStock  = (int)($body['new_stock']   ?? 0);
        $note      = trim($body['note']          ?? 'Manuel düzeltme');

        $current = getStockBalance($productId);
        $diff    = $newStock - $current;
        if ($diff === 0) jsonResponse(true, ['message' => 'Değişiklik yok.']);

        $stmt = $db->prepare("INSERT INTO stock_movements (product_id,type,quantity,user_id,note) VALUES (?,'correction',?,?,?)");
        $stmt->execute([$productId, $diff, $user['id'], $note]);
        jsonResponse(true, ['new_balance' => $newStock]);
    }

    // Toplu stok girişi
    if ($action === 'bulk_stock_in') {
        $items = $body['items'] ?? [];
        $note = $body['note'] ?? '';

        if (is_array($items) && !empty($items)) {
            try {
                $db->beginTransaction();

                foreach ($items as $item) {
                    if (isset($item['barcode']) && isset($item['quantity'])) {
                        $barcode = trim($item['barcode']);
                        $qty = (int)($item['quantity']);
                        $itemNote = trim($item['note'] ?? '');
                        $locationId = (int)($item['location_id'] ?? 0) ?: null;

                        if ($barcode && $qty > 0) {
                            $stmt = $db->prepare("SELECT id FROM products WHERE barcode = ? AND active = 1");
                            $stmt->execute([$barcode]);
                            $product = $stmt->fetch();

                            if ($product) {
                                $stmt = $db->prepare("
                                    INSERT INTO stock_movements (product_id, type, quantity, reference, note, user_id, location_id)
                                    VALUES (?, 'in', ?, 'Toplu Giriş', ?, ?, ?)
                                ");
                                $stmt->execute([
                                    $product['id'], 
                                    $qty, 
                                    $itemNote ?: $note,
                                    $_SESSION['user_id'] ?? null,
                                    $locationId
                                ]);

                                if ($locationId) {
                                    $stmt = $db->prepare("INSERT OR REPLACE INTO product_locations (product_id, location_id, quantity) VALUES (?, ?, COALESCE((SELECT quantity FROM product_locations WHERE product_id=? AND location_id=?), 0) + ?)");
                                    $stmt->execute([$product['id'], $locationId, $product['id'], $locationId, $qty]);
                                }
                            }
                        }
                    }
                }

                $db->commit();
                jsonResponse(true, ['message' => count($items) . ' ürün için toplu stok girişi yapıldı.']);
            } catch (PDOException $e) {
                $db->rollBack();
                jsonResponse(false, null, 'Hata: ' . $e->getMessage());
            }
        }
    }

    // Lokasyonlar arası transfer
    if ($action === 'transfer') {
        requireDepocu();

        $productId = (int)($body['product_id'] ?? 0);
        $fromLocId = (int)($body['from_location_id'] ?? 0);
        $toLocId = (int)($body['to_location_id'] ?? 0);
        $quantity = (int)($body['quantity'] ?? 0);

        if (!$productId || !$fromLocId || !$toLocId || $quantity < 1) {
            jsonResponse(false, null, 'Eksik veri.');
        }

        if ($fromLocId === $toLocId) {
            jsonResponse(false, null, 'Kaynak ve hedef aynı olamaz.');
        }

        // Stok kontrolü
        $stmt = $db->prepare("SELECT quantity FROM product_locations WHERE product_id = ? AND location_id = ?");
        $stmt->execute([$productId, $fromLocId]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$current || $current['quantity'] < $quantity) {
            jsonResponse(false, null, 'Yeterli stok yok.');
        }

        // Kaynak gözden düş
        $stmt = $db->prepare("UPDATE product_locations SET quantity = quantity - ? WHERE product_id = ? AND location_id = ?");
        $stmt->execute([$quantity, $productId, $fromLocId]);

        // Hedef gözden ekle (veya oluştur)
        $stmt = $db->prepare("INSERT INTO product_locations (product_id, location_id, quantity) VALUES (?, ?, ?) ON CONFLICT(product_id, location_id) DO UPDATE SET quantity = quantity + ?");
        $stmt->execute([$productId, $toLocId, $quantity, $quantity]);

        // Stok hareketi kaydet
        $stmt = $db->prepare("INSERT INTO stock_movements (product_id, type, quantity, user_id, from_location_id, to_location_id) VALUES (?, 'transfer', ?, ?, ?, ?)");
        $stmt->execute([$productId, $quantity, $user['id'], $fromLocId, $toLocId]);

        jsonResponse(true, ['message' => 'Transfer tamamlandı.']);
    }

    // Transfer iptal (ters transfer)
    if ($action === 'cancel_transfer') {
        requireAdmin();
        $movementId = (int)($body['movement_id'] ?? 0);
        if (!$movementId) jsonResponse(false, null, 'ID gerekli.');
        
        $stmt = $db->prepare("SELECT * FROM stock_movements WHERE id=? AND type='transfer'");
        $stmt->execute([$movementId]);
        $transfer = $stmt->fetch();
        if (!$transfer) jsonResponse(false, null, 'Transfer bulunamadı.');
        
        $productId = $transfer['product_id'];
        $quantity = $transfer['quantity'];
        $fromLocId = $transfer['to_location_id']; // Ters: hedef -> kaynak
        $toLocId = $transfer['from_location_id'];  // Ters: kaynak -> hedef
        
        // Ters transfer kaydı
        $db->prepare("INSERT INTO stock_movements (product_id, type, quantity, user_id, from_location_id, to_location_id, note) VALUES (?, 'transfer', ?, ?, ?, ?, 'İptal')")
           ->execute([$productId, $quantity, $user['id'], $fromLocId, $toLocId]);
        
        // Stok güncelleme
        $db->prepare("UPDATE product_locations SET quantity = quantity - ? WHERE product_id = ? AND location_id = ?")
           ->execute([$quantity, $productId, $fromLocId]);
        $db->prepare("INSERT INTO product_locations (product_id, location_id, quantity) VALUES (?, ?, ?) ON CONFLICT(product_id, location_id) DO UPDATE SET quantity = quantity + ?")
           ->execute([$productId, $toLocId, $quantity, $quantity]);
        
        adminLog('cancel_transfer', 'stock_movements', $movementId, json_encode($transfer), json_encode(['cancelled_by' => $user['id']]));
        jsonResponse(true, ['message' => 'Transfer iptal edildi.']);
    }

    // Stok hareketi sil (ters hareket ile)
    if ($action === 'delete_movement') {
        requireAdmin();
        $movementId = (int)($body['movement_id'] ?? 0);
        if (!$movementId) jsonResponse(false, null, 'ID gerekli.');
        
        $stmt = $db->prepare("SELECT * FROM stock_movements WHERE id=?");
        $stmt->execute([$movementId]);
        $movement = $stmt->fetch();
        if (!$movement) jsonResponse(false, null, 'Hareket bulunamadı.');
        
        // Ters kayıt
        $newType = $movement['type'] === 'in' ? 'out' : ($movement['type'] === 'out' ? 'in' : 'correction');
        $db->prepare("INSERT INTO stock_movements (product_id, type, quantity, user_id, location_id, note) VALUES (?, ?, ?, ?, ?, 'Silinen hareket iptali')")
           ->execute([$movement['product_id'], $newType, $movement['quantity'], $user['id'], $movement['location_id']]);
        
        adminLog('delete_movement', 'stock_movements', $movementId, json_encode($movement), null);
        jsonResponse(true, null);
    }

    jsonResponse(false, null, 'Bilinmeyen işlem.');
}
