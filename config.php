<?php
// Output buffering to avoid headers already sent
ob_start();
// Hata gosterimi UI'da cikmasin (log icin acik)
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// SQL Server connection string (ihtiyacina gore guncelle)
$DB = [
    // MSSQLSERVER01 instance, sertifika uyarisi kapali
    'conn' => 'sqlsrv:Server=localhost\\SQLEXPRESS;Database=OtelRezervasyonSistemi;TrustServerCertificate=true;',
    // SQL auth kullanacaksan doldur, Windows auth icin bos birak
    'user' => '',
    'pass' => ''
];

function db() {
    static $pdo = null;
    global $DB;
    if ($pdo === null) {
        $dsn = $DB['conn'];
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ];
        if (defined('PDO::SQLSRV_ATTR_QUERY_TIMEOUT')) {
            $options[PDO::SQLSRV_ATTR_QUERY_TIMEOUT] = 5;
        }
        if (!extension_loaded('pdo_sqlsrv')) {
            die('pdo_sqlsrv eklentisi yuklu degil. php.ini ve DLLleri kontrol edin.');
        }
        try {
            $pdo = new PDO($dsn, $DB['user'], $DB['pass'], $options);
        } catch (PDOException $ex) {
            error_log('DB connect error: '.$ex->getMessage());
            die('Veritabani baglantisi basarisiz.');
        }
    }
    return $pdo;
}

function current_user() {
    return $_SESSION['kullanici'] ?? null;
}

function require_login() {
    if (!current_user()) {
        header('Location: login.php');
        exit;
    }
}

function is_personel() {
    $u = current_user();
    return $u && ($u['personelMi'] ?? false);
}

function h($v) {
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

function money_tr($v) {
    return number_format((float)$v, 2, '.', ',') . ' TRY';
}
?>
