<?php
require_once __DIR__ . '/../config.php';
if (current_user()) { header('Location: dashboard.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isim     = trim($_POST['first_name'] ?? '');
    $soy      = trim($_POST['last_name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = preg_replace('/\D+/', '', $_POST['phone'] ?? '');
    $ulkekodu = trim($_POST['ulke_kodu'] ?? '+90');
    $ulke     = trim($_POST['ulke'] ?? 'Türkiye');
    $il       = trim($_POST['il'] ?? '');
    $ilce     = trim($_POST['ilce'] ?? '');
    $mahalle  = trim($_POST['mahalle'] ?? '');
    $tckn     = preg_replace('/\D+/', '', $_POST['tckn'] ?? '');
    $pasaport = trim($_POST['pasaport'] ?? '');
    $tcVatandasiDegil = isset($_POST['tc_vatandas_degilim']);

    $pass   = $_POST['password'] ?? '';
    $pass2  = $_POST['password2'] ?? '';

    if ($isim === '' || $soy === '' || $email === '' || $pass === '') {
        $error = 'Zorunlu alanları doldurun.';
    } elseif ($pass !== $pass2) {
        $error = 'Parolalar eşleşmiyor.';
    } elseif (!$tcVatandasiDegil && (strlen($tckn) !== 11 || !ctype_digit($tckn))) {
        $error = 'TCKN 11 haneli sayı olmalıdır.';
    } elseif ($tcVatandasiDegil && (strlen($pasaport) < 5)) {
        $error = 'Pasaport numarası en az 5 karakter olmalıdır.';
    } else {
        try {
            db()->beginTransaction();
            $stmt = db()->prepare("INSERT INTO Musteriler (Isim, Soyisim, Email, UlkeKodu, TelefonNo, Ulke, Il, Ilce, Mahalle, TCKN, PasaportNo) VALUES (:i,:s,:e,:uk,:tel,:ulke,:il,:ilce,:mah,:tckn,:pasaport);");
            $stmt->execute([
                ':i' => $isim,
                ':s' => $soy,
                ':e' => $email,
                ':uk' => $ulkekodu,
                ':tel' => $phone,
                ':ulke' => $ulke,
                ':il' => $il,
                ':ilce' => $ilce,
                ':mah' => $mahalle,
                ':tckn' => $tcVatandasiDegil ? null : $tckn,
                ':pasaport' => $tcVatandasiDegil ? $pasaport : null
            ]);
            $musteriId = db()->lastInsertId();
            $hash = password_hash($pass, PASSWORD_BCRYPT);
            $stmt2 = db()->prepare("INSERT INTO Kullanicilar (Email, Sifre, PersonelMi, MusteriID) VALUES (:e,:p,0,:m)");
            $stmt2->execute([':e'=>$email, ':p'=>$hash, ':m'=>$musteriId]);
            db()->commit();
            header('Location: login.php');
            exit;
        } catch (Exception $ex) {
            db()->rollBack();
            $error = 'Kayıt oluşturulamadı: ' . $ex->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8" />
<title>Kayıt Ol - Otel</title>
<link rel="stylesheet" href="assets/style.css" />
</head>
<body class="full-bg">
  <div class="glass">
    <h1 style="margin:0 0 8px;">Yeni Hesap Oluştur</h1>
    <p style="margin:0 0 20px; color:#e5e7eb;">Eşsiz konforu deneyimlemek için bize katılın</p>
    <?php if ($error): ?>
      <div style="background:#fee2e2;color:#991b1b;padding:12px;border-radius:12px;margin-bottom:12px;"><?= h($error) ?></div>
    <?php endif; ?>
    <form method="POST">
      <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <input class="input" style="flex:1; min-width:200px;" type="text" name="first_name" placeholder="Ad" required />
        <input class="input" style="flex:1; min-width:200px;" type="text" name="last_name" placeholder="Soyad" required />
      </div>
      <input class="input" type="email" name="email" placeholder="E-posta adresi" required />
      <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <select class="input" name="ulke_kodu" style="flex:1; min-width:150px; background:#fff;">
          <option value="+90">+90 Türkiye</option>
          <option value="+1">+1 ABD/Kanada</option>
          <option value="+44">+44 Birleşik Krallık</option>
          <option value="+49">+49 Almanya</option>
          <option value="+33">+33 Fransa</option>
          <option value="+39">+39 İtalya</option>
        </select>
        <input class="input" style="flex:2; min-width:200px;" type="text" name="phone" placeholder="Telefon (5xx xxx xx xx)" />
      </div>
      <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <select class="input" name="ulke" style="flex:1; min-width:180px; background:#fff;">
          <option value="Türkiye">Türkiye</option>
          <option value="ABD">ABD</option>
          <option value="Almanya">Almanya</option>
          <option value="Fransa">Fransa</option>
          <option value="İtalya">İtalya</option>
          <option value="Birleşik Krallık">Birleşik Krallık</option>
        </select>
        <input class="input" style="flex:1; min-width:160px;" type="text" name="il" placeholder="İl" />
        <input class="input" style="flex:1; min-width:160px;" type="text" name="ilce" placeholder="İlçe" />
        <input class="input" style="flex:1; min-width:160px;" type="text" name="mahalle" placeholder="Mahalle" />
      </div>
      <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
        <input class="input" style="flex:1; min-width:200px;" type="text" name="tckn" placeholder="TCKN (11 hane)" />
        <label style="color:#e5e7eb; display:flex; align-items:center; gap:6px;">
          <input type="checkbox" name="tc_vatandas_degilim" id="tcdegil" /> TC vatandaşı değilim
        </label>
        <input class="input" id="pasaport" style="flex:1; min-width:200px; display:none;" type="text" name="pasaport" placeholder="Pasaport No" />
      </div>
      <input class="input" type="password" name="password" placeholder="Parola" required />
      <input class="input" type="password" name="password2" placeholder="Parola (tekrar)" required />
      <button class="btn" type="submit">Kayıt Ol</button>
    </form>
    <a class="small-link" href="login.php">Zaten hesabınız var mı? Giriş Yap</a>
  </div>
</body>
<script>
const tcCheckbox = document.getElementById('tcdegil');
const pasaportInput = document.getElementById('pasaport');
const tcknInput = document.querySelector('input[name=\"tckn\"]');
function toggleFields(){
  if (tcCheckbox.checked) {
    pasaportInput.style.display = 'block';
    tcknInput.style.visibility = 'hidden';
    tcknInput.style.position = 'absolute';
    tcknInput.value = '';
  } else {
    pasaportInput.style.display = 'none';
    pasaportInput.value = '';
    tcknInput.style.visibility = 'visible';
    tcknInput.style.position = 'static';
  }
}
tcCheckbox.addEventListener('change', toggleFields);
toggleFields();
</script>
</html>
