<?php
declare(strict_types=1);

// PDO SQL Server bağlantı testi
header('Content-Type: text/plain; charset=UTF-8');

$dsn  = 'sqlsrv:Server=localhost\\MSSQLSERVER01;Database=OtelRezervasyonSistemi;TrustServerCertificate=true;';
$user = ''; // SQL auth kullanıyorsan doldur, Windows auth için boş bırak
$pass = '';

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    echo 'Bağlantı başarılı';
} catch (PDOException $ex) {
    error_log('DB connect error: ' . $ex->getMessage());
    http_response_code(500);
    echo 'Veritabanı bağlantısı başarısız.';
}
