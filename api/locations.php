<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();

if ($method === 'GET') {
    $id = $_GET['id'] ?? null;
    $kod = $_GET['kod'] ?? null;
    $list = $_GET['list'] ?? null;
    $empty = $_GET['empty'] ?? null;
    $full = $_GET['full'] ?? null;
    $kat = $_GET['kat'] ?? null;
    $bolge = $_GET['bolge'] ?? null;
    $raf = $_GET['raf'] ?? null;
    $distinct = $_GET['distinct'] ?? null;

    if ($id) {
        $stmt = $db->prepare("SELECT * FROM locations WHERE id = ? AND aktif = 1");
        $stmt->execute([(int)$id]);
        $loc = $stmt->fetch();
        if (!$loc) jsonResponse(false, null, 'Lokasyon bulunamadı.');
        jsonResponse(true, $loc);
    }

    if ($kod) {
        $stmt = $db->prepare("SELECT * FROM locations WHERE kod = ? AND aktif = 1");
        $stmt->execute([trim($kod)]);
        $loc = $stmt->fetch();
        if (!$loc) jsonResponse(false, null, 'Lokasyon bulunamadı: ' . htmlspecialchars($kod));
        jsonResponse(true, $loc);
    }

    if ($list || $empty || $full || $kat) {
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, max(10, (int)($_GET['per_page'] ?? 30)));
        $offset  = ($page - 1) * $perPage;
        $kat     = $_GET['kat'] ?? null;
        $bolge   = $_GET['bolge'] ?? null;
        $raf     = $_GET['raf'] ?? null;

        $sql = "SELECT l.*,
            (SELECT COALESCE(SUM(pl.quantity),0) FROM product_locations pl WHERE pl.location_id = l.id) AS current_stock
            FROM locations l WHERE l.aktif = 1";
        $params = [];
        $countSql = "SELECT COUNT(*) FROM locations l WHERE l.aktif = 1";

        if ($kat) {
            $sql .= " AND l.kat = ?";
            $countSql .= " AND l.kat = ?";
            $params[] = $kat;
        }
        if ($bolge) {
            $sql .= " AND l.bolge = ?";
            $countSql .= " AND l.bolge = ?";
            $params[] = $bolge;
        }
        if ($raf) {
            $sql .= " AND l.raf = ?";
            $countSql .= " AND l.raf = ?";
            $params[] = (int)$raf;
        }

        if ($empty) {
            $sql .= " AND (SELECT COALESCE(SUM(pl.quantity),0) FROM product_locations pl WHERE pl.location_id = l.id) = 0";
            $countSql .= " AND (SELECT COALESCE(SUM(pl.quantity),0) FROM product_locations pl WHERE pl.location_id = l.id) = 0";
        }

        $totalStmt = $db->prepare($countSql);
        $totalStmt->execute($params);
        $total = (int)$totalStmt->fetchColumn();

        $sql .= " ORDER BY l.kat, l.bolge, l.raf, l.goz LIMIT $perPage OFFSET $offset";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $locations = $stmt->fetchAll();

        jsonResponse(true, [
            'items'   => $locations,
            'total'   => $total,
            'page'    => $page,
            'per_page'=> $perPage,
            'pages'   => (int)ceil($total / $perPage),
        ]);
    }

    if ($distinct === 'kat') {
        $locations = $db->query("SELECT DISTINCT kat FROM locations WHERE aktif = 1 ORDER BY kat")->fetchAll(PDO::FETCH_COLUMN);
        jsonResponse(true, $locations);
    }
    if ($distinct === 'bolge') {
        $katFilter = $_GET['kat'] ?? '';
        $sql = "SELECT DISTINCT bolge FROM locations WHERE aktif = 1";
        $params = [];
        if ($katFilter) {
            $sql .= " AND kat = ?";
            $params[] = $katFilter;
        }
        $sql .= " ORDER BY bolge";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $locations = $stmt->fetchAll(PDO::FETCH_COLUMN);
        jsonResponse(true, $locations);
    }
    if ($distinct === 'raf') {
        $katFilter = $_GET['kat'] ?? '';
        $bolgeFilter = $_GET['bolge'] ?? '';
        $sql = "SELECT DISTINCT raf FROM locations WHERE aktif = 1";
        $params = [];
        if ($katFilter) {
            $sql .= " AND kat = ?";
            $params[] = $katFilter;
        }
        if ($bolgeFilter) {
            $sql .= " AND bolge = ?";
            $params[] = $bolgeFilter;
        }
        $sql .= " ORDER BY raf";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $locations = $stmt->fetchAll(PDO::FETCH_COLUMN);
        jsonResponse(true, $locations);
    }

    jsonResponse(false, null, 'Geçersiz istek.');
}

