<?php
/**
 * Kimlik doğrulama ve oturum yönetimi
 * Session, config.php dosyasında başlatıldığı için tekrar başlatmaya gerek yok
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

/**
 * Session durumunu kontrol et ve gerekirse başlat
 */
function startSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
}

/**
 * Kullanıcı giriş yapmış mı kontrol et
 */
function isLoggedIn(): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['last_activity'])) {
        return false;
    }
    
    return (time() - $_SESSION['last_activity']) < SESSION_LIFETIME;
}

/**
 * Giriş yapmayı zorunlu kıl
 */
function requireLogin(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    
    // Kurulum yapılmamışsa install sayfasına yönlendir
    if (!file_exists(DB_PATH)) {
        header('Location: ' . BASE_PATH . '/install/setup.php');
        exit;
    }
    
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['last_activity']) ||
        (time() - $_SESSION['last_activity']) >= SESSION_LIFETIME) {
        header('Location: ' . BASE_PATH . '/login.php');
        exit;
    }
    
    $_SESSION['last_activity'] = time();
}

/**
 * Admin yetkisi gerektiren sayfalar için kontrol
 */
function requireAdmin(): void
{
    requireLogin();
    
    if (($_SESSION['role'] ?? '') !== 'admin') {
        http_response_code(403);
        die('Yetkiniz yok.');
    }
}

/**
 * Muhasebe yetkisi gerektiren sayfalar için kontrol
 */
function requireMuhasebe(): void
{
    requireLogin();
    
    $role = $_SESSION['role'] ?? '';
    if ($role !== 'admin' && $role !== 'muhasebe') {
        http_response_code(403);
        die('Yetkiniz yok.');
    }
}

/**
 * Depocu yetkisi gerektiren sayfalar için kontrol
 */
function requireDepocu(): void
{
    requireLogin();
    
    $role = $_SESSION['role'] ?? '';
    if ($role !== 'admin' && $role !== 'depocu') {
        http_response_code(403);
        die('Yetkiniz yok.');
    }
}

/**
 * Mevcut kullanıcı bilgilerini döndür
 */
function currentUser(): array
{
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? '',
        'name' => $_SESSION['full_name'] ?? '',
        'role' => $_SESSION['role'] ?? '',
    ];
}

/**
 * Kullanıcı girişi
 */
function login(string $username, string $password): bool
{
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE username=? AND active=1 LIMIT 1");
    $stmt->execute([trim($username)]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['last_activity'] = time();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        
        return true;
    }
    
    return false;
}

/**
 * Çıkış yap
 */
function logout(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    
    session_destroy();
    header('Location: ' . BASE_PATH . '/login.php');
    exit;
}

/**
 * Basit rate limiting (1 dakikada max 60 istek)
 */
function checkRateLimit(string $action = 'api', int $maxRequests = 60, int $windowSeconds = 60): bool {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    
    $key = "rate_{$action}";
    $now = time();
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'reset' => $now + $windowSeconds];
    }
    
    $data = &$_SESSION[$key];
    
    if ($now > $data['reset']) {
        $data['count'] = 0;
        $data['reset'] = $now + $windowSeconds;
    }
    
    $data['count']++;
    
    return $data['count'] <= $maxRequests;
}