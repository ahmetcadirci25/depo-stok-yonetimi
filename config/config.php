<?php
/**
 * Depo & Stok Yönetim Sistemi - Genel Yapılandırma
 * Bu dosya her şeyden önce yüklenmelidir
 */

// Session ayarları - HİÇBİR ÇIKTI gönderilmeden önce yapılmalı
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_lifetime', '28800');
    session_start();
}

// Veritabanı — mutlak yol kullan
define('DB_PATH', dirname(__DIR__) . '/database/depo.sqlite');

// Base path otomatik algılama (root vs alt klasör kurulumu)
$scriptPath = $_SERVER['SCRIPT_FILENAME'] ?? '';
if ($scriptPath && preg_match('#/([^/]+)/config/config\.php$#', $scriptPath, $m)) {
    $basePath = '/' . $m[1];
} else {
    $rootDirs = ['public_html', 'htdocs', 'httpdocs', 'www', 'html', 'root', 'var'];
    $rootDir = basename(dirname(__DIR__));
    $basePath = in_array($rootDir, $rootDirs) ? '' : '/' . $rootDir;
}
define('BASE_PATH', $basePath);

// Site URL otomatik
$siteUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') 
         . ($_SERVER['HTTP_HOST'] ?? 'localhost') 
         . BASE_PATH;
define('SITE_URL', $siteUrl);

// Firma Bilgileri - Veritabanından yükle, yoksa varsayılan
$defaultSettings = [
    'company_name' => 'Stok',
    'company_short' => 'HK',
    'company_domain' => $_SERVER['HTTP_HOST'] ?? 'localhost',
    'site_name' => 'Depo Yönetim Sistemi'
];

// Veritabanı varsa ayarları yükle
if (file_exists(DB_PATH)) {
    try {
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $row = $pdo->query("SELECT settings FROM app_settings WHERE id = 1")->fetchColumn();
        if ($row) {
            $dbSettings = json_decode($row, true);
            if ($dbSettings) {
                $defaultSettings = array_merge($defaultSettings, $dbSettings);
            }
        }
        unset($pdo);
    } catch (PDOException $e) {
        // Veritabanı hatası varsa varsayılan değerler
    }
}

define('COMPANY_NAME', $defaultSettings['company_name']);
define('COMPANY_SHORT', $defaultSettings['company_short']);
define('COMPANY_DOMAIN', $defaultSettings['company_domain']);
define('SITE_NAME', $defaultSettings['site_name']);

// Uygulama versiyonu - cache busting için (YYYYMMDD.HHMM formatında)
define('APP_VERSION', date('Ymd.Hi'));

/**
 * URL oluşturucu helper - cache busting ile
 */
function url(string $path): string {
    if (preg_match('#^https?://#', $path)) {
        return $path;
    }
    $path = ltrim($path, '/');
    $isLocalAsset = strpos($path, 'assets/') === 0;
    $url = BASE_PATH . '/' . $path;
    if ($isLocalAsset) {
        $url .= '?v=' . APP_VERSION;
    }
    return $url;
}

// Session süresi (8 saat)
define('SESSION_LIFETIME', 28800);

// Stok uyarı renkleri
define('STOCK_CRITICAL', 0);
define('STOCK_LOW', 5);

// Timezone
date_default_timezone_set('Europe/Istanbul');

// Hata gösterimi — production'da false yapın
define('DEBUG_MODE', true);

if (DEBUG_MODE) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}
