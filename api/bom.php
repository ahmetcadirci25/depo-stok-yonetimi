<?php
// api/bom.php — Reçete (BOM) API
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

// GET: Mamul ID'ye göre reçete getir
if ($method === 'GET') {
    if (isset($_GET['mamul_id'])) {
        $mamulId = (int)$_GET['mamul_id'];
        $stmt = $db->prepare("
            SELECT b.id, b.mamul_id, b.parca_id, b.miktar, b.created_at,
                   p.name as parca_name, p.barcode as parca_barcode, p.sku as parca_sku
            FROM bom_items b
            JOIN products p ON b.parca_id = p.id
            WHERE b.mamul_id = ?
            ORDER BY b.id
        ");
        $stmt->execute([$mamulId]);
        $items = $stmt->fetchAll();
        jsonResponse(true, $items);
    }
    
    // Tüm reçeteleri listele
    $stmt = $db->query("
        SELECT b.*, m.name as mamul_name, p.name as parca_name
        FROM bom_items b
        JOIN products m ON b.mamul_id = m.id
        JOIN products p ON b.parca_id = p.id
        ORDER BY m.name, p.name
    ");
    jsonResponse(true, $stmt->fetchAll());
}

// POST: Reçete kaydet
if ($method === 'POST') {
    requireAdmin();
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $body['action'] ?? '';
    
    if ($action === 'save_bom') {
        $mamulId = (int)($body['mamul_id'] ?? 0);
        $parcaList = $body['parca_list'] ?? [];
        
        if (!$mamulId) {
            jsonResponse(false, null, 'Mamul ürün belirtilmedi.');
        }
        
        if (!is_array($parcaList) || empty($parcaList)) {
            jsonResponse(false, null, 'Parça listesi boş.');
        }
        
        try {
            $db->beginTransaction();
            
            // Eski reçeteyi sil
            $db->prepare("DELETE FROM bom_items WHERE mamul_id = ?")
              ->execute([$mamulId]);
            
            // Yeni reçeteyi ekle
            $stmt = $db->prepare("
                INSERT INTO bom_items (mamul_id, parca_id, miktar)
                VALUES (?, ?, ?)
            ");
            
            foreach ($parcaList as $item) {
                $stmt->execute([
                    $mamulId,
                    (int)$item['parca_id'],
                    (int)$item['miktar']
                ]);
            }
            
            $db->commit();
            jsonResponse(true, ['message' => 'Reçete kaydedildi']);
        } catch (PDOException $e) {
            $db->rollBack();
            jsonResponse(false, null, 'Hata: ' . $e->getMessage());
        }
    }
    
    if ($action === 'add_item') {
        requireAdmin();
        $mamulId = (int)($body['mamul_id'] ?? 0);
        $parcaId = (int)($body['parca_id'] ?? 0);
        $miktar = (int)($body['miktar'] ?? 1);
        
        if (!$mamulId || !$parcaId) jsonResponse(false, null, 'Mamul ve parça gerekli.');
        
        try {
            $stmt = $db->prepare("INSERT INTO bom_items (mamul_id, parca_id, miktar) VALUES (?, ?, ?)");
            $stmt->execute([$mamulId, $parcaId, $miktar]);
            $newId = $db->lastInsertId();
            adminLog('add_item', 'bom_items', $newId, null, json_encode($body));
            jsonResponse(true, ['id' => $newId]);
        } catch (PDOException $e) {
            jsonResponse(false, null, 'Bu parça zaten reçetede mevcut.');
        }
    }
    
    if ($action === 'update_item') {
        requireAdmin();
        $id = (int)($body['id'] ?? 0);
        $miktar = (int)($body['miktar'] ?? 1);
        
        if (!$id) jsonResponse(false, null, 'ID gerekli.');
        
        // Get old for logging
        $old = $db->prepare("SELECT * FROM bom_items WHERE id = ?")->execute([$id])->fetch();
        
        $stmt = $db->prepare("UPDATE bom_items SET miktar = ? WHERE id = ?");
        $stmt->execute([$miktar, $id]);
        adminLog('update_item', 'bom_items', $id, json_encode($old), json_encode($body));
        jsonResponse(true, null);
    }
    
    if ($action === 'delete_item') {
        requireAdmin();
        $id = (int)($body['id'] ?? 0);
        
        if (!$id) jsonResponse(false, null, 'ID gerekli.');
        
        // Get old for logging
        $old = $db->prepare("SELECT * FROM bom_items WHERE id = ?")->execute([$id])->fetch();
        
        $db->prepare("DELETE FROM bom_items WHERE id = ?")->execute([$id]);
        adminLog('delete_item', 'bom_items', $id, json_encode($old), null);
        jsonResponse(true, null);
    }
    
    if ($action === 'delete_bom') {
        requireAdmin();
        $mamulId = (int)($body['mamul_id'] ?? 0);
        
        if (!$mamulId) jsonResponse(false, null, 'Mamul ID gerekli.');
        
        $db->prepare("DELETE FROM bom_items WHERE mamul_id = ?")->execute([$mamulId]);
        jsonResponse(true, null);
    }
    
    jsonResponse(false, null, 'Bilinmeyen işlem.');
}

jsonResponse(false, null, 'Geçersiz istek.');
