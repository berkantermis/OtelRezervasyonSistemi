<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
// Zaten giriş yapıldıysa panele at
if (current_user()) { header('Location: dashboard.php'); exit; }
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    if ($email === '' || $pass === '') {
        $error = 'Lütfen personel e-posta ve parolanızı girin.';
    } else {
        try {
            // Veritabanı sorgusu (Personel bilgileriyle beraber çekiyoruz)
            $stmt = db()->prepare("
                SELECT K.*, P.KullaniciAdi, P.KullaniciSoyadi, P.KullaniciRolu
                FROM Kullanicilar K
                LEFT JOIN Personeller P ON P.PersonelID = K.PersonelID
                WHERE K.Email = :e
            ");
            $stmt->execute([':e' => $email]);
            $u = $stmt->fetch();

            $ok = false;
            if ($u) {
                if (password_verify($pass, $u['Sifre'])) {
                    $ok = true;
                } elseif (hash_equals((string)$u['Sifre'], $pass)) {
                    $ok = true;
                }
            }

            if ($ok) {
                // KONTROL: Sadece PersonelMi = 1 olanlar girebilir
                if ((bool)$u['PersonelMi'] === false) {
                    $error = 'Yetkiniz yok. Bu panele sadece otel personeli giriş yapabilir.';
                } else {
                    session_regenerate_id(true);
                    // Personel session verisi oluşturuluyor
                    $_SESSION['kullanici'] = [
                        'id'         => $u['KullaniciID'],
                        'email'      => $u['Email'],
                        // Eğer personel tablosunda adı varsa onu al, yoksa KullaniciAdi'ni al
                        'ad'         => $u['KullaniciAdi'] ?? 'Personel',
                        'soyad'      => $u['KullaniciSoyadi'] ?? '',
                        'personelMi' => true,
                        'rol'        => $u['KullaniciRolu'] ?? 'Personel', // Rolü de kaydettik
                        'musteriId'  => $u['MusteriID'],
                        'personelId' => $u['PersonelID']
                    ];
                    header('Location: dashboard.php');
                    exit;
                }
            } else {
                $error = 'Geçersiz personel bilgisi.';
            }
        } catch (Exception $ex) {
            $error = 'Sistem Hatası: ' . $ex->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8" />
  <title>Personel Girişi - Marun Otel</title>
  <link rel="stylesheet" href="assets/style.css" />
</head>
<body class="full-bg" style="background: linear-gradient(180deg, rgba(0,0,0,0.7), rgba(0,0,0,0.8)), url('images/otel2.jpeg') center/cover no-repeat;">
  <div class="glass login-hero" style="border-top: 4px solid #fbbf24;">
    <h1 class="login-title" style="color:#fbbf24;">Personel Yönetim Paneli</h1>
    <p class="login-sub">Yetkili personel girişi</p>
    
    <?php if ($error): ?>
      <div style="background:#7f1d1d; color:#fecaca; padding:12px; border-radius:12px; margin-bottom:12px; font-size:0.9rem;">
        <?= h($error) ?>
      </div>
    <?php endif; ?>

    <form method="POST" class="login-form">
      <input class="input" type="email" name="email" placeholder="Kurumsal E-posta" required />
      <input class="input" type="password" name="password" placeholder="Parola" required />
      <button class="btn login-btn" style="background: #fbbf24; color:#000;" type="submit">Panele Gir</button>
    </form>
    
    <div style="text-align:center; margin-top:20px;">
        <a class="small-link" href="login.php">&larr; Müşteri Girişine Dön</a>
    </div>
  </div>
</body>
</html>