if ($method === 'POST') {
    requireAdmin();
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $body['action'] ?? '';

    if ($action === 'create') {
        $kat = trim($body['kat'] ?? '');
        $bolge = trim($body['bolge'] ?? '');
        $raf = (int)($body['raf'] ?? 0);
        $goz = (int)($body['goz'] ?? 0);
        if (!$kat || !$bolge || !$raf || !$goz) {
            jsonResponse(false, null, 'Kat, bölge, raf ve göz zorunlu.');
        }
        $kod = 'K' . $kat . '-' . strtoupper($bolge) . '-R' . $raf . '-G' . $goz;
        $kapasite = (int)($body['kapasite'] ?? 100);

        $stmt = $db->prepare("INSERT INTO locations (kat, bolge, raf, goz, kod, kapasite) VALUES (?,?,?,?,?,?)");
        try {
            $stmt->execute([$kat, $bolge, $raf, $goz, $kod, $kapasite]);
            jsonResponse(true, ['id' => $db->lastInsertId(), 'kod' => $kod]);
        } catch (Exception $e) {
            jsonResponse(false, null, 'Bu lokasyon zaten var veya kod üretilemedi.');
        }
    }

    if ($action === 'createBulk') {
        $kat = trim($body['kat'] ?? '');
        $bolge = trim($body['bolge'] ?? '');
        $rafStart = (int)($body['raf_start'] ?? 1);
        $rafEnd = (int)($body['raf_end'] ?? 1);
        $gozPerRaf = (int)($body['goz_per_raf'] ?? 4);
        $kapasite = (int)($body['kapasite'] ?? 100);

        if (!$kat || !$bolge || !$rafStart || !$rafEnd || !$gozPerRaf) {
            jsonResponse(false, null, 'Tüm alanlar zorunlu.');
        }

        $created = [];
        $db->beginTransaction();
        try {
            for ($r = $rafStart; $r <= $rafEnd; $r++) {
                for ($g = 1; $g <= $gozPerRaf; $g++) {
                    $kod = 'K' . $kat . '-' . strtoupper($bolge) . '-R' . $r . '-G' . $g;
                    $stmt = $db->prepare("INSERT OR IGNORE INTO locations (kat, bolge, raf, goz, kod, kapasite) VALUES (?,?,?,?,?,?)");
                    $stmt->execute([$kat, $bolge, $r, $g, $kod, $kapasite]);
                    if ($db->lastInsertId()) {
                        $created[] = $kod;
                    }
                }
            }
            $db->commit();
            jsonResponse(true, ['created' => count($created)]);
        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(false, null, 'Toplu oluşturma başarısız.');
        }
    }

    if ($action === 'update') {
        $id = (int)($body['id'] ?? 0);
        if (!$id) jsonResponse(false, null, 'ID gerekli.');
        $stmt = $db->prepare("UPDATE locations SET kapasite = ? WHERE id = ?");
        $stmt->execute([(int)($body['kapasite'] ?? 100), $id]);
        jsonResponse(true, null);
    }

    if ($action === 'deactivate') {
        $id = (int)($body['id'] ?? 0);
        if (!$id) jsonResponse(false, null, 'ID gerekli.');
        $stmt = $db->prepare("UPDATE locations SET aktif = 0 WHERE id = ?");
        $stmt->execute([$id]);
        jsonResponse(true, null);
    }

    if ($action === 'reactivate') {
        $id = (int)($body['id'] ?? 0);
        if (!$id) jsonResponse(false, null, 'ID gerekli.');
        $stmt = $db->prepare("UPDATE locations SET aktif = 1 WHERE id = ?");
        $stmt->execute([$id]);
        jsonResponse(true, null);
    }

    if ($action === 'delete') {
        $id = (int)($body['id'] ?? 0);
        if (!$id) jsonResponse(false, null, 'ID gerekli.');
        
        // Get old for logging
        $oldLoc = $db->prepare("SELECT * FROM locations WHERE id = ?")->execute([$id])->fetch();
        
        // Check if has products
        $stmt = $db->prepare("SELECT COUNT(*) FROM product_locations WHERE location_id = ?");
        $stmt->execute([$id]);
        $hasProducts = (int)$stmt->fetchColumn() > 0;
        
        if ($hasProducts) {
            jsonResponse(false, null, 'Bu gözde ürün var. Önce ürünleri başka göze taşıyın.');
        }
        
        // Check if has stock movements
        $stmt = $db->prepare("SELECT COUNT(*) FROM stock_movements WHERE location_id = ? OR from_location_id = ? OR to_location_id = ?");
        $stmt->execute([$id, $id, $id]);
        $hasMovements = (int)$stmt->fetchColumn() > 0;
        
        if ($hasMovements) {
            // Just deactivate
            $stmt = $db->prepare("UPDATE locations SET aktif = 0 WHERE id = ?");
            $stmt->execute([$id]);
            adminLog('deactivate', 'locations', $id, json_encode($oldLoc), null);
            jsonResponse(true, ['message' => 'Lokasyon pasif yapıldı (hareket geçmişi var).']);
        }
        
        // Actually delete
        $stmt = $db->prepare("DELETE FROM locations WHERE id = ?");
        $stmt->execute([$id]);
        adminLog('delete', 'locations', $id, json_encode($oldLoc), null);
        jsonResponse(true, null);
    }

    jsonResponse(false, null, 'Bilinmeyen işlem.');
}

jsonResponse(false, null, 'Geçersiz yöntem.');
