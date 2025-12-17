<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

// Zaten giriş yapmışsa, kim olduğuna göre yönlendir
if (current_user()) {
    if (!empty($_SESSION['kullanici']['personelMi'])) {
        header('Location: dashboard.php'); // Personelse Yönetim Paneline
    } else {
        header('Location: dashboard_musteri.php'); // Müşteriyse Müşteri Paneline
    }
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    if ($email === '' || $pass === '') {
        $error = 'Lütfen e-posta ve parolanızı girin.';
    } else {
        try {
            $stmt = db()->prepare("
                SELECT K.*, M.Isim, M.Soyisim
                FROM Kullanicilar K
                LEFT JOIN Musteriler M ON M.MusteriID = K.MusteriID
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
                // KONTROL: Personel buradan giremez (login_personel.php'ye gitmeli)
                if ((bool)$u['PersonelMi'] === true) {
                    $error = 'Personel girişi için lütfen aşağıdaki "Personel Girişi" butonunu kullanınız.';
                } else {
                    // MÜŞTERİ GİRİŞİ BAŞARILI
                    session_regenerate_id(true);
                    $_SESSION['kullanici'] = [
                        'id'         => $u['KullaniciID'],
                        'email'      => $u['Email'],
                        'ad'         => $u['Isim'] ?? 'Misafir',
                        'soyad'      => $u['Soyisim'] ?? '',
                        'personelMi' => false,
                        'musteriId'  => $u['MusteriID'],
                        'personelId' => null
                    ];
                    
                    // İŞTE BURAYI DEĞİŞTİRDİK: Artık müşteri paneline gidiyor
                    header('Location: dashboard_musteri.php');
                    exit;
                }
            } else {
                $error = 'Geçersiz giriş bilgisi.';
            }
        } catch (Exception $ex) {
            $error = 'Giriş başarısız: ' . $ex->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8" />
  <title>Müşteri Girişi - Marun Otel</title>
  <link rel="stylesheet" href="assets/style.css" />
</head>
<body class="full-bg" style="background: linear-gradient(180deg, rgba(0,0,0,0.38), rgba(0,0,0,0.45)), url('images/otel2.jpeg') center/cover no-repeat;">
  <div class="glass login-hero">
    <h1 class="login-title">Marun Otele Hoşgeldiniz</h1>
    <p class="login-sub">Değerli misafirimiz, lütfen giriş yapınız.</p>
    
    <?php if ($error): ?>
      <div style="background:#fee2e2;color:#991b1b;padding:12px;border-radius:12px;margin-bottom:12px;font-size:0.9rem;">
        <?= h($error) ?>
      </div>
    <?php endif; ?>

    <form method="POST" class="login-form">
      <input class="input" type="email" name="email" placeholder="E-posta adresi" required />
      <input class="input" type="password" name="password" placeholder="Parola" required />
      <button class="btn login-btn" type="submit">Giriş Yap</button>
    </form>
    
    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:15px;">
        <a class="small-link" href="register.php">Kayıt Ol</a>
        <a class="small-link" style="color:#fbbf24;" href="login_personel.php">Personel Girişi &rarr;</a>
    </div>
  </div>
</body>
</html>