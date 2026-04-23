<?php
require_once __DIR__ . '/config.php';

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO('sqlite:' . DB_PATH);
            $pdo->setAttribute(PDO::ATTR_ERRMODE,        PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            $wal = $pdo->query("PRAGMA journal_mode=WAL")->fetchColumn();
            if ($wal !== 'wal') {
                error_log("WAL mode failed, using default: $wal");
            }
            $pdo->exec("PRAGMA foreign_keys=ON");
            runMigrations($pdo);
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['success' => false, 'error' => 'Veritabanı bağlantı hatası.']));
        }
    }
    return $pdo;
}

$migrationRan = false;
function runMigrations(PDO $db): void {
    global $migrationRan;
    if ($migrationRan) return;
    $migrationRan = true;
    
    @error_reporting(0);
    try { $db->exec("CREATE TABLE IF NOT EXISTS locations ( id INTEGER PRIMARY KEY AUTOINCREMENT, kat TEXT NOT NULL, bolge TEXT NOT NULL, raf INTEGER NOT NULL, goz INTEGER NOT NULL, kod TEXT UNIQUE NOT NULL, kapasite INTEGER DEFAULT 100, aktif INTEGER DEFAULT 1, created_at DATETIME DEFAULT CURRENT_TIMESTAMP )"); } catch (Exception $e) {}
    try { $db->exec("CREATE TABLE IF NOT EXISTS product_locations ( id INTEGER PRIMARY KEY AUTOINCREMENT, product_id INTEGER NOT NULL, location_id INTEGER NOT NULL, quantity INTEGER DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP )"); } catch (Exception $e) {}
    try {
        $cols = $db->query("PRAGMA table_info(stock_movements)")->fetchAll(PDO::FETCH_ASSOC);
        $colNames = array_column($cols, 'name');
        if (!in_array('location_id', $colNames)) { $db->exec("ALTER TABLE stock_movements ADD COLUMN location_id INTEGER"); }
        if (!in_array('from_location_id', $colNames)) { $db->exec("ALTER TABLE stock_movements ADD COLUMN from_location_id INTEGER"); }
        if (!in_array('to_location_id', $colNames)) { $db->exec("ALTER TABLE stock_movements ADD COLUMN to_location_id INTEGER"); }
    } catch (Exception $e) {}
    try {
        $prodCols = $db->query("PRAGMA table_info(products)")->fetchAll(PDO::FETCH_ASSOC);
        $prodColNames = array_column($prodCols, 'name');
        if (!in_array('location_id', $prodColNames)) { $db->exec("ALTER TABLE products ADD COLUMN location_id INTEGER"); }
    } catch (Exception $e) {}
    @error_reporting(E_ALL);
}

/**
 * Ürünün güncel stok bakiyesini döndür
 */
function getStockBalance(int $productId): int {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(
            CASE WHEN type='in'         THEN quantity
                 WHEN type='out'        THEN -quantity
                 WHEN type='correction' THEN quantity
            END
        ), 0)
        FROM stock_movements
        WHERE product_id = ?
    ");
    $stmt->execute([$productId]);
    return (int)$stmt->fetchColumn();
}

/**
 * Tüm ürünleri stok bakiyesiyle getir
 */
function getAllProductsWithStock(int $limit = 0, int $offset = 0): array {
    $db = getDB();
    $sql = "
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
        WHERE p.active = 1
        GROUP BY p.id
        ORDER BY p.name ASC
    ";
    if ($limit > 0) { $sql .= " LIMIT {$limit} OFFSET {$offset}"; }
    return $db->query($sql)->fetchAll();
}

/**
 * JSON API cevabı döndür
 */
function jsonResponse(bool $success, $data = null, string $error = ''): void {
    header('Content-Type: application/json; charset=utf-8');
    if ($success) {
        echo json_encode(['success' => true, 'data' => $data]);
    } else {
        echo json_encode(['success' => false, 'error' => $error]);
    }
    exit;
}
