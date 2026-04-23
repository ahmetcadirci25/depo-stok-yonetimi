<?php
// Yardımcı fonksiyonlar
require_once __DIR__ . '/auth.php';

function generateCSRFToken(): string {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken(string $token): bool {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function requireCSRFToken(): void {
    $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    if (!verifyCSRFToken($token)) {
        jsonResponse(false, null, 'Güvenlik hatası. Sayfayı yenileyip tekrar deneyin.');
    }
}

function csrfField(): string {
    $token = generateCSRFToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

function adminLog(string $action, ?string $tableName = null, ?int $recordId = null, ?string $oldValue = null, ?string $newValue = null): void {
    $db = getDb();
    $user = currentUser();
    $userId = $user['id'] ?? 0;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    
    try {
        $stmt = $db->prepare("
            INSERT INTO admin_logs (user_id, action, table_name, record_id, old_value, new_value, ip_address)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $userId,
            $action,
            $tableName,
            $recordId,
            $oldValue,
            $newValue,
            $ip
        ]);
    } catch (Exception $e) {
        // Log table doesn't exist yet
    }
}

function getAdminLogs(?string $tableName = null, ?int $recordId = null, int $limit = 100): array {
    $db = getDb();
    
    $sql = "
        SELECT al.*, u.username, u.full_name
        FROM admin_logs al
        LEFT JOIN users u ON u.id = al.user_id
    ";
    
    $conditions = [];
    $params = [];
    
    if ($tableName) {
        $conditions[] = "al.table_name = ?";
        $params[] = $tableName;
    }
    
    if ($recordId) {
        $conditions[] = "al.record_id = ?";
        $params[] = $recordId;
    }
    
    if (count($conditions) > 0) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }
    
    $sql .= " ORDER BY al.created_at DESC LIMIT ?";
    $params[] = $limit;
    
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